<?php

namespace App\Http\Middleware;

use Closure;
use Auth;

class CandidateMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        // role_id = 3 => Candidate
        if (Auth::user()->role_id != 3) {
            abort(403, 'Unauthorized Access');
        }

        return $next($request);
    }
}