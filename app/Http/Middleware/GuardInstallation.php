<?php

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global, so it runs before any sign-in check: sends a fresh copy to the browser installer and an updated
 * copy to the update page, so nobody works against missing tables. Sign-in stays reachable during an update
 * so the owner can apply it.
 */
class GuardInstallation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('costoreal.installer') || $request->is('up')) {
            return $next($request);
        }
        $installerPage = $request->is('instalar');
        $updatePage = $request->is('instalar/actualizar');
        if (! Installation::installed()) {
            return $installerPage ? $next($request) : redirect('/instalar');
        }
        if ($installerPage) {
            return redirect('/login');
        }
        $pending = Installation::pendingMigrations() !== [];
        if ($updatePage) {
            return $pending ? $next($request) : redirect('/login');
        }
        if ($pending && ! $request->is('login', 'logout')) {
            return redirect('/instalar/actualizar');
        }

        return $next($request);
    }
}
