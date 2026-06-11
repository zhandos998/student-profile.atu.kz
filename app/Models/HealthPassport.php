<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'fluorography_date',
    'fluorography_image_path',
    'dispensary_accounting',
    'diagnosis',
    'disability_group',
    'psychological_diagnosis',
    'pregnancy',
])]
class HealthPassport extends Model
{
    /**
     * @return BelongsTo<User, HealthPassport>
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
            'fluorography_date' => 'date:Y-m-d',
            'dispensary_accounting' => 'boolean',
        ];
    }
}
