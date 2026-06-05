<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_social_passports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('group_name')->nullable();
            $table->string('leader_full_name')->nullable();
            $table->string('leader_phone')->nullable();
            $table->string('leader_email')->nullable();
            $table->string('curator_full_name')->nullable();
            $table->string('curator_phone')->nullable();
            $table->string('curator_email')->nullable();
            $table->json('students')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_social_passports');
    }
};
