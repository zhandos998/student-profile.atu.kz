<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncStudentSpecialtiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_a_legacy_specialty(): void
    {
        $profile = $this->profile('060515500608', 'B057 - Информационные технологии');
        $this->fakeStudent('060515500608', '6B06102', 'Вычислительная техника и программное обеспечение');

        $this->artisan('student-specialties:sync', ['--dry-run' => true, '--limit' => 1, '--delay-ms' => 0])
            ->expectsOutputToContain('would update: 1')
            ->assertSuccessful();

        $this->assertSame('B057 - Информационные технологии', $profile->fresh()->specialty);
    }

    public function test_updates_specialty_to_russian_program_code_and_name(): void
    {
        $profile = $this->profile('060515500608', 'B057 - Информационные технологии');
        $this->fakeStudent('060515500608', '6B06102', 'Вычислительная техника и программное обеспечение');

        $this->artisan('student-specialties:sync', ['--limit' => 1, '--delay-ms' => 0])
            ->expectsOutputToContain('updated: 1')
            ->assertSuccessful();

        $this->assertSame('6B06102 - Вычислительная техника и программное обеспечение', $profile->fresh()->specialty);
    }

    public function test_rejects_mismatched_iin_and_legacy_group_code(): void
    {
        $profile = $this->profile('060515500608', 'B057 - Информационные технологии');
        $this->fakeStudent('060515500609', 'B057', 'Информационные технологии');

        $this->artisan('student-specialties:sync', ['--limit' => 1, '--delay-ms' => 0])
            ->expectsOutputToContain('invalid API data: 1')
            ->assertSuccessful();

        $this->assertSame('B057 - Информационные технологии', $profile->fresh()->specialty);
    }

    private function profile(string $iin, string $specialty): StudentProfile
    {
        config()->set('services.platonus.api_key', 'test-key');
        config()->set('services.platonus.student_full_url', 'https://hub.test/api/v1/hub/student_full');

        return StudentProfile::create([
            'user_id' => User::factory()->create()->id,
            'iin' => $iin,
            'specialty' => $specialty,
        ]);
    }

    private function fakeStudent(string $iin, string $code, string $name): void
    {
        Http::fake([
            'https://hub.test/api/v1/hub/student_full*' => Http::response([
                'student' => [
                    'iin' => $iin,
                    'education' => [
                        'speciality_code' => $code,
                        'speciality_name_ru' => $name,
                    ],
                ],
            ]),
        ]);
    }
}
