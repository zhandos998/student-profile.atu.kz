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
        Schema::table('student_service_requests', function (Blueprint $table): void {
            $table->string('education_level', 16)->default(StudentProfile::EDUCATION_BACHELOR);
        });

        DB::table('student_service_requests')
            ->where('service_type', 'id_card')
            ->orderBy('id')
            ->chunkById(200, function ($requests): void {
                foreach ($requests as $request) {
                    $level = StudentProfile::inferredEducationLevel($request->specialty)
                        ?? DB::table('student_profiles')->where('user_id', $request->student_id)->value('education_level')
                        ?? StudentProfile::EDUCATION_BACHELOR;

                    DB::table('student_service_requests')->where('id', $request->id)->update(['education_level' => $level]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table): void {
            $table->dropColumn('education_level');
        });
    }
};
