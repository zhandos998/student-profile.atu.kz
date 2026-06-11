<?php

namespace App\Http\Controllers;

use App\Models\AcademicProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
            'profile_status' => ['nullable', Rule::in(['with_profile', 'without_profile'])],
        ]);

        $students = User::query()
            ->with(['role', 'studentProfile', 'academicProfile'])
            ->whereHas('role', fn ($query) => $query->where('slug', Role::STUDENT))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('studentProfile', function ($query) use ($search) {
                            $query
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('iin', 'like', "%{$search}%")
                                ->orWhere('group_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['faculty'] ?? null, fn ($query, string $faculty) => $query
                ->whereHas('studentProfile', fn ($query) => $query->where('faculty', $faculty)))
            ->when($filters['group_name'] ?? null, fn ($query, string $groupName) => $query
                ->whereHas('studentProfile', fn ($query) => $query->where('group_name', 'like', "%{$groupName}%")))
            ->when($filters['course'] ?? null, fn ($query, int $course) => $query
                ->whereHas('studentProfile', fn ($query) => $query->where('course', $course)))
            ->when(($filters['profile_status'] ?? null) === 'with_profile', fn ($query) => $query->has('studentProfile'))
            ->when(($filters['profile_status'] ?? null) === 'without_profile', fn ($query) => $query->doesntHave('studentProfile'))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('StudentProfile/Index', [
            'students' => $students->through(fn (User $student): array => $this->studentIndexPayload($student)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'faculty' => $filters['faculty'] ?? '',
                'group_name' => $filters['group_name'] ?? '',
                'course' => $filters['course'] ?? '',
                'profile_status' => $filters['profile_status'] ?? '',
            ],
            'options' => StudentProfileOptions::forInertia(),
        ]);
    }

    public function createManaged(Request $request): Response
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);

        return Inertia::render('StudentProfile/Create', [
            'options' => StudentProfileOptions::forInertia(),
        ]);
    }

    public function storeManaged(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'faculty' => ['nullable', Rule::in(StudentProfileOptions::facultyNames())],
            'group_name' => ['nullable', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
        ]);

        $studentRoleId = Role::query()->where('slug', Role::STUDENT)->value('id');

        $student = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $studentRoleId,
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => $validated['full_name'] ?: $validated['name'],
            'faculty' => $validated['faculty'] ?? null,
            'group_name' => $validated['group_name'] ?? null,
            'specialty' => $validated['specialty'] ?? null,
            'course' => $validated['course'] ?? null,
        ]);

        return redirect()
            ->route('student-profiles.edit', $student)
            ->with('status', 'student-profile-created');
    }

    public function editManaged(Request $request, User $student): Response
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);

        return $this->renderProfileForm($student, true);
    }

    public function updateManaged(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);

        $this->persistProfile($request, $student);

        return back()->with('status', 'student-profile-saved');
    }

    /**
     * Display the student profile form.
     */
    public function edit(Request $request): Response
    {
        abort_if($request->user()->loadMissing('role')->role?->slug === Role::ADVISOR, 403);

        return $this->renderProfileForm($request->user(), false);
    }

    /**
     * Store the student card and academic profile.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_if($request->user()->loadMissing('role')->role?->slug === Role::ADVISOR, 403);

        $this->persistProfile($request, $request->user());

        return back()->with('status', 'student-profile-saved');
    }

    private function persistProfile(Request $request, User $user): void
    {
        $validated = $request->validate($this->profileValidationRules());

        $profile = StudentProfile::query()->firstOrNew(['user_id' => $user->id]);
        $profileData = Arr::only($validated, $this->profileFields());
        $profileData['benefits'] = $validated['benefits'] ?? [];

        foreach ($this->booleanProfileFields() as $field) {
            $profileData[$field] = $request->boolean($field);
        }

        if (! $profileData['is_orphan']) {
            $profileData['legal_representative'] = null;
        }

        if (! $profileData['is_half_orphan']) {
            $profileData['half_orphan_type'] = null;
        }

        if (($profileData['military_department_status'] ?? null) !== 'studying') {
            $profileData['military_department_place'] = null;
        }

        if (($profileData['social_support_need_status'] ?? null) !== 'needs') {
            $profileData['social_support_need_details'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($profile->photo_path) {
                Storage::disk('public')->delete($profile->photo_path);
            }

            $profileData['photo_path'] = $request->file('photo')->store('student-profiles/photos', 'public');
        }

        if ($request->hasFile('identity_card')) {
            if ($profile->identity_card_path) {
                Storage::disk('public')->delete($profile->identity_card_path);
            }

            $profileData['identity_card_path'] = $request->file('identity_card')->store('student-profiles/identity-cards', 'public');
        }

        $profile->fill($profileData);
        $profile->save();

        AcademicProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            Arr::only($validated, $this->academicFields()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function profileValidationRules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'study_form' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'citizenship' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::CITIZENSHIPS))],
            'military_department_status' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::MILITARY_DEPARTMENT_STATUSES))],
            'military_department_place' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'iin' => ['nullable', 'string', 'size:12'],
            'identity_document_number' => ['nullable', 'string', 'max:100'],
            'identity_card' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'gender' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::GENDERS))],
            'faculty' => ['nullable', Rule::in(StudentProfileOptions::facultyNames())],
            'group_name' => ['nullable', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
            'admission_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'marital_status' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::MARITAL_STATUSES))],
            'disability_group' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::DISABILITY_GROUPS))],
            'disabled_parent_group' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::DISABILITY_GROUPS))],
            'disabled_sibling_group' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::DISABILITY_GROUPS))],
            'is_orphan' => ['nullable'],
            'legal_representative' => ['nullable', 'string', 'max:255'],
            'is_half_orphan' => ['nullable'],
            'half_orphan_type' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::HALF_ORPHAN_TYPES))],
            'is_incomplete_family' => ['nullable'],
            'is_large_family' => ['nullable'],
            'is_low_income' => ['nullable'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => [Rule::in(StudentProfileOptions::values(StudentProfileOptions::BENEFITS))],
            'social_support_need_status' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::SOCIAL_SUPPORT_NEED_STATUSES))],
            'social_support_need_details' => ['nullable', 'string', 'max:2000'],
            'special_educational_needs' => ['nullable', 'string', 'max:2000'],
            'stay_address' => ['nullable', 'string', 'max:2000'],
            'residence_address' => ['nullable', 'string', 'max:2000'],
            'contact_details' => ['nullable', 'string', 'max:1000'],
            'foreign_student_country' => ['nullable', 'string', 'max:100'],
            'kandas_country' => ['nullable', 'string', 'max:100'],
            'dormitory_details' => ['nullable', 'string', 'max:1000'],
            'relatives_living_details' => ['nullable', 'string', 'max:1000'],
            'rental_housing_details' => ['nullable', 'string', 'max:1000'],
            'education_language' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::EDUCATION_LANGUAGES))],
            'gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'final_grades' => ['nullable', 'string', 'max:4000'],
            'current_performance' => ['nullable', 'string', 'max:4000'],
            'academic_debt' => ['nullable', 'string', 'max:4000'],
            'grade_dynamics' => ['nullable', 'string', 'max:4000'],
            'group_comparison' => ['nullable', 'string', 'max:4000'],
            'success_forecast' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function profileFields(): array
    {
        return [
            'full_name',
            'birth_date',
            'study_form',
            'nationality',
            'citizenship',
            'military_department_status',
            'military_department_place',
            'iin',
            'identity_document_number',
            'gender',
            'faculty',
            'group_name',
            'specialty',
            'course',
            'admission_year',
            'marital_status',
            'disability_group',
            'disabled_parent_group',
            'disabled_sibling_group',
            'legal_representative',
            'half_orphan_type',
            'social_support_need_status',
            'social_support_need_details',
            'special_educational_needs',
            'stay_address',
            'residence_address',
            'contact_details',
            'foreign_student_country',
            'kandas_country',
            'dormitory_details',
            'relatives_living_details',
            'rental_housing_details',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function booleanProfileFields(): array
    {
        return [
            'is_orphan',
            'is_half_orphan',
            'is_incomplete_family',
            'is_large_family',
            'is_low_income',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function academicFields(): array
    {
        return [
            'education_language',
            'gpa',
            'final_grades',
            'current_performance',
            'academic_debt',
            'grade_dynamics',
            'group_comparison',
            'success_forecast',
        ];
    }

    private function renderProfileForm(User $user, bool $managed): Response
    {
        $user->load([
            'academicProfile',
            'extracurricularAchievements' => fn ($query) => $query->latest(),
            'portfolioItems' => fn ($query) => $query->latest(),
            'studentProfile',
        ]);

        return Inertia::render('StudentProfile/Edit', [
            'profile' => $this->profilePayload($user->studentProfile),
            'academicProfile' => $this->academicPayload($user->academicProfile),
            'achievements' => $user->extracurricularAchievements->map(fn ($achievement): array => [
                ...$achievement->toArray(),
                'document_url' => $achievement->document_path
                    ? Storage::disk('public')->url($achievement->document_path)
                    : null,
            ]),
            'portfolioItems' => $user->portfolioItems->map(fn ($item): array => [
                ...$item->toArray(),
                'file_url' => Storage::disk('public')->url($item->file_path),
            ]),
            'options' => StudentProfileOptions::forInertia(),
            'isManagedProfile' => $managed,
            'targetUser' => $managed ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function studentIndexPayload(User $student): array
    {
        $profile = $student->studentProfile;
        $academic = $student->academicProfile;

        return [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'hasProfile' => $profile !== null,
            'fullName' => $profile?->full_name ?: $student->name,
            'faculty' => $profile?->faculty,
            'groupName' => $profile?->group_name,
            'course' => $profile?->course,
            'specialty' => $profile?->specialty,
            'iin' => $profile?->iin,
            'gpa' => $academic?->gpa !== null ? (float) $academic->gpa : null,
            'completion' => $this->profileCompletion($profile),
            'editUrl' => route('student-profiles.edit', $student),
        ];
    }

    private function profileCompletion(?StudentProfile $profile): int
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

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(?StudentProfile $profile): array
    {
        $payload = array_fill_keys([
            ...$this->profileFields(),
            ...$this->booleanProfileFields(),
            'photo_path',
            'identity_card_path',
        ], null);

        $payload['benefits'] = [];

        if ($profile) {
            $payload = [
                ...$payload,
                ...$profile->toArray(),
            ];
        }

        foreach ($this->booleanProfileFields() as $field) {
            $payload[$field] = (bool) $payload[$field];
        }

        $payload['benefits'] = $payload['benefits'] ?? [];
        $payload['photo_url'] = $profile?->photo_path ? Storage::disk('public')->url($profile->photo_path) : null;
        $payload['identity_card_url'] = $profile?->identity_card_path ? Storage::disk('public')->url($profile->identity_card_path) : null;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function academicPayload(?AcademicProfile $academicProfile): array
    {
        $payload = array_fill_keys($this->academicFields(), null);

        if ($academicProfile) {
            $payload = [
                ...$payload,
                ...$academicProfile->toArray(),
            ];
        }

        return $payload;
    }
}
