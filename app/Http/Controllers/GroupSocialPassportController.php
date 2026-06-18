<?php

namespace App\Http\Controllers;

use App\Models\GroupSocialPassport;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupSocialPassportController extends Controller
{
    public function edit(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);

        $group = $this->accessibleGroups($request)->orderBy('name')->first();

        if (! $group) {
            return redirect()->route('groups.index');
        }

        return redirect()->route('groups.social-passport.edit', $group);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);

        $validated = $this->validated($request);
        $studentGroup = $this->syncStudentGroup($request, $validated);

        if (! $studentGroup) {
            return back()->withErrors(['group_name' => 'Укажите группу.']);
        }

        return $this->persistPassport($request, $studentGroup, $validated);
    }

    public function editGroup(Request $request, StudentGroup $studentGroup): Response
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);
        abort_unless($this->canAccessGroup($request, $studentGroup), 403);

        $passport = $studentGroup->socialPassport;

        return Inertia::render('GroupSocialPassport/Edit', [
            'passport' => $this->payload($passport, $studentGroup, $request->user()?->canManageStudentProfiles() ?? false),
            'updateRoute' => route('groups.social-passport.update', $studentGroup),
            'groupsIndexUrl' => route('groups.index'),
            'groupOptions' => $this->groupOptions($request),
        ]);
    }

    public function updateGroup(Request $request, StudentGroup $studentGroup): RedirectResponse
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);
        abort_unless($this->canAccessGroup($request, $studentGroup), 403);

        $validated = $this->validated($request);
        $studentGroup = $this->syncStudentGroup($request, $validated, $studentGroup);

        return $this->persistPassport($request, $studentGroup, $validated);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge(['students' => []]);

        return $request->validate([
            'faculty' => ['nullable', 'string', 'max:255'],
            'student_group_id' => ['nullable', 'integer', 'exists:student_groups,id'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'leader_full_name' => ['nullable', 'string', 'max:255'],
            'leader_phone' => ['nullable', 'string', 'max:100'],
            'leader_email' => ['nullable', 'email', 'max:255'],
            'curator_full_name' => ['nullable', 'string', 'max:255'],
            'curator_phone' => ['nullable', 'string', 'max:100'],
            'curator_email' => ['nullable', 'email', 'max:255'],
            'deputy_dean_ur_full_name' => ['nullable', 'string', 'max:255'],
            'deputy_dean_ur_phone' => ['nullable', 'string', 'max:100'],
            'deputy_dean_ur_email' => ['nullable', 'email', 'max:255'],
            'deputy_dean_vr_full_name' => ['nullable', 'string', 'max:255'],
            'deputy_dean_vr_phone' => ['nullable', 'string', 'max:100'],
            'deputy_dean_vr_email' => ['nullable', 'email', 'max:255'],
            'students' => ['nullable', 'array'],
            'students.*.full_name' => ['nullable', 'string', 'max:255'],
            'students.*.birth_date' => ['nullable', 'date'],
            'students.*.study_form' => ['nullable', 'string', 'max:100'],
            'students.*.nationality' => ['nullable', 'string', 'max:100'],
            'students.*.iin' => ['nullable', 'string', 'max:12'],
            'students.*.identity_document_number' => ['nullable', 'string', 'max:100'],
            'students.*.identity_details' => ['nullable', 'string', 'max:255'],
            'students.*.contact_details' => ['nullable', 'string', 'max:1000'],
            'students.*.stay_address' => ['nullable', 'string', 'max:1000'],
            'students.*.residence_address' => ['nullable', 'string', 'max:1000'],
            'students.*.parent_details' => ['nullable', 'string', 'max:2000'],
            'students.*.social_status' => ['nullable', 'string', 'max:2000'],
            'students.*.religion_details' => ['nullable', 'string', 'max:2000'],
            'summary' => ['nullable', 'array'],
            'summary.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistPassport(Request $request, StudentGroup $studentGroup, array $validated): RedirectResponse
    {
        $validated['student_group_id'] = $studentGroup->id;
        $validated['faculty'] = $studentGroup->faculty;
        $validated['group_name'] = $studentGroup->name;
        $summary = $this->summaryForGroup($studentGroup);

        $passport = GroupSocialPassport::query()->firstOrNew([
            'student_group_id' => $studentGroup->id,
        ]);

        $passport->fill([
            ...$validated,
            'user_id' => $passport->user_id ?: ($studentGroup->curator_id ?: $request->user()->id),
            'students' => [],
            'summary' => $summary,
            'departed_students' => $this->departedStudentRows($studentGroup),
        ]);
        $passport->save();

        return back()->with('status', 'group-social-passport-saved');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        ?GroupSocialPassport $passport,
        ?StudentGroup $studentGroup = null,
        bool $canOpenStudentProfiles = false,
    ): array
    {
        return [
            'faculty' => $studentGroup?->faculty ?? $passport?->faculty ?? '',
            'student_group_id' => $studentGroup?->id ? (string) $studentGroup->id : ($passport?->student_group_id ? (string) $passport->student_group_id : ''),
            'group_name' => $studentGroup?->name ?? $passport?->group_name ?? '',
            'leader_full_name' => $passport?->leader_full_name ?? '',
            'leader_phone' => $passport?->leader_phone ?? '',
            'leader_email' => $passport?->leader_email ?? '',
            'curator_full_name' => $passport?->curator_full_name ?? '',
            'curator_phone' => $passport?->curator_phone ?? '',
            'curator_email' => $passport?->curator_email ?? '',
            'deputy_dean_ur_full_name' => $passport?->deputy_dean_ur_full_name ?? '',
            'deputy_dean_ur_phone' => $passport?->deputy_dean_ur_phone ?? '',
            'deputy_dean_ur_email' => $passport?->deputy_dean_ur_email ?? '',
            'deputy_dean_vr_full_name' => $passport?->deputy_dean_vr_full_name ?? '',
            'deputy_dean_vr_phone' => $passport?->deputy_dean_vr_phone ?? '',
            'deputy_dean_vr_email' => $passport?->deputy_dean_vr_email ?? '',
            'students' => $this->studentRows($passport, $studentGroup, $canOpenStudentProfiles),
            'summary' => $this->summaryForGroup($studentGroup, $passport),
            'departed_students' => $this->departedStudentRows($studentGroup, $passport),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentRows(
        ?GroupSocialPassport $passport,
        ?StudentGroup $studentGroup = null,
        bool $canOpenStudentProfiles = false,
    ): array
    {
        return $this->studentProfilesForGroup($studentGroup, $passport, StudentProfile::STUDENT_STATUS_ACTIVE)
            ->get()
            ->map(fn (StudentProfile $profile): array => [
                'profile_id' => $profile->id,
                'user_id' => $profile->user_id,
                'profile_url' => $canOpenStudentProfiles && $profile->user
                    ? route('student-profiles.edit', $profile->user)
                    : null,
                'full_name' => $profile->full_name ?: $profile->user?->name,
                'birth_date' => $profile->birth_date?->format('Y-m-d'),
                'study_form' => $profile->study_form,
                'nationality' => $profile->nationality,
                'iin' => $profile->iin,
                'identity_document_number' => $profile->identity_document_number,
                'contact_details' => $profile->contact_details,
                'stay_address' => $profile->stay_address,
                'residence_address' => $profile->residence_address,
                'parent_details' => $profile->parent_guardian_contacts,
                'social_status' => $this->studentSocialStatus($profile),
                'religion_details' => '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function summaryForGroup(?StudentGroup $studentGroup = null, ?GroupSocialPassport $passport = null): array
    {
        $profiles = $this->studentProfilesForGroup($studentGroup, $passport, StudentProfile::STUDENT_STATUS_ACTIVE)->get();

        return [
            'disabled_students' => $profiles->filter(fn (StudentProfile $profile): bool => filled($profile->disability_group))->count(),
            'orphan_students' => $profiles->filter(fn (StudentProfile $profile): bool => $profile->is_orphan)->count(),
            'incomplete_family_students' => $profiles
                ->filter(fn (StudentProfile $profile): bool => $profile->is_incomplete_family || $profile->is_half_orphan)
                ->count(),
            'large_family_students' => $profiles->filter(fn (StudentProfile $profile): bool => $profile->is_large_family)->count(),
            'low_income_students' => $profiles->filter(fn (StudentProfile $profile): bool => $profile->is_low_income)->count(),
            'married_students' => $profiles
                ->filter(fn (StudentProfile $profile): bool => in_array($profile->marital_status, ['married_male', 'married_female'], true))
                ->count(),
            'foreign_students' => $profiles
                ->filter(fn (StudentProfile $profile): bool => filled($profile->foreign_student_country) || $profile->citizenship === 'foreign_citizen')
                ->count(),
            'dormitory_students' => $profiles->filter(fn (StudentProfile $profile): bool => filled($profile->dormitory_details))->count(),
            'relatives_living_students' => $profiles->filter(fn (StudentProfile $profile): bool => filled($profile->relatives_living_details))->count(),
            'rental_housing_students' => $profiles->filter(fn (StudentProfile $profile): bool => filled($profile->rental_housing_details))->count(),
            'total_students' => $profiles->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function departedStudentRows(?StudentGroup $studentGroup = null, ?GroupSocialPassport $passport = null): array
    {
        return $this->studentProfilesForGroup($studentGroup, $passport, StudentProfile::STUDENT_STATUS_DEPARTED)
            ->get()
            ->map(fn (StudentProfile $profile): array => [
                'profile_id' => $profile->id,
                'user_id' => $profile->user_id,
                'full_name' => $profile->full_name ?: $profile->user?->name,
                'faculty' => $profile->faculty,
                'education_program' => $profile->specialty,
                'group_name' => $profile->group_name,
                'course' => $profile->course,
                'reason' => $profile->departure_reason,
                'reason_label' => $this->departureReasonLabel($profile),
                'reason_other' => $profile->departure_reason_other,
                'departed_at' => $profile->departed_at?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    private function studentProfilesForGroup(
        ?StudentGroup $studentGroup = null,
        ?GroupSocialPassport $passport = null,
        ?string $studentStatus = null,
    ): Builder
    {
        $groupId = $studentGroup?->id ?? $passport?->student_group_id;
        $groupName = $studentGroup?->name ?? $passport?->group_name;

        return StudentProfile::query()
            ->with('user')
            ->when(
                filled($groupId),
                fn (Builder $query) => $query->where('student_group_id', $groupId),
                fn (Builder $query) => filled($groupName)
                    ? $query->where('group_name', $groupName)
                    : $query->whereRaw('1 = 0'),
            )
            ->when(
                $studentStatus,
                fn (Builder $query, string $status) => $query->where('student_status', $status),
                fn (Builder $query) => $query->where(function (Builder $query): void {
                    $query
                        ->whereNull('student_status')
                        ->orWhere('student_status', StudentProfile::STUDENT_STATUS_ACTIVE);
                }),
            )
            ->orderBy('full_name');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncStudentGroup(Request $request, array $validated, ?StudentGroup $group = null): ?StudentGroup
    {
        if (! $group && filled($validated['student_group_id'] ?? null)) {
            $group = StudentGroup::query()->find((int) $validated['student_group_id']);
            abort_unless(! $group || $this->canAccessGroup($request, $group), 403);
        }

        if (! $group && filled($validated['group_name'] ?? null)) {
            $group = StudentGroup::query()->firstOrNew([
                'name' => $validated['group_name'],
            ]);
        }

        if (! $group) {
            return null;
        }

        $group->fill([
            'curator_id' => $group->curator_id ?: $request->user()->id,
            'faculty' => $validated['faculty'] ?? $group->faculty,
            'name' => filled($validated['group_name'] ?? null) ? $validated['group_name'] : $group->name,
        ]);
        $group->save();

        return $group;
    }

    /**
     * @return Builder<StudentGroup>
     */
    private function accessibleGroups(Request $request): Builder
    {
        $user = $request->user();
        $user?->loadMissing('role');

        return StudentGroup::query()
            ->when(
                ! $user?->canViewAllStudentData(),
                fn (Builder $query) => $query->where('curator_id', $user?->id),
            );
    }

    private function canAccessGroup(Request $request, StudentGroup $studentGroup): bool
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if ($user?->canViewAllStudentData()) {
            return true;
        }

        return $studentGroup->curator_id === $user?->id;
    }

    /**
     * @return array<int, array{value: string, label: string, name: string, faculty: string|null}>
     */
    private function groupOptions(Request $request): array
    {
        return $this->accessibleGroups($request)
            ->orderBy('faculty')
            ->orderBy('name')
            ->get(['id', 'name', 'faculty'])
            ->map(fn (StudentGroup $group): array => [
                'value' => (string) $group->id,
                'label' => trim($group->name.' — '.($group->faculty ?: 'Факультет не указан')),
                'name' => $group->name,
                'faculty' => $group->faculty,
            ])
            ->values()
            ->all();
    }

    private function studentSocialStatus(StudentProfile $profile): string
    {
        return collect([
            filled($profile->disability_group) ? 'Инвалид: '.$profile->disability_group : null,
            filled($profile->disabled_parent_group) ? 'Родитель/ли инвалиды: '.$profile->disabled_parent_group : null,
            filled($profile->disabled_sibling_group) ? 'Сестра/брат инвалид: '.$profile->disabled_sibling_group : null,
            $profile->is_orphan ? 'Сирота' : null,
            $profile->is_half_orphan ? 'Полусирота' : null,
            $profile->is_incomplete_family ? 'Неполная семья' : null,
            $profile->is_large_family ? 'Многодетная семья' : null,
            $profile->is_low_income ? 'Малообеспеченная семья' : null,
            filled($profile->benefits) ? 'Льготы' : null,
            filled($profile->foreign_student_country) ? 'Иностранный студент: '.$profile->foreign_student_country : null,
            filled($profile->dormitory_details) ? 'Проживает в общежитии' : null,
            filled($profile->relatives_living_details) ? 'Проживает у родственников' : null,
            filled($profile->rental_housing_details) ? 'Арендует жилье' : null,
        ])
            ->filter()
            ->implode(', ');
    }

    private function departureReasonLabel(StudentProfile $profile): ?string
    {
        if (! filled($profile->departure_reason)) {
            return null;
        }

        return StudentProfile::DEPARTURE_REASONS[$profile->departure_reason] ?? $profile->departure_reason;
    }
}
