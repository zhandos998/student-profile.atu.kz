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
        Schema::create('health_passports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('fluorography_date')->nullable();
            $table->string('fluorography_image_path')->nullable();
            $table->boolean('dispensary_accounting')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('disability_group')->nullable();
            $table->text('psychological_diagnosis')->nullable();
            $table->text('pregnancy')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_passports');
    }
};
