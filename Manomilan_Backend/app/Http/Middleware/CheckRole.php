<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->role) {
            // Optionally redirect instead of aborting
            return abort(403, 'Access denied: No role assigned to your account.');
        }

        if (in_array($user->role->name, $roles)) {
            return $next($request);
        }

        abort(403, 'Access denied: You do not have the required role.');
    }
}
