<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\StudentSurveyResultSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncStudentSurveysCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_saves_results_for_all_profiles_with_an_iin(): void
    {
        $this->configureSurveyApi();
        $this->student('123456789012');
        $this->student('234567890123');
        $this->student(null);

        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response([
                'iin' => $query['iin'],
                'surveys' => [[
                    'survey_name' => 'PGSI',
                    'questions' => array_fill(0, 9, ['question' => 'Hidden question', 'answer' => '0 — Никогда']),
                ]],
            ]);
        });

        $this->artisan('student-surveys:sync', ['--delay-ms' => 0])
            ->assertSuccessful();

        $this->assertSame(2, StudentSurveyResultSnapshot::query()->count());
        $this->assertSame(2, StudentSurveyResultSnapshot::query()->distinct()->count('student_profile_id'));
        $this->assertSame(['pgsi'], StudentSurveyResultSnapshot::query()->distinct()->pluck('survey_key')->all());
        Http::assertSentCount(2);
    }

    public function test_command_continues_after_one_students_api_failure(): void
    {
        $this->configureSurveyApi();
        $this->student('123456789012');
        $this->student('234567890123');

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '123456789012')) {
                return Http::response([], 503);
            }

            return Http::response([
                'iin' => '234567890123',
                'surveys' => [[
                    'survey_name' => 'PGSI',
                    'questions' => array_fill(0, 9, ['answer' => '1 — Иногда']),
                ]],
            ]);
        });

        $this->artisan('student-surveys:sync', ['--delay-ms' => 0])
            ->assertFailed();

        $this->assertSame(1, StudentSurveyResultSnapshot::query()->count());
        $this->assertSame('234567890123', StudentSurveyResultSnapshot::query()->firstOrFail()->source_iin);
        Http::assertSentCount(2);
    }

    private function configureSurveyApi(): void
    {
        config([
            'services.platonus.surveys_url' => 'https://hub.test/api/v1/students/surveys',
            'services.platonus.api_key' => 'test-key',
        ]);
    }

    private function student(?string $iin): StudentProfile
    {
        $user = User::factory()->create();

        return StudentProfile::query()->create([
            'user_id' => $user->id,
            'iin' => $iin,
        ]);
    }
}
