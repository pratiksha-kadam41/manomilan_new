<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MelavaRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $registrations = MelavaRegistration::latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $registrations]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required',
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $registration = MelavaRegistration::create($request->all());
        return response()->json(['success' => true, 'message' => 'Registration successful', 'data' => $registration], 201);
    }

    public function show($id)
    {
        $registration = MelavaRegistration::find($id);
        if (!$registration) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $registration]);
    }
}

