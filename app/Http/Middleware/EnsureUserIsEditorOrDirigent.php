<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEditorOrDirigent
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = session('roles', []);

        if (empty($roles)) {
            abort(403, __('You do not have permission to access this application.'));
        }

        if (! array_intersect(['admin', 'muziekbeheer', 'dirigent'], $roles)) {
            abort(403);
        }

        return $next($request);
    }
}
