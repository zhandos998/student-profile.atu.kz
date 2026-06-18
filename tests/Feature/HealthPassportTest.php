<?php

namespace Tests\Feature;

use App\Models\HealthPassport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HealthPassportTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_center_user_is_redirected_to_student_list_from_health_passport_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::ADMINISTRATION, 'Здравпункт');

        $this->actingAs($user)
            ->get(route('health-passport.index'))
            ->assertRedirect(route('student-profiles.index'));
    }

    public function test_student_cannot_view_health_passport_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($user)
            ->get(route('health-passport.index'))
            ->assertForbidden();
    }

    public function test_health_center_user_sees_student_card_readonly_and_health_form(): void
    {
        $this->seed(RoleSeeder::class);

        $healthCenter = $this->userWithRole(Role::ADMINISTRATION, 'Здравпункт');
        $student = $this->userWithRole(Role::STUDENT, 'Студент', [
            'name' => 'Student Name',
            'email' => 'student@example.com',
        ]);

        $this->actingAs($healthCenter)
            ->get(route('student-profiles.edit', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentProfile/Edit')
                ->where('canEditProfile', false)
                ->where('canEditHealthPassport', true)
                ->where('targetUser.email', 'student@example.com')
            );
    }

    public function test_health_center_user_cannot_update_student_profile_data(): void
    {
        $this->seed(RoleSeeder::class);

        $healthCenter = $this->userWithRole(Role::ADMINISTRATION, 'Здравпункт');
        $student = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($healthCenter)
            ->post(route('student-profiles.update', $student), [
                'full_name' => 'Changed By Health Center',
            ])
            ->assertForbidden();
    }

    public function test_psychologist_sees_student_card_readonly_without_health_form(): void
    {
        $this->seed(RoleSeeder::class);

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->userWithRole(Role::STUDENT, 'Студент', [
            'email' => 'student@example.com',
        ]);

        $this->actingAs($psychologist)
            ->get(route('student-profiles.edit', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentProfile/Edit')
                ->where('canEditProfile', false)
                ->where('canEditHealthPassport', false)
                ->where('targetUser.email', 'student@example.com')
            );
    }

    public function test_psychologist_cannot_update_student_profile_data(): void
    {
        $this->seed(RoleSeeder::class);

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($psychologist)
            ->post(route('student-profiles.update', $student), [
                'full_name' => 'Changed By Psychologist',
            ])
            ->assertForbidden();
    }

    public function test_psychologist_cannot_save_student_health_passport(): void
    {
        $this->seed(RoleSeeder::class);

        $psychologist = $this->userWithRole(Role::ADMINISTRATION, 'Психолог');
        $student = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($psychologist)
            ->post(route('student-profiles.health-passport.update', $student), [
                'diagnosis' => 'Should not be saved by psychologist',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('health_passports', [
            'user_id' => $student->id,
        ]);
    }

    public function test_health_passport_update_requires_selected_student(): void
    {
        $this->seed(RoleSeeder::class);

        $healthCenter = $this->userWithRole(Role::ADMINISTRATION, 'Здравпункт');

        $this->actingAs($healthCenter)
            ->post(route('health-passport.update'), [
                'diagnosis' => 'Should not be saved on staff user',
            ])
            ->assertRedirect(route('student-profiles.index'));

        $this->assertDatabaseMissing('health_passports', [
            'user_id' => $healthCenter->id,
        ]);
    }

    public function test_health_center_user_can_save_student_health_passport(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $healthCenter = $this->userWithRole(Role::ADMINISTRATION, 'Здравпункт');
        $student = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($healthCenter)
            ->post(route('student-profiles.health-passport.update', $student), [
                'fluorography_date' => '2026-06-10',
                'fluorography_image' => UploadedFile::fake()->image('fluorography.png'),
                'dispensary_accounting' => '1',
                'diagnosis' => 'Хронический бронхит',
                'disability_group' => '2',
                'psychological_diagnosis' => 'Нет',
                'pregnancy' => 'Нет',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $passport = HealthPassport::query()->where('user_id', $student->id)->firstOrFail();

        $this->assertSame('2026-06-10', $passport->fluorography_date->format('Y-m-d'));
        $this->assertTrue($passport->dispensary_accounting);
        $this->assertSame('Хронический бронхит', $passport->diagnosis);
        $this->assertSame('2', $passport->disability_group);
        $this->assertSame('Нет', $passport->psychological_diagnosis);
        $this->assertSame('Нет', $passport->pregnancy);
        Storage::disk('public')->assertExists($passport->fluorography_image_path);
        $this->assertDatabaseMissing('health_passports', [
            'user_id' => $healthCenter->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleSlug, string $position, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            ...$attributes,
            'role_id' => $role->id,
            'position' => $position,
        ]);
    }
}
