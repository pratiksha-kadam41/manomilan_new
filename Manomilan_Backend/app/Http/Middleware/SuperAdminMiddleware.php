<?php

namespace App\Http\Middleware;

use Closure;
use Auth;

class SuperAdminMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Unauthorized Access');
        }

        return $next($request);
    }
}