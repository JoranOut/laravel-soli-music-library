<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = session('roles', []);

        if (empty($roles)) {
            abort(403, __('You do not have permission to access this application.'));
        }

        if (! in_array('admin', $roles)) {
            abort(403);
        }

        return $next($request);
    }
}
