<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clearance_request_id', 'reviewer_id', 'reviewer_name', 'reviewer_position', 'decision', 'comment', 'responded_at'])]
class ClearanceResponse extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function clearanceRequest(): BelongsTo
    {
        return $this->belongsTo(ClearanceRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
