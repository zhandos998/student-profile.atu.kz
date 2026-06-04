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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('study_form')->nullable();
            $table->string('nationality')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('iin', 12)->nullable();
            $table->string('identity_document_number')->nullable();
            $table->string('identity_card_path')->nullable();
            $table->string('gender')->nullable();
            $table->string('faculty')->nullable();
            $table->string('group_name')->nullable();
            $table->string('specialty')->nullable();
            $table->unsignedTinyInteger('course')->nullable();
            $table->unsignedSmallInteger('admission_year')->nullable();
            $table->string('marital_status')->nullable();
            $table->json('social_statuses')->nullable();
            $table->string('disability_group')->nullable();
            $table->string('disabled_parent_group')->nullable();
            $table->string('disabled_sibling_group')->nullable();
            $table->boolean('is_orphan')->default(false);
            $table->string('legal_representative')->nullable();
            $table->boolean('is_half_orphan')->default(false);
            $table->string('half_orphan_type')->nullable();
            $table->boolean('is_incomplete_family')->default(false);
            $table->boolean('is_large_family')->default(false);
            $table->boolean('is_low_income')->default(false);
            $table->json('benefits')->nullable();
            $table->text('special_educational_needs')->nullable();
            $table->text('stay_address')->nullable();
            $table->text('residence_address')->nullable();
            $table->text('contact_details')->nullable();
            $table->string('foreign_student_country')->nullable();
            $table->text('dormitory_details')->nullable();
            $table->text('relatives_living_details')->nullable();
            $table->text('rental_housing_details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
