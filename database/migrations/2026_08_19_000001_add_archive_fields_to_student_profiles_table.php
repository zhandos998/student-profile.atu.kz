<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('student_profiles', 'archived_at')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('departed_at');
            });
        }

        if (! Schema::hasColumn('student_profiles', 'archived_by_id')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->foreignId('archived_by_id')
                    ->nullable()
                    ->after('archived_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('student_profiles', 'archived_by_id')) {
                $table->dropConstrainedForeignId('archived_by_id');
            }

            if (Schema::hasColumn('student_profiles', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
