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

        if (Auth::user()->portal() !== 'candidate') {
            abort(403, 'Unauthorized Access');
        }

        return $next($request);
    }
}