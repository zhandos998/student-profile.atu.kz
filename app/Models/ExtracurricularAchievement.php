<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'activity_type',
    'title',
    'level',
    'result',
    'description',
    'document_path',
    'document_original_name',
])]
class ExtracurricularAchievement extends Model
{
    /**
     * @return BelongsTo<User, ExtracurricularAchievement>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
