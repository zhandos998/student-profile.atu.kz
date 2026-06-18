<?php

namespace App\Services;

use App\Models\AcademicProfile;
use App\Models\HealthPassport;
use App\Models\StudentProfile;
use App\Models\User;

class StudentRiskService
{
    private const LOW_GPA_LIMIT = 2.5;

    /**
     * @return array<int, string>
     */
    private const EMPTY_TEXT_VALUES = [
        '',
        'Нет',
        'нет',
        'НЕТ',
        'No',
        'no',
        'NO',
        'Не указано',
        'не указано',
    ];

    public function profileCompletion(?StudentProfile $profile): int
    {
        if ($profile === null) {
            return 0;
        }

        $fields = [
            $profile->full_name,
            $profile->birth_date,
            $profile->faculty,
            $profile->group_name,
            $profile->specialty,
            $profile->course,
            $profile->contact_details,
            $profile->residence_address,
        ];

        $filled = collect($fields)
            ->filter(fn ($value): bool => filled($value))
            ->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    public function hasLowGpa(?AcademicProfile $academic): bool
    {
        return $academic?->gpa !== null && (float) $academic->gpa < self::LOW_GPA_LIMIT;
    }

    public function hasAcademicDebt(?string $value): bool
    {
        return $this->hasMeaningfulText($value);
    }

    public function hasMeaningfulText(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $text = trim((string) $value);

        return ! in_array($text, self::EMPTY_TEXT_VALUES, true);
    }

    /**
     * @return array<int, string>
     */
    public function socialStatusLabels(StudentProfile $profile): array
    {
        return collect([
            $this->hasMeaningfulText($profile->disability_group) ? 'Инвалид: '.$profile->disability_group : null,
            $this->hasMeaningfulText($profile->disabled_parent_group) ? 'Родитель инвалид: '.$profile->disabled_parent_group : null,
            $this->hasMeaningfulText($profile->disabled_sibling_group) ? 'Брат/сестра инвалид: '.$profile->disabled_sibling_group : null,
            $profile->is_orphan ? 'Сирота' : null,
            $profile->is_half_orphan ? 'Полусирота'.($this->hasMeaningfulText($profile->half_orphan_type) ? ': '.$profile->half_orphan_type : '') : null,
            $profile->is_incomplete_family ? 'Неполная семья' : null,
            $profile->is_large_family ? 'Многодетная семья' : null,
            $profile->is_low_income ? 'Малообеспеченная семья' : null,
            $this->hasMeaningfulText($profile->foreign_student_country) ? 'Иностранный студент: '.$profile->foreign_student_country : null,
            $this->hasMeaningfulText($profile->dormitory_details) ? 'Проживает в общежитии' : null,
        ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function academicRiskReasons(AcademicProfile $academic): array
    {
        return collect([
            $this->hasLowGpa($academic) ? 'Низкий GPA' : null,
            $this->hasAcademicDebt($academic->academic_debt) ? 'Академическая задолженность' : null,
        ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function academicRiskReasonsForProfile(StudentProfile $profile): array
    {
        $academic = $profile->user?->academicProfile;

        return $academic ? $this->academicRiskReasons($academic) : [];
    }

    /**
     * @return array<int, string>
     */
    public function socialRiskReasons(StudentProfile $profile): array
    {
        return collect([
            $this->hasMeaningfulText($profile->disability_group) ? 'Инвалидность студента: '.$profile->disability_group : null,
            $this->hasMeaningfulText($profile->disabled_parent_group) ? 'Родитель/ли инвалиды: '.$profile->disabled_parent_group : null,
            $this->hasMeaningfulText($profile->disabled_sibling_group) ? 'Сестра/брат инвалид: '.$profile->disabled_sibling_group : null,
            $profile->is_orphan ? 'Сирота'.($this->hasMeaningfulText($profile->legal_representative) ? ': '.$profile->legal_representative : '') : null,
            $profile->is_half_orphan ? 'Полусирота'.($this->hasMeaningfulText($profile->half_orphan_type) ? ': '.$profile->half_orphan_type : '') : null,
            $profile->is_incomplete_family ? 'Неполная семья' : null,
            $profile->is_large_family ? 'Многодетная семья' : null,
            $profile->is_low_income ? 'Малообеспеченная семья' : null,
            filled($profile->benefits) ? 'Льготы: '.$this->listValue($profile->benefits) : null,
            $profile->social_support_need_status === 'needs'
                ? 'Нуждается в социальной поддержке'.($this->hasMeaningfulText($profile->social_support_need_details) ? ': '.$profile->social_support_need_details : '')
                : null,
        ])
            ->filter()
            ->values()
            ->all();
    }

    public function hasSocialRisk(StudentProfile $profile): bool
    {
        return $this->socialRiskReasons($profile) !== [];
    }

    /**
     * @return array<int, string>
     */
    public function psychologicalRiskReasons(User $user): array
    {
        $profile = $user->psychologicalProfile;
        $passport = $user->healthPassport;

        return collect([
            $this->hasMeaningfulText($profile?->testing_results) ? 'Результаты тестирований' : null,
            $this->hasMeaningfulText($profile?->individual_features) ? 'Индивидуальные особенности' : null,
            $this->hasMeaningfulText($passport?->psychological_diagnosis) ? 'Психологический диагноз: '.$passport?->psychological_diagnosis : null,
        ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function medicalRiskReasons(HealthPassport $passport): array
    {
        return collect([
            $passport->dispensary_accounting === true ? 'Диспансерный учет' : null,
            $this->hasMeaningfulText($passport->diagnosis) ? 'Диагноз: '.$passport->diagnosis : null,
            $this->hasMeaningfulText($passport->disability_group) ? 'Группа инвалидности: '.$passport->disability_group : null,
            $this->hasMeaningfulText($passport->pregnancy) ? 'Беременность: '.$passport->pregnancy : null,
        ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function dashboardRiskReasons(StudentProfile $profile): array
    {
        $reasons = [];
        $academic = $profile->user?->academicProfile;

        if ($this->hasLowGpa($academic)) {
            $reasons[] = 'GPA ниже '.self::LOW_GPA_LIMIT;
        }

        if ($this->hasAcademicDebt($academic?->academic_debt)) {
            $reasons[] = 'Есть академическая задолженность';
        }

        if ($this->socialStatusLabels($profile) !== []) {
            $reasons[] = 'Есть социальные факторы';
        }

        if ($this->profileCompletion($profile) < 80) {
            $reasons[] = 'Анкета заполнена не полностью';
        }

        return $reasons;
    }

    private function listValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->filter(fn (mixed $item): bool => $this->hasMeaningfulText($item))
                ->implode(', ');
        }

        return trim((string) $value);
    }
}
