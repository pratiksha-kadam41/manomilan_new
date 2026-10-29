<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $totalUsers = User::count();
        $totalProfiles = DB::table('profiles')
            ->where('active', 'active')
            ->count();
        $verifiedProfiles = DB::table('profiles')
            ->where('active', 'active')
            ->where('profileVerificationStatus', 'Completed')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_profiles' => $totalProfiles,
                'verified_profiles' => $verifiedProfiles,
            ],
        ]);
    }

    public function homeData(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $profile = null;
        if ($user->profile_id) {
            $profile = DB::table('profiles')
                ->where('id', $user->profile_id)
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'profile' => $profile,
            ],
        ]);
    }
}
