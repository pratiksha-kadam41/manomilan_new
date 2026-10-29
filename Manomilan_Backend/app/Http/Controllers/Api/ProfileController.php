<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $query = Profile::where('active', 'active');
        if ($request->filled('groomOrBride')) {
            $query->where('groomOrBride', $request->groomOrBride);
        }
        if ($request->filled('religion')) {
            $query->where('religion', $request->religion);
        }
        if ($request->filled('caste')) {
            $query->where('caste', $request->caste);
        }
        $profiles = $query->with('user')->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $profiles]);
    }

    public function show($id)
    {
        $profile = Profile::find($id);
        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $profile]);
    }

    public function myProfile(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->profile_id) {
            return response()->json(['success' => true, 'data' => null]);
        }
        $profile = Profile::find($user->profile_id);
        return response()->json(['success' => true, 'data' => $profile]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        return response()->json(['success' => true, 'message' => 'Profile creation endpoint']);
    }
}

