<?php

namespace App\Http\Controllers\Api;

use App\Profile;
use App\ProfileVisitor;
use App\UserInterest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class HomeController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        try {
            if ($user->isAdmin()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'role' => 'admin',
                        'total_users' => User::count(),
                        'total_profiles' => Profile::where('active', 'active')->count(),
                        'verified_profiles' => Profile::where('active', 'active')
                            ->where('profileVerificationStatus', 'Completed')
                            ->count(),
                    ],
                ]);
            }

            $profile = $user->profile_id ? Profile::find($user->profile_id) : null;
            $profileId = $profile?->id;

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => 'candidate',
                    'profile_views' => $profileId ? ProfileVisitor::where('profile_id', $profileId)->count() : 0,
                    'interests_received' => $profileId ? UserInterest::where('profile_id', $profileId)->count() : 0,
                    'interests_sent' => UserInterest::where('user_id', $user->id)->count(),
                    'profile_completion' => (int) ($profile->profile_completion ?? 0),
                    'profile_id' => $profileId,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load dashboard data right now.',
            ], 500);
        }
    }

    public function homeData(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        try {
            $profile = $user->profile_id
                ? Profile::with('user')->find($user->profile_id)
                : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'role_id' => $user->role_id,
                        'user_type' => $user->user_type,
                        'status' => $user->status,
                    ],
                    'profile' => $profile ? [
                        'id' => $profile->id,
                        'registration_number' => $profile->registration_number ?: $profile->profile_id,
                        'profile_id' => $profile->profile_id,
                        'full_name' => $profile->fullName,
                        'gender' => $profile->groomOrBride,
                        'photo_url' => $profile->profilePhoto ? $this->profilePhotoUrl($profile->profilePhoto) : '',
                        'profile_completion' => (int) $profile->profile_completion,
                        'verification_status' => $profile->profileVerificationStatus,
                        'birth_date' => $profile->birthDate,
                        'city' => $profile->location ?: $profile->professionalCity,
                        'education' => $profile->education,
                        'profession' => $profile->profession,
                        'has_family' => filled($profile->fathersName) && filled($profile->mothersName),
                        'has_education' => filled($profile->education) && filled($profile->profession),
                        'has_photos' => filled($profile->profilePhoto),
                        'has_preferences' => $user->preference()->exists(),
                    ] : null,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load profile data right now.',
            ], 500);
        }
    }

    private function profilePhotoUrl(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        if (str_starts_with($path, 'candidate/')) {
            return url('storage/'.$path);
        }

        return url($path);
    }
}
