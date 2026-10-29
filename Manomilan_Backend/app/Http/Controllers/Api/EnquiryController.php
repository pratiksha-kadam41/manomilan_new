<?php

namespace App\Http\Controllers\Api;

use App\Enquiry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $enquiries = Enquiry::latest()->paginate((int) $request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $enquiries]);
    }

    public function store(Request $request)
    {
        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', $request->input('mobileNo')));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobileNo' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'taluka' => ['nullable', 'string', 'max:255'],
            'dist' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000', 'required_without:extraInformation'],
            'extraInformation' => ['nullable', 'string', 'max:255', 'required_without:message'],
        ]);

        try {
            $enquiry = DB::transaction(function () use ($data, $phone) {
                return Enquiry::create([
                    'melavaNo' => 'N/A',
                    'name' => $data['name'],
                    'address' => $data['address'] ?? '',
                    'city' => $data['city'] ?? '',
                    'taluka' => $data['taluka'] ?? '',
                    'dist' => $data['dist'] ?? '',
                    'contactPersonName1' => $data['name'],
                    'contactPersonMobile1' => $phone,
                    'email' => $data['email'] ?? null,
                    'mobileNo' => $phone,
                    'extraInformation' => $data['extraInformation'] ?? $data['message'] ?? null,
                    'status' => 'pending',
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Enquiry submitted successfully.',
                'data' => $enquiry,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to submit the enquiry right now.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $enquiry = Enquiry::find($id);

        if (! $enquiry) {
            return response()->json(['success' => false, 'message' => 'Enquiry not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $enquiry]);
    }

    public function update(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $enquiry = Enquiry::find($id);

        if (! $enquiry) {
            return response()->json(['success' => false, 'message' => 'Enquiry not found.'], 404);
        }

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mobileNo' => ['sometimes', 'nullable', 'string', 'max:20'],
            'extraInformation' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($enquiry, $data) {
                $enquiry->update($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Enquiry updated successfully.',
                'data' => $enquiry->fresh(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the enquiry right now.',
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $enquiry = Enquiry::find($id);

        if (! $enquiry) {
            return response()->json(['success' => false, 'message' => 'Enquiry not found.'], 404);
        }

        try {
            DB::transaction(fn () => $enquiry->delete());

            return response()->json(['success' => true, 'message' => 'Enquiry deleted successfully.']);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete the enquiry right now.',
            ], 500);
        }
    }

    private function isAdmin($user): bool
    {
        return $user && ((int) $user->role_id === 1 || (string) $user->user_type === '00');
    }

    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden. Administrator access is required.',
        ], 403);
    }
}
