<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    protected $fillable = ['name_ru', 'name_kk', 'name_en'];

    public function specialties(): HasMany
    {
        return $this->hasMany(Specialty::class);
    }

    public function nameFor(string $locale): string
    {
        return $this->{'name_'.$locale} ?: $this->name_ru;
    }
}
