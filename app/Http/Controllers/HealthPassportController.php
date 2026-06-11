<?php

namespace App\Http\Controllers;

use App\Models\HealthPassport;
use App\Support\StudentProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HealthPassportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canViewHealthPassport(), 403);

        $passport = $request->user()->healthPassport;

        return Inertia::render('HealthPassport/Index', [
            'passport' => [
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
            ],
            'options' => [
                'dispensaryAccounting' => [
                    ['value' => '1', 'label' => 'Да'],
                    ['value' => '0', 'label' => 'Нет'],
                ],
                'disabilityGroups' => StudentProfileOptions::toSelectOptions(StudentProfileOptions::DISABILITY_GROUPS),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canViewHealthPassport(), 403);

        $validated = $request->validate([
            'fluorography_date' => ['nullable', 'date'],
            'fluorography_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'dispensary_accounting' => ['nullable', 'boolean'],
            'diagnosis' => ['nullable', 'string', 'max:4000'],
            'disability_group' => ['nullable', Rule::in(StudentProfileOptions::values(StudentProfileOptions::DISABILITY_GROUPS))],
            'psychological_diagnosis' => ['nullable', 'string', 'max:4000'],
            'pregnancy' => ['nullable', 'string', 'max:2000'],
        ]);

        $passport = HealthPassport::query()->firstOrNew(['user_id' => $request->user()->id]);

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

        return back()->with('status', 'health-passport-saved');
    }
}
