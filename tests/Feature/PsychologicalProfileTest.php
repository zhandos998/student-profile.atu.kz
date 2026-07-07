<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
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

    public function test_psychologist_can_view_student_test_results_on_student_profile_page(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'services.psychotest.base_url' => 'https://psychotest.test',
            'services.psychotest.token' => 'test-token',
            'services.psychotest.test_ids' => ['1', '2'],
        ]);
        Http::fake([
            'https://psychotest.test/api/students/123456789012/test-results*' => Http::response([
                'user' => [
                    'name' => 'Тестовый студент',
                    'iin' => '123456789012',
                    'group_name' => 'IS-101',
                ],
                'tests' => [
                    [
                        'id' => 1,
                        'title' => 'Тест адаптации',
                        'attempts' => [
                            [
                                'status' => 'completed',
                                'total_score' => '42.00',
                                'is_high_risk' => true,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $psychologistRole = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();
        $psychologist = User::factory()->create([
            'role_id' => $psychologistRole->id,
            'position' => 'Психолог',
        ]);
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $student = User::factory()->create([
            'role_id' => $studentRole->id,
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Тестовый студент',
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'group_name' => 'IS-101',
            'iin' => '123456789012',
        ]);

        $this->actingAs($psychologist)
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentProfile/Edit')
                ->where('canViewPsychotestResults', true)
                ->where('psychotestResults.iin', '123456789012')
                ->where('psychotestResults.test_ids', '1,2')
                ->where('psychotestResults.configured', true)
                ->where('psychotestResults.ok', true)
                ->where('psychotestResults.user.name', 'Тестовый студент')
                ->where('psychotestResults.results.0.id', 1)
                ->where('psychotestResults.results.0.title', 'Тест адаптации')
                ->where('psychotestResults.results.0.attempts.0.total_score', '42.00')
            );

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://psychotest.test/api/students/123456789012/test-results')
            && str_contains(urldecode($request->url()), 'test_ids=1,2')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('X-API-TOKEN', 'test-token'));
    }

    public function test_curator_cannot_view_student_test_results_on_student_profile_page(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'services.psychotest.base_url' => 'https://psychotest.test',
            'services.psychotest.token' => 'test-token',
            'services.psychotest.test_ids' => ['1', '2'],
        ]);
        Http::fake();

        $curatorRole = Role::query()->where('slug', Role::CURATOR)->firstOrFail();
        $curator = User::factory()->create([
            'role_id' => $curatorRole->id,
            'position' => 'Куратор / эдвайзер',
        ]);
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $student = User::factory()->create([
            'role_id' => $studentRole->id,
            'position' => 'Студент',
        ]);
        $group = StudentGroup::query()->create([
            'curator_id' => $curator->id,
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'name' => 'IS-101',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_group_id' => $group->id,
            'full_name' => 'Тестовый студент',
            'faculty' => $group->faculty,
            'group_name' => $group->name,
            'iin' => '123456789012',
        ]);

        $this->actingAs($curator)
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentProfile/Edit')
                ->where('canViewPsychotestResults', false)
                ->where('psychotestResults', null)
            );

        Http::assertNothingSent();
    }

    public function test_psychotest_results_can_be_requested_without_test_ids(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'services.psychotest.base_url' => 'https://psychotest.test',
            'services.psychotest.token' => 'test-token',
            'services.psychotest.test_ids' => [],
        ]);
        Http::fake([
            'https://psychotest.test/api/students/123456789012/test-results' => Http::response([
                'user' => [
                    'name' => 'Тестовый студент',
                    'iin' => '123456789012',
                ],
                'tests' => [
                    [
                        'id' => 1,
                        'title' => 'Тест адаптации',
                        'attempts' => [],
                    ],
                    [
                        'id' => 2,
                        'title' => 'Социальный опрос',
                        'attempts' => [],
                    ],
                ],
            ]),
        ]);

        $psychologistRole = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();
        $psychologist = User::factory()->create([
            'role_id' => $psychologistRole->id,
            'position' => 'Психолог',
        ]);
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $student = User::factory()->create([
            'role_id' => $studentRole->id,
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Тестовый студент',
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'group_name' => 'IS-101',
            'iin' => '123456789012',
        ]);

        $this->actingAs($psychologist)
            ->get(route('student-profiles.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('psychotestResults.test_ids', 'Все доступные')
                ->where('psychotestResults.results.0.id', 1)
                ->where('psychotestResults.results.1.id', 2)
            );

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://psychotest.test/api/students/123456789012/test-results'
            && ! str_contains($request->url(), 'test_ids=')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('X-API-TOKEN', 'test-token'));
    }
}
