<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class ClientMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        if (Auth::check() && Auth::user()->role == '21') {
            return $next($request);
        }

        abort(403, 'Unauthorized access.');
    }
}
