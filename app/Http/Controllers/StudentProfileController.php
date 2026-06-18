<?php

namespace App\Http\Controllers;

use App\Models\AcademicProfile;
use App\Models\HealthPassport;
use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRiskService;
use App\Support\StudentProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    public function __construct(private readonly StudentRiskService $riskService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')],
            'group_name' => ['nullable', 'string', 'max:100'],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
            'profile_status' => ['nullable', Rule::in([
                'with_profile',
                'without_profile',
                StudentProfile::STATUS_NOT_STARTED,
                StudentProfile::STATUS_DRAFT,
                StudentProfile::STATUS_SUBMITTED,
                StudentProfile::STATUS_VERIFIED,
                StudentProfile::STATUS_NEEDS_REVISION,
            ])],
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
            ->when($filters['student_group_id'] ?? null, fn ($query, int $studentGroupId) => $query
                ->whereHas('studentProfile', fn ($query) => $query->where('student_group_id', $studentGroupId)))
            ->when($filters['group_name'] ?? null, fn ($query, string $groupName) => $query
                ->whereHas('studentProfile', fn ($query) => $query->where('group_name', 'like', "%{$groupName}%")))
            ->when($filters['course'] ?? null, fn ($query, int $course) => $query
                ->whereHas('studentProfile', fn ($query) => $query->where('course', $course)))
            ->when(($filters['profile_status'] ?? null) === 'with_profile', fn ($query) => $query->has('studentProfile'))
            ->when(
                in_array($filters['profile_status'] ?? null, [
                    'without_profile',
                    StudentProfile::STATUS_NOT_STARTED,
                ], true),
                fn ($query) => $query->doesntHave('studentProfile')
            )
            ->when(
                in_array($filters['profile_status'] ?? null, [
                    StudentProfile::STATUS_DRAFT,
                    StudentProfile::STATUS_SUBMITTED,
                    StudentProfile::STATUS_VERIFIED,
                    StudentProfile::STATUS_NEEDS_REVISION,
                ], true),
                fn ($query) => $query->whereHas('studentProfile', fn ($query) => $query->where('profile_status', $filters['profile_status'])),
            )
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('StudentProfile/Index', [
            'students' => $students->through(fn (User $student): array => $this->studentIndexPayload($student)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'faculty' => $filters['faculty'] ?? '',
                'student_group_id' => isset($filters['student_group_id']) ? (string) $filters['student_group_id'] : '',
                'group_name' => $filters['group_name'] ?? '',
                'course' => $filters['course'] ?? '',
                'profile_status' => $filters['profile_status'] ?? '',
            ],
            'options' => StudentProfileOptions::forInertia(),
            'availableGroups' => $this->availableGroupOptions(),
            'profileStatusOptions' => $this->profileStatusOptions(),
            'canCreateStudentProfiles' => $request->user()?->canEditStudentProfileData() ?? false,
        ]);
    }

    public function createManaged(Request $request): Response
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);

        return Inertia::render('StudentProfile/Create', [
            'options' => StudentProfileOptions::forInertia(),
            'availableGroups' => $this->availableGroupOptions(),
        ]);
    }

    public function storeManaged(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')],
            'group_name' => ['nullable', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
        ]);
        $studentGroup = $this->selectedStudentGroup($validated);
        $this->ensureGroupNameIsKnown($validated, $studentGroup);

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
            'profile_status' => StudentProfile::STATUS_DRAFT,
            'full_name' => $validated['full_name'] ?: $validated['name'],
            'student_group_id' => $studentGroup?->id,
            'faculty' => $studentGroup?->faculty ?: ($validated['faculty'] ?? null),
            'group_name' => $studentGroup?->name ?: ($validated['group_name'] ?? null),
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
        abort_unless($request->user()?->canEditStudentProfileData(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);

        $this->persistProfile($request, $student, false, true, true);

        return back()->with('status', 'student-profile-saved');
    }

    public function updateStatus(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);

        $validated = $request->validate([
            'profile_status' => ['required', Rule::in([
                StudentProfile::STATUS_VERIFIED,
                StudentProfile::STATUS_NEEDS_REVISION,
            ])],
            'revision_comment' => ['nullable', 'string', 'max:2000', 'required_if:profile_status,'.StudentProfile::STATUS_NEEDS_REVISION],
        ]);

        $profile = $student->studentProfile;
        abort_unless($profile, 404);

        $profile->fill([
            'profile_status' => $validated['profile_status'],
            'reviewed_by_id' => $request->user()->id,
            'verified_at' => $validated['profile_status'] === StudentProfile::STATUS_VERIFIED ? now() : null,
            'revision_comment' => $validated['profile_status'] === StudentProfile::STATUS_NEEDS_REVISION
                ? $validated['revision_comment']
                : null,
        ]);
        $profile->save();

        return back()->with('status', 'student-profile-status-updated');
    }

    /**
     * Display the student profile form.
     */
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->canUseOwnStudentProfile(), 403);

        return $this->renderProfileForm($request->user(), false);
    }

    /**
     * Store the student card and academic profile.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canUseOwnStudentProfile(), 403);

        $this->persistProfile($request, $request->user(), true);

        return back()->with('status', 'student-profile-saved');
    }

    public function submit(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canUseOwnStudentProfile(), 403);

        $profile = $request->user()->studentProfile;

        if (! $profile) {
            return back()->withErrors(['profile_status' => 'Сначала сохраните анкету.']);
        }

        $profile->fill([
            'profile_status' => StudentProfile::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'verified_at' => null,
            'reviewed_by_id' => null,
            'revision_comment' => null,
        ]);
        $profile->save();

        return back()->with('status', 'student-profile-submitted');
    }

    private function persistProfile(
        Request $request,
        User $user,
        bool $submitAfterSave = false,
        bool $includeServiceFields = true,
        bool $includeLifecycleFields = false,
    ): void
    {
        $validated = $request->validate($this->profileValidationRules($includeServiceFields, $includeLifecycleFields));
        $studentGroup = $this->selectedStudentGroup($validated);
        $this->ensureGroupNameIsKnown($validated, $studentGroup);

        $profile = StudentProfile::query()->firstOrNew(['user_id' => $user->id]);
        $profileData = Arr::only($validated, $this->profileFields($includeServiceFields, $includeLifecycleFields));
        $profileData['student_group_id'] = $studentGroup?->id;
        $profileData['group_name'] = $studentGroup?->name ?: ($profileData['group_name'] ?? null);
        $profileData['faculty'] = $studentGroup?->faculty ?: ($profileData['faculty'] ?? null);

        if ($includeLifecycleFields) {
            $profileData['student_status'] = $profileData['student_status'] ?? StudentProfile::STUDENT_STATUS_ACTIVE;

            if ($profileData['student_status'] !== StudentProfile::STUDENT_STATUS_DEPARTED) {
                $profileData['departure_reason'] = null;
                $profileData['departure_reason_other'] = null;
                $profileData['departed_at'] = null;
            }

            if (($profileData['departure_reason'] ?? null) !== 'other') {
                $profileData['departure_reason_other'] = null;
            }
        }

        if (($profileData['military_department_status'] ?? null) !== 'studying') {
            $profileData['military_department_place'] = null;
        }

        if ($includeServiceFields) {
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

            if (($profileData['social_support_need_status'] ?? null) !== 'needs') {
                $profileData['social_support_need_details'] = null;
            }
        }

        if ($submitAfterSave) {
            $profileData['profile_status'] = StudentProfile::STATUS_SUBMITTED;
            $profileData['submitted_at'] = now();
            $profileData['verified_at'] = null;
            $profileData['reviewed_by_id'] = null;
            $profileData['revision_comment'] = null;
        } elseif (blank($profile->profile_status)) {
            $profileData['profile_status'] = StudentProfile::STATUS_DRAFT;
        }

        if ($submitAfterSave && $includeServiceFields) {
            $profileData['social_review_status'] = StudentProfile::REVIEW_PENDING;
            $profileData['social_review_comment'] = null;
            $profileData['social_reviewed_at'] = null;
            $profileData['social_reviewed_by_id'] = null;
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

        if ($includeServiceFields) {
            $academicData = Arr::only($validated, $this->academicFields());

            if ($submitAfterSave) {
                $academicData['academic_review_status'] = AcademicProfile::REVIEW_PENDING;
                $academicData['academic_review_comment'] = null;
                $academicData['academic_reviewed_at'] = null;
                $academicData['academic_reviewed_by_id'] = null;
            }

            AcademicProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $academicData,
            );
        }
    }

    public function updateReviewBlock(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);

        $validated = $request->validate([
            'block' => ['required', Rule::in(['social', 'academic'])],
            'review_status' => ['required', Rule::in([
                StudentProfile::REVIEW_VERIFIED,
                StudentProfile::REVIEW_NEEDS_REVISION,
            ])],
            'review_comment' => ['nullable', 'string', 'max:2000', 'required_if:review_status,'.StudentProfile::REVIEW_NEEDS_REVISION],
        ]);

        if ($validated['block'] === 'social') {
            $profile = $student->studentProfile;
            abort_unless($profile, 404);

            $profile->fill([
                'social_review_status' => $validated['review_status'],
                'social_review_comment' => $validated['review_status'] === StudentProfile::REVIEW_NEEDS_REVISION
                    ? $validated['review_comment']
                    : null,
                'social_reviewed_at' => now(),
                'social_reviewed_by_id' => $request->user()->id,
            ]);
            $profile->save();

            return back()->with('status', 'student-profile-social-review-updated');
        }

        $academic = $student->academicProfile;
        abort_unless($academic, 404);

        $academic->fill([
            'academic_review_status' => $validated['review_status'],
            'academic_review_comment' => $validated['review_status'] === AcademicProfile::REVIEW_NEEDS_REVISION
                ? $validated['review_comment']
                : null,
            'academic_reviewed_at' => now(),
            'academic_reviewed_by_id' => $request->user()->id,
        ]);
        $academic->save();

        return back()->with('status', 'student-profile-academic-review-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function profileValidationRules(bool $includeServiceFields = true, bool $includeLifecycleFields = false): array
    {
        $rules = [
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
            'faculty' => ['nullable', 'string', 'max:255'],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')],
            'group_name' => ['nullable', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
            'admission_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'marital_status' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::MARITAL_STATUSES))],
            'special_educational_needs' => ['nullable', 'string', 'max:2000'],
            'stay_address' => ['nullable', 'string', 'max:2000'],
            'residence_address' => ['nullable', 'string', 'max:2000'],
            'contact_details' => ['nullable', 'string', 'max:1000'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'parent_guardian_contacts' => ['nullable', 'string', 'max:2000'],
            'foreign_student_country' => ['nullable', 'string', 'max:100'],
            'kandas_country' => ['nullable', 'string', 'max:100'],
            'dormitory_details' => ['nullable', 'string', 'max:1000'],
            'relatives_living_details' => ['nullable', 'string', 'max:1000'],
            'rental_housing_details' => ['nullable', 'string', 'max:1000'],
        ];

        if ($includeLifecycleFields) {
            $rules = [
                ...$rules,
                'student_status' => ['nullable', Rule::in(array_keys(StudentProfile::STUDENT_STATUS_LABELS))],
                'departure_reason' => ['nullable', 'required_if:student_status,'.StudentProfile::STUDENT_STATUS_DEPARTED, Rule::in(array_keys(StudentProfile::DEPARTURE_REASONS))],
                'departure_reason_other' => ['nullable', 'string', 'max:1000', 'required_if:departure_reason,other'],
                'departed_at' => ['nullable', 'date'],
            ];
        }

        if (! $includeServiceFields) {
            return $rules;
        }

        return [
            ...$rules,
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
            'education_language' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::EDUCATION_LANGUAGES))],
            'gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'final_grades' => ['nullable', 'string', 'max:4000'],
            'current_performance' => ['nullable', 'string', 'max:4000'],
            'academic_debt' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function profileFields(bool $includeServiceFields = true, bool $includeLifecycleFields = false): array
    {
        $fields = [
            'full_name',
            'birth_date',
            'study_form',
            'nationality',
            'citizenship',
            'military_department_status',
            'military_department_place',
            'student_group_id',
            'iin',
            'identity_document_number',
            'gender',
            'faculty',
            'group_name',
            'specialty',
            'course',
            'admission_year',
            'marital_status',
            'special_educational_needs',
            'stay_address',
            'residence_address',
            'contact_details',
            'personal_email',
            'parent_guardian_contacts',
            'foreign_student_country',
            'kandas_country',
            'dormitory_details',
            'relatives_living_details',
            'rental_housing_details',
        ];

        if ($includeLifecycleFields) {
            $fields = [
                ...$fields,
                'student_status',
                'departure_reason',
                'departure_reason_other',
                'departed_at',
            ];
        }

        if (! $includeServiceFields) {
            return $fields;
        }

        return [
            ...$fields,
            'disability_group',
            'disabled_parent_group',
            'disabled_sibling_group',
            'legal_representative',
            'half_orphan_type',
            'social_support_need_status',
            'social_support_need_details',
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
        ];
    }

    private function renderProfileForm(User $user, bool $managed): Response
    {
        $user->load([
            'academicProfile',
            'extracurricularAchievements' => fn ($query) => $query->latest(),
            'portfolioItems' => fn ($query) => $query->latest(),
            'studentProfile',
            'healthPassport',
        ]);

        $viewer = request()->user();
        $canEditProfile = ! $managed || ($viewer?->canEditStudentProfileData() ?? false);
        $canEditHealthPassport = $managed && ($viewer?->canEditStudentHealthPassport() ?? false);

        return Inertia::render('StudentProfile/Edit', [
            'profile' => $this->profilePayload($user->studentProfile),
            'academicProfile' => $this->academicPayload($user->academicProfile),
            'healthPassport' => $this->healthPassportPayload($user->healthPassport),
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
            'availableGroups' => $this->availableGroupOptions(),
            'profileStatusOptions' => $this->profileStatusOptions(),
            'isManagedProfile' => $managed,
            'canEditProfile' => $canEditProfile,
            'canEditHealthPassport' => $canEditHealthPassport,
            'healthPassportUpdateUrl' => $canEditHealthPassport
                ? route('student-profiles.health-passport.update', $user)
                : null,
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
    private function healthPassportPayload(?HealthPassport $passport): array
    {
        return [
            'fluorography_date' => $passport?->fluorography_date?->format('Y-m-d') ?? '',
            'fluorography_image_url' => $passport?->fluorography_image_path
                ? Storage::disk('public')->url($passport->fluorography_image_path)
                : null,
            'dispensary_accounting' => $passport?->dispensary_accounting === null
                ? ''
                : (string) (int) $passport->dispensary_accounting,
            'diagnosis' => $passport?->diagnosis ?? '',
            'disability_group' => $passport?->disability_group ?? '',
            'psychological_diagnosis' => $passport?->psychological_diagnosis ?? '',
            'pregnancy' => $passport?->pregnancy ?? '',
        ];
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
            'profileStatus' => $profile?->profile_status ?? StudentProfile::STATUS_NOT_STARTED,
            'profileStatusLabel' => $this->profileStatusLabel($profile),
            'completion' => $this->profileCompletion($profile),
            'editUrl' => route('student-profiles.edit', $student),
        ];
    }

    private function profileCompletion(?StudentProfile $profile): int
    {
        return $this->riskService->profileCompletion($profile);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(?StudentProfile $profile): array
    {
        $payload = array_fill_keys([
            ...$this->profileFields(),
            ...$this->booleanProfileFields(),
            'student_status',
            'departure_reason',
            'departure_reason_other',
            'departed_at',
            'profile_status',
            'submitted_at',
            'verified_at',
            'reviewed_by_id',
            'revision_comment',
            'social_review_status',
            'social_review_comment',
            'social_reviewed_at',
            'social_reviewed_by_id',
            'photo_path',
            'identity_card_path',
        ], null);

        $payload['benefits'] = [];
        $payload['student_status'] = StudentProfile::STUDENT_STATUS_ACTIVE;
        $payload['student_status_label'] = StudentProfile::STUDENT_STATUS_LABELS[StudentProfile::STUDENT_STATUS_ACTIVE];
        $payload['departure_reason_label'] = null;
        $payload['profile_status'] = StudentProfile::STATUS_NOT_STARTED;
        $payload['profile_status_label'] = StudentProfile::STATUS_LABELS[StudentProfile::STATUS_NOT_STARTED];
        $payload['social_review_status'] = StudentProfile::REVIEW_PENDING;
        $payload['social_review_status_label'] = StudentProfile::REVIEW_LABELS[StudentProfile::REVIEW_PENDING];
        $payload['social_reviewed_at_display'] = null;
        $payload['submitted_at_display'] = null;
        $payload['verified_at_display'] = null;

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
        $studentStatus = $profile?->student_status ?? StudentProfile::STUDENT_STATUS_ACTIVE;
        $payload['student_status'] = $studentStatus;
        $payload['student_status_label'] = StudentProfile::STUDENT_STATUS_LABELS[$studentStatus] ?? $studentStatus;
        $payload['departure_reason_label'] = $profile?->departure_reason
            ? (StudentProfile::DEPARTURE_REASONS[$profile->departure_reason] ?? $profile->departure_reason)
            : null;
        $status = $profile?->profile_status ?? StudentProfile::STATUS_NOT_STARTED;
        $payload['profile_status'] = $status;
        $payload['profile_status_label'] = StudentProfile::STATUS_LABELS[$status] ?? $status;
        $socialReviewStatus = $profile?->social_review_status ?? StudentProfile::REVIEW_PENDING;
        $payload['social_review_status'] = $socialReviewStatus;
        $payload['social_review_status_label'] = StudentProfile::REVIEW_LABELS[$socialReviewStatus] ?? $socialReviewStatus;
        $payload['social_reviewed_at_display'] = $profile?->social_reviewed_at?->format('d.m.Y H:i');
        $payload['submitted_at_display'] = $profile?->submitted_at?->format('d.m.Y H:i');
        $payload['verified_at_display'] = $profile?->verified_at?->format('d.m.Y H:i');
        $payload['photo_url'] = $profile?->photo_path ? Storage::disk('public')->url($profile->photo_path) : null;
        $payload['identity_card_url'] = $profile?->identity_card_path ? Storage::disk('public')->url($profile->identity_card_path) : null;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function academicPayload(?AcademicProfile $academicProfile): array
    {
        $payload = array_fill_keys([
            ...$this->academicFields(),
            'academic_review_status',
            'academic_review_comment',
            'academic_reviewed_at',
            'academic_reviewed_by_id',
        ], null);
        $payload['academic_review_status'] = AcademicProfile::REVIEW_PENDING;
        $payload['academic_review_status_label'] = AcademicProfile::REVIEW_LABELS[AcademicProfile::REVIEW_PENDING];
        $payload['academic_reviewed_at_display'] = null;

        if ($academicProfile) {
            $payload = [
                ...$payload,
                ...$academicProfile->toArray(),
            ];
        }

        $reviewStatus = $academicProfile?->academic_review_status ?? AcademicProfile::REVIEW_PENDING;
        $payload['academic_review_status'] = $reviewStatus;
        $payload['academic_review_status_label'] = AcademicProfile::REVIEW_LABELS[$reviewStatus] ?? $reviewStatus;
        $payload['academic_reviewed_at_display'] = $academicProfile?->academic_reviewed_at?->format('d.m.Y H:i');

        return $payload;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function profileStatusOptions(): array
    {
        return collect([
            StudentProfile::STATUS_NOT_STARTED,
            StudentProfile::STATUS_DRAFT,
            StudentProfile::STATUS_SUBMITTED,
            StudentProfile::STATUS_VERIFIED,
            StudentProfile::STATUS_NEEDS_REVISION,
        ])
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => StudentProfile::STATUS_LABELS[$status],
            ])
            ->values()
            ->all();
    }

    private function profileStatusLabel(?StudentProfile $profile): string
    {
        $status = $profile?->profile_status ?? StudentProfile::STATUS_NOT_STARTED;

        return StudentProfile::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * @return array<int, array{value: string, label: string, faculty: string|null}>
     */
    private function availableGroupOptions(): array
    {
        return StudentGroup::query()
            ->orderBy('faculty')
            ->orderBy('name')
            ->get(['id', 'faculty', 'name'])
            ->map(fn (StudentGroup $group): array => [
                'value' => (string) $group->id,
                'label' => $group->name,
                'name' => $group->name,
                'faculty' => $group->faculty,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    /**
     * @param  array<string, mixed>  $validated
     */
    private function selectedStudentGroup(array $validated): ?StudentGroup
    {
        if (filled($validated['student_group_id'] ?? null)) {
            return StudentGroup::query()->find((int) $validated['student_group_id']);
        }

        if (filled($validated['group_name'] ?? null)) {
            return StudentGroup::query()
                ->where('name', $validated['group_name'])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureGroupNameIsKnown(array $validated, ?StudentGroup $studentGroup): void
    {
        if ($studentGroup || blank($validated['group_name'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages([
            'group_name' => 'Выберите группу из списка.',
        ]);
    }
}
