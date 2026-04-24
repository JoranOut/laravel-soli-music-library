<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEditor
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = session('roles', []);

        if (empty($roles)) {
            return redirect()->route('login');
        }

        if (! array_intersect(['admin', 'muziekbeheer'], $roles)) {
            abort(403);
        }

        return $next($request);
    }
}
