<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectBlockedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->fresh()?->is_blocked) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Ваш аккаунт заблокирован. Обратитесь к администратору.');
        }

        return $next($request);
    }
}
