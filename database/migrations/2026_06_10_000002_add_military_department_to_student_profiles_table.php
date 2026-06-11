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
        if (
            Schema::hasColumn('student_profiles', 'military_department_status')
            && Schema::hasColumn('student_profiles', 'military_department_place')
        ) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('student_profiles', 'military_department_status')) {
                $table->string('military_department_status')->nullable()->after('citizenship');
            }

            if (! Schema::hasColumn('student_profiles', 'military_department_place')) {
                $table->string('military_department_place')->nullable()->after('military_department_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('student_profiles', 'military_department_place') ? 'military_department_place' : null,
            Schema::hasColumn('student_profiles', 'military_department_status') ? 'military_department_status' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
