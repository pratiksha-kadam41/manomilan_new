<?php

namespace App\Http\Middleware;

use Closure;

use Illuminate\Support\Facades\Auth;

class ForcePasswordChange
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
        if (Auth::check()) {
            $user = Auth::user();

            // Bypass for Melava 3 candidates (MM3- prefix and october_2026 batch)
            if ($user->profile_id) {
                $profile = \App\Profile::find($user->profile_id);
                if ($profile && $profile->isMelava3Candidate()) {
                    return $next($request);
                }
            }

            if (!$user->is_password_changed) {
                if (!$request->is('password/change') && !$request->is('force-logout') && !$request->is('logout')) {
                    return redirect()->route('password.change.form');
                }
            } else {
                if ($user->user_type == '21' && $user->profile_id) {
                    $profile = \App\Profile::find($user->profile_id);
                    if ($profile && !$profile->hasCompletedMandatoryFields()) {
                        if (!$request->is('profiles/edit-details/*') && 
                            !$request->is('profiles/update-details/*') && 
                            !$request->is('profiles/delete-document/*') &&
                            !$request->is('force-logout') && 
                            !$request->is('logout')) {
                            
                            return redirect()->route('profiles.editDetails', $profile->id)
                                ->with('info', 'Please complete all mandatory fields in your profile to proceed to search your life partner');
                        }
                    }
                }
            }
        }
        return $next($request);
    }
}
