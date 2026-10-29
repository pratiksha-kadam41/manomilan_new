<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\UserInterest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InterestController extends Controller
{
    public function sent(Request $request)
    {
        $user = $request->user();
        $profileId = $user->profile_id;
        $interests = UserInterest::where('from_profile_id', $profileId)->with('profile')->latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $interests]);
    }

    public function received(Request $request)
    {
        $user = $request->user();
        $profileId = $user->profile_id;
        $interests = UserInterest::where('profile_id', $profileId)->with('fromProfile')->latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $interests]);
    }

    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_profile_id' => 'required|integer|exists:profiles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $interest = UserInterest::firstOrCreate([
            'from_profile_id' => $user->profile_id,
            'profile_id' => $request->to_profile_id,
        ], [
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Interest sent successfully', 'data' => $interest], 201);
    }

    public function respond(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $interest = UserInterest::find($id);
        if (!$interest) {
            return response()->json(['success' => false, 'message' => 'Interest not found'], 404);
        }

        $user = $request->user();
        if ($interest->profile_id != $user->profile_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $interest->status = $request->status;
        $interest->save();

        return response()->json(['success' => true, 'message' => 'Interest response updated', 'data' => $interest]);
    }
}

