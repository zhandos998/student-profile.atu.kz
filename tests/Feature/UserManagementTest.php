<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->has('roleOptions', 6)
                ->where('filters.search', '')
                ->where('filters.role', '')
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
