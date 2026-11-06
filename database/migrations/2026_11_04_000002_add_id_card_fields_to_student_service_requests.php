<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table) {
            $table->string('full_name')->nullable();
            $table->string('iin', 12)->nullable();
            $table->string('phone', 32)->nullable();
            $table->date('study_start_date')->nullable();
            $table->date('study_end_date')->nullable();
            $table->unsignedTinyInteger('course')->nullable();
            $table->string('education_language', 2)->nullable();
            $table->string('faculty')->nullable();
            $table->string('specialty')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('receipt_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table) {
            $table->dropColumn([
                'full_name', 'iin', 'phone',
                'study_start_date', 'study_end_date', 'course', 'education_language',
                'faculty', 'specialty', 'photo_path', 'receipt_path',
            ]);
        });
    }
};
