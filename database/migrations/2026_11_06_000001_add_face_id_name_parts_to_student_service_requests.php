<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table): void {
            $table->string('last_name', 100)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table): void {
            $table->dropColumn(['last_name', 'first_name', 'middle_name']);
        });
    }
};
