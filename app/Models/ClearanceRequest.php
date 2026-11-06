<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_profile_id', 'created_by_id', 'creator_name'])]
class ClearanceRequest extends Model
{
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ClearanceResponse::class);
    }

    public function status(): string
    {
        $responses = $this->responses;

        if ($responses->contains('decision', ClearanceResponse::REJECTED)) {
            return ClearanceResponse::REJECTED;
        }

        return $responses->isNotEmpty() && $responses->every(fn (ClearanceResponse $response): bool => $response->decision === ClearanceResponse::APPROVED)
            ? ClearanceResponse::APPROVED
            : ClearanceResponse::PENDING;
    }
}
