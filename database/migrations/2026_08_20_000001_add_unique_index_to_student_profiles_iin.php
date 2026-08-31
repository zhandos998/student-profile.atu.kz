<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('student_profiles')
            ->where('iin', '')
            ->update(['iin' => null]);

        $duplicates = DB::table('student_profiles')
            ->select('iin')
            ->whereNotNull('iin')
            ->where('iin', '<>', '')
            ->groupBy('iin')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('iin');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add unique index to student_profiles.iin. Duplicate IIN values: '.$duplicates->implode(', '),
            );
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->unique('iin', 'student_profiles_iin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropUnique('student_profiles_iin_unique');
        });
    }
};
