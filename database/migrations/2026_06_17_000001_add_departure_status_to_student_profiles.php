<?php

use App\Models\StudentProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('student_status', 50)
                ->default(StudentProfile::STUDENT_STATUS_ACTIVE)
                ->after('profile_status');
            $table->string('departure_reason', 100)->nullable()->after('student_status');
            $table->text('departure_reason_other')->nullable()->after('departure_reason');
            $table->date('departed_at')->nullable()->after('departure_reason_other');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'student_status',
                'departure_reason',
                'departure_reason_other',
                'departed_at',
            ]);
        });
    }
};
