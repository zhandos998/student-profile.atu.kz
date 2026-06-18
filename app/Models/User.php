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

    public const READONLY_STUDENT_PROFILE_POSITIONS = [
        'Психолог',
        'Здравпункт',
    ];

    public const PSYCHOLOGICAL_PROFILE_POSITIONS = [
        'Психолог',
        'Проректор ВР',
        'Декан',
        'Зам. декана по ВР',
        'Директор ДДМ',
    ];

    public const HEALTH_PASSPORT_POSITIONS = [
        'Психолог',
        'Проректор ВР',
        'Декан',
        'Зам. декана по ВР',
        'Директор ДДМ',
        'Здравпункт',
    ];

    public function canViewPsychologicalProfile(): bool
    {
        if ($this->hasAnyRole([Role::ADMINISTRATOR_DIT])) {
            return true;
        }

        return $this->hasAnyRole([Role::ADMINISTRATION])
            && in_array($this->position, self::PSYCHOLOGICAL_PROFILE_POSITIONS, true);
    }

    public function canViewHealthPassport(): bool
    {
        if ($this->hasAnyRole([Role::ADMINISTRATOR_DIT])) {
            return true;
        }

        return $this->hasAnyRole([Role::ADMINISTRATION])
            && in_array($this->position, self::HEALTH_PASSPORT_POSITIONS, true);
    }

    public function canViewGroupSocialPassport(): bool
    {
        return $this->hasAnyRole([
            Role::ADMINISTRATOR_DIT,
            Role::ADMINISTRATION,
            Role::CURATOR,
            Role::ADVISOR,
        ]);
    }

    public function canViewAllStudentData(): bool
    {
        return $this->hasAnyRole([
            Role::ADMINISTRATOR_DIT,
            Role::ADMINISTRATION,
        ]);
    }

    public function canManageStudentProfiles(): bool
    {
        return $this->hasAnyRole([
            Role::ADMINISTRATOR_DIT,
            Role::ADMINISTRATION,
            Role::CURATOR,
            Role::ADVISOR,
        ]);
    }

    public function canEditStudentProfileData(): bool
    {
        if ($this->hasAnyRole([Role::ADMINISTRATOR_DIT, Role::CURATOR, Role::ADVISOR])) {
            return true;
        }

        return $this->hasAnyRole([Role::ADMINISTRATION])
            && ! in_array($this->position, self::READONLY_STUDENT_PROFILE_POSITIONS, true);
    }

    public function canEditStudentHealthPassport(): bool
    {
        if ($this->hasAnyRole([Role::ADMINISTRATOR_DIT])) {
            return true;
        }

        return $this->hasAnyRole([Role::ADMINISTRATION])
            && $this->position === 'Здравпункт';
    }

    public function canUseOwnStudentProfile(): bool
    {
        return $this->hasAnyRole([
            Role::STUDENT,
            Role::GROUP_LEADER,
        ]);
    }

    public function canViewCuratorAdvisorDashboard(): bool
    {
        return $this->hasAnyRole([
            Role::CURATOR,
            Role::ADVISOR,
        ]);
    }

    public function canViewAnalyticsDashboard(): bool
    {
        return $this->hasAnyRole([
            Role::ADMINISTRATOR_DIT,
            Role::ADMINISTRATION,
        ]);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $this->loadMissing('role');

        return in_array($this->role?->slug, $roles, true);
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
     * @return HasOne<HealthPassport>
     */
    public function healthPassport(): HasOne
    {
        return $this->hasOne(HealthPassport::class);
    }

    /**
     * @return HasOne<GroupSocialPassport>
     */
    public function groupSocialPassport(): HasOne
    {
        return $this->hasOne(GroupSocialPassport::class);
    }

    /**
     * @return HasMany<StudentGroup>
     */
    public function studentGroups(): HasMany
    {
        return $this->hasMany(StudentGroup::class, 'curator_id');
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
