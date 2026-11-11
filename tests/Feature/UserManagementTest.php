<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dit_administrator_can_view_all_users(): void
    {
        $this->seed(RoleSeeder::class);

        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT, [
            'name' => 'ДИТ Администратор',
            'email' => 'dit@example.com',
        ]);
        $this->userWithRole(Role::ADMINISTRATION, [
            'name' => 'Психолог',
            'email' => 'psychologist@example.com',
        ]);
        $this->userWithRole(Role::STUDENT, [
            'name' => 'Студент',
            'email' => 'student@example.com',
        ]);

        $this->actingAs($dit)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('users.total', 3)
                ->has('roleOptions', 7)
                ->where('filters.search', '')
                ->where('filters.role', '')
            );
    }

    public function test_users_pagination_labels_are_translated(): void
    {
        $this->seed(RoleSeeder::class);

        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();

        User::factory()
            ->count(25)
            ->create([
                'role_id' => $studentRole->id,
                'position' => $studentRole->name,
            ]);

        $this->actingAs($dit)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.links.0.label', '&laquo; Назад')
                ->where('users.links.3.label', 'Далее &raquo;')
            );
    }

    public function test_student_cannot_view_users_page(): void
    {
        $this->seed(RoleSeeder::class);

        $student = $this->userWithRole(Role::STUDENT);

        $this->actingAs($student)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_dit_administrator_can_create_user_and_change_role(): void
    {
        $this->seed(RoleSeeder::class);
        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);

        $this->actingAs($dit)->post(route('users.store'), [
            'name' => 'Новый сотрудник',
            'email' => 'new.staff@example.com',
            'phone' => '+7 777 123 45 67',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => Role::CURATOR,
        ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

        $staff = User::query()->where('email', 'new.staff@example.com')->firstOrFail();
        $this->assertSame(Role::CURATOR, $staff->role->slug);
        $this->assertSame('77771234567', $staff->phone_normalized);

        $this->patch(route('users.role.update', $staff), [
            'role' => Role::ADMINISTRATION,
            'position' => 'Психолог',
        ])->assertSessionHasNoErrors();

        $this->assertSame(Role::ADMINISTRATION, $staff->fresh()->role->slug);
        $this->assertSame('Психолог', $staff->fresh()->position);
    }

    public function test_only_dit_can_manage_users_and_cannot_change_own_role(): void
    {
        $this->seed(RoleSeeder::class);
        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);
        $student = $this->userWithRole(Role::STUDENT);

        $this->actingAs($student)->post(route('users.store'), [])->assertForbidden();
        $this->patch(route('users.role.update', $dit), ['role' => Role::STUDENT])->assertForbidden();
        $this->patch(route('users.blocked.update', $dit), ['blocked' => true])->assertForbidden();

        $this->actingAs($dit)->patch(route('users.role.update', $dit), ['role' => Role::STUDENT])->assertStatus(422);
        $this->patch(route('users.blocked.update', $dit), ['blocked' => true])->assertStatus(422);
        $this->patch(route('users.role.update', $student), ['role' => Role::GROUP_LEADER])->assertSessionHasErrors('role');
        $this->assertFalse($dit->fresh()->is_blocked);
    }

    public function test_assigned_curator_must_transfer_groups_before_role_change(): void
    {
        $this->seed(RoleSeeder::class);
        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);
        $curator = $this->userWithRole(Role::CURATOR);
        StudentGroup::query()->create([
            'name' => 'ИС-101',
            'faculty' => 'Факультет информационных технологий',
            'curator_id' => $curator->id,
        ]);

        $this->actingAs($dit)->patch(route('users.role.update', $curator), ['role' => Role::STUDENT])
            ->assertSessionHasErrors('role');
        $this->assertSame(Role::CURATOR, $curator->fresh()->role->slug);

        $this->patch(route('users.role.update', $curator), ['role' => Role::ADVISOR])
            ->assertSessionHasNoErrors();
        $this->assertSame(Role::ADVISOR, $curator->fresh()->role->slug);
    }

    public function test_blocking_user_rejects_login_and_existing_session(): void
    {
        $this->seed(RoleSeeder::class);
        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);
        $student = $this->userWithRole(Role::STUDENT, ['password' => 'StrongPass123!']);

        $this->actingAs($dit)->patch(route('users.blocked.update', $student), ['blocked' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($student->fresh()->is_blocked);

        $this->post(route('users.impersonate', $student))->assertStatus(422);
        $this->actingAs($student)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'StrongPass123!',
            'auth_type' => 'local',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($dit)->patch(route('users.blocked.update', $student), ['blocked' => false]);
        $this->assertFalse($student->fresh()->is_blocked);
    }

    public function test_blocked_account_cannot_sign_in_through_platonus(): void
    {
        $this->seed(RoleSeeder::class);
        $student = $this->userWithRole(Role::STUDENT, [
            'platonus_login' => 'blocked.student',
            'is_blocked' => true,
        ]);
        config([
            'services.platonus.verify_url' => 'https://hub.atu.kz/api/v1/students/verify',
            'services.platonus.api_key' => 'test-key',
        ]);
        Http::fake([
            'https://hub.atu.kz/api/v1/students/verify' => Http::response([
                'authenticated' => true,
                'student' => ['email' => $student->email],
            ]),
        ]);

        $this->post(route('login'), [
            'auth_type' => 'platonus',
            'login' => 'blocked.student',
            'password' => 'platonus-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertTrue($student->fresh()->is_blocked);
    }

    public function test_dit_administrator_can_assign_and_remove_service_operator_role(): void
    {
        $this->seed(RoleSeeder::class);
        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);
        $student = $this->userWithRole(Role::STUDENT);

        $this->actingAs($dit)->patch(route('users.service-operator.update', $student), ['enabled' => true])
            ->assertRedirect();
        $this->assertSame(Role::SERVICE_OPERATOR, $student->fresh()->role->slug);

        $this->patch(route('users.service-operator.update', $student), ['enabled' => false])
            ->assertRedirect();
        $this->assertSame(Role::STUDENT, $student->fresh()->role->slug);
    }

    public function test_service_operator_assignment_cannot_change_other_roles_or_be_used_by_non_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);
        $curator = $this->userWithRole(Role::CURATOR);
        $student = $this->userWithRole(Role::STUDENT);

        $this->actingAs($curator)->patch(route('users.service-operator.update', $student), ['enabled' => true])
            ->assertForbidden();
        $this->actingAs($dit)->patch(route('users.service-operator.update', $curator), ['enabled' => true])
            ->assertStatus(422);
        $this->assertSame(Role::CURATOR, $curator->fresh()->role->slug);
    }

    public function test_non_dit_user_cannot_impersonate(): void
    {
        $this->seed(RoleSeeder::class);

        $curator = $this->userWithRole(Role::CURATOR);
        $student = $this->userWithRole(Role::STUDENT);

        $this->actingAs($curator)
            ->post(route('users.impersonate', $student))
            ->assertForbidden();
    }

    public function test_dit_administrator_can_impersonate_user_and_return(): void
    {
        $this->seed(RoleSeeder::class);

        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT, [
            'name' => 'ДИТ Администратор',
        ]);
        $student = $this->userWithRole(Role::STUDENT, [
            'name' => 'Тестовый студент',
        ]);

        $this->actingAs($dit)
            ->post(route('users.impersonate', $student))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($student);
        $this->assertSame($dit->id, session('impersonator_id'));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($dit);
        $this->assertFalse(session()->has('impersonator_id'));
    }

    public function test_dit_administrator_cannot_impersonate_self(): void
    {
        $this->seed(RoleSeeder::class);

        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);

        $this->actingAs($dit)
            ->post(route('users.impersonate', $dit))
            ->assertStatus(422);
    }

    public function test_dit_administrator_can_delete_user(): void
    {
        $this->seed(RoleSeeder::class);

        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);
        $student = $this->userWithRole(Role::STUDENT);

        $this->actingAs($dit)
            ->delete(route('users.destroy', $student))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $student->id,
        ]);
    }

    public function test_dit_administrator_cannot_delete_self(): void
    {
        $this->seed(RoleSeeder::class);

        $dit = $this->userWithRole(Role::ADMINISTRATOR_DIT);

        $this->actingAs($dit)
            ->delete(route('users.destroy', $dit))
            ->assertSessionHasErrors('user_delete');

        $this->assertDatabaseHas('users', [
            'id' => $dit->id,
        ]);
    }

    public function test_student_cannot_delete_user(): void
    {
        $this->seed(RoleSeeder::class);

        $student = $this->userWithRole(Role::STUDENT);
        $otherStudent = $this->userWithRole(Role::STUDENT);

        $this->actingAs($student)
            ->delete(route('users.destroy', $otherStudent))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $otherStudent->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'position' => $role->name,
            ...$attributes,
        ]);
    }
}
