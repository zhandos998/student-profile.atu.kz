<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $filters = [
            'search' => (string) $request->query('search', ''),
            'role' => (string) $request->query('role', ''),
        ];

        $users = User::query()
            ->with('role')
            ->when($filters['role'] !== '', function ($query) use ($filters): void {
                $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', $filters['role']));
            })
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $query->where(function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('phone', 'like', $search)
                        ->orWhere('platonus_login', 'like', $search)
                        ->orWhere('position', 'like', $search)
                        ->orWhereHas('role', fn ($roleQuery) => $roleQuery
                            ->where('name', 'like', $search)
                            ->orWhere('slug', 'like', $search));
                });
            })
            ->orderBy(
                Role::query()
                    ->select('name')
                    ->whereColumn('roles.id', 'users.role_id')
                    ->limit(1)
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'platonusLogin' => $user->platonus_login,
                'position' => $user->position,
                'roleSlug' => $user->role?->slug,
                'roleName' => $user->role?->name ?? 'Без роли',
                'createdAt' => $user->created_at?->format('d.m.Y H:i'),
                'canImpersonate' => ! $request->session()->has('impersonator_id')
                    && ! $request->user()?->is($user),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roleOptions' => Role::query()
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (Role $role): array => [
                    'value' => $role->slug,
                    'label' => $role->name,
                ])
                ->values(),
        ]);
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->canImpersonateUsers(), 403);
        abort_if($request->session()->has('impersonator_id'), 403);
        abort_if($request->user()->is($user), 422);

        $impersonatorId = $request->user()->id;

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $impersonatorId);

        return redirect()
            ->route('dashboard')
            ->with('status', "Вы вошли как {$user->name}.");
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        abort_unless($impersonatorId, 403);

        $impersonator = User::query()
            ->whereKey($impersonatorId)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::ADMINISTRATOR_DIT))
            ->first();

        abort_unless($impersonator, 403);

        Auth::login($impersonator);
        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');

        return redirect()
            ->route('users.index')
            ->with('status', 'Вы вернулись в аккаунт администратора ДИТ.');
    }
}
