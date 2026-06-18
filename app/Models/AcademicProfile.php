<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'education_language',
    'gpa',
    'final_grades',
    'current_performance',
    'academic_debt',
    'grade_dynamics',
    'group_comparison',
    'success_forecast',
    'academic_review_status',
    'academic_review_comment',
    'academic_reviewed_at',
    'academic_reviewed_by_id',
])]
class AcademicProfile extends Model
{
    public const REVIEW_PENDING = 'pending';

    public const REVIEW_VERIFIED = 'verified';

    public const REVIEW_NEEDS_REVISION = 'needs_revision';

    public const REVIEW_LABELS = [
        self::REVIEW_PENDING => 'Ожидает проверки',
        self::REVIEW_VERIFIED => 'Подтверждено',
        self::REVIEW_NEEDS_REVISION => 'Требует исправления',
    ];

    /**
     * @return BelongsTo<User, AcademicProfile>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, AcademicProfile>
     */
    public function academicReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'academic_reviewed_by_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gpa' => 'decimal:2',
            'academic_reviewed_at' => 'datetime',
            'academic_reviewed_by_id' => 'integer',
        ];
    }
}
