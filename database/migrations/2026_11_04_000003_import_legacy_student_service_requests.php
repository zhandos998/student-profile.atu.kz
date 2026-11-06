<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_student_service_requests')) {
            return;
        }

        foreach (['id', 'user_id', 'type', 'status'] as $column) {
            if (! Schema::hasColumn('legacy_student_service_requests', $column)) {
                return;
            }
        }

        DB::table('legacy_student_service_requests')->orderBy('id')->chunkById(100, function ($requests): void {
            foreach ($requests as $legacy) {
                if (! in_array($legacy->type, ['id_card', 'face_id'], true)) {
                    continue;
                }

                $studentId = $legacy->student_profile_id
                    ? DB::table('student_profiles')->where('id', $legacy->student_profile_id)->value('user_id')
                    : null;
                $studentId ??= $legacy->user_id;

                if (! DB::table('users')->where('id', $studentId)->exists()) {
                    continue;
                }

                $status = match ($legacy->status) {
                    'in_progress', 'processing' => 'in_progress',
                    'completed', 'done', 'approved' => 'completed',
                    'rejected', 'declined' => 'rejected',
                    default => 'pending',
                };

                $handlerId = $legacy->handled_by_id && DB::table('users')->where('id', $legacy->handled_by_id)->exists()
                    ? $legacy->handled_by_id
                    : null;

                DB::table('student_service_requests')->insertOrIgnore([
                    'id' => $legacy->id,
                    'student_id' => $studentId,
                    'service_type' => $legacy->type,
                    'status' => $status,
                    'student_comment' => $legacy->comment,
                    'staff_comment' => $legacy->response_comment,
                    'handled_by_id' => $handlerId,
                    'handled_at' => $legacy->completed_at,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        // The preserved legacy table remains the source of truth for a rollback.
    }
};
