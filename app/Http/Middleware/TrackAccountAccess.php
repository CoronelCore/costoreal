<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an account the owner suspended, even with an open session or a "remember me" cookie, and records when
 * each account last used the installation. The time is written at most every few minutes to keep requests cheap.
 */
class TrackAccountAccess
{
    public const SUSPENDED = 'Tu acceso a esta instalación de CostoReal está suspendido. Habla con quien te invitó.';

    private const REFRESH_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort_if($request->expectsJson(), 403, self::SUSPENDED);

            return to_route('login')->with('status', self::SUSPENDED);
        }
        if ($user && ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinutes(self::REFRESH_MINUTES)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
