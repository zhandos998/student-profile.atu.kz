<?php

namespace App\Http\Controllers;

use App\Models\StudentSurveyResultSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TestScoreReportController extends Controller
{
    private const TESTS = [
        'self_esteem' => 'Шкала самооценки Розенберга',
        'adaptation' => 'Шкала адаптации',
        'hads' => 'Шкала тревоги и депрессии (HADS)',
        'loneliness' => 'Одиночество',
        'stress' => 'Шкала психологического стресса',
        'temperament_formula' => 'Формула темперамента',
        'five_traits' => 'Пять черт характера',
        'sl19' => 'СЛ-19',
        'pgsi' => 'PGSI',
    ];

    private const EXPORT_COLUMNS = ['ФИО', 'ИИН', 'Факультет', 'Группа', 'Тест', 'Шкала', 'Балл', 'Обновлено'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canViewPsychologicalProfile(), 403);

        $filters = $this->filters($request);
        $snapshots = $this->snapshots($filters)
            ->with('studentProfile.user')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StudentSurveyResultSnapshot $snapshot): array => $this->row($snapshot));

        return Inertia::render('Reports/TestScores', [
            'snapshots' => $snapshots,
            'filters' => $filters,
            'tests' => self::TESTS,
            'indexUrl' => route('reports.test-scores.index'),
            'exportUrl' => route('reports.test-scores.export', $filters),
            'xlsxUrl' => route('reports.test-scores.xlsx', $filters),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->canViewPsychologicalProfile(), 403);

        $filters = $this->filters($request);
        $filename = 'test-scores-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, self::EXPORT_COLUMNS, ';');
            $this->eachScore($filters, function (array $values) use ($stream): void {
                fputcsv($stream, array_map($this->csvCell(...), $values), ';');
            });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function exportXlsx(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->canViewPsychologicalProfile(), 403);

        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($filters): void {
            $writer = new XlsxWriter();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(self::EXPORT_COLUMNS));

            $this->eachScore($filters, function (array $values) use ($writer): void {
                $writer->addRow(Row::fromValues([
                    ...array_map($this->csvCell(...), array_slice($values, 0, 6)),
                    is_numeric($values[6]) ? (float) $values[6] : null,
                    $this->csvCell($values[7]),
                ]));
            });

            $writer->close();
        }, 'test-scores-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{q: string, test: string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'test' => ['nullable', 'string', Rule::in(array_keys(self::TESTS))],
        ]);

        return [
            'q' => trim($validated['q'] ?? ''),
            'test' => $validated['test'] ?? '',
        ];
    }

    /** @param array{q: string, test: string} $filters */
    private function snapshots(array $filters): Builder
    {
        $latestIds = DB::table('student_survey_result_snapshots')
            ->selectRaw('MAX(id)')
            ->groupBy('student_profile_id', 'source_iin', 'survey_key');

        return StudentSurveyResultSnapshot::query()
            ->whereIn('id', $latestIds)
            ->where('status', 'calculated')
            ->whereIn('survey_key', array_keys(self::TESTS))
            ->when($filters['test'] !== '', fn (Builder $query) => $query->where('survey_key', $filters['test']))
            ->whereHas('studentProfile', function (Builder $profile) use ($filters): void {
                $profile->whereColumn('student_profiles.iin', 'student_survey_result_snapshots.source_iin');
                if ($filters['q'] !== '') {
                    $term = '%'.addcslashes($filters['q'], '%_\\').'%';
                    $profile->where(fn (Builder $query) => $query
                        ->where('full_name', 'like', $term)
                        ->orWhere('iin', 'like', $term));
                }
            });
    }

    /** @return array<string, mixed> */
    private function row(StudentSurveyResultSnapshot $snapshot): array
    {
        $profile = $snapshot->studentProfile;

        return [
            'id' => $snapshot->id,
            'student_name' => $profile?->full_name ?: $profile?->user?->name,
            'student_url' => $profile?->user_id ? route('student-profiles.show', $profile->user_id) : null,
            'iin' => $snapshot->source_iin,
            'faculty' => $profile?->faculty,
            'group' => $profile?->group_name,
            'test' => $snapshot->survey_name,
            'metrics' => collect($snapshot->metrics ?? [])->map(fn (array $metric): array => [
                'label' => $metric['label'] ?? '',
                'score' => $metric['score'] ?? null,
            ])->all(),
            'updated_at' => $snapshot->updated_at?->format('d.m.Y H:i'),
        ];
    }

    /** @param array{q: string, test: string} $filters */
    private function eachScore(array $filters, callable $callback): void
    {
        $this->snapshots($filters)
            ->with('studentProfile.user')
            ->orderBy('id')
            ->chunkById(200, function ($snapshots) use ($callback): void {
                foreach ($snapshots as $snapshot) {
                    $row = $this->row($snapshot);
                    foreach ($row['metrics'] as $metric) {
                        $callback([
                            $row['student_name'], $row['iin'], $row['faculty'], $row['group'],
                            $row['test'], $metric['label'], $metric['score'], $row['updated_at'],
                        ]);
                    }
                }
            });
    }

    private function csvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $text) ? "'".$text : $text;
    }
}
