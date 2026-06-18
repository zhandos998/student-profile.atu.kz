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
            $table->string('profile_status', 50)
                ->default(StudentProfile::STATUS_DRAFT)
                ->after('student_group_id');
            $table->timestamp('submitted_at')->nullable()->after('profile_status');
            $table->timestamp('verified_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by_id')
                ->nullable()
                ->after('verified_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('revision_comment')->nullable()->after('reviewed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropColumn([
                'profile_status',
                'submitted_at',
                'verified_at',
                'revision_comment',
            ]);
        });
    }
};
