<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $impersonator = null;

        $user?->loadMissing('role');

        if ($request->session()->has('impersonator_id')) {
            $impersonator = User::query()
                ->with('role')
                ->find($request->session()->get('impersonator_id'));
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'canViewPsychologicalProfile' => $user?->canViewPsychologicalProfile() ?? false,
                'canViewHealthPassport' => $user?->canViewHealthPassport() ?? false,
                'canViewGroupSocialPassport' => $user?->canViewGroupSocialPassport() ?? false,
                'canViewAnalyticsDashboard' => $user?->canViewAnalyticsDashboard() ?? false,
                'canManageStudentProfiles' => $user?->canManageStudentProfiles() ?? false,
                'canUseOwnStudentProfile' => $user?->canUseOwnStudentProfile() ?? false,
                'canManageUsers' => $user?->canManageUsers() ?? false,
            ],
            'impersonation' => [
                'active' => $request->session()->has('impersonator_id'),
                'impersonator' => $impersonator ? [
                    'id' => $impersonator->id,
                    'name' => $impersonator->name,
                    'email' => $impersonator->email,
                    'role' => [
                        'slug' => $impersonator->role?->slug,
                        'name' => $impersonator->role?->name,
                    ],
                ] : null,
            ],
            'csrfToken' => csrf_token(),
            'locale' => app()->getLocale(),
            'availableLocales' => collect(config('locales.supported', []))
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ];
    }
}
