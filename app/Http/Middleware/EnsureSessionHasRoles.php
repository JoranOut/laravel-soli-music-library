<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detect stale sessions where the user is authenticated (via remember token)
 * but the OAuth session data (roles, assignments) has been lost.
 * Forces a re-login through the OAuth flow to repopulate the session.
 */
class EnsureSessionHasRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && empty(session('roles'))) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
