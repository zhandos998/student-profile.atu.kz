<?php

namespace Tests\Feature;

use App\Models\ClearanceRequest;
use App\Models\ClearanceResponse;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClearanceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.clearance_requests_enabled' => true]);
        $this->seed(RoleSeeder::class);
    }

    public function test_clearance_requests_are_hidden_and_unavailable_by_default(): void
    {
        config(['app.clearance_requests_enabled' => false]);
        $admin = $this->user(Role::ADMINISTRATOR_DIT, 'Администратор ДИТ');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.canViewClearanceRequests', false)
            );

        $this->actingAs($admin)->get(route('clearance-requests.index'))->assertNotFound();
        $this->actingAs($admin)->postJson(route('clearance-requests.lookup'), ['iin' => '060515500608'])->assertNotFound();
        $this->actingAs($admin)->post(route('clearance-requests.store'))->assertNotFound();
        $this->actingAs($admin)->post(route('clearance-responses.respond', 1))->assertNotFound();
    }

    public function test_only_deputy_dean_can_find_student_by_iin(): void
    {
        $deputy = $this->user(Role::ADMINISTRATION, 'Зам. декана по ВР');
        $otherStaff = $this->user(Role::ADMINISTRATION, 'Психолог');
        $student = $this->student('060515500608');

        $this->actingAs($deputy)
            ->postJson(route('clearance-requests.lookup'), ['iin' => '060515500608'])
            ->assertOk()
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('student.name', 'Test Student');

        $this->actingAs($otherStaff)
            ->postJson(route('clearance-requests.lookup'), ['iin' => '060515500608'])
            ->assertForbidden();

        $this->actingAs($deputy)
            ->postJson(route('clearance-requests.lookup'), ['iin' => '123'])
            ->assertUnprocessable();
    }

    public function test_deputy_can_send_request_to_multiple_staff_members(): void
    {
        $deputy = $this->user(Role::ADMINISTRATION, 'Зам. декана по ВР');
        $registrar = $this->user(Role::ADMINISTRATION, 'Офис регистратора');
        $curator = $this->user(Role::CURATOR, 'Куратор');
        $student = $this->student('060515500608');

        $this->actingAs($deputy)
            ->post(route('clearance-requests.store'), [
                'iin' => $student->iin,
                'student_profile_id' => $student->id,
                'reviewer_ids' => [$registrar->id, $curator->id],
            ])
            ->assertRedirect(route('clearance-requests.index'))
            ->assertSessionHasNoErrors();

        $clearance = ClearanceRequest::query()->with('responses')->firstOrFail();
        $this->assertSame($student->id, $clearance->student_profile_id);
        $this->assertSame($deputy->id, $clearance->created_by_id);
        $this->assertSame(ClearanceResponse::PENDING, $clearance->status());
        $this->assertCount(2, $clearance->responses);
        $this->assertEqualsCanonicalizing([$registrar->id, $curator->id], $clearance->responses->pluck('reviewer_id')->all());

        $this->actingAs($student->user)
            ->get(route('clearance-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClearanceRequests/Index')
                ->has('studentRequests.data', 1)
                ->where('studentRequests.data.0.status', 'pending')
                ->has('studentRequests.data.0.responses', 2)
                ->missing('recipientOptions.0')
            );
    }

    public function test_dit_administrator_can_find_student_and_send_request(): void
    {
        $admin = $this->user(Role::ADMINISTRATOR_DIT, 'Администратор ДИТ');
        $reviewer = $this->user(Role::ADMINISTRATION, 'Офис регистратора');
        $student = $this->student('060515500608');

        $this->actingAs($admin)
            ->get(route('clearance-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreate', true)
                ->where('canReview', true)
            );

        $this->actingAs($admin)
            ->postJson(route('clearance-requests.lookup'), ['iin' => $student->iin])
            ->assertOk()
            ->assertJsonPath('student.id', $student->id);

        $this->actingAs($admin)
            ->post(route('clearance-requests.store'), [
                'iin' => $student->iin,
                'student_profile_id' => $student->id,
                'reviewer_ids' => [$reviewer->id],
            ])
            ->assertRedirect(route('clearance-requests.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clearance_requests', [
            'student_profile_id' => $student->id,
            'created_by_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('clearance_responses', ['reviewer_id' => $reviewer->id]);
    }

    public function test_invalid_recipients_and_mismatched_iin_are_rejected(): void
    {
        $deputy = $this->user(Role::ADMINISTRATION, 'Зам. декана по УР');
        $student = $this->student('060515500608');
        $anotherStudent = $this->user(Role::STUDENT, 'Студент');

        foreach ([[$anotherStudent->id], [$deputy->id], [$deputy->id, $deputy->id]] as $reviewerIds) {
            $this->actingAs($deputy)
                ->post(route('clearance-requests.store'), [
                    'iin' => $student->iin,
                    'student_profile_id' => $student->id,
                    'reviewer_ids' => $reviewerIds,
                ])
                ->assertSessionHasErrors(count(array_unique($reviewerIds)) === count($reviewerIds)
                    ? 'reviewer_ids'
                    : 'reviewer_ids.0');
        }

        $reviewer = $this->user(Role::ADMINISTRATION, 'Психолог');
        $this->actingAs($deputy)
            ->post(route('clearance-requests.store'), [
                'iin' => '111111111111',
                'student_profile_id' => $student->id,
                'reviewer_ids' => [$reviewer->id],
            ])
            ->assertSessionHasErrors('iin');

        $this->assertDatabaseCount('clearance_requests', 0);
    }

    public function test_only_deputy_can_create_and_open_request_cannot_be_duplicated(): void
    {
        $deputy = $this->user(Role::ADMINISTRATION, 'Зам. декана по ВР');
        $registrar = $this->user(Role::ADMINISTRATION, 'Офис регистратора');
        $student = $this->student('060515500608');
        $payload = [
            'iin' => $student->iin,
            'student_profile_id' => $student->id,
            'reviewer_ids' => [$registrar->id],
        ];

        $this->actingAs($registrar)
            ->post(route('clearance-requests.store'), $payload)
            ->assertForbidden();

        $this->actingAs($deputy)
            ->post(route('clearance-requests.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($deputy)
            ->post(route('clearance-requests.store'), $payload)
            ->assertSessionHasErrors('iin');

        $this->assertDatabaseCount('clearance_requests', 1);
    }

    public function test_recipient_can_decide_and_student_can_see_comments_and_statuses(): void
    {
        $deputy = $this->user(Role::ADMINISTRATION, 'Зам. декана по ВР');
        $registrar = $this->user(Role::ADMINISTRATION, 'Офис регистратора');
        $health = $this->user(Role::ADMINISTRATION, 'Здравпункт');
        $unassigned = $this->user(Role::ADMINISTRATION, 'Психолог');
        $student = $this->student('060515500608');

        $this->actingAs($deputy)->post(route('clearance-requests.store'), [
            'iin' => $student->iin,
            'student_profile_id' => $student->id,
            'reviewer_ids' => [$registrar->id, $health->id],
        ])->assertSessionHasNoErrors();

        $clearance = ClearanceRequest::query()->with('responses')->firstOrFail();
        $registrarResponse = $clearance->responses->firstWhere('reviewer_id', $registrar->id);
        $healthResponse = $clearance->responses->firstWhere('reviewer_id', $health->id);

        $this->actingAs($unassigned)
            ->get(route('clearance-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->has('assignedRequests.data', 0));

        $this->actingAs($unassigned)
            ->post(route('clearance-responses.respond', $registrarResponse), [
                'decision' => 'approved',
                'comment' => 'Не мой ответ',
            ])
            ->assertNotFound();

        $this->actingAs($student->user)
            ->post(route('clearance-responses.respond', $registrarResponse), [
                'decision' => 'approved',
                'comment' => 'Не мой ответ',
            ])
            ->assertForbidden();

        $this->actingAs($registrar)
            ->post(route('clearance-responses.respond', $registrarResponse), [
                'decision' => 'approved',
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');

        $this->actingAs($registrar)
            ->post(route('clearance-responses.respond', $registrarResponse), [
                'decision' => 'approved',
                'comment' => 'Разрешаю выдачу.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($registrar)
            ->post(route('clearance-responses.respond', $registrarResponse), [
                'decision' => 'rejected',
                'comment' => 'Повторное решение',
            ])
            ->assertSessionHasErrors('decision');

        $this->actingAs($student->user)
            ->get(route('clearance-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('studentRequests.data.0.status', 'pending')
                ->where('studentRequests.data.0.responses.0.decision', 'approved')
                ->where('studentRequests.data.0.responses.0.comment', 'Разрешаю выдачу.')
                ->where('studentRequests.data.0.responses.1.decision', 'pending')
            );

        $this->actingAs($health)
            ->post(route('clearance-responses.respond', $healthResponse), [
                'decision' => 'rejected',
                'comment' => 'Есть задолженность.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($student->user)
            ->get(route('clearance-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('studentRequests.data.0.status', 'rejected')
                ->where('studentRequests.data.0.responses.1.comment', 'Есть задолженность.')
            );
    }

    public function test_student_only_sees_own_requests(): void
    {
        $deputy = $this->user(Role::ADMINISTRATION, 'Зам. декана по ВР');
        $reviewer = $this->user(Role::ADMINISTRATION, 'Офис регистратора');
        $student = $this->student('060515500608');
        $otherStudent = $this->student('060515500609');

        foreach ([$student, $otherStudent] as $profile) {
            $this->actingAs($deputy)->post(route('clearance-requests.store'), [
                'iin' => $profile->iin,
                'student_profile_id' => $profile->id,
                'reviewer_ids' => [$reviewer->id],
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($student->user)
            ->get(route('clearance-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('studentRequests.data', 1)
                ->where('studentRequests.data.0.student_iin', $student->iin)
                ->where('canCreate', false)
                ->where('canReview', false)
            );
    }

    private function user(string $roleSlug, string $position): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            'position' => $position,
        ]);
    }

    private function student(string $iin): StudentProfile
    {
        $user = $this->user(Role::STUDENT, 'Студент');

        return StudentProfile::query()->create([
            'user_id' => $user->id,
            'iin' => $iin,
            'full_name' => 'Test Student',
        ]);
    }
}
