<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaRegistration;
use App\PaymentScreenshot;
use App\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class PaymentController extends Controller
{
    public function forProfile(Request $request, int $profileId)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        try {
            $profile = Profile::with('user')->find($profileId);

            if (! $profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile not found.',
                ], 404);
            }

            $screenshots = PaymentScreenshot::where('profile_id', $profile->id)
                ->latest()
                ->get()
                ->map(fn (PaymentScreenshot $payment) => [
                    'id' => $payment->id,
                    'type' => 'payment',
                    'status' => $payment->payment_verified ? 'verified' : 'pending',
                    'image_url' => $this->screenshotUrl($request, $payment->screenshot_path),
                ]);

            $melavaScreenshots = MelavaRegistration::where('profile_id', $profile->id)
                ->whereNotNull('payment_screenshot')
                ->where('payment_screenshot', '<>', '')
                ->latest()
                ->get()
                ->map(fn (MelavaRegistration $registration) => [
                    'id' => $registration->id,
                    'type' => 'melava',
                    'status' => $registration->payment_verified ? 'verified' : 'pending',
                    'image_url' => $this->screenshotUrl($request, $registration->payment_screenshot),
                ]);

            return response()->json([
                'success' => true,
                'profile' => [
                    'id' => $profile->id,
                    'full_name' => $profile->fullName,
                    'registration_number' => $profile->profile_id ?: $profile->serial_no,
                    'mobile' => $profile->user?->username ?: $profile->phone,
                ],
                'screenshots' => $screenshots
                    ->concat($melavaScreenshots)
                    ->sortByDesc('id')
                    ->values(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load payment screenshots right now.',
            ], 500);
        }
    }

    public function verifyForProfile(Request $request, int $profileId, int $paymentId)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['payment', 'melava'])],
        ]);

        try {
            if (! Profile::whereKey($profileId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile not found.',
                ], 404);
            }

            $payment = $data['type'] === 'melava'
                ? MelavaRegistration::where('profile_id', $profileId)->find($paymentId)
                : PaymentScreenshot::where('profile_id', $profileId)->find($paymentId);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment screenshot not found.',
                ], 404);
            }

            DB::transaction(function () use ($data, $payment, $profileId) {
                $payment->payment_verified = true;
                $payment->save();

                if ($data['type'] === 'melava') {
                    PaymentScreenshot::where('profile_id', $profileId)
                        ->where('screenshot_path', $payment->payment_screenshot)
                        ->update(['payment_verified' => true]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify this payment right now.',
            ], 500);
        }
    }

    public function uploadForProfile(Request $request, int $profileId)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'payment_screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $profile = Profile::find($profileId);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], 404);
        }

        $path = null;

        try {
            $path = $data['payment_screenshot']->store('payment_screenshots', 'public');

            if (! $path) {
                throw new \RuntimeException('The payment screenshot could not be stored.');
            }

            $payment = DB::transaction(fn () => PaymentScreenshot::create([
                'profile_id' => $profile->id,
                'screenshot_path' => $path,
                'payment_verified' => false,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Payment screenshot uploaded successfully.',
                'data' => [
                    'id' => $payment->id,
                    'type' => 'payment',
                    'status' => 'pending',
                    'image_url' => $this->screenshotUrl($request, $payment->screenshot_path),
                ],
            ], 201);
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload the payment screenshot right now.',
            ], 500);
        }
    }

    private function screenshotUrl(Request $request, ?string $path): string
    {
        if (! $path) {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if (str_starts_with(ltrim($path, '/'), 'uploads/')) {
            return $request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
        }

        return Storage::disk('public')->url($path);
    }

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
