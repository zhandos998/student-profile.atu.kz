<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\StudentSurveyResultSnapshot;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

class TestScoreReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_see_latest_scores_and_can_filter_or_export_them(): void
    {
        $this->seed(RoleSeeder::class);
        $psychologist = $this->user(Role::ADMINISTRATION, 'Психолог');
        $student = $this->user(Role::STUDENT, 'Студент');
        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Тестовый студент',
            'iin' => '123456789012',
            'group_name' => 'IS-101',
        ]);

        $this->snapshot($profile, 'temperament_formula', 3);
        $this->snapshot($profile, 'temperament_formula', 8);
        $this->snapshot($profile, 'hads', 5);
        $this->snapshot($profile, 'temperament_formula', 11, '999999999999');

        $this->actingAs($psychologist)
            ->get(route('reports.test-scores.index', ['q' => '123456789012', 'test' => 'temperament_formula']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/TestScores')
                ->has('snapshots.data', 1)
                ->where('snapshots.data.0.student_name', 'Тестовый студент')
                ->where('snapshots.data.0.metrics.0.score', 8)
                ->where('snapshots.data.0.test', 'Формула темперамента')
                ->missing('snapshots.data.0.status')
                ->missing('snapshots.data.0.metrics.0.maximum')
                ->missing('snapshots.data.0.metrics.0.share')
            );

        $this->actingAs($psychologist)
            ->get(route('reports.test-scores.index'))
            ->assertInertia(fn (Assert $page) => $page->has('snapshots.data', 2));

        $csv = $this->actingAs($psychologist)
            ->get(route('reports.test-scores.export', ['test' => 'temperament_formula']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('Тестовый студент', $csv);
        $this->assertStringContainsString('Формула темперамента', $csv);
        $this->assertStringContainsString(';8;', $csv);
        $this->assertStringNotContainsString('Максимум', $csv);
        $this->assertStringNotContainsString('Статус', $csv);
        $this->assertStringNotContainsString('Доля', $csv);
        $this->assertStringNotContainsString(';3;', $csv);
        $this->assertStringNotContainsString('999999999999', $csv);

        $xlsx = $this->actingAs($psychologist)
            ->get(route('reports.test-scores.xlsx', ['test' => 'temperament_formula']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();

        $file = tempnam(sys_get_temp_dir(), 'test-scores-');
        file_put_contents($file, $xlsx);
        try {
            $reader = new XlsxReader();
            $reader->open($file);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
                }
            }
            $reader->close();
        } finally {
            unlink($file);
        }

        $this->assertSame(['ФИО', 'ИИН', 'Факультет', 'Группа', 'Тест', 'Шкала', 'Балл', 'Обновлено'], $rows[0]);
        $this->assertSame('Тестовый студент', $rows[1][0]);
        $this->assertSame('123456789012', $rows[1][1]);
        $this->assertSame(8, $rows[1][6]);
        $this->assertCount(2, $rows);
    }

    public function test_report_and_export_require_psychological_access(): void
    {
        $this->seed(RoleSeeder::class);
        $student = $this->user(Role::STUDENT, 'Студент');
        $registrar = $this->user(Role::ADMINISTRATION, 'Офис регистратора');
        $dit = $this->user(Role::ADMINISTRATOR_DIT, 'Администратор ДИТ');

        foreach ([$student, $registrar] as $user) {
            $this->actingAs($user)->get(route('reports.test-scores.index'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.test-scores.export'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.test-scores.xlsx'))->assertForbidden();
        }

        $this->actingAs($dit)->get(route('reports.test-scores.index'))->assertOk();
    }

    public function test_csv_does_not_execute_student_supplied_formula_text(): void
    {
        $this->seed(RoleSeeder::class);
        $psychologist = $this->user(Role::ADMINISTRATION, 'Психолог');
        $student = $this->user(Role::STUDENT, 'Студент');
        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => '=1+1',
            'iin' => '123456789012',
        ]);
        $this->snapshot($profile, 'hads', 5);

        $csv = $this->actingAs($psychologist)
            ->get(route('reports.test-scores.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=1+1", $csv);
    }

    private function user(string $role, string $position): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('slug', $role)->firstOrFail()->id,
            'position' => $position,
        ]);
    }

    private function snapshot(StudentProfile $profile, string $key, int $score, ?string $iin = null): void
    {
        StudentSurveyResultSnapshot::query()->create([
            'student_profile_id' => $profile->id,
            'source_iin' => $iin ?? $profile->iin,
            'survey_key' => $key,
            'survey_name' => $key === 'temperament_formula' ? 'Формула темперамента' : 'HADS',
            'status' => 'calculated',
            'metrics' => [['label' => 'Шкала', 'score' => $score, 'maximum' => 20, 'level' => null]],
            'content_hash' => hash('sha256', (string) $score),
            'source_order' => 0,
        ]);
    }
}
