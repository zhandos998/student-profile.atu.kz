<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_social_passports', function (Blueprint $table) {
            $table->dropUnique('group_social_passports_user_id_unique');
            $table->unique('student_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('group_social_passports', function (Blueprint $table) {
            $table->dropUnique('group_social_passports_student_group_id_unique');
            $table->unique('user_id');
        });
    }
};
