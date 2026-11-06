<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_service_requests') || Schema::hasColumn('student_service_requests', 'service_type')) {
            return;
        }

        if (Schema::hasTable('legacy_student_service_requests')) {
            throw new RuntimeException('Cannot preserve the legacy student service requests: archive table already exists.');
        }

        Schema::rename('student_service_requests', 'legacy_student_service_requests');
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_service_requests') && Schema::hasTable('legacy_student_service_requests')) {
            Schema::rename('legacy_student_service_requests', 'student_service_requests');
        }
    }
};
