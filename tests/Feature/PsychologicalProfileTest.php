<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\StudentSurveyResultSnapshot;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PsychologicalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_psychologist_sees_hub_surveys_in_student_profile(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();

        $reverse = [2, 5, 7, 8, 11, 12, 15, 16];
        $questions = collect(range(1, 16))
            ->map(fn (int $number): array => [
                'question' => 'Вопрос '.$number,
                'answer' => in_array($number, $reverse, true) ? 'Нет – 0 баллов' : 'Да – 2 балла',
            ])
            ->all();

        Http::fake([
            'https://hub.test/api/v1/students/surveys*' => Http::response([
                'iin' => '123456789012',
                'surveys_count' => 1,
                'surveys' => [[
                    'survey_name' => 'Тест «Шкала адаптации»',
                    'questions_count' => 16,
                    'questions' => $questions,
                ]],
            ]),
        ]);

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->studentWithIin();

        $this->actingAs($psychologist)
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentProfile/Edit')
                ->where('canViewSurveyResults', true)
                ->where('surveyResults.iin', '123456789012')
                ->where('surveyResults.ok', true)
                ->where('surveyResults.results.0.name', 'Тест «Шкала адаптации»')
                ->where('surveyResults.results.0.metrics.0.score', 16)
                ->where('surveyResults.results.0.metrics.1.score', 16)
                ->missing('surveyResults.surveys')
                ->missing('surveyResults.results.0.questions')
                ->missing('surveyResults.raw')
            );

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://hub.test/api/v1/students/surveys?')
            && str_contains($request->url(), 'iin=123456789012')
            && str_contains($request->url(), 'lang=ru')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('X-API-Key', 'test-key'));
    }

    public function test_curator_and_student_cannot_receive_hub_surveys(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();
        Http::fake();

        $curator = $this->userWithRole(Role::CURATOR, 'Куратор / эдвайзер');
        $student = $this->studentWithIin();
        $group = StudentGroup::query()->create([
            'curator_id' => $curator->id,
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'name' => 'IS-101',
        ]);
        $student->studentProfile->update(['student_group_id' => $group->id]);

        $this->actingAs($curator)
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewSurveyResults', false)
                ->where('surveyResults', null)
            );

        $this->actingAs($student)
            ->get(route('student-profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewSurveyResults', false)
                ->where('surveyResults', null)
            );

        Http::assertNothingSent();
    }

    public function test_hub_survey_request_uses_kazakh_language_when_selected(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();
        Http::fake([
            'https://hub.test/api/v1/students/surveys*' => Http::response([
                'iin' => '123456789012',
                'surveys' => [],
            ]),
        ]);

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->studentWithIin();

        $this->actingAs($psychologist)
            ->withSession(['locale' => 'kk'])
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyResults.ok', true)
                ->where('surveyResults.results', [])
            );

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'lang=kz'));
    }

    public function test_kazakh_hads_with_unknown_option_text_uses_russian_scoring_fallback(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();

        $russianAnswers = [
            'часто', 'да, но не очень сильно', 'иногда', 'иногда', 'совсем нет', 'совсем нет', 'часто',
            'в очень малой степени', 'да, безусловно', 'иногда', 'совсем нет',
            'возможно стал(а) уделять меньше внимания', 'как обычно', 'часто',
        ];

        Http::fake(function (Request $request) use ($russianAnswers) {
            $kazakh = str_contains($request->url(), 'lang=kz');

            return Http::response([
                'iin' => '123456789012',
                'surveys' => [[
                    'survey_name' => $kazakh
                        ? 'Мазасыздық және депрессия шкаласы (HADS)'
                        : 'Шкала тревоги и депрессии (HADS)',
                    'questions' => array_map(
                        fn (string $answer): array => ['question' => 'Сұрақ', 'answer' => $answer],
                        $kazakh ? array_fill(0, 14, 'белгісіз жауап') : $russianAnswers,
                    ),
                ]],
            ]);
        });

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->studentWithIin();

        $this->actingAs($psychologist)
            ->withSession(['locale' => 'kk'])
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyResults.results.0.name', 'Мазасыздық және депрессия шкаласы (HADS)')
                ->where('surveyResults.results.0.status', 'calculated')
                ->where('surveyResults.results.0.metrics.0.score', 9)
                ->where('surveyResults.results.0.metrics.1.score', 7)
                ->missing('surveyResults.results.0.questions')
            );

        Http::assertSentCount(2);
    }

    public function test_temperament_blocks_are_grouped_and_only_summaries_reach_the_page(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();

        $surveys = [
            ['survey_name' => 'Опросник для определения типа темперамента', 'questions' => [['question' => 'Старый вопрос', 'answer' => 'да']]],
            ['survey_name' => 'Тест для определения типа характера', 'questions' => [['question' => 'Старый вопрос', 'answer' => 'нет']]],
        ];
        foreach ([
            ['I блок — Холерический темперамент', 20],
            ['II блок — Сангвинический темперамент', 20],
            ['III блок — Флегматический темперамент', 21],
            ['IV блок — Меланхолический темперамент', 20],
        ] as [$name, $count]) {
            $surveys[] = [
                'survey_name' => $name,
                'questions' => array_fill(0, $count, ['question' => 'Текст вопроса', 'answer' => 'да']),
            ];
        }
        $surveys[] = [
            'survey_name' => 'PGSI',
            'questions' => array_fill(0, 9, ['question' => 'Текст вопроса', 'answer' => '0 — Никогда']),
        ];

        Http::fake(['https://hub.test/api/v1/students/surveys*' => Http::response([
            'iin' => '123456789012',
            'surveys' => $surveys,
        ])]);

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->studentWithIin();

        $this->actingAs($psychologist)
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('surveyResults.results', 2)
                ->where('surveyResults.results.0.name', 'Формула темперамента')
                ->where('surveyResults.results.0.status', 'calculated')
                ->where('surveyResults.results.1.name', 'PGSI')
                ->where('surveyResults.results.1.metrics.0.score', 0)
                ->missing('surveyResults.results.0.questions')
                ->missing('surveyResults.results.1.questions')
            );
    }

    public function test_survey_results_are_saved_without_raw_answers_and_changed_scores_create_snapshots(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();

        $answer = '0 — Никогда';
        Http::fake(function () use (&$answer) {
            return Http::response([
                'iin' => '123456789012',
                'surveys' => [[
                    'survey_name' => 'PGSI',
                    'questions' => array_fill(0, 9, ['question' => 'Секретный вопрос', 'answer' => $answer]),
                ]],
            ]);
        });

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->studentWithIin();
        $url = route('student-profiles.show', $student);

        $this->actingAs($psychologist)->get($url)->assertOk();
        $this->actingAs($psychologist)->get($url)->assertOk();

        $this->assertSame(1, StudentSurveyResultSnapshot::query()->count());
        $snapshot = StudentSurveyResultSnapshot::query()->firstOrFail();
        $this->assertSame($student->studentProfile->id, $snapshot->student_profile_id);
        $this->assertSame('123456789012', $snapshot->source_iin);
        $this->assertSame('pgsi', $snapshot->survey_key);
        $this->assertSame(0, $snapshot->metrics[0]['score']);
        $this->assertStringNotContainsString('Секретный вопрос', $snapshot->toJson());
        $this->assertStringNotContainsString('Никогда', $snapshot->toJson());

        $answer = '1 — Иногда';
        $this->actingAs($psychologist)->get($url)->assertOk();

        $this->assertSame(2, StudentSurveyResultSnapshot::query()->count());
        $this->assertSame(9, StudentSurveyResultSnapshot::query()->latest('id')->firstOrFail()->metrics[0]['score']);
    }

    public function test_saved_survey_results_are_used_only_for_the_same_iin_when_api_fails(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();
        $available = true;
        Http::fake(function () use (&$available) {
            if (! $available) {
                return Http::response([], 503);
            }

            return Http::response([
                'iin' => '123456789012',
                'surveys' => [[
                    'survey_name' => 'PGSI',
                    'questions' => array_fill(0, 9, ['question' => 'Вопрос', 'answer' => '0 — Никогда']),
                ]],
            ]);
        });

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->studentWithIin();
        $url = route('student-profiles.show', $student);
        $this->actingAs($psychologist)->get($url)->assertOk();

        $available = false;
        $this->actingAs($psychologist)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyResults.ok', false)
                ->where('surveyResults.cached', true)
                ->where('surveyResults.results.0.metrics.0.score', 0)
                ->missing('surveyResults.results.0.questions')
            );

        $this->actingAs($student)
            ->post(route('student-profile.update'), ['iin' => '999999999999'])
            ->assertRedirect();
        $this->assertSame(0, StudentSurveyResultSnapshot::query()->count());

        $this->actingAs($psychologist)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyResults.cached', false)
                ->where('surveyResults.results', [])
            );
    }

    public function test_hub_survey_response_for_another_iin_is_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureSurveyApi();
        Http::fake([
            'https://hub.test/api/v1/students/surveys*' => Http::response([
                'iin' => '999999999999',
                'surveys' => [['survey_name' => 'Чужая анкета', 'questions' => []]],
            ]),
        ]);

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->studentWithIin();

        $this->actingAs($psychologist)
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyResults.ok', false)
                ->where('surveyResults.results', [])
            );
    }

    private function configureSurveyApi(): void
    {
        config([
            'services.platonus.surveys_url' => 'https://hub.test/api/v1/students/surveys',
            'services.platonus.api_key' => 'test-key',
        ]);
    }

    private function userWithRole(string $slug, string $position): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('slug', $slug)->firstOrFail()->id,
            'position' => $position,
        ]);
    }

    private function studentWithIin(): User
    {
        $student = $this->userWithRole(Role::STUDENT, 'Студент');

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Тестовый студент',
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'group_name' => 'IS-101',
            'iin' => '123456789012',
        ]);

        return $student;
    }
}
