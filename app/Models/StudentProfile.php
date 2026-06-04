<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'full_name',
    'birth_date',
    'study_form',
    'nationality',
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
    'special_educational_needs',
    'stay_address',
    'residence_address',
    'contact_details',
    'foreign_student_country',
    'dormitory_details',
    'relatives_living_details',
    'rental_housing_details',
])]
class StudentProfile extends Model
{
    /**
     * @return BelongsTo<User, StudentProfile>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'course' => 'integer',
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
