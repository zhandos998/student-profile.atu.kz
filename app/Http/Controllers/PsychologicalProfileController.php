<?php

namespace App\Http\Controllers;

use App\Models\PsychologicalProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PsychologicalProfileController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canViewPsychologicalProfile(), 403);

        $profile = $request->user()->psychologicalProfile;

        return Inertia::render('PsychologicalProfile/Index', [
            'profile' => [
                'testing_results' => $profile?->testing_results ?? '',
                'individual_features' => $profile?->individual_features ?? '',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewPsychologicalProfile(), 403);

        $validated = $request->validate([
            'testing_results' => ['nullable', 'string', 'max:10000'],
            'individual_features' => ['nullable', 'string', 'max:10000'],
        ]);

        PsychologicalProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated,
        );

        return back()->with('status', 'psychological-profile-saved');
    }
}
