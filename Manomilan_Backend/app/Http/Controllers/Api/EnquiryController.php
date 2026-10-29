<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $enquiries = Enquiry::latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $enquiries]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required',
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $enquiry = Enquiry::create($request->all());
        return response()->json(['success' => true, 'message' => 'Enquiry submitted successfully', 'data' => $enquiry], 201);
    }

    public function show($id)
    {
        $enquiry = Enquiry::find($id);
        if (!$enquiry) {
            return response()->json(['success' => false, 'message' => 'Enquiry not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $enquiry]);
    }

    public function update(Request $request, $id)
    {
        $enquiry = Enquiry::find($id);
        if (!$enquiry) {
            return response()->json(['success' => false, 'message' => 'Enquiry not found'], 404);
        }
        $enquiry->update($request->all());
        return response()->json(['success' => true, 'message' => 'Enquiry updated successfully', 'data' => $enquiry]);
    }

    public function destroy($id)
    {
        $enquiry = Enquiry::find($id);
        if (!$enquiry) {
            return response()->json(['success' => false, 'message' => 'Enquiry not found'], 404);
        }
        $enquiry->delete();
        return response()->json(['success' => true, 'message' => 'Enquiry deleted successfully']);
    }
}

