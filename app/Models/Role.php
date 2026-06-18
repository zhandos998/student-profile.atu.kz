<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'description'])]
class Role extends Model
{
    public const ADMINISTRATOR_DIT = 'administrator_dit';

    public const ADMINISTRATION = 'administration';

    public const CURATOR = 'curator';

    public const ADVISOR = 'advisor';

    public const GROUP_LEADER = 'group_leader';

    public const STUDENT = 'student';

    public const DEFINITIONS = [
        self::ADMINISTRATOR_DIT => [
            'name' => 'Администратор (ДИТ)',
            'description' => 'Управление системой, мониторинг, выгрузка данных.',
        ],
        self::ADMINISTRATION => [
            'name' => 'Администрация',
            'description' => 'Проректор ВР, деканы, зам. деканов по ВР, директор ДДМ, психолог, здравпункт, офис регистратора.',
        ],
        self::CURATOR => [
            'name' => 'Куратор / эдвайзер',
            'description' => 'Сопровождение и академическое консультирование закрепленных учебных групп.',
        ],
        self::ADVISOR => [
            'name' => 'Куратор / эдвайзер',
            'description' => 'Сопровождение и академическое консультирование закрепленных учебных групп.',
        ],
        self::GROUP_LEADER => [
            'name' => 'Староста',
            'description' => 'Представитель студенческой группы.',
        ],
        self::STUDENT => [
            'name' => 'Студент',
            'description' => 'Базовый пользователь студенческого профиля.',
        ],
    ];

    /**
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
