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
        if (Schema::hasColumn('group_social_passports', 'departed_students')) {
            return;
        }

        Schema::table('group_social_passports', function (Blueprint $table) {
            $table->json('departed_students')->nullable()->after('summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('group_social_passports', 'departed_students')) {
            return;
        }

        Schema::table('group_social_passports', function (Blueprint $table) {
            $table->dropColumn('departed_students');
        });
    }
};
