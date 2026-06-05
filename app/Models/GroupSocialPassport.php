<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'group_name',
    'leader_full_name',
    'leader_phone',
    'leader_email',
    'curator_full_name',
    'curator_phone',
    'curator_email',
    'students',
    'summary',
    'departed_students',
])]
class GroupSocialPassport extends Model
{
    /**
     * @return BelongsTo<User, GroupSocialPassport>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'students' => 'array',
            'summary' => 'array',
            'departed_students' => 'array',
        ];
    }
}
