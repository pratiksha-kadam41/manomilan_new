<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PaymentScreenshot;
use App\Profile;
use App\ProfileMelavaRegistration;
use Illuminate\Http\Request;
use Throwable;

class ReceiptController extends Controller
{
    public function show(Request $request, int $profileId)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator access is required.',
            ], 403);
        }

        try {
            $profile = Profile::with('user')->find($profileId);

            if (! $profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Receipt not found.',
                ], 404);
            }

            $verifiedMelava = ProfileMelavaRegistration::with('campaign')
                ->where('profile_id', $profile->id)
                ->where('payment_verified', true)
                ->latest()
                ->first();

            $hasVerifiedProfilePayment = PaymentScreenshot::where('profile_id', $profile->id)
                ->where('payment_verified', true)
                ->exists();

            if (! $verifiedMelava && ! $hasVerifiedProfilePayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'A receipt is available only after payment is verified.',
                ], 404);
            }

            $registrationFee = $verifiedMelava?->campaign?->registration_fee;
            $bookletFee = $verifiedMelava?->campaign?->booklet_fee;
            $total = $registrationFee !== null
                ? (float) $registrationFee + ($verifiedMelava->is_booklet_req ? (float) $bookletFee : 0)
                : null;

            if ($verifiedMelava) {
                $registrationFee = (float) ($registrationFee ?? config('melava.fee', 0));
                $bookletFee = $verifiedMelava->is_booklet_req
                    ? (float) ($bookletFee ?: config('melava.booklet_fee', 0))
                    : 0;
                $total = $registrationFee + $bookletFee;
            }

            $gender = strtolower((string) $profile->groomorbride);
            $issuedAt = now();
            return response()->json([
                'success' => true,
                'data' => [
                    'receipt_no' => 'REC/'.$issuedAt->format('Y').'/'.$profile->id,
                    'receipt_date' => $issuedAt->format('d-m-Y'),
                    'registration_type' => $verifiedMelava ? 'Melava Registration' : 'Registration',
                    'candidate_name' => $profile->full_name,
                    'mobile' => $profile->user?->username ?? $profile->phone,
                    'profile_for' => match ($gender) {
                        'groom' => 'Groom',
                        'bride' => 'Bride',
                        default => null,
                    },
                    'city' => $profile->employments()->value('employment_city')
                        ?: $profile->educations()->value('education_city'),
                    'registration_fee' => $registrationFee,
                    'booklet_fee' => $bookletFee,
                    'total' => $total,
                    'payment_status' => 'verified',
                ],
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load the receipt right now.',
            ], 500);
        }
    }

    private function isAdmin($user): bool
    {
        return $user && $user->isAdmin();
    }
}
