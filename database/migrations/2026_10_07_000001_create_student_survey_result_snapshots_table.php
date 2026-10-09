<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_survey_result_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->char('source_iin', 12);
            $table->string('survey_key', 64);
            $table->string('survey_name');
            $table->string('status', 24);
            $table->json('metrics');
            $table->text('message')->nullable();
            $table->char('content_hash', 64);
            $table->unsignedSmallInteger('source_order');
            $table->timestamps();

            $table->index(['student_profile_id', 'source_iin', 'survey_key'], 'survey_snapshots_student_lookup');
            $table->index(['survey_key', 'status'], 'survey_snapshots_report_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_survey_result_snapshots');
    }
};
