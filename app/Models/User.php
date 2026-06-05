<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'position'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PSYCHOLOGICAL_PROFILE_POSITIONS = [
        'Психолог',
        'Проректор ВР',
        'Декан',
        'Зам. декана по ВР',
        'Директор ДДМ',
    ];

    public function canViewPsychologicalProfile(): bool
    {
        $this->loadMissing('role');

        if ($this->role?->slug === Role::ADMINISTRATOR_DIT) {
            return true;
        }

        return $this->role?->slug === Role::ADMINISTRATION
            && in_array($this->position, self::PSYCHOLOGICAL_PROFILE_POSITIONS, true);
    }

    public function canViewGroupSocialPassport(): bool
    {
        $this->loadMissing('role');

        return in_array($this->role?->slug, [
            Role::ADMINISTRATOR_DIT,
            Role::ADMINISTRATION,
            Role::CURATOR,
            Role::ADVISOR,
            Role::GROUP_LEADER,
        ], true);
    }

    public function canViewAnalyticsDashboard(): bool
    {
        $this->loadMissing('role');

        return in_array($this->role?->slug, [
            Role::ADMINISTRATOR_DIT,
            Role::ADMINISTRATION,
        ], true);
    }

    /**
     * @return BelongsTo<Role, User>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasOne<StudentProfile>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * @return HasOne<AcademicProfile>
     */
    public function academicProfile(): HasOne
    {
        return $this->hasOne(AcademicProfile::class);
    }

    /**
     * @return HasOne<PsychologicalProfile>
     */
    public function psychologicalProfile(): HasOne
    {
        return $this->hasOne(PsychologicalProfile::class);
    }

    /**
     * @return HasOne<GroupSocialPassport>
     */
    public function groupSocialPassport(): HasOne
    {
        return $this->hasOne(GroupSocialPassport::class);
    }

    /**
     * @return HasMany<ExtracurricularAchievement>
     */
    public function extracurricularAchievements(): HasMany
    {
        return $this->hasMany(ExtracurricularAchievement::class);
    }

    /**
     * @return HasMany<PortfolioItem>
     */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role_id' => 'integer',
        ];
    }
}
