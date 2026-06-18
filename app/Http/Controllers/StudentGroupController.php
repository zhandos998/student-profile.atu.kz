<?php

namespace App\Http\Controllers;

use App\Models\GroupSocialPassport;
use App\Models\StudentGroup;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->withCount('studentProfiles')
            ->when($filters['faculty'] ?? null, fn (Builder $query, string $faculty) => $query->where('faculty', $faculty))
            ->when($filters['course'] ?? null, fn (Builder $query, int $course) => $query
                ->whereHas('studentProfiles', fn (Builder $query) => $query->where('course', $course)))
            ->when($filters['curator_id'] ?? null, fn (Builder $query, int $curatorId) => $query->where('curator_id', $curatorId))
            ->orderBy('faculty')
            ->orderBy('name')
            ->get()
            ->map(fn (StudentGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'faculty' => $group->faculty,
                'students_count' => $group->student_profiles_count,
                'curator_name' => $group->curator?->name,
                'passport_url' => route('groups.social-passport.edit', $group),
            ]);

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

        GroupSocialPassport::query()->create([
            'user_id' => $request->user()->id,
            'student_group_id' => $group->id,
            'faculty' => $group->faculty,
            'group_name' => $group->name,
            'students' => [],
            'summary' => [],
            'departed_students' => [],
        ]);

        return redirect()
            ->route('groups.social-passport.edit', $group)
            ->with('status', 'group-created');
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
}
