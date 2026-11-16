<?php

namespace App\Console\Commands;

use App\Models\Faculty;
use App\Models\Specialty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAcademicSpecialties extends Command
{
    protected $signature = 'academic-specialties:import
        {--replace : Delete current specialty directory entries and import the validated file set}
        {--directory= : Directory containing op-rus.txt, op-kaz.txt and op-eng.txt}';

    protected $description = 'Import education programs in three languages from the OP text files';

    private const DEPARTMENT_FACULTIES = [
        'Химия и экология' => 'Факультет биотехнологии и химических технологий',
        'Прикладная биотехнология' => 'Факультет биотехнологии и химических технологий',
        'Технологии химического и фармацевтического производства' => 'Факультет биотехнологии и химических технологий',
        'Дизайн' => 'Факультет дизайна, технологий текстиля и одежды',
        'Технология и конструирование изделий и товаров' => 'Факультет дизайна, технологий текстиля и одежды',
        'Технология текстильного производства' => 'Факультет дизайна, технологий текстиля и одежды',
        'Автоматизация и робототехника' => 'Факультет интеллектуальных и инженерных систем',
        'Машины и аппараты производственных процессов' => 'Факультет интеллектуальных и инженерных систем',
        'Информационные системы' => 'Факультет информационных технологий',
        'Компьютерная инженерия' => 'Факультет информационных технологий',
        'Безопасность и качество пищевых продуктов' => 'Факультет пищевых технологий',
        'Технология продуктов питания' => 'Факультет пищевых технологий',
        'Технология хлебопродуктов и перерабатывающих производств' => 'Факультет пищевых технологий',
        'Бухгалтерский учет и финансы' => 'Факультет экономики и бизнеса',
        'Туризм и сервисное обслуживание' => 'Факультет экономики и бизнеса',
        'Экономика и менеджмент' => 'Факультет экономики и бизнеса',
    ];

    public function handle(): int
    {
        $directory = $this->option('directory') ?: base_path();

        try {
            $ru = $this->readFile($directory, 'rus');
            $kk = $this->readFile($directory, 'kaz');
            $en = $this->readFile($directory, 'eng');

            if (array_diff_key($ru, $kk) || array_diff_key($ru, $en) || array_diff_key($kk, $ru) || array_diff_key($en, $ru)) {
                throw new RuntimeException('Specialty codes do not match across the three files.');
            }

            $facultyIds = Faculty::query()->pluck('id', 'name_ru');
            $rows = [];
            $now = now();

            foreach ($ru as $code => $russian) {
                $department = $russian['department'];
                $facultyName = self::DEPARTMENT_FACULTIES[$department] ?? null;
                $facultyId = $facultyIds[$facultyName] ?? null;

                if (! $facultyId) {
                    throw new RuntimeException("Unknown department or faculty for {$code}: {$department}");
                }

                $rows[] = [
                    'faculty_id' => $facultyId,
                    'code' => $code,
                    'value' => $code.' - '.$russian['name'],
                    'name_ru' => $russian['name'],
                    'name_kk' => $kk[$code]['name'],
                    'name_en' => $en[$code]['name'],
                    'is_active' => true,
                    'is_manual' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $existing = Specialty::query()->count();
            $this->line('Validated '.count($rows)." specialties; current catalog: {$existing}.");

            if (! $this->option('replace')) {
                $this->info('No data changed. Use --replace to replace the specialty catalog.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($rows): void {
                Specialty::query()->delete();
                foreach (array_chunk($rows, 100) as $chunk) {
                    Specialty::query()->insert($chunk);
                }
            });

            $this->info("Replaced {$existing} catalog entries with ".count($rows).' specialties. Student profiles and requests were not changed.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string, array{name: string, department: string}> */
    private function readFile(string $directory, string $language): array
    {
        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'op-'.$language.'.txt';
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $programs = [];
        foreach ($lines as $index => $line) {
            $columns = explode("\t", trim($line));
            $first = preg_replace('/^\x{FEFF}/u', '', trim($columns[0] ?? ''));
            if (count($columns) < 3 || ! preg_match('/^([0-9][A-Za-z]\d{5,7})\s+(.+)$/u', $first, $matches)) {
                throw new RuntimeException("Invalid specialty at {$path}:".($index + 1));
            }

            $code = strtoupper($matches[1]);
            $name = trim($matches[2]);
            $department = preg_replace('/\s+\d{4}\s*$/u', '', trim($columns[2]));
            if ($name === '' || $department === '' || mb_strlen($name) > 255 || mb_strlen($code.' - '.$name) > 255 || isset($programs[$code])) {
                throw new RuntimeException("Invalid or duplicate specialty {$code} at {$path}:".($index + 1));
            }

            $programs[$code] = ['name' => $name, 'department' => $department];
        }

        if ($programs === []) {
            throw new RuntimeException("No specialties found in {$path}.");
        }

        return $programs;
    }
}
