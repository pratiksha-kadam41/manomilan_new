<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class ProfileAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        // Super Admin gets access to any profile
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Candidates can only access their own profile
        $profileParam = $request->route('profile');
        $idParam = $request->route('id');

        $targetProfileId = null;

        if ($profileParam) {
            $targetProfileId = is_object($profileParam) ? $profileParam->id : $profileParam;
        } elseif ($idParam) {
            $targetProfileId = is_object($idParam) ? $idParam->id : $idParam;
        }

        if ($targetProfileId && $user->profile_id == $targetProfileId) {
            return $next($request);
        }

        abort(403, 'Unauthorized Access');
    }
}
