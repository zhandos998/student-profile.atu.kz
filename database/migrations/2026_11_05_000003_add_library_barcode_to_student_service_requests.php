<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table): void {
            $table->string('library_rdr_id', 32)->nullable();
            $table->date('library_regdate')->nullable();
            $table->date('library_reregdate')->nullable();
            $table->timestamp('library_registered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_service_requests', function (Blueprint $table): void {
            $table->dropColumn(['library_rdr_id', 'library_regdate', 'library_reregdate', 'library_registered_at']);
        });
    }
};
