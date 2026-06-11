<?php

namespace App\Http\Controllers;

use App\Models\ExtracurricularAchievement;
use App\Models\Role;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExtracurricularAchievementController extends Controller
{
    /**
     * Store a student extracurricular achievement.
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->storeForUser($request, $request->user());
    }

    public function storeForStudent(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);

        return $this->storeForUser($request, $student);
    }

    /**
     * Delete a student extracurricular achievement.
     */
    public function destroy(Request $request, ExtracurricularAchievement $achievement): RedirectResponse
    {
        abort_unless($achievement->user_id === $request->user()->id, 404);

        $this->deleteAchievement($achievement);

        return back()->with('status', 'achievement-deleted');
    }

    public function destroyForStudent(Request $request, User $student, ExtracurricularAchievement $achievement): RedirectResponse
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);
        abort_unless($achievement->user_id === $student->id, 404);

        $this->deleteAchievement($achievement);

        return back()->with('status', 'achievement-deleted');
    }

    private function storeForUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'activity_type' => ['required', Rule::in(StudentProfileOptions::values(StudentProfileOptions::ACTIVITY_TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'level' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::ACHIEVEMENT_LEVELS))],
            'result' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::ACHIEVEMENT_RESULTS))],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,mp4', 'max:51200'],
        ]);

        $document = $request->file('document');

        ExtracurricularAchievement::query()->create([
            'user_id' => $user->id,
            'activity_type' => $validated['activity_type'],
            'title' => $validated['title'],
            'level' => $validated['level'] ?? null,
            'result' => $validated['result'] ?? null,
            'description' => $validated['description'] ?? null,
            'document_path' => $document?->store('student-profiles/achievement-documents', 'public'),
            'document_original_name' => $document?->getClientOriginalName(),
        ]);

        return back()->with('status', 'achievement-added');
    }

    private function deleteAchievement(ExtracurricularAchievement $achievement): void
    {
        if ($achievement->document_path) {
            Storage::disk('public')->delete($achievement->document_path);
        }

        $achievement->delete();
    }
}
