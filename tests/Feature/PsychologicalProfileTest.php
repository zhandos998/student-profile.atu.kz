<?php

namespace Tests\Feature;

use App\Models\PsychologicalProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PsychologicalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_psychologist_can_view_psychological_profile_page(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Психолог',
        ]);

        $this->actingAs($user)
            ->get(route('psychological-profile.index'))
            ->assertOk();
    }

    public function test_dit_administrator_can_view_psychological_profile_page(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Администратор ДИТ',
        ]);

        $this->actingAs($user)
            ->get(route('psychological-profile.index'))
            ->assertOk();
    }

    public function test_student_cannot_view_psychological_profile_page(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Студент',
        ]);

        $this->actingAs($user)
            ->get(route('psychological-profile.index'))
            ->assertForbidden();
    }

    public function test_psychologist_can_save_psychological_profile_text_fields(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Психолог',
        ]);

        $this->actingAs($user)
            ->post(route('psychological-profile.update'), [
                'testing_results' => 'Результаты тестирований по психотестам',
                'individual_features' => 'Индивидуальные особенности студента',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = PsychologicalProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Результаты тестирований по психотестам', $profile->testing_results);
        $this->assertSame('Индивидуальные особенности студента', $profile->individual_features);
    }

    public function test_student_cannot_save_psychological_profile_text_fields(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Студент',
        ]);

        $this->actingAs($user)
            ->post(route('psychological-profile.update'), [
                'testing_results' => 'Закрытые данные',
                'individual_features' => 'Закрытые данные',
            ])
            ->assertForbidden();
    }
}
