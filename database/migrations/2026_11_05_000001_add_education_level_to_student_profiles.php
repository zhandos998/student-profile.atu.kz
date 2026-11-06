<?php

use App\Models\StudentProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('education_level', 16)->default(StudentProfile::EDUCATION_BACHELOR);
        });

        DB::table('student_profiles')
            ->whereNotNull('specialty')
            ->orderBy('id')
            ->chunkById(200, function ($profiles): void {
                foreach ($profiles as $profile) {
                    $level = StudentProfile::inferredEducationLevel($profile->specialty);

                    if ($level !== null) {
                        DB::table('student_profiles')->where('id', $profile->id)->update(['education_level' => $level]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropColumn('education_level');
        });
    }
};
