<?php

namespace App\Http\Controllers;

use App\Models\AcademicProfile;
use App\Models\StudentProfile;
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
    /**
     * Display the student profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load([
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
        ]);
    }

    /**
     * Store the student card and academic profile.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'study_form' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
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
            'special_educational_needs' => ['nullable', 'string', 'max:2000'],
            'stay_address' => ['nullable', 'string', 'max:2000'],
            'residence_address' => ['nullable', 'string', 'max:2000'],
            'contact_details' => ['nullable', 'string', 'max:1000'],
            'foreign_student_country' => ['nullable', 'string', 'max:100'],
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
        ]);

        $user = $request->user();
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

        return back()->with('status', 'student-profile-saved');
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
            'special_educational_needs',
            'stay_address',
            'residence_address',
            'contact_details',
            'foreign_student_country',
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
