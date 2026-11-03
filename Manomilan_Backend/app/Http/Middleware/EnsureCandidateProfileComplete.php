<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCandidateProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $profile = $user?->profile_id
            ? $user->profile()->first()
            : null;

        if (! $profile || (int) $profile->profile_completion < 100) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Complete your profile before accessing this section.',
                    'profile_incomplete' => true,
                ], 403);
            }

            return redirect('/candidate/profile');
        }

        return $next($request);
    }
}