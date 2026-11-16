<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Specialty;
use App\Services\AcademicDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_catalog_has_russian_and_kazakh_names(): void
    {
        $this->assertSame(6, Faculty::query()->count());

        $directory = app(AcademicDirectory::class);
        $russian = $directory->options('ru')['faculties'];
        $kazakh = $directory->options('kk')['faculties'];
        $faculty = 'Факультет информационных технологий';

        $this->assertSame($faculty, collect($russian)->firstWhere('value', $faculty)['label']);
        $this->assertSame('Ақпараттық технологиялар факультеті', collect($kazakh)->firstWhere('value', $faculty)['label']);
        $this->assertSame($faculty, $directory->facultyName($faculty, 'en'));
    }

    public function test_platonus_specialty_translations_are_upserted_by_code(): void
    {
        $directory = app(AcademicDirectory::class);
        $faculty = 'Факультет информационных технологий';
        $student = [
            'faculty_name_kz' => 'Ескі атау',
            'faculty_name_en' => 'Faculty of Information Technology',
            'speciality_code' => '7M06101',
            'speciality_name_ru' => 'Информационные системы',
            'speciality_name_kz' => 'Ақпараттық жүйелер',
            'speciality_name_en' => 'Information Systems',
        ];

        $specialty = $directory->syncFromPlatonus($student, $faculty);

        $this->assertSame('7M06101 - Информационные системы', $specialty->value);
        $this->assertSame('7M06101 - Ақпараттық жүйелер', $directory->specialtyLabel($specialty->value, 'kk'));
        $this->assertSame('7M06101 - Ақпараттық жүйелер', $directory->specialtyLabel('7М06101 - Информационные системы', 'kk'));
        $this->assertSame('7M06101 - Information Systems', $directory->specialtyLabel($specialty->value, 'en'));
        $this->assertSame('Faculty of Information Technology', Faculty::query()->where('name_ru', $faculty)->value('name_en'));
        $this->assertSame('Ақпараттық технологиялар факультеті', Faculty::query()->where('name_ru', $faculty)->value('name_kk'));

        $student['speciality_name_ru'] = 'Информационные системы (обновлено)';
        $directory->syncFromPlatonus($student, $faculty);

        $this->assertSame(1, Specialty::query()->count());
        $this->assertSame('7M06101 - Информационные системы (обновлено)', Specialty::query()->firstOrFail()->value);
        $this->assertSame('7M06101 - Ақпараттық жүйелер', $directory->specialtyLabel('7M06101 - Информационные системы', 'kk'));

        $directory->syncFromPlatonus($student, 'Факультет экономики и бизнеса');
        $this->assertNull(Specialty::query()->firstOrFail()->faculty_id);
    }
}
