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

        // role_id = 1 => Super Admin
        if (Auth::user()->role_id != 1) {
            abort(403, 'Unauthorized Access');
        }

        return $next($request);
    }
}