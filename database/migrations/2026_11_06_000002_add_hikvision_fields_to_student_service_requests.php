<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('admission_year')->nullable();
            $table->string('hikvision_org_index_code', 16)->nullable();
            $table->timestamp('hikvision_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table) {
            $table->dropColumn(['admission_year', 'hikvision_org_index_code', 'hikvision_synced_at']);
        });
    }
};
