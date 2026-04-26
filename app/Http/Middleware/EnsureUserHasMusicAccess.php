<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasMusicAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = session('roles', []);

        if (empty($roles)) {
            abort(403, __('You do not have permission to access this application.'));
        }

        $editorRoles = ['admin', 'muziekbeheer'];
        if (array_intersect($editorRoles, $roles)) {
            return $next($request);
        }

        if (in_array('dirigent', $roles)) {
            return $next($request);
        }

        $resolved = session('resolved_assignments', []);
        if (! empty($resolved)) {
            return $next($request);
        }

        abort(403);
    }
}
