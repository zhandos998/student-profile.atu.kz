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
        if (Schema::hasColumn('student_profiles', 'kandas_country')) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('kandas_country')->nullable()->after('foreign_student_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('student_profiles', 'kandas_country')) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('kandas_country');
        });
    }
};
