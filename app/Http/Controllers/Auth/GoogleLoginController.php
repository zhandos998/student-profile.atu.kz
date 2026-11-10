<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleIdentityVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleLoginController extends Controller
{
    public function challenge(Request $request): JsonResponse
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return response()->json(['enabled' => false])->header('Cache-Control', 'no-store');
        }

        $nonce = Str::random(64);
        $request->session()->put('google_login_nonce', [
            'value' => $nonce,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);
        Cache::put('google-login:nonce:'.$nonce, true, now()->addMinutes(5));

        return response()->json([
            'enabled' => true,
            'client_id' => $clientId,
            'nonce' => $nonce,
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, GoogleIdentityVerifier $verifier): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        $validated = $request->validate([
            'credential' => ['required', 'string', 'max:12000'],
            'nonce' => ['required', 'string', 'size:64', 'alpha_num:ascii'],
        ]);

        $challenge = $request->session()->pull('google_login_nonce');
        $nonce = $validated['nonce'];
        $accepted = is_array($challenge)
            && is_string($challenge['value'] ?? null)
            && hash_equals($challenge['value'], $nonce)
            && ($challenge['expires_at'] ?? 0) > now()->timestamp
            && Cache::lock('google-login:lock:'.$nonce, 10)->get(
                fn (): bool => Cache::pull('google-login:nonce:'.$nonce) === true
            );

        if (! $accepted) {
            $this->invalid();
        }

        try {
            $identity = $verifier->verify($validated['credential'], $nonce);
        } catch (ConnectionException|RequestException) {
            $this->invalid('Google временно недоступен. Попробуйте позже.');
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException) {
            $this->invalid();
        }

        $user = DB::transaction(function () use ($identity): User {
            $user = User::query()->where('google_sub', $identity['sub'])->lockForUpdate()->first();

            if (! $user) {
                $authoritative = str_ends_with($identity['email'], '@gmail.com')
                    || filled($identity['hd'] ?? null);

                if (! $authoritative) {
                    $this->invalid('Этот Google-адрес нельзя привязать автоматически. Войдите по паролю.');
                }

                $matches = User::query()
                    ->whereRaw('LOWER(email) = ?', [$identity['email']])
                    ->lockForUpdate()
                    ->get();

                if ($matches->count() !== 1) {
                    $this->invalid('Аккаунт с этим Google-адресом не найден. Сначала зарегистрируйтесь.');
                }

                $user = $matches->first();

                if ($user->google_sub !== null) {
                    $this->invalid();
                }

                $user->google_sub = $identity['sub'];
                $user->email_verified_at ??= now();
                $user->save();
            }

            if (mb_strtolower($user->email) !== $identity['email']) {
                $this->invalid('Адрес аккаунта изменился. Войдите по паролю.');
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function invalid(string $message = 'Не удалось войти через Google. Попробуйте ещё раз.'): never
    {
        throw ValidationException::withMessages(['google' => $message]);
    }
}
