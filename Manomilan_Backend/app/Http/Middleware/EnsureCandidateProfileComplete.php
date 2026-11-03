<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCandidateProfileComplete
{
    public function handle(Request $request, Closure $next, int $minimumCompletion = 100): Response
    {
        $user = $request->user();
        $profile = $user?->profile_id
            ? $user->profile()->first()
            : null;

        if (! $profile || (int) $profile->profile_completion < $minimumCompletion) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $minimumCompletion < 100
                        ? "Complete your profile to at least {$minimumCompletion}% before accessing this section."
                        : 'Complete your profile before accessing this section.',
                    'profile_incomplete' => true,
                    'minimum_completion' => $minimumCompletion,
                ], 403);
            }

            return redirect('/candidate/profile');
        }

        return $next($request);
    }
}