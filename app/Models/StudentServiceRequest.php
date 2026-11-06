<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id', 'service_type', 'status', 'student_comment', 'staff_comment', 'handled_by_id', 'handled_at',
    'full_name', 'last_name', 'first_name', 'middle_name', 'iin', 'phone', 'study_start_date', 'study_end_date',
    'course', 'education_language', 'education_level', 'faculty', 'specialty', 'admission_year',
    'photo_path', 'receipt_path', 'hikvision_org_index_code', 'hikvision_synced_at',
    'library_rdr_id', 'library_regdate', 'library_reregdate', 'library_registered_at',
])]
class StudentServiceRequest extends Model
{
    public const ID_CARD = 'id_card';

    public const FACE_ID = 'face_id';

    public const PENDING = 'pending';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'study_start_date' => 'date',
            'study_end_date' => 'date',
            'library_regdate' => 'date',
            'library_reregdate' => 'date',
            'library_registered_at' => 'datetime',
            'hikvision_synced_at' => 'datetime',
            'admission_year' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }
}
