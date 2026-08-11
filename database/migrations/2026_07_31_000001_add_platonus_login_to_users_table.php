<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'platonus_login')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('platonus_login')->nullable()->unique()->after('phone_normalized');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'platonus_login')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platonus_login');
        });
    }
};
