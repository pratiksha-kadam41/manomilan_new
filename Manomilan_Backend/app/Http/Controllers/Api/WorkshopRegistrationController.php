<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\WorkshopRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorkshopRegistrationController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $registration = WorkshopRegistration::create($request->all());
        return response()->json(['success' => true, 'message' => 'Workshop registration successful', 'data' => $registration], 201);
    }

    public function index(Request $request)
    {
        $registrations = WorkshopRegistration::latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $registrations]);
    }
}

