<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_groups')) {
            Schema::create('student_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('faculty')->nullable();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('student_profiles', 'student_group_id')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->foreignId('student_group_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('student_groups')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('group_social_passports', 'student_group_id')) {
            Schema::table('group_social_passports', function (Blueprint $table) {
                $table->foreignId('student_group_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('student_groups')
                    ->nullOnDelete();
            });
        }

        $now = now();

        DB::table('group_social_passports')
            ->whereNotNull('group_name')
            ->where('group_name', '<>', '')
            ->orderBy('id')
            ->get(['id', 'user_id', 'faculty', 'group_name'])
            ->each(function (object $passport) use ($now): void {
                $group = DB::table('student_groups')
                    ->where('name', $passport->group_name)
                    ->first();

                if (! $group) {
                    $groupId = DB::table('student_groups')->insertGetId([
                        'curator_id' => $passport->user_id,
                        'faculty' => $passport->faculty,
                        'name' => $passport->group_name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $groupId = $group->id;
                }

                DB::table('group_social_passports')
                    ->where('id', $passport->id)
                    ->update(['student_group_id' => $groupId]);

                DB::table('student_profiles')
                    ->where('group_name', $passport->group_name)
                    ->whereNull('student_group_id')
                    ->update(['student_group_id' => $groupId]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_social_passports', 'student_group_id')) {
            Schema::table('group_social_passports', function (Blueprint $table) {
                $table->dropConstrainedForeignId('student_group_id');
            });
        }

        if (Schema::hasColumn('student_profiles', 'student_group_id')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('student_group_id');
            });
        }

        Schema::dropIfExists('student_groups');
    }
};
