<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\StudentSurveyResultSnapshot;
use App\Models\User;
use App\Services\StudentSurveySynchronizer;
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

    public function test_complete_results_skip_api_and_do_not_use_the_limit(): void
    {
        $this->configureSurveyApi();
        $complete = $this->student('123456789012');
        $this->saveCompleteResults($complete);
        $missing = $this->student('234567890123');

        Http::fake(['*' => Http::response([
            'iin' => '234567890123',
            'surveys' => [[
                'survey_name' => 'PGSI',
                'questions' => array_fill(0, 9, ['answer' => '0 — Никогда']),
            ]],
        ])]);

        $cached = app(StudentSurveySynchronizer::class)->synchronize($complete);
        $this->assertTrue($cached['skipped']);
        $this->assertTrue($cached['cached']);
        $this->assertCount(9, $cached['results']);
        Http::assertNothingSent();

        $this->artisan('student-surveys:sync', ['--limit' => 1, '--delay-ms' => 0])
            ->expectsOutput("Profile {$missing->id}: API OK; calculated tests in response 1; saved complete tests 1/9.")
            ->expectsOutput('Processed: 1; API OK: 1; profiles with calculated tests: 1; calculated tests in responses: 1; complete after sync: 0; skipped complete: 1; failed: 0; invalid IIN: 0.')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_successful_empty_api_response_is_not_reported_as_calculated_data(): void
    {
        $this->configureSurveyApi();
        $profile = $this->student('123456789012');
        Http::fake(['*' => Http::response(['iin' => '123456789012', 'surveys' => []])]);

        $this->artisan('student-surveys:sync', ['--limit' => 1, '--delay-ms' => 0])
            ->expectsOutput("Profile {$profile->id}: API OK; calculated tests in response 0; saved complete tests 0/9.")
            ->expectsOutput('Processed: 1; API OK: 1; profiles with calculated tests: 0; calculated tests in responses: 0; complete after sync: 0; skipped complete: 0; failed: 0; invalid IIN: 0.')
            ->assertSuccessful();

        $this->assertSame(0, StudentSurveyResultSnapshot::query()->count());
    }

    public function test_missing_or_outdated_results_are_fetched_and_force_refreshes_complete_results(): void
    {
        $this->configureSurveyApi();
        $profile = $this->student('123456789012');
        $this->saveCompleteResults($profile);

        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response([
                'iin' => $query['iin'],
                'surveys' => [[
                    'survey_name' => 'PGSI',
                    'questions' => array_fill(0, 9, ['answer' => '1 — Иногда']),
                ]],
            ]);
        });

        $this->artisan('student-surveys:sync', ['--force' => true, '--delay-ms' => 0])->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertSame(10, StudentSurveyResultSnapshot::query()->where('student_profile_id', $profile->id)->count());

        StudentSurveyResultSnapshot::query()
            ->where('student_profile_id', $profile->id)
            ->where('survey_key', 'hads')
            ->update(['status' => 'incomplete']);

        $this->artisan('student-surveys:sync', ['--delay-ms' => 0])->assertSuccessful();
        Http::assertSentCount(2);

        $profile->update(['iin' => '234567890123']);
        $this->artisan('student-surveys:sync', ['--delay-ms' => 0])->assertSuccessful();
        Http::assertSentCount(3);
    }

    private function saveCompleteResults(StudentProfile $profile): void
    {
        foreach ([
            'self_esteem' => 1,
            'adaptation' => 2,
            'hads' => 2,
            'loneliness' => 1,
            'stress' => 1,
            'temperament_formula' => 4,
            'five_traits' => 5,
            'sl19' => 1,
            'pgsi' => 1,
        ] as $key => $count) {
            StudentSurveyResultSnapshot::query()->create([
                'student_profile_id' => $profile->id,
                'source_iin' => $profile->iin,
                'survey_key' => $key,
                'survey_name' => $key,
                'status' => 'calculated',
                'metrics' => array_fill(0, $count, ['label' => 'Шкала', 'score' => 0, 'maximum' => 20]),
                'content_hash' => hash('sha256', $key),
                'source_order' => 0,
            ]);
        }
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
