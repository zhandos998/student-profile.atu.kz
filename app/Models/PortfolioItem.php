<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'item_type',
    'title',
    'file_path',
    'original_name',
    'mime_type',
    'size',
])]
class PortfolioItem extends Model
{
    /**
     * @return BelongsTo<User, PortfolioItem>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
