<?php

namespace App\Support;

use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StudentProfileAccess
{
    public static function scopeStudentUsers(Builder $query, User $viewer): Builder
    {
        if ($viewer->canViewAllStudentData()) {
            return $query;
        }

        $groupIds = self::accessibleGroupIds($viewer);

        return $query->whereHas(
            'studentProfile',
            fn (Builder $query) => $query->whereIn('student_group_id', $groupIds),
        );
    }

    public static function scopeStudentProfiles(Builder $query, User $viewer): Builder
    {
        if ($viewer->canViewAllStudentData()) {
            return $query;
        }

        return $query->whereIn('student_group_id', self::accessibleGroupIds($viewer));
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

        return in_array($student->studentProfile->student_group_id, self::accessibleGroupIds($viewer), true);
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
     * @return array<int, int>
     */
    private static function accessibleGroupIds(User $viewer): array
    {
        return self::accessibleGroupsQuery($viewer)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();
    }
}
