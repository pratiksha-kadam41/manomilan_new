<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreferenceController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $preference = Preference::where('user_id', $user->id)->first();
        return response()->json(['success' => true, 'data' => $preference]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'age_from' => 'nullable|integer',
            'age_to' => 'nullable|integer',
            'religion' => 'nullable|string',
            'caste' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $preference = Preference::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['age_from', 'age_to', 'religion', 'caste', 'manglik', 'marital_status', 'country', 'state', 'city'])
        );

        return response()->json(['success' => true, 'message' => 'Preferences saved successfully', 'data' => $preference]);
    }
}

