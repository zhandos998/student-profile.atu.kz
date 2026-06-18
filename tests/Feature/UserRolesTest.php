<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_demo_user_seeders_create_required_accounts(): void
    {
        $this->seed([
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        $this->assertDatabaseCount('roles', 6);

        $this->assertDatabaseHas('roles', [
            'slug' => Role::ADMINISTRATOR_DIT,
            'name' => 'Администратор (ДИТ)',
        ]);
        $this->assertDatabaseHas('roles', [
            'slug' => Role::CURATOR,
            'name' => 'Куратор / эдвайзер',
        ]);
        $this->assertDatabaseHas('roles', [
            'slug' => Role::ADVISOR,
            'name' => 'Куратор / эдвайзер',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin.dit@atu.kz',
            'position' => 'Администратор ДИТ',
        ]);

        $administration = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();

        $this->assertSame(7, $administration->users()->count());
    }

    public function test_registered_users_receive_student_role_when_roles_are_seeded(): void
    {
        $this->seed(RoleSeeder::class);

        $this->post('/register', [
            'name' => 'New Student',
            'email' => 'new.student@atu.kz',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'new.student@atu.kz')->firstOrFail();

        $this->assertSame(Role::STUDENT, $user->role->slug);
    }
}
