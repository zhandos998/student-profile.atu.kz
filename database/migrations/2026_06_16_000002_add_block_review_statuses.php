<?php

use App\Models\AcademicProfile;
use App\Models\StudentProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('social_review_status')->default(StudentProfile::REVIEW_PENDING)->after('revision_comment');
            $table->text('social_review_comment')->nullable()->after('social_review_status');
            $table->timestamp('social_reviewed_at')->nullable()->after('social_review_comment');
            $table->foreignId('social_reviewed_by_id')
                ->nullable()
                ->after('social_reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('personal_email')->nullable()->after('contact_details');
            $table->text('parent_guardian_contacts')->nullable()->after('personal_email');
        });

        Schema::table('academic_profiles', function (Blueprint $table) {
            $table->string('academic_review_status')->default(AcademicProfile::REVIEW_PENDING)->after('success_forecast');
            $table->text('academic_review_comment')->nullable()->after('academic_review_status');
            $table->timestamp('academic_reviewed_at')->nullable()->after('academic_review_comment');
            $table->foreignId('academic_reviewed_by_id')
                ->nullable()
                ->after('academic_reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academic_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_reviewed_by_id');
            $table->dropColumn([
                'academic_review_status',
                'academic_review_comment',
                'academic_reviewed_at',
            ]);
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_reviewed_by_id');
            $table->dropColumn([
                'social_review_status',
                'social_review_comment',
                'social_reviewed_at',
                'personal_email',
                'parent_guardian_contacts',
            ]);
        });
    }
};
