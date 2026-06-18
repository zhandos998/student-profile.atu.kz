<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        Schema::table('group_social_passports', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['user_id']);
            }

            $table->dropUnique('group_social_passports_user_id_unique');

            if (! $isSqlite) {
                $table->index('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }

            $table->unique('student_group_id');
        });
    }

    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        Schema::table('group_social_passports', function (Blueprint $table) use ($isSqlite) {
            $table->dropUnique('group_social_passports_student_group_id_unique');

            if (! $isSqlite) {
                $table->dropForeign(['user_id']);
                $table->dropIndex('group_social_passports_user_id_index');
            }

            $table->unique('user_id');

            if (! $isSqlite) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }
        });
    }
};
