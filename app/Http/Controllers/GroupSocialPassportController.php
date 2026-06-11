<?php

namespace App\Http\Controllers;

use App\Models\GroupSocialPassport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupSocialPassportController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);

        return Inertia::render('GroupSocialPassport/Edit', [
            'passport' => $this->payload($request->user()->groupSocialPassport),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewGroupSocialPassport(), 403);

        $validated = $request->validate([
            'faculty' => ['nullable', 'string', 'max:255'],
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
            'departed_students' => ['nullable', 'array'],
            'departed_students.*.full_name' => ['nullable', 'string', 'max:255'],
            'departed_students.*.faculty' => ['nullable', 'string', 'max:255'],
            'departed_students.*.education_program' => ['nullable', 'string', 'max:255'],
            'departed_students.*.group_name' => ['nullable', 'string', 'max:100'],
            'departed_students.*.course' => ['nullable', 'integer', 'min:1', 'max:8'],
            'departed_students.*.reason' => ['nullable', 'string', 'max:100'],
            'departed_students.*.reason_other' => ['nullable', 'string', 'max:1000'],
        ]);

        GroupSocialPassport::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                ...$validated,
                'students' => $validated['students'] ?? [],
                'summary' => $validated['summary'] ?? [],
                'departed_students' => $validated['departed_students'] ?? [],
            ],
        );

        return back()->with('status', 'group-social-passport-saved');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?GroupSocialPassport $passport): array
    {
        return [
            'faculty' => $passport?->faculty ?? '',
            'group_name' => $passport?->group_name ?? '',
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
            'students' => $passport?->students ?? [],
            'summary' => $passport?->summary ?? [],
            'departed_students' => $passport?->departed_students ?? [],
        ];
    }
}
