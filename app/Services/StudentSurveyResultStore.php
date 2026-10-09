<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\StudentSurveyResultSnapshot;
use Illuminate\Support\Facades\DB;

class StudentSurveyResultStore
{
    private const EXPECTED_METRIC_COUNTS = [
        'self_esteem' => 1,
        'adaptation' => 2,
        'hads' => 2,
        'loneliness' => 1,
        'stress' => 1,
        'temperament_formula' => 4,
        'five_traits' => 5,
        'sl19' => 1,
        'pgsi' => 1,
    ];

    public function hasCompleteResults(StudentProfile $profile, string $iin): bool
    {
        $latestIds = DB::table('student_survey_result_snapshots')
            ->selectRaw('MAX(id)')
            ->where('student_profile_id', $profile->id)
            ->where('source_iin', $iin)
            ->groupBy('survey_key');

        $snapshots = StudentSurveyResultSnapshot::query()
            ->whereIn('id', $latestIds)
            ->whereIn('survey_key', array_keys(self::EXPECTED_METRIC_COUNTS))
            ->get(['survey_key', 'status', 'metrics']);

        if ($snapshots->count() !== count(self::EXPECTED_METRIC_COUNTS)) {
            return false;
        }

        return $snapshots->every(fn (StudentSurveyResultSnapshot $snapshot): bool =>
            $snapshot->status === 'calculated'
            && count($snapshot->metrics ?? []) === self::EXPECTED_METRIC_COUNTS[$snapshot->survey_key]
            && collect($snapshot->metrics)->every(fn (array $metric): bool => is_numeric($metric['score'] ?? null))
        );
    }

    /** @param array<int, array<string, mixed>> $results */
    public function synchronize(StudentProfile $profile, string $iin, array $results): void
    {
        DB::transaction(function () use ($profile, $iin, $results): void {
            foreach ($results as $order => $result) {
                $key = $result['key'] ?? null;
                if (! is_string($key) || $key === '') {
                    continue;
                }

                $metrics = $result['metrics'] ?? [];
                $hash = hash('sha256', json_encode([
                    'status' => $result['status'],
                    'scores' => array_map(
                        fn (array $metric): array => [
                            'score' => $metric['score'],
                            'maximum' => $metric['maximum'],
                            'share' => $metric['share'] ?? null,
                        ],
                        $metrics,
                    ),
                ], JSON_THROW_ON_ERROR));

                $attributes = [
                    'survey_name' => $result['name'] ?? $key,
                    'status' => $result['status'],
                    'metrics' => $metrics,
                    'message' => $result['message'] ?? null,
                    'source_order' => $order,
                ];

                $latest = StudentSurveyResultSnapshot::query()
                    ->where('student_profile_id', $profile->id)
                    ->where('source_iin', $iin)
                    ->where('survey_key', $key)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($latest?->content_hash === $hash) {
                    $latest->fill($attributes);
                    $latest->touch();
                    continue;
                }

                StudentSurveyResultSnapshot::query()->create([
                    'student_profile_id' => $profile->id,
                    'source_iin' => $iin,
                    'survey_key' => $key,
                    'content_hash' => $hash,
                    ...$attributes,
                ]);
            }
        });
    }

    /** @return array{results: array<int, array<string, mixed>>, updated_at: ?string} */
    public function latest(StudentProfile $profile, string $iin): array
    {
        $snapshots = StudentSurveyResultSnapshot::query()
            ->where('student_profile_id', $profile->id)
            ->where('source_iin', $iin)
            ->orderByDesc('id')
            ->get()
            ->unique('survey_key')
            ->sortBy('source_order')
            ->values();

        return [
            'results' => $snapshots->map(fn (StudentSurveyResultSnapshot $snapshot): array => [
                'key' => $snapshot->survey_key,
                'name' => $snapshot->survey_name,
                'status' => $snapshot->status,
                'metrics' => $snapshot->metrics,
                'message' => $snapshot->message,
            ])->all(),
            'updated_at' => $snapshots->max('updated_at')?->toIso8601String(),
        ];
    }
}
