<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('service_type', 32);
            $table->string('status', 32)->default('pending');
            $table->text('student_comment')->nullable();
            $table->text('staff_comment')->nullable();
            $table->foreignId('handled_by_id')->nullable()->constrained('users', 'id', 'student_services_v2_handler_fk')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'service_type', 'status']);
            $table->index(['service_type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_service_requests');
    }
};
