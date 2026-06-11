<?php

namespace Tests\Feature;

use App\Models\HealthPassport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthPassportTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_center_user_can_view_health_passport_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::ADMINISTRATION, 'Здравпункт');

        $this->actingAs($user)
            ->get(route('health-passport.index'))
            ->assertOk();
    }

    public function test_student_cannot_view_health_passport_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($user)
            ->get(route('health-passport.index'))
            ->assertForbidden();
    }

    public function test_health_center_user_can_save_health_passport(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::ADMINISTRATION, 'Здравпункт');

        $this->actingAs($user)
            ->post(route('health-passport.update'), [
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

        $passport = HealthPassport::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-06-10', $passport->fluorography_date->format('Y-m-d'));
        $this->assertTrue($passport->dispensary_accounting);
        $this->assertSame('Хронический бронхит', $passport->diagnosis);
        $this->assertSame('2', $passport->disability_group);
        $this->assertSame('Нет', $passport->psychological_diagnosis);
        $this->assertSame('Нет', $passport->pregnancy);
        Storage::disk('public')->assertExists($passport->fluorography_image_path);
    }

    private function userWithRole(string $roleSlug, string $position): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'position' => $position,
        ]);
    }
}
