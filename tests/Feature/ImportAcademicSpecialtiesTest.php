<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportAcademicSpecialtiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_replaces_only_specialty_catalog_after_validating_all_languages(): void
    {
        Storage::fake('local');
        $directory = Storage::disk('local')->path('');
        $this->writeFile('rus', '6B06101 Информационные системы', 'Информационные системы');
        $this->writeFile('kaz', '6B06101 Ақпараттық жүйелер', 'Ақпараттық жүйелер');
        $this->writeFile('eng', '6B06101 Information Systems', 'Information Systems');
        Specialty::query()->create(['value' => 'Old value', 'name_ru' => 'Old value']);

        $this->artisan('academic-specialties:import', ['--directory' => $directory])
            ->expectsOutputToContain('Validated 1 specialties; current catalog: 1.')
            ->assertSuccessful();
        $this->assertDatabaseHas('specialties', ['value' => 'Old value']);

        $this->artisan('academic-specialties:import', ['--directory' => $directory, '--replace' => true])
            ->expectsOutputToContain('Replaced 1 catalog entries with 1 specialties.')
            ->assertSuccessful();

        $this->assertDatabaseCount('specialties', 1);
        $this->assertDatabaseHas('specialties', [
            'code' => '6B06101',
            'name_ru' => 'Информационные системы',
            'name_kk' => 'Ақпараттық жүйелер',
            'name_en' => 'Information Systems',
            'faculty_id' => Faculty::query()->where('name_ru', 'Факультет информационных технологий')->value('id'),
            'is_active' => true,
            'is_manual' => true,
        ]);
    }

    public function test_mismatched_codes_leave_current_catalog_intact(): void
    {
        Storage::fake('local');
        $directory = Storage::disk('local')->path('');
        $this->writeFile('rus', '6B06101 Информационные системы', 'Информационные системы');
        $this->writeFile('kaz', '6B06101 Ақпараттық жүйелер', 'Ақпараттық жүйелер');
        $this->writeFile('eng', '6B06102 Information Systems', 'Information Systems');
        Specialty::query()->create(['value' => 'Old value', 'name_ru' => 'Old value']);

        $this->artisan('academic-specialties:import', ['--directory' => $directory, '--replace' => true])
            ->expectsOutputToContain('Specialty codes do not match across the three files.')
            ->assertFailed();

        $this->assertDatabaseCount('specialties', 1);
        $this->assertDatabaseHas('specialties', ['value' => 'Old value']);
    }

    private function writeFile(string $language, string $first, string $department): void
    {
        Storage::disk('local')->put("op-{$language}.txt", "{$first}\tB057 Group\t{$department} 2026\t\n");
    }
}
