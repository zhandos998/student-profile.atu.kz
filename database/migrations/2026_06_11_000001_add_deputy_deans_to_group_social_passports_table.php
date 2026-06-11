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
        $columns = [
            'deputy_dean_ur_full_name',
            'deputy_dean_ur_phone',
            'deputy_dean_ur_email',
            'deputy_dean_vr_full_name',
            'deputy_dean_vr_phone',
            'deputy_dean_vr_email',
        ];

        if (collect($columns)->every(fn (string $column): bool => Schema::hasColumn('group_social_passports', $column))) {
            return;
        }

        Schema::table('group_social_passports', function (Blueprint $table) {
            if (! Schema::hasColumn('group_social_passports', 'deputy_dean_ur_full_name')) {
                $table->string('deputy_dean_ur_full_name')->nullable()->after('curator_email');
            }

            if (! Schema::hasColumn('group_social_passports', 'deputy_dean_ur_phone')) {
                $table->string('deputy_dean_ur_phone')->nullable()->after('deputy_dean_ur_full_name');
            }

            if (! Schema::hasColumn('group_social_passports', 'deputy_dean_ur_email')) {
                $table->string('deputy_dean_ur_email')->nullable()->after('deputy_dean_ur_phone');
            }

            if (! Schema::hasColumn('group_social_passports', 'deputy_dean_vr_full_name')) {
                $table->string('deputy_dean_vr_full_name')->nullable()->after('deputy_dean_ur_email');
            }

            if (! Schema::hasColumn('group_social_passports', 'deputy_dean_vr_phone')) {
                $table->string('deputy_dean_vr_phone')->nullable()->after('deputy_dean_vr_full_name');
            }

            if (! Schema::hasColumn('group_social_passports', 'deputy_dean_vr_email')) {
                $table->string('deputy_dean_vr_email')->nullable()->after('deputy_dean_vr_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('group_social_passports', 'deputy_dean_vr_email') ? 'deputy_dean_vr_email' : null,
            Schema::hasColumn('group_social_passports', 'deputy_dean_vr_phone') ? 'deputy_dean_vr_phone' : null,
            Schema::hasColumn('group_social_passports', 'deputy_dean_vr_full_name') ? 'deputy_dean_vr_full_name' : null,
            Schema::hasColumn('group_social_passports', 'deputy_dean_ur_email') ? 'deputy_dean_ur_email' : null,
            Schema::hasColumn('group_social_passports', 'deputy_dean_ur_phone') ? 'deputy_dean_ur_phone' : null,
            Schema::hasColumn('group_social_passports', 'deputy_dean_ur_full_name') ? 'deputy_dean_ur_full_name' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('group_social_passports', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
