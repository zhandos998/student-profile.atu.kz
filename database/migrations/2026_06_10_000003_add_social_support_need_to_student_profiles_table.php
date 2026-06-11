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
            Schema::hasColumn('student_profiles', 'social_support_need_status')
            && Schema::hasColumn('student_profiles', 'social_support_need_details')
        ) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('student_profiles', 'social_support_need_status')) {
                $table->string('social_support_need_status')->nullable()->after('benefits');
            }

            if (! Schema::hasColumn('student_profiles', 'social_support_need_details')) {
                $table->text('social_support_need_details')->nullable()->after('social_support_need_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('student_profiles', 'social_support_need_details') ? 'social_support_need_details' : null,
            Schema::hasColumn('student_profiles', 'social_support_need_status') ? 'social_support_need_status' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
