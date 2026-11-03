<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\WorkshopEvent;
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
            'transaction_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{12,22}$/'],
            'workshop_event_id' => [
                'required',
                'integer',
                Rule::exists('workshop_events', 'id')->where('status', 'active'),
            ],
            'consent' => ['required', 'boolean'],
            'payment_screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ], [
            'transaction_id.regex' => 'Transaction ID / UTR Number must be 12 to 22 letters or numbers, with no spaces or special characters.',
        ]);

        $path = null;
        $event = WorkshopEvent::query()
            ->where('status', 'active')
            ->findOrFail($data['workshop_event_id']);
        try {
            $registration = DB::transaction(function () use ($request, $data, $event, &$path) {
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
                    'workshop_event_id' => $event->id,
                    'registration_fee' => $event->registration_fee,
                    'registration_currency' => $event->currency,
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

        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:15'],
            'city' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['Pending', 'Verified', 'Rejected'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WorkshopRegistration::query()->with('workshopEvent:id,title,workshop_code');
        if (! empty($filters['name'])) {
            $query->where('full_name', 'LIKE', '%' . $filters['name'] . '%');
        }
        if (! empty($filters['mobile'])) {
            $query->where('mobile_number', 'LIKE', '%' . $filters['mobile'] . '%');
        }
        if (! empty($filters['city'])) {
            $query->where('city', 'LIKE', '%' . $filters['city'] . '%');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $registrations = $query->latest()->paginate($filters['per_page'] ?? 20);
        $stats = WorkshopRegistration::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(status = 'Pending') as pending")
            ->selectRaw("SUM(status = 'Verified') as verified")
            ->selectRaw("SUM(status = 'Rejected') as rejected")
            ->first();

        return response()->json([
            'success' => true,
            'data' => $registrations,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, $id)
    {
        if (! $request->user() || (! $request->user()->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Administrator access is required.',
            ], 403);
        }

        $registration = WorkshopRegistration::with('workshopEvent:id,title,workshop_code')->findOrFail($id);
        $registration->payment_screenshot_url = Storage::disk('public')->url($registration->payment_screenshot);

        return response()->json(['success' => true, 'data' => $registration]);
    }

    public function updateStatus(Request $request, $id)
    {
        if (! $request->user() || (! $request->user()->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Administrator access is required.',
            ], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['Verified', 'Rejected'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $registration = WorkshopRegistration::findOrFail($id);
        try {
            $registration->status = $data['status'];
            $registration->remarks = $data['status'] === 'Rejected'
                ? ($data['remarks'] ?? null)
                : null;
            $registration->save();

            return response()->json([
                'success' => true,
                'message' => 'Workshop registration updated successfully.',
                'data' => $registration,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update this workshop registration right now.',
            ], 500);
        }
    }
}
