<?php

namespace App\Http\Controllers;

use App\Models\GroupSocialPassport;
use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\FacultyDeputyDeanContacts;
use App\Support\StudentProfileOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentGroupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);

        $filters = $request->validate([
            'faculty' => ['nullable', 'string', Rule::in(StudentProfileOptions::facultyNames())],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
            'curator_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $groups = $this->accessibleGroups($request)
            ->with('curator:id,name,email')
            ->withCount([
                'studentProfiles' => fn (Builder $query) => $query->active(),
                'studentProfiles as total_student_profiles_count',
            ])
            ->when($filters['faculty'] ?? null, fn (Builder $query, string $faculty) => $query->where('faculty', $faculty))
            ->when($filters['course'] ?? null, fn (Builder $query, int $course) => $query
                ->whereHas('studentProfiles', fn (Builder $query) => $query->active()->where('course', $course)))
            ->when($filters['curator_id'] ?? null, fn (Builder $query, int $curatorId) => $query->where('curator_id', $curatorId))
            ->orderBy('faculty')
            ->orderBy('name')
            ->get()
            ->map(function (StudentGroup $group) use ($request): array {
                $canRequestDelete = $this->canDeleteGroup($request, $group);
                $hasStudents = (int) $group->total_student_profiles_count > 0;

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'faculty' => $group->faculty,
                    'students_count' => $group->student_profiles_count,
                    'curator_name' => $group->curator?->name,
                    'passport_url' => route('groups.social-passport.edit', $group),
                    'can_rename' => $this->canRenameGroup($request, $group),
                    'can_delete' => $canRequestDelete && ! $hasStudents,
                    'delete_blocked_reason' => $canRequestDelete && $hasStudents
                        ? 'Удаление недоступно: в группе есть студенты.'
                        : null,
                ];
            });

        return Inertia::render('StudentGroups/Index', [
            'groups' => $groups,
            'filters' => [
                'faculty' => $filters['faculty'] ?? '',
                'course' => $filters['course'] ?? '',
                'curator_id' => isset($filters['curator_id']) ? (string) $filters['curator_id'] : '',
            ],
            'options' => [
                'faculties' => StudentProfileOptions::toSameValueOptions(StudentProfileOptions::facultyNames()),
                'courses' => range(1, 8),
                'curators' => $this->curatorOptions($request),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);

        $validated = $request->validate([
            'faculty' => ['nullable', 'string', Rule::in(StudentProfileOptions::facultyNames())],
            'name' => ['required', 'string', 'max:100', 'unique:student_groups,name'],
        ]);

        $group = StudentGroup::query()->create([
            'curator_id' => $request->user()->id,
            'faculty' => $validated['faculty'] ?? null,
            'name' => $validated['name'],
        ]);

        $deputyDeanContacts = FacultyDeputyDeanContacts::passportDefaults($group->faculty);

        GroupSocialPassport::query()->create([
            'user_id' => $request->user()->id,
            'student_group_id' => $group->id,
            'faculty' => $group->faculty,
            'group_name' => $group->name,
            'curator_full_name' => $request->user()->name,
            'curator_phone' => $request->user()->phone,
            'curator_email' => $request->user()->email,
            ...$deputyDeanContacts,
            'students' => [],
            'summary' => [],
            'departed_students' => [],
        ]);

        return redirect()
            ->route('groups.social-passport.edit', $group)
            ->with('status', 'group-created');
    }

    public function update(Request $request, StudentGroup $studentGroup): RedirectResponse
    {
        abort_unless($this->canRenameGroup($request, $studentGroup), 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('student_groups', 'name')->ignore($studentGroup->id),
            ],
        ]);

        $oldName = $studentGroup->name;
        $newName = $validated['name'];

        DB::transaction(function () use ($studentGroup, $oldName, $newName): void {
            $studentGroup->update([
                'name' => $newName,
            ]);

            GroupSocialPassport::query()
                ->where('student_group_id', $studentGroup->id)
                ->orWhere('group_name', $oldName)
                ->update([
                    'group_name' => $newName,
                ]);

            StudentProfile::query()
                ->where('student_group_id', $studentGroup->id)
                ->orWhere('group_name', $oldName)
                ->update([
                    'group_name' => $newName,
                ]);
        });

        return back()->with('status', 'group-renamed');
    }

    public function destroy(Request $request, StudentGroup $studentGroup): RedirectResponse
    {
        abort_unless($this->canDeleteGroup($request, $studentGroup), 403);

        if ($studentGroup->studentProfiles()->exists()) {
            return back()->withErrors([
                'group_delete' => 'Нельзя удалить группу, пока в ней есть студенты.',
            ]);
        }

        $studentGroup->socialPassport()->delete();
        $studentGroup->delete();

        return redirect()
            ->route('groups.index')
            ->with('status', 'group-deleted');
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
                fn (Builder $query) => $query->where(function (Builder $query) use ($user): void {
                    $query
                        ->where('curator_id', $user?->id)
                        ->orWhere('leader_id', $user?->id);
                }),
            );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function curatorOptions(Request $request): array
    {
        $user = $request->user();

        return User::query()
            ->whereHas('studentGroups')
            ->when(
                ! $user?->canViewAllStudentData(),
                fn (Builder $query) => $query->whereKey($user?->id),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $curator): array => [
                'value' => (string) $curator->id,
                'label' => $curator->name ?: $curator->email,
            ])
            ->values()
            ->all();
    }

    private function canRenameGroup(Request $request, StudentGroup $studentGroup): bool
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if (! $user?->canViewGroupSocialPassport()) {
            return false;
        }

        if ($user->canViewAllStudentData()) {
            return true;
        }

        if (! $user->hasAnyRole([Role::CURATOR, Role::ADVISOR])) {
            return false;
        }

        return $studentGroup->curator_id === $user->id;
    }

    private function canDeleteGroup(Request $request, StudentGroup $studentGroup): bool
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if (! $user?->canViewGroupSocialPassport()) {
            return false;
        }

        if ($user->canViewAllStudentData()) {
            return true;
        }

        if ($user->hasAnyRole([Role::GROUP_LEADER])) {
            return false;
        }

        return $studentGroup->curator_id === $user->id;
    }
}
