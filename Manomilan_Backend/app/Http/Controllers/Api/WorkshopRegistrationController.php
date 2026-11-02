<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\WorkshopRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class WorkshopRegistrationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'digits:10'],
            'whatsapp_number' => ['required', 'digits:10'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'education' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'marital_status' => ['required', Rule::in(['Unmarried', 'Divorced', 'Widow', 'Widower'])],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_contact_number' => ['required', 'digits:10'],
            'transaction_id' => ['required', 'string', 'max:255'],
            'consent' => ['required', 'boolean'],
            'payment_screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $path = null;
        try {
            $registration = DB::transaction(function () use ($request, $data, &$path) {
                $path = $request->file('payment_screenshot')->store('workshop_payments', 'public');
                if (! $path) {
                    throw new RuntimeException('Unable to store the workshop payment screenshot.');
                }

                return WorkshopRegistration::create([
                    'full_name' => $data['full_name'],
                    'mobile_number' => $data['mobile_number'],
                    'whatsapp_number' => $data['whatsapp_number'],
                    'date_of_birth' => $data['date_of_birth'],
                    'education' => $data['education'],
                    'city' => $data['city'],
                    'district' => $data['district'],
                    'marital_status' => $data['marital_status'],
                    'parent_name' => $data['parent_name'],
                    'parent_contact_number' => $data['parent_contact_number'],
                    'payment_screenshot' => $path,
                    'transaction_id' => $data['transaction_id'],
                    'consent' => (bool) $data['consent'],
                    'status' => 'Pending',
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Workshop registration successful.',
                'data' => $registration,
            ], 201);
        } catch (Throwable $e) {
            try {
                if ($path && ! Storage::disk('public')->delete($path)) {
                    report(new RuntimeException('Unable to remove the workshop payment upload after registration failed.'));
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete the workshop registration right now.',
            ], 500);
        }
    }

    public function index(Request $request)
    {
        if (! $request->user() || (! $request->user()->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Administrator access is required.',
            ], 403);
        }

        $registrations = WorkshopRegistration::latest()->paginate((int) $request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $registrations]);
    }
}
