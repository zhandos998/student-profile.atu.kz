<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('name_ru')->unique();
            $table->string('name_kk')->nullable();
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->string('code', 32)->nullable()->unique();
            $table->string('value')->unique();
            $table->string('name_ru');
            $table->string('name_kk')->nullable();
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('faculties')->insert([
            ['name_ru' => 'Факультет биотехнологии и химических технологий', 'name_kk' => 'Биотехнология және химиялық технологиялар факультеті', 'created_at' => $now, 'updated_at' => $now],
            ['name_ru' => 'Факультет дизайна, технологий текстиля и одежды', 'name_kk' => 'Дизайн, текстиль және киім технологиялары факультеті', 'created_at' => $now, 'updated_at' => $now],
            ['name_ru' => 'Факультет интеллектуальных и инженерных систем', 'name_kk' => 'Интеллектуалды және инженерлік жүйелер факультеті', 'created_at' => $now, 'updated_at' => $now],
            ['name_ru' => 'Факультет информационных технологий', 'name_kk' => 'Ақпараттық технологиялар факультеті', 'created_at' => $now, 'updated_at' => $now],
            ['name_ru' => 'Факультет пищевых технологий', 'name_kk' => 'Тағам технологиялары факультеті', 'created_at' => $now, 'updated_at' => $now],
            ['name_ru' => 'Факультет экономики и бизнеса', 'name_kk' => 'Экономика және бизнес факультеті', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $facultyIds = DB::table('faculties')->pluck('id', 'name_ru');

        foreach (['student_profiles', 'student_service_requests'] as $table) {
            DB::table($table)
                ->whereNotNull('specialty')
                ->where('specialty', '<>', '')
                ->select('specialty', 'faculty')
                ->distinct()
                ->orderBy('specialty')
                ->get()
                ->each(function ($row) use ($facultyIds, $now): void {
                    $value = trim($row->specialty);
                    $code = null;
                    $name = $value;

                    if (preg_match('/^([0-9]?[A-Za-zА-Яа-я]\d{3,7})\s*[-–]\s*(.+)$/u', $value, $matches)) {
                        $code = mb_strtoupper(strtr($matches[1], ['В' => 'B', 'М' => 'M', 'Д' => 'D', 'в' => 'B', 'м' => 'M', 'д' => 'D']));
                        $name = $matches[2];
                    }

                    $existing = DB::table('specialties')->where('value', $value);

                    if ($code !== null) {
                        $existing->orWhere('code', $code);
                    }

                    $existingRow = $existing->first();
                    $facultyId = $facultyIds[$row->faculty] ?? null;

                    if ($existingRow) {
                        if ($existingRow->faculty_id && $facultyId && $existingRow->faculty_id !== $facultyId) {
                            DB::table('specialties')->where('id', $existingRow->id)->update(['faculty_id' => null]);
                        }

                        return;
                    }

                    DB::table('specialties')->insert([
                        'faculty_id' => $facultyId,
                        'code' => $code,
                        'value' => $value,
                        'name_ru' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
        Schema::dropIfExists('faculties');
    }
};
