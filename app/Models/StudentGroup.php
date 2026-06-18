<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'curator_id',
    'faculty',
    'name',
])]
class StudentGroup extends Model
{
    /**
     * @return BelongsTo<User, StudentGroup>
     */
    public function curator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'curator_id');
    }

    /**
     * @return HasMany<StudentProfile>
     */
    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    /**
     * @return HasOne<GroupSocialPassport>
     */
    public function socialPassport(): HasOne
    {
        return $this->hasOne(GroupSocialPassport::class);
    }
}
