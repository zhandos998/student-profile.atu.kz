<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('creator_name');
            $table->timestamps();

            $table->index(['student_profile_id', 'created_at']);
        });

        Schema::create('clearance_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name');
            $table->string('reviewer_position')->nullable();
            $table->string('decision')->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['clearance_request_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_responses');
        Schema::dropIfExists('clearance_requests');
    }
};
