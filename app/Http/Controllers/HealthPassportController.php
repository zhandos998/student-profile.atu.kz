<?php

namespace App\Http\Controllers;

use App\Models\HealthPassport;
use App\Models\Role;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HealthPassportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewHealthPassport(), 403);

        return redirect()->route('student-profiles.index');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewHealthPassport(), 403);

        return redirect()->route('student-profiles.index');
    }

    public function updateForStudent(Request $request, User $student): RedirectResponse
    {
        abort_unless($request->user()?->canEditStudentHealthPassport(), 403);
        abort_unless($student->loadMissing('role')->role?->slug === Role::STUDENT, 404);

        $this->persist($request, $student);

        return back()->with('status', 'student-health-passport-saved');
    }

    private function persist(Request $request, User $user): HealthPassport
    {
        $validated = $request->validate([
            'fluorography_date' => ['nullable', 'date'],
            'fluorography_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'dispensary_accounting' => ['nullable', 'boolean'],
            'diagnosis' => ['nullable', 'string', 'max:4000'],
            'disability_group' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::DISABILITY_GROUPS))],
            'psychological_diagnosis' => ['nullable', 'string', 'max:4000'],
            'pregnancy' => ['nullable', 'string', 'max:2000'],
        ]);

        $passport = HealthPassport::query()->firstOrNew(['user_id' => $user->id]);

        if ($request->hasFile('fluorography_image')) {
            if ($passport->fluorography_image_path) {
                Storage::disk('public')->delete($passport->fluorography_image_path);
            }

            $validated['fluorography_image_path'] = $request
                ->file('fluorography_image')
                ->store('health-passports/fluorography', 'public');
        }

        unset($validated['fluorography_image']);
        $validated['dispensary_accounting'] = $request->filled('dispensary_accounting')
            ? $request->boolean('dispensary_accounting')
            : null;

        $passport->fill($validated);
        $passport->save();

        return $passport;
    }
}
