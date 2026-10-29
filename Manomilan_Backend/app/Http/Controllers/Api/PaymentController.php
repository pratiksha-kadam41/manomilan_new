<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PaymentScreenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class PaymentController extends Controller
{
    public function uploadScreenshot(Request $request)
    {
        $data = $request->validate([
            'screenshot' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $user = $request->user();

        if (! $user->profile_id) {
            return response()->json([
                'success' => false,
                'message' => 'Link a profile before uploading a payment screenshot.',
            ], 422);
        }

        try {
            $payment = DB::transaction(function () use ($request, $user) {
                $path = $request->file('screenshot')->store('payment_screenshots', 'public');

                return PaymentScreenshot::create([
                    'profile_id' => $user->profile_id,
                    'screenshot_path' => $path,
                    'payment_verified' => false,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment screenshot uploaded successfully.',
                'data' => $this->presentPayment($payment),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload the payment screenshot right now.',
            ], 500);
        }
    }

    public function myPayments(Request $request)
    {
        $user = $request->user();

        $payments = PaymentScreenshot::where('profile_id', $user->profile_id)
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        $payments->getCollection()->transform(fn (PaymentScreenshot $payment) => $this->presentPayment($payment));

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $payments = PaymentScreenshot::with('profile')
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        $payments->getCollection()->transform(fn (PaymentScreenshot $payment) => $this->presentPayment($payment));

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function updateStatus(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $payment = PaymentScreenshot::find($id);

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        }

        try {
            DB::transaction(function () use ($payment, $data) {
                $payment->payment_verified = $data['status'] === 'approved';
                $payment->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully.',
                'data' => $this->presentPayment($payment->fresh('profile')),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the payment status right now.',
            ], 500);
        }
    }

    private function presentPayment(PaymentScreenshot $payment): array
    {
        return [
            'id' => $payment->id,
            'profile_id' => $payment->profile_id,
            'amount' => null,
            'status' => $payment->payment_verified ? 'approved' : 'pending',
            'payment_verified' => (bool) $payment->payment_verified,
            'screenshot_url' => $payment->screenshot_path
                ? Storage::disk('public')->url($payment->screenshot_path)
                : '',
            'created_at' => optional($payment->created_at)->toIso8601String(),
        ];
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
