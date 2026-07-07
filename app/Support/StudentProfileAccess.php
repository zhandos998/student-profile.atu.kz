<?php

namespace App\Support;

use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StudentProfileAccess
{
    public static function scopeStudentUsers(Builder $query, User $viewer): Builder
    {
        if ($viewer->canViewAllStudentData()) {
            return $query;
        }

        $groups = self::accessibleGroupKeys($viewer);

        return $query->whereHas(
            'studentProfile',
            fn (Builder $query) => self::scopeStudentProfilesByGroups($query, $groups),
        );
    }

    public static function scopeStudentProfiles(Builder $query, User $viewer): Builder
    {
        if ($viewer->canViewAllStudentData()) {
            return $query;
        }

        return $query->where(
            fn (Builder $query) => self::scopeStudentProfilesByGroups($query, self::accessibleGroupKeys($viewer)),
        );
    }

    public static function accessibleGroupsQuery(User $viewer): Builder
    {
        return StudentGroup::query()
            ->when(
                ! $viewer->canViewAllStudentData(),
                fn (Builder $query) => $query->where(function (Builder $query) use ($viewer): void {
                    $query
                        ->where('curator_id', $viewer->id)
                        ->orWhere('leader_id', $viewer->id);
                }),
            );
    }

    public static function canAccessStudent(User $viewer, User $student): bool
    {
        if ($viewer->canViewAllStudentData()) {
            return true;
        }

        if (! $viewer->canManageStudentProfiles()) {
            return false;
        }

        $student->loadMissing('studentProfile');

        if (! $student->studentProfile) {
            return false;
        }

        return self::studentProfileMatchesGroups($student->studentProfile, self::accessibleGroupKeys($viewer));
    }

    public static function canAccessGroup(User $viewer, ?StudentGroup $group): bool
    {
        if (! $group) {
            return false;
        }

        if ($viewer->canViewAllStudentData()) {
            return true;
        }

        return $group->curator_id === $viewer->id
            || $group->leader_id === $viewer->id;
    }

    /**
     * @return array{ids: array<int, int>, names: array<int, string>}
     */
    private static function accessibleGroupKeys(User $viewer): array
    {
        $groups = self::accessibleGroupsQuery($viewer)->get(['id', 'name']);

        return [
            'ids' => $groups->pluck('id')->map(fn (int $id): int => $id)->values()->all(),
            'names' => $groups->pluck('name')->filter()->values()->all(),
        ];
    }

    /**
     * @param  array{ids: array<int, int>, names: array<int, string>}  $groups
     */
    private static function scopeStudentProfilesByGroups(Builder $query, array $groups): void
    {
        $query->where(function (Builder $query) use ($groups): void {
            $hasCondition = false;

            if ($groups['ids'] !== []) {
                $query->whereIn('student_group_id', $groups['ids']);
                $hasCondition = true;
            }

            if ($groups['names'] !== []) {
                $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                $query->{$method}('group_name', $groups['names']);
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @param  array{ids: array<int, int>, names: array<int, string>}  $groups
     */
    private static function studentProfileMatchesGroups(StudentProfile $profile, array $groups): bool
    {
        return ($profile->student_group_id && in_array($profile->student_group_id, $groups['ids'], true))
            || ($profile->group_name && in_array($profile->group_name, $groups['names'], true));
    }
}
