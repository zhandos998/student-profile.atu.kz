<?php

namespace App\Services;

use App\Models\Faculty;
use App\Models\Specialty;

class AcademicDirectory
{
    public function options(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return [
            'faculties' => $this->facultyOptions($locale),
            'specialties' => Specialty::query()->where('is_active', true)->with('faculty')->orderBy('code')->orderBy('name_ru')->get()->map(
                fn (Specialty $specialty): array => $this->specialtyOption($specialty, $locale)
            )->all(),
        ];
    }

    public function facultyOptions(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return Faculty::query()->orderBy('name_ru')->get()->map(fn (Faculty $faculty): array => [
            'value' => $faculty->name_ru,
            'label' => $faculty->nameFor($locale),
        ])->all();
    }

    public function specialtyOption(Specialty $specialty, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return [
            'value' => $specialty->value,
            'label' => $specialty->labelFor($locale),
            'faculty' => $specialty->faculty?->name_ru,
            'code' => $specialty->code,
        ];
    }

    public function syncFromPlatonus(array $student, ?string $facultyName): ?Specialty
    {
        $faculty = $facultyName ? Faculty::query()->where('name_ru', $facultyName)->first() : null;
        $education = data_get($student, 'education', []);
        $education = is_array($education) ? $education : [];

        if ($faculty) {
            foreach (['kk', 'en'] as $locale) {
                $apiLocale = $locale === 'kk' ? 'kz' : $locale;
                $name = trim((string) ($education['faculty_name_'.$apiLocale] ?? data_get($student, 'faculty_name_'.$apiLocale, '')));

                if ($name !== '' && blank($faculty->{'name_'.$locale})) {
                    $faculty->{'name_'.$locale} = $name;
                }
            }

            if ($faculty->isDirty()) {
                $faculty->save();
            }
        }

        $code = $this->normalizedCode((string) ($education['speciality_code'] ?? data_get($student, 'speciality_code', '')));
        $nameRu = trim((string) ($education['speciality_name_ru'] ?? data_get($student, 'speciality_name_ru', '')));

        if ($nameRu === '') {
            return null;
        }

        $value = $code !== '' ? $code.' - '.$nameRu : $nameRu;
        $specialty = ($code !== '' ? Specialty::query()->where('code', $code)->first() : null)
            ?? Specialty::query()->where('value', $value)->first()
            ?? new Specialty(['value' => $value]);

        $specialty->code = $code !== '' ? $code : $specialty->code;
        if (! $specialty->is_manual && $specialty->value !== $value && ! Specialty::query()->where('value', $value)->whereKeyNot($specialty->id)->exists()) {
            $specialty->value = $value;
        }
        if (! $specialty->is_manual) {
            $specialty->name_ru = $nameRu;
        }
        if (! $specialty->exists) {
            $specialty->faculty_id = $faculty?->id;
        } elseif ($faculty && $specialty->faculty_id && $specialty->faculty_id !== $faculty->id) {
            $specialty->faculty_id = null;
        }

        foreach (['kk', 'en'] as $locale) {
            $apiLocale = $locale === 'kk' ? 'kz' : $locale;
            $name = trim((string) ($education['speciality_name_'.$apiLocale] ?? data_get($student, 'speciality_name_'.$apiLocale, '')));

            if ($name !== '' && ! $specialty->is_manual) {
                $specialty->{'name_'.$locale} = $name;
            }
        }

        $specialty->save();

        return $specialty->load('faculty');
    }

    public function facultyName(?string $value, string $locale): ?string
    {
        return $value ? Faculty::query()->where('name_ru', $value)->first()?->nameFor($locale) ?? $value : null;
    }

    public function specialtyLabel(?string $value, string $locale): ?string
    {
        if (! $value) {
            return null;
        }

        $specialty = Specialty::query()->where('value', $value)->first();

        if (! $specialty && preg_match('/^([0-9]?[A-Za-zА-Яа-я]\d{3,7})\s*[-–]/u', $value, $matches)) {
            $specialty = Specialty::query()->where('code', $this->normalizedCode($matches[1]))->first();
        }

        return $specialty?->labelFor($locale) ?? $value;
    }

    private function normalizedCode(string $code): string
    {
        return mb_strtoupper(strtr(trim($code), [
            'В' => 'B', 'М' => 'M', 'Д' => 'D',
            'в' => 'B', 'м' => 'M', 'д' => 'D',
        ]));
    }
}
