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
        Schema::create('academic_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('education_language')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->text('final_grades')->nullable();
            $table->text('current_performance')->nullable();
            $table->text('academic_debt')->nullable();
            $table->text('grade_dynamics')->nullable();
            $table->text('group_comparison')->nullable();
            $table->text('success_forecast')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_profiles');
    }
};
