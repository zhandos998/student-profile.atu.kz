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
])]
class AcademicProfile extends Model
{
    /**
     * @return BelongsTo<User, AcademicProfile>
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
            'gpa' => 'decimal:2',
        ];
    }
}
