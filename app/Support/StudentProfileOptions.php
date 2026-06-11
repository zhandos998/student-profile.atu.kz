<?php

namespace App\Support;

class StudentProfileOptions
{
    public const GENDERS = [
        'male' => 'Мужской',
        'female' => 'Женский',
    ];

    public const MARITAL_STATUSES = [
        'married_male' => 'Женат',
        'married_female' => 'Замужем',
        'single_male' => 'Не женат',
        'single_female' => 'Не замужем',
        'divorced' => 'Разведен/а',
    ];

    public const CITIZENSHIPS = [
        'kazakhstan_citizen' => 'Гражданин Республики Казахстан',
        'kandas' => 'Кандас',
        'foreign_citizen' => 'Иностранный гражданин',
    ];

    public const MILITARY_DEPARTMENT_STATUSES = [
        'studying' => 'Обучается на военной кафедре',
        'not_studying' => 'Не обучается на военной кафедре',
    ];

    public const DISABILITY_GROUPS = [
        '1' => '1 группы',
        '2' => '2 группы',
        '3' => '3 группы',
    ];

    public const HALF_ORPHAN_TYPES = [
        'stepfather' => 'Живет с отчимом',
        'stepmother' => 'Живет с мачехой',
        'single_parent' => 'Одинокий родитель',
    ];

    public const BENEFITS = [
        'asp' => 'Получает АСП',
        'loss_of_breadwinner' => 'По утере кормильца',
        'rural_quota' => 'Сельская квота',
        'veteran_family_or_combatant' => 'Семья ветерана, участник боевых действий',
        'ecological_disaster_victim' => 'Пострадавший от экологического бедствия',
        'emergency_victim' => 'Пострадавший от ЧС',
    ];

    public const SOCIAL_SUPPORT_NEED_STATUSES = [
        'needs' => 'Нуждается',
        'not_needs' => 'Не нуждается',
    ];

    public const EDUCATION_LANGUAGES = [
        'kk' => 'Казахский',
        'ru' => 'Русский',
        'en' => 'Английский',
    ];

    public const ACTIVITY_TYPES = [
        'olympiad' => 'Олимпиады',
        'contest' => 'Конкурсы',
        'sport' => 'Спортивные соревнования',
        'volunteer' => 'Волонтерская деятельность',
        'club' => 'Студенческие клубы',
        'project' => 'Проекты',
        'publication' => 'Научные публикации',
        'other' => 'Другое',
    ];

    public const ACHIEVEMENT_LEVELS = [
        'atu' => 'Внутри АО «АТУ»',
        'city' => 'Городской',
        'republican' => 'Республиканский',
        'international' => 'Международный',
    ];

    public const ACHIEVEMENT_RESULTS = [
        'first_place' => '1 место',
        'second_place' => '2 место',
        'third_place' => '3 место',
        'participant' => 'Участник',
    ];

    public const PORTFOLIO_TYPES = [
        'certificate' => 'Сертификат',
        'diploma' => 'Диплом',
        'commendation' => 'Грамота',
        'project' => 'Проект',
        'scientific_work' => 'Научная работа',
        'video' => 'Видеоматериал',
        'thank_you_letter' => 'Благодарственные письма и др.',
    ];

    public const FACULTIES = [
        'Факультет биотехнологии и химических технологий',
        'Факультет дизайна, технологий текстиля и одежды',
        'Факультет интеллектуальных и инженерных систем',
        'Факультет информационных технологий',
        'Факультет пищевых технологий',
        'Факультет экономики и бизнеса',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function forInertia(): array
    {
        return [
            'faculties' => self::toSameValueOptions(self::facultyNames()),
            'genders' => self::toSelectOptions(self::GENDERS),
            'citizenships' => self::toSelectOptions(self::CITIZENSHIPS),
            'militaryDepartmentStatuses' => self::toSelectOptions(self::MILITARY_DEPARTMENT_STATUSES),
            'maritalStatuses' => self::toSelectOptions(self::MARITAL_STATUSES),
            'disabilityGroups' => self::toSelectOptions(self::DISABILITY_GROUPS),
            'halfOrphanTypes' => self::toSelectOptions(self::HALF_ORPHAN_TYPES),
            'benefits' => self::toSelectOptions(self::BENEFITS),
            'socialSupportNeedStatuses' => self::toSelectOptions(self::SOCIAL_SUPPORT_NEED_STATUSES),
            'educationLanguages' => self::toSelectOptions(self::EDUCATION_LANGUAGES),
            'activityTypes' => self::toSelectOptions(self::ACTIVITY_TYPES),
            'achievementLevels' => self::toSelectOptions(self::ACHIEVEMENT_LEVELS),
            'achievementResults' => self::toSelectOptions(self::ACHIEVEMENT_RESULTS),
            'portfolioTypes' => self::toSelectOptions(self::PORTFOLIO_TYPES),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function facultyNames(): array
    {
        return self::FACULTIES;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{value: string, label: string}>
     */
    public static function toSelectOptions(array $options): array
    {
        return array_map(
            fn (string $value, string $label): array => [
                'value' => $value,
                'label' => $label,
            ],
            array_keys($options),
            $options,
        );
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    public static function toSameValueOptions(array $values): array
    {
        return array_map(
            fn (string $value): array => [
                'value' => $value,
                'label' => $value,
            ],
            $values,
        );
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, string>
     */
    public static function values(array $options): array
    {
        return array_keys($options);
    }
}
