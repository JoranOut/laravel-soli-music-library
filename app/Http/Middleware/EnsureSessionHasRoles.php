<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\AuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detect stale sessions where the user is authenticated (via remember token)
 * but the OAuth session data (roles, assignments) has been lost.
 * Restores the session from the OIDC data persisted on the user model.
 */
class EnsureSessionHasRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && empty(session('roles'))) {
            AuthController::populateSession($request->user());
        }

        return $next($request);
    }
}
