<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'student_group_id',
    'profile_status',
    'student_status',
    'departure_reason',
    'departure_reason_other',
    'departed_at',
    'submitted_at',
    'verified_at',
    'reviewed_by_id',
    'revision_comment',
    'social_review_status',
    'social_review_comment',
    'social_reviewed_at',
    'social_reviewed_by_id',
    'full_name',
    'birth_date',
    'study_form',
    'nationality',
    'citizenship',
    'military_department_status',
    'military_department_place',
    'photo_path',
    'iin',
    'identity_document_number',
    'identity_card_path',
    'gender',
    'faculty',
    'group_name',
    'specialty',
    'course',
    'admission_year',
    'marital_status',
    'disability_group',
    'disabled_parent_group',
    'disabled_sibling_group',
    'is_orphan',
    'legal_representative',
    'is_half_orphan',
    'half_orphan_type',
    'is_incomplete_family',
    'is_large_family',
    'is_low_income',
    'benefits',
    'social_support_need_status',
    'social_support_need_details',
    'special_educational_needs',
    'stay_address',
    'residence_address',
    'contact_details',
    'personal_email',
    'parent_guardian_contacts',
    'foreign_student_country',
    'kandas_country',
    'dormitory_details',
    'relatives_living_details',
    'rental_housing_details',
])]
class StudentProfile extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_NEEDS_REVISION = 'needs_revision';

    public const STUDENT_STATUS_ACTIVE = 'active';

    public const STUDENT_STATUS_DEPARTED = 'departed';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_VERIFIED = 'verified';

    public const REVIEW_NEEDS_REVISION = 'needs_revision';

    public const STATUSES = [
        self::STATUS_NOT_STARTED,
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_VERIFIED,
        self::STATUS_NEEDS_REVISION,
    ];

    public const STATUS_LABELS = [
        self::STATUS_NOT_STARTED => 'Не заполнена',
        self::STATUS_DRAFT => 'Черновик',
        self::STATUS_SUBMITTED => 'Отправлена',
        self::STATUS_VERIFIED => 'Проверена куратором',
        self::STATUS_NEEDS_REVISION => 'Требует исправления',
    ];

    public const REVIEW_LABELS = [
        self::REVIEW_PENDING => 'Ожидает проверки',
        self::REVIEW_VERIFIED => 'Подтверждено',
        self::REVIEW_NEEDS_REVISION => 'Требует исправления',
    ];

    public const STUDENT_STATUS_LABELS = [
        self::STUDENT_STATUS_ACTIVE => 'Обучается',
        self::STUDENT_STATUS_DEPARTED => 'Выбыл',
    ];

    public const DEPARTURE_REASONS = [
        'transferred' => 'Переведен в другой университет',
        'expelled' => 'Отчислен',
        'deported' => 'Депортирован',
        'death' => 'Смерть',
        'other' => 'Другое',
    ];

    /**
     * @return BelongsTo<User, StudentProfile>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<StudentGroup, StudentProfile>
     */
    public function studentGroup(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class);
    }

    /**
     * @return BelongsTo<User, StudentProfile>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * @return BelongsTo<User, StudentProfile>
     */
    public function socialReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'social_reviewed_by_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'departed_at' => 'date:Y-m-d',
            'course' => 'integer',
            'student_group_id' => 'integer',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'reviewed_by_id' => 'integer',
            'social_reviewed_at' => 'datetime',
            'social_reviewed_by_id' => 'integer',
            'admission_year' => 'integer',
            'is_orphan' => 'boolean',
            'is_half_orphan' => 'boolean',
            'is_incomplete_family' => 'boolean',
            'is_large_family' => 'boolean',
            'is_low_income' => 'boolean',
            'benefits' => 'array',
        ];
    }
}
