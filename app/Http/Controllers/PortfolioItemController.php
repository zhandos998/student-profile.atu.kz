<?php

namespace App\Http\Controllers;

use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PortfolioItemController extends Controller
{
    /**
     * Store a student portfolio file.
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
     * Delete a student portfolio file.
     */
    public function destroy(Request $request, PortfolioItem $portfolioItem): RedirectResponse
    {
        abort_unless($portfolioItem->user_id === $request->user()->id, 404);

        $this->deletePortfolioItem($portfolioItem);

        return back()->with('status', 'portfolio-deleted');
    }

    public function destroyForStudent(Request $request, User $student, PortfolioItem $portfolioItem): RedirectResponse
    {
        abort_unless($request->user()?->canManageStudentProfiles(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);
        abort_unless($portfolioItem->user_id === $student->id, 404);

        $this->deletePortfolioItem($portfolioItem);

        return back()->with('status', 'portfolio-deleted');
    }

    private function storeForUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', Rule::in(StudentProfileOptions::values(StudentProfileOptions::PORTFOLIO_TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,mp4', 'max:51200'],
        ]);

        $file = $request->file('file');

        PortfolioItem::query()->create([
            'user_id' => $user->id,
            'item_type' => $validated['item_type'],
            'title' => $validated['title'],
            'file_path' => $file->store('student-profiles/portfolio', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        return back()->with('status', 'portfolio-added');
    }

    private function deletePortfolioItem(PortfolioItem $portfolioItem): void
    {
        Storage::disk('public')->delete($portfolioItem->file_path);

        $portfolioItem->delete();
    }
}
