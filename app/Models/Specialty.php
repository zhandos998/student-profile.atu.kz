<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Specialty extends Model
{
    protected $attributes = ['is_active' => true, 'is_manual' => false];

    protected $fillable = ['faculty_id', 'code', 'value', 'name_ru', 'name_kk', 'name_en', 'is_active', 'is_manual'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_manual' => 'boolean'];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function labelFor(string $locale): string
    {
        $name = $this->{'name_'.$locale} ?: $this->name_ru;

        return $this->code ? $this->code.' - '.$name : $name;
    }
}
