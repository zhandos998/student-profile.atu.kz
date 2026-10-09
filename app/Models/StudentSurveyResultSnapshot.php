<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_profile_id',
    'source_iin',
    'survey_key',
    'survey_name',
    'status',
    'metrics',
    'message',
    'content_hash',
    'source_order',
])]
class StudentSurveyResultSnapshot extends Model
{
    protected function casts(): array
    {
        return ['metrics' => 'array'];
    }

    /** @return BelongsTo<StudentProfile, StudentSurveyResultSnapshot> */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
