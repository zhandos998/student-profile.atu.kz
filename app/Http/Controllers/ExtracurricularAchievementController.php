<?php

namespace App\Http\Controllers;

use App\Models\ExtracurricularAchievement;
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
            'user_id' => $request->user()->id,
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

    /**
     * Delete a student extracurricular achievement.
     */
    public function destroy(Request $request, ExtracurricularAchievement $achievement): RedirectResponse
    {
        abort_unless($achievement->user_id === $request->user()->id, 404);

        if ($achievement->document_path) {
            Storage::disk('public')->delete($achievement->document_path);
        }

        $achievement->delete();

        return back()->with('status', 'achievement-deleted');
    }
}
