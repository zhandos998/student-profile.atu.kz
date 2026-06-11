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
        if (Schema::hasColumn('group_social_passports', 'faculty')) {
            return;
        }

        Schema::table('group_social_passports', function (Blueprint $table) {
            $table->string('faculty')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('group_social_passports', 'faculty')) {
            return;
        }

        Schema::table('group_social_passports', function (Blueprint $table) {
            $table->dropColumn('faculty');
        });
    }
};
