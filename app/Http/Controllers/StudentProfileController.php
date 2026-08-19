<?php

namespace App\Http\Controllers;

use App\Models\AcademicProfile;
use App\Models\HealthPassport;
use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\PlatonusAuthClient;
use App\Services\PsychotestApiClient;
use App\Services\StudentRiskService;
use App\Support\StudentProfileAccess;
use App\Support\StudentProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    public function __construct(
        private readonly StudentRiskService $riskService,
        private readonly PsychotestApiClient $psychotestApi,
        private readonly PlatonusAuthClient $platonusApi,
    )
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
            'archive_status' => ['nullable', Rule::in(['active', 'archived', 'all'])],
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
        $archiveStatus = $filters['archive_status'] ?? 'active';

        $students = StudentProfileAccess::scopeStudentUsers(User::query(), $request->user())
            ->with(['role', 'studentProfile', 'academicProfile'])
            ->whereHas('role', fn ($query) => $query->whereIn('slug', User::STUDENT_DATA_ROLES))
            ->when($archiveStatus === 'active', fn ($query) => $query
                ->where(function ($query): void {
                    $query
                        ->doesntHave('studentProfile')
                        ->orWhereHas('studentProfile', fn ($query) => $query->active());
                }))
            ->when($archiveStatus === 'archived', fn ($query) => $query
                ->whereHas('studentProfile', fn ($query) => $query->archived()))
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
            ->when(($filters['profile_status'] ?? null) === 'without_profile', fn ($query) => $query->doesntHave('studentProfile'))
            ->when(
                ($filters['profile_status'] ?? null) === StudentProfile::STATUS_NOT_STARTED,
                fn ($query) => $query->where(function ($query): void {
                    $query
                        ->doesntHave('studentProfile')
                        ->orWhereHas('studentProfile', fn ($query) => $query->where('profile_status', StudentProfile::STATUS_NOT_STARTED));
                })
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
                'archive_status' => $archiveStatus,
                'profile_status' => $filters['profile_status'] ?? '',
            ],
            'options' => StudentProfileOptions::forInertia(),
            'availableGroups' => $this->availableGroupOptions($request->user()),
            'profileStatusOptions' => $this->profileStatusOptions(),
            'canCreateStudentProfiles' => $request->user()?->canEditStudentProfileData() ?? false,
            'canArchiveStudentProfiles' => $request->user()?->canEditStudentProfileData() ?? false,
        ]);
    }

    public function createManaged(Request $request): Response
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);

        return Inertia::render('StudentProfile/Create', [
            'options' => StudentProfileOptions::forInertia(),
            'availableGroups' => $this->availableGroupOptions($request->user()),
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
        $this->ensureCanUseStudentGroup($request->user(), $studentGroup);

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
        abort_unless($student->loadMissing('role')->hasStudentDataRole(), 404);
        abort_unless(StudentProfileAccess::canAccessStudent($request->user(), $student), 403);

        return $this->renderProfileForm($student, true);
    }

    public function updateManaged(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);
        abort_unless($student->loadMissing('role')->hasStudentDataRole(), 404);
        abort_unless(StudentProfileAccess::canAccessStudent($request->user(), $student), 403);

        $this->persistProfile($request, $student, false, true, true, true);

        return back()->with('status', 'student-profile-saved');
    }

    public function updateStatus(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);
        abort_unless($student->loadMissing('role')->hasStudentDataRole(), 404);
        abort_unless(StudentProfileAccess::canAccessStudent($request->user(), $student), 403);

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

    public function archive(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);
        abort_unless($student->loadMissing('role')->hasStudentDataRole(), 404);
        abort_unless(StudentProfileAccess::canAccessStudent($request->user(), $student), 403);

        $profile = StudentProfile::query()->firstOrNew(['user_id' => $student->id]);

        $profile->forceFill([
            'full_name' => $profile->full_name ?: $student->name,
            'profile_status' => $profile->profile_status ?: StudentProfile::STATUS_NOT_STARTED,
            'archived_at' => now(),
            'archived_by_id' => $request->user()->id,
        ])->save();

        return back()->with('status', 'student-profile-archived');
    }

    public function restore(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentProfileData(), 403);
        abort_unless($student->loadMissing('role')->hasStudentDataRole(), 404);
        abort_unless(StudentProfileAccess::canAccessStudent($request->user(), $student), 403);

        $profile = $student->studentProfile;
        abort_unless($profile, 404);

        $profile->forceFill([
            'archived_at' => null,
            'archived_by_id' => null,
        ])->save();

        return back()->with('status', 'student-profile-restored');
    }

    /**
     * Display the student profile form.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user?->canUseOwnStudentProfile()) {
            if ($user?->canManageStudentProfiles()) {
                return redirect()->route('student-profiles.index');
            }

            abort(403);
        }

        return $this->renderProfileForm($user, false);
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
        bool $enforceManagedGroupAccess = false,
    ): void
    {
        $validated = $request->validate($this->profileValidationRules($includeServiceFields, $includeLifecycleFields));
        $studentGroup = $this->selectedStudentGroup($validated);
        $this->ensureGroupNameIsKnown($validated, $studentGroup);

        if ($enforceManagedGroupAccess) {
            $this->ensureCanUseStudentGroup($request->user(), $studentGroup);
        }

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
        abort_unless($student->loadMissing('role')->hasStudentDataRole(), 404);
        abort_unless(StudentProfileAccess::canAccessStudent($request->user(), $student), 403);

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

    public function fetchPlatonusStudent(Request $request): JsonResponse
    {
        $viewer = $request->user();

        abort_unless(
            $viewer?->canUseOwnStudentProfile() || $viewer?->canEditStudentProfileData(),
            403,
        );

        $validated = $request->validate([
            'iin' => ['required', 'digits:12'],
        ]);

        $result = $this->platonusApi->studentFull($validated['iin']);

        if (! $result['configured']) {
            return response()->json([
                'message' => $result['message'] ?: 'API Платонуса не настроен.',
            ], 503);
        }

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'] ?: 'Не удалось загрузить данные из API Платонуса.',
            ], 422);
        }

        if ($this->platonusPayloadSaysFailure($result['raw'])) {
            return response()->json([
                'message' => $this->platonusPayloadMessage($result['raw']) ?: 'Студент с указанным ИИН не найден.',
            ], 404);
        }

        $profile = $this->platonusProfilePayload($result['student'], $validated['iin']);

        if (! collect($profile)->except(['iin'])->filter(fn ($value): bool => filled($value))->isNotEmpty()) {
            return response()->json([
                'message' => 'API Платонуса не вернул данные студента по этому ИИН.',
            ], 404);
        }

        return response()->json([
            'message' => $profile['group_warning'] ?? 'Данные из Платонуса загружены. Проверьте поля и нажмите “Сохранить”.',
            'profile' => Arr::except($profile, ['group_warning']),
        ]);
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
        $canViewPsychotestResults = $managed && ($viewer?->canViewPsychologicalProfile() ?? false);

        return Inertia::render('StudentProfile/Edit', [
            'profile' => $this->profilePayload($user->studentProfile),
            'academicProfile' => $this->academicPayload($user->academicProfile),
            'healthPassport' => $this->healthPassportPayload($user->healthPassport),
            'psychotestResults' => $canViewPsychotestResults
                ? $this->psychotestResultsPayload($user->studentProfile)
                : null,
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
            'availableGroups' => $this->availableGroupOptions($managed ? $viewer : null),
            'profileStatusOptions' => $this->profileStatusOptions(),
            'isManagedProfile' => $managed,
            'canEditProfile' => $canEditProfile,
            'canEditHealthPassport' => $canEditHealthPassport,
            'canViewPsychotestResults' => $canViewPsychotestResults,
            'canArchiveStudentProfile' => $managed && $canEditProfile,
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
    private function psychotestResultsPayload(?StudentProfile $profile): array
    {
        $iin = trim((string) $profile?->iin);
        $testIds = config('services.psychotest.test_ids', []);
        $testIds = is_array($testIds) ? $testIds : [];
        $testIdsLabel = $testIds === [] ? 'Все доступные' : implode(',', $testIds);

        if ($iin === '') {
            return [
                'iin' => '',
                'test_ids' => $testIdsLabel,
                'configured' => true,
                'ok' => false,
                'status' => null,
                'message' => 'У студента не указан ИИН. Результаты психотестов нельзя получить.',
                'results' => [],
                'raw' => null,
            ];
        }

        return [
            'iin' => $iin,
            'test_ids' => $testIdsLabel,
            ...$this->psychotestApi->testResults($iin, $testIds),
        ];
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
            'isArchived' => $profile?->archived_at !== null,
            'archivedAtDisplay' => $profile?->archived_at?->format('d.m.Y H:i'),
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
            'archived_at',
            'archived_by_id',
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
        $payload['has_profile'] = false;
        $payload['is_archived'] = false;
        $payload['archived_at_display'] = null;
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
            $payload['has_profile'] = true;
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
        $payload['is_archived'] = $profile?->archived_at !== null;
        $payload['archived_at_display'] = $profile?->archived_at?->format('d.m.Y H:i');
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
     * @param  array<string, mixed>  $student
     * @return array<string, mixed>
     */
    private function platonusProfilePayload(array $student, string $iin): array
    {
        $groupName = $this->platonusValue($student, [
            'group',
            'group_name',
            'student_group',
            'groupName',
            'group_title',
            'education.group_name',
        ]);
        $faculty = $this->normalizePlatonusFaculty($this->platonusValue($student, [
            'faculty',
            'faculty_name',
            'facultyName',
            'education.faculty_name_ru',
            'education.faculty_name_kz',
        ]));
        $studentGroup = $this->platonusStudentGroup($groupName, $faculty);
        $profile = [
            'full_name' => $this->platonusFullName($student),
            'birth_date' => $this->normalizePlatonusDate($this->platonusValue($student, [
                'birth_date',
                'birthday',
                'date_of_birth',
            ])),
            'study_form' => $this->platonusValue($student, [
                'study_form',
                'education_form',
                'form_of_study',
                'education.study_form_ru',
            ]),
            'nationality' => $this->normalizePlatonusNationality($this->platonusValue($student, [
                'nationality',
                'nationality.ru',
                'nationality.kz',
            ])),
            'citizenship' => $this->platonusCitizenship($student),
            'iin' => $this->platonusValue($student, [
                'iin',
                'IIN',
                'iin_number',
                'individual_identification_number',
            ]) ?: $iin,
            'identity_document_number' => $this->platonusValue($student, [
                'identity_document_number',
                'identity_number',
                'document_number',
            ]),
            'gender' => $this->platonusGender($student),
            'faculty' => $studentGroup?->faculty ?: $faculty,
            'student_group_id' => $studentGroup ? (string) $studentGroup->id : '',
            'group_name' => $studentGroup?->name ?? '',
            'specialty' => $this->platonusSpecialty($student),
            'course' => $this->platonusCourse($student),
            'admission_year' => $this->platonusAdmissionYear($student),
            'stay_address' => $this->platonusValue($student, [
                'stay_address',
                'living_address',
                'address.living_address',
            ]),
            'residence_address' => $this->platonusValue($student, [
                'residence_address',
                'registration_address',
                'address.registration_address',
            ]),
            'contact_details' => $this->platonusValue($student, [
                'phone',
                'phone_number',
                'mobile',
                'mobile_phone',
                'contact_phone',
                'contacts.mobile',
                'contacts.phone',
            ]),
            'personal_email' => $this->platonusEmail($student),
            'parent_guardian_contacts' => $this->platonusParentContacts($student),
        ];

        if ($groupName && ! $studentGroup) {
            $profile['group_warning'] = "Данные из Платонуса загружены, но группа “{$groupName}” не найдена в системе. Выберите группу вручную или сначала создайте ее.";
        }

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusFullName(array $student): ?string
    {
        $fullName = $this->platonusValue($student, [
            'full_name',
            'fullname',
            'fullName',
            'fio',
            'student_name',
            'studentName',
            'name',
        ]);

        if ($fullName) {
            return $fullName;
        }

        $parts = array_filter([
            $this->platonusValue($student, ['last_name', 'lastname', 'surname']),
            $this->platonusValue($student, ['first_name', 'firstname', 'given_name']),
            $this->platonusValue($student, ['middle_name', 'middlename', 'patronymic']),
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $student
     * @param  array<int, string>  $keys
     */
    private function platonusValue(array $student, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($student, $key);

            if ($value === null) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value)) {
                $value = data_get($value, 'ru')
                    ?? data_get($value, 'kz')
                    ?? data_get($value, 'name')
                    ?? data_get($value, 'title')
                    ?? data_get($value, 'value');
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizePlatonusDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match)) {
            return "{$match[1]}-{$match[2]}-{$match[3]}";
        }

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $match)) {
            return "{$match[3]}-{$match[2]}-{$match[1]}";
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function normalizePlatonusNationality(?string $nationality): ?string
    {
        if (! $nationality) {
            return null;
        }

        $nationality = trim($nationality);
        $nationalityLower = Str::lower($nationality);

        if (Str::contains($nationalityLower, ['қазақ', 'казах'])) {
            return 'Казах';
        }

        foreach (StudentProfileOptions::NATIONALITIES as $option) {
            if (Str::lower($option) === $nationalityLower) {
                return $option;
            }
        }

        return 'Другая национальность';
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusGender(array $student): ?string
    {
        $gender = Str::lower((string) $this->platonusValue($student, ['gender', 'sex', 'sex.ru', 'sex.kz']));

        if ($gender === '') {
            return null;
        }

        if (Str::contains($gender, ['female', 'жен', 'әйел'])) {
            return 'female';
        }

        if (Str::contains($gender, ['male', 'муж', 'ер'])) {
            return 'male';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusCitizenship(array $student): ?string
    {
        $citizenship = Str::lower((string) $this->platonusValue($student, [
            'citizenship',
            'citizenship.ru',
            'citizenship.kz',
        ]));

        if ($citizenship === '') {
            return null;
        }

        if (Str::contains($citizenship, ['канд', 'қандас'])) {
            return 'kandas';
        }

        if (Str::contains($citizenship, ['казахстан', 'қазақстан', 'рк', 'kazakhstan'])) {
            return 'kazakhstan_citizen';
        }

        return 'foreign_citizen';
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusSpecialty(array $student): ?string
    {
        $specialty = $this->platonusValue($student, [
            'specialty',
            'speciality',
            'educational_program',
            'education_program',
            'program',
            'education.speciality_name_ru',
        ]);

        $code = $this->platonusValue($student, ['speciality_code', 'education.speciality_code']);

        if ($specialty && $code) {
            return $code.' - '.$specialty;
        }

        return $specialty ?: $code;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusCourse(array $student): ?int
    {
        $course = $this->platonusValue($student, ['course', 'year', 'study_year', 'education.course']);

        if (! is_numeric($course)) {
            return null;
        }

        $course = (int) $course;

        return $course > 0 && $course <= 10 ? $course : null;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusAdmissionYear(array $student): ?int
    {
        $year = $this->platonusValue($student, [
            'admission_year',
            'start_year',
            'education.admission_year',
            'education.start_year',
        ]);

        if (! is_numeric($year)) {
            return null;
        }

        $year = (int) $year;

        return $year >= 1900 && $year <= now()->year + 1 ? $year : null;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusEmail(array $student): ?string
    {
        $email = $this->platonusValue($student, [
            'email',
            'mail',
            'e_mail',
            'personal_email',
            'contacts.email',
        ]);

        if (! $email || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return Str::lower($email);
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function platonusParentContacts(array $student): ?string
    {
        $parents = data_get($student, 'parents');

        if (! is_array($parents) || $parents === []) {
            return null;
        }

        $contacts = collect($parents)
            ->map(function (mixed $parent): ?string {
                if (is_scalar($parent)) {
                    return trim((string) $parent) ?: null;
                }

                if (! is_array($parent)) {
                    return null;
                }

                $parts = array_filter([
                    $this->platonusFullName($parent),
                    $this->platonusValue($parent, ['phone', 'mobile', 'phone_number']),
                    $this->platonusValue($parent, ['address', 'registration_address', 'living_address']),
                ]);

                return $parts === [] ? null : implode(', ', $parts);
            })
            ->filter()
            ->values();

        return $contacts->isEmpty() ? null : $contacts->implode("\n");
    }

    private function normalizePlatonusFaculty(?string $faculty): ?string
    {
        if (! $faculty) {
            return null;
        }

        $facultyLower = Str::lower($faculty);
        $faculties = StudentProfileOptions::facultyNames();

        if (Str::contains($facultyLower, ['биотехнолог', 'химичес'])) {
            return $faculties[0] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['дизайн', 'текстил', 'одежд', 'киім'])) {
            return $faculties[1] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['интеллект', 'инженер'])) {
            return $faculties[2] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['информацион', 'ақпарат'])) {
            return $faculties[3] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['пищев', 'тағам'])) {
            return $faculties[4] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['эконом', 'бизнес'])) {
            return $faculties[5] ?? $faculty;
        }

        return $faculty;
    }

    private function platonusStudentGroup(?string $groupName, ?string $faculty): ?StudentGroup
    {
        if (! $groupName) {
            return null;
        }

        return StudentGroup::query()
            ->where('name', $groupName)
            ->when($faculty, fn ($query) => $query->where(function ($query) use ($faculty): void {
                $query->whereNull('faculty')->orWhere('faculty', $faculty);
            }))
            ->first();
    }

    private function platonusPayloadSaysFailure(mixed $raw): bool
    {
        if (! is_array($raw)) {
            return false;
        }

        foreach (['authenticated', 'success', 'ok', 'found'] as $field) {
            if (array_key_exists($field, $raw) && ! filter_var($raw[$field], FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        $status = Str::lower((string) data_get($raw, 'status', ''));

        if (in_array($status, ['error', 'failed', 'not_found'], true)) {
            return true;
        }

        $message = Str::lower((string) $this->platonusPayloadMessage($raw));

        return $message !== '' && Str::contains($message, [
            'not found',
            'не найден',
            'табылма',
            'нет данных',
        ]);
    }

    private function platonusPayloadMessage(mixed $raw): ?string
    {
        if (! is_array($raw)) {
            return null;
        }

        foreach (['message', 'error', 'detail', 'data.message', 'student.message'] as $field) {
            $value = data_get($raw, $field);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
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
    private function availableGroupOptions(?User $viewer = null): array
    {
        $query = $viewer
            ? StudentProfileAccess::accessibleGroupsQuery($viewer)
            : StudentGroup::query();

        return $query
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

    private function ensureCanUseStudentGroup(User $viewer, ?StudentGroup $studentGroup): void
    {
        if ($viewer->canViewAllStudentData()) {
            return;
        }

        if (! $studentGroup) {
            throw ValidationException::withMessages([
                'student_group_id' => 'Выберите свою группу из списка.',
            ]);
        }

        abort_unless(StudentProfileAccess::canAccessGroup($viewer, $studentGroup), 403);
    }
}
