<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PaymentScreenshot;
use App\Profile;
use App\ProfileMelavaRegistration;
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

            $payments = PaymentScreenshot::where('profile_id', $profile->id)
                ->latest()
                ->get();

            $melavaRegistrations = ProfileMelavaRegistration::where('profile_id', $profile->id)
                ->where(function ($query) {
                    $query->where(function ($paymentQuery) {
                        $paymentQuery->whereNotNull('payment_screenshot')
                            ->where('payment_screenshot', '<>', '');
                    })->orWhere(function ($bookletQuery) {
                        $bookletQuery->whereNotNull('booklet_payment_screenshot')
                            ->where('booklet_payment_screenshot', '<>', '');
                    });
                })
                ->latest()
                ->get();

            $matchedMelavaPaths = [];
            $screenshots = $payments->map(function (PaymentScreenshot $payment) use (
                $request,
                $melavaRegistrations,
                &$matchedMelavaPaths
            ) {
                $details = $payment->payment_details ?? [];
                $melavaRegistration = $melavaRegistrations->first(
                    fn (ProfileMelavaRegistration $registration) => $registration->payment_screenshot === $payment->screenshot_path
                        || $registration->booklet_payment_screenshot === $payment->screenshot_path
                );

                if (! $melavaRegistration) {
                    return [
                        'id' => $payment->id,
                        'updated_by_user_id' => $payment->updated_by_user_id,
                        'type' => 'payment',
                        'status' => $payment->payment_verified ? 'verified' : 'pending',
                        'image_url' => $this->screenshotUrl($request, $payment->screenshot_path),
                        'payment_details' => $details,
                        'created_at' => optional($payment->created_at)->toIso8601String(),
                    ];
                }

                $matchedMelavaPaths[] = $payment->screenshot_path;
                $paymentStatus = $payment->payment_verified ? 'verified' : 'pending';
                $matchesBooklet = $melavaRegistration->booklet_payment_screenshot === $payment->screenshot_path;
                $melavaVerified = $matchesBooklet
                    ? $melavaRegistration->booklet_payment_verified
                    : $melavaRegistration->payment_verified;
                if ($matchesBooklet && $melavaRegistration->payment_screenshot === $payment->screenshot_path) {
                    $melavaVerified = $melavaVerified && $melavaRegistration->payment_verified;
                }
                $melavaStatus = $melavaVerified ? 'verified' : 'pending';
                $combinedVerified = $paymentStatus === 'verified' && $melavaStatus === 'verified';
                $details['verification_status'] = $combinedVerified ? 'verified' : 'pending';

                return [
                    'id' => $payment->id,
                    'updated_by_user_id' => $payment->updated_by_user_id
                        ?: $melavaRegistration->updated_by_user_id,
                    'type' => 'combined',
                    'melava_id' => $melavaRegistration->id,
                    'status' => $combinedVerified ? 'verified' : 'pending',
                    'payment_status' => $paymentStatus,
                    'melava_status' => $melavaStatus,
                    'category' => $matchesBooklet ? 'booklet' : 'melava',
                    'image_url' => $this->screenshotUrl($request, $payment->screenshot_path),
                    'payment_details' => $details,
                    'created_at' => optional($payment->created_at)->toIso8601String(),
                ];
            });

            $melavaScreenshots = $melavaRegistrations
                ->filter(fn (ProfileMelavaRegistration $registration) => $registration->payment_screenshot
                    && ! in_array($registration->payment_screenshot, $matchedMelavaPaths, true))
                ->map(fn (ProfileMelavaRegistration $registration) => [
                    'id' => $registration->id,
                    'updated_by_user_id' => $registration->updated_by_user_id,
                    'type' => 'melava',
                    'status' => $registration->payment_verified ? 'verified' : 'pending',
                    'image_url' => $this->screenshotUrl($request, $registration->payment_screenshot),
                    'payment_details' => [
                        'category' => 'melava',
                        'amount' => null,
                        'payment_date' => optional($registration->created_at)->toDateString(),
                        'payment_method' => null,
                        'reference_number' => null,
                        'remarks' => null,
                        'verification_status' => $registration->payment_verified ? 'verified' : 'pending',
                        'proof_file' => $registration->payment_screenshot,
                    ],
                    'created_at' => optional($registration->created_at)->toIso8601String(),
                ]);

            $bookletScreenshots = $melavaRegistrations
                ->filter(fn (ProfileMelavaRegistration $registration) => $registration->booklet_payment_screenshot
                    && ! in_array($registration->booklet_payment_screenshot, $matchedMelavaPaths, true))
                ->map(fn (ProfileMelavaRegistration $registration) => [
                    'id' => $registration->id,
                    'updated_by_user_id' => $registration->updated_by_user_id,
                    'type' => 'booklet',
                    'status' => $registration->booklet_payment_verified ? 'verified' : 'pending',
                    'image_url' => $this->screenshotUrl($request, $registration->booklet_payment_screenshot),
                    'payment_details' => [
                        'category' => 'booklet',
                        'amount' => null,
                        'payment_date' => optional($registration->created_at)->toDateString(),
                        'payment_method' => null,
                        'reference_number' => null,
                        'remarks' => null,
                        'verification_status' => $registration->booklet_payment_verified ? 'verified' : 'pending',
                        'proof_file' => $registration->booklet_payment_screenshot,
                    ],
                    'created_at' => optional($registration->created_at)->toIso8601String(),
                ]);

            $screenshots = $screenshots
                ->concat($melavaScreenshots)
                ->concat($bookletScreenshots)
                ->sortByDesc('created_at')
                ->values();

            return response()->json([
                'success' => true,
                'profile' => [
                    'id' => $profile->id,
                    'full_name' => $profile->full_name
                        ?: $profile->getRawOriginal('fullName')
                        ?: $profile->user?->name
                        ?: 'Unnamed profile',
                    'registration_number' => $profile->reg_id ?: ($profile->profile_id ?: $profile->serial_no),
                    'mobile' => $profile->user?->username ?: $profile->phone,
                ],
                'screenshots' => $screenshots,
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
            'type' => ['required', Rule::in(['payment', 'melava', 'booklet', 'combined'])],
            'melava_id' => ['required_if:type,combined', 'integer', 'min:1'],
        ]);

        try {
            if (! Profile::whereKey($profileId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile not found.',
                ], 404);
            }

            $payment = in_array($data['type'], ['melava', 'booklet'], true)
                ? ProfileMelavaRegistration::where('profile_id', $profileId)->find($paymentId)
                : PaymentScreenshot::where('profile_id', $profileId)->find($paymentId);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment screenshot not found.',
                ], 404);
            }

            $combinedRegistration = null;
            if ($data['type'] === 'combined') {
                $combinedRegistration = ProfileMelavaRegistration::where('profile_id', $profileId)
                    ->where(function ($query) use ($payment) {
                        $query->where('payment_screenshot', $payment->screenshot_path)
                            ->orWhere('booklet_payment_screenshot', $payment->screenshot_path);
                    })
                    ->find($data['melava_id']);

                if (! $combinedRegistration) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Matching Melava payment screenshot not found.',
                    ], 404);
                }
            }

            DB::transaction(function () use ($data, $payment, $profileId, $combinedRegistration) {
                if ($payment instanceof PaymentScreenshot) {
                    $payment->payment_verified = true;
                    $details = $payment->payment_details ?? [];
                    $details['verification_status'] = 'verified';
                    $payment->payment_details = $details;
                    $payment->save();
                }

                if ($data['type'] === 'combined') {
                    if ($combinedRegistration->payment_screenshot === $payment->screenshot_path) {
                        $combinedRegistration->payment_verified = true;
                    }
                    if ($combinedRegistration->booklet_payment_screenshot === $payment->screenshot_path) {
                        $combinedRegistration->booklet_payment_verified = true;
                    }
                    $combinedRegistration->save();
                } elseif (in_array($data['type'], ['melava', 'booklet'], true)) {
                    $column = $data['type'] === 'booklet' ? 'booklet_payment_verified' : 'payment_verified';
                    $path = $data['type'] === 'booklet'
                        ? $payment->booklet_payment_screenshot
                        : $payment->payment_screenshot;
                    $payment->{$column} = true;
                    $payment->save();
                    PaymentScreenshot::where('profile_id', $profileId)
                        ->where('screenshot_path', $path)
                        ->get()
                        ->each(function (PaymentScreenshot $screenshot): void {
                            $screenshot->payment_verified = true;
                            $details = $screenshot->payment_details ?? [];
                            $details['verification_status'] = 'verified';
                            $screenshot->payment_details = $details;
                            $screenshot->save();
                        });
                }
            });

            Profile::find($profileId)?->syncDocumentVerificationStatus();

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
            'payment_screenshot' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'payment_details' => ['nullable', 'array'],
            'payment_details.category' => ['nullable', 'string', 'max:80'],
            'payment_details.amount' => ['nullable', 'numeric', 'min:0'],
            'payment_details.payment_date' => ['nullable', 'date'],
            'payment_details.payment_method' => ['nullable', 'string', 'max:80'],
            'payment_details.reference_number' => ['nullable', 'string', 'max:120'],
            'payment_details.remarks' => ['nullable', 'string', 'max:2000'],
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

            $payment = DB::transaction(function () use ($data, $profile, $path) {
                $details = $data['payment_details'] ?? [];
                $details = [
                    'category' => $details['category'] ?? 'profile',
                    'amount' => isset($details['amount']) && $details['amount'] !== ''
                        ? (float) $details['amount']
                        : null,
                    'payment_date' => $details['payment_date'] ?? null,
                    'payment_method' => $details['payment_method'] ?? null,
                    'reference_number' => $details['reference_number'] ?? null,
                    'remarks' => $details['remarks'] ?? null,
                    'verification_status' => 'pending',
                    'proof_file' => $path,
                ];

                return PaymentScreenshot::create([
                    'profile_id' => $profile->id,
                    'screenshot_path' => $path,
                    'payment_verified' => false,
                    'payment_details' => $details,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment screenshot uploaded successfully.',
                'data' => [
                    'id' => $payment->id,
                    'type' => 'payment',
                    'status' => 'pending',
                    'image_url' => $this->screenshotUrl($request, $payment->screenshot_path),
                    'payment_details' => $payment->payment_details,
                    'created_at' => optional($payment->created_at)->toIso8601String(),
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
                    'payment_details' => [
                        'category' => 'profile',
                        'amount' => null,
                        'payment_date' => now()->toDateString(),
                        'payment_method' => null,
                        'reference_number' => null,
                        'remarks' => null,
                        'verification_status' => 'pending',
                        'proof_file' => $path,
                    ],
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
                $details = $payment->payment_details ?? [];
                $details['verification_status'] = $payment->payment_verified ? 'verified' : $data['status'];
                $payment->payment_details = $details;
                $payment->save();
            });

            Profile::find($payment->profile_id)?->syncDocumentVerificationStatus();

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
            'amount' => $payment->payment_details['amount'] ?? null,
            'status' => $payment->payment_verified ? 'approved' : 'pending',
            'payment_verified' => (bool) $payment->payment_verified,
            'payment_details' => $payment->payment_details ?? [],
            'screenshot_url' => $payment->screenshot_path
                ? Storage::disk('public')->url($payment->screenshot_path)
                : '',
            'created_at' => optional($payment->created_at)->toIso8601String(),
        ];
    }

    private function isAdmin($user): bool
    {
        return $user && $user->isAdmin();
    }

    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden. Administrator access is required.',
        ], 403);
    }
}
