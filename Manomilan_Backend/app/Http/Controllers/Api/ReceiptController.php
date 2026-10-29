<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaRegistration;
use App\PaymentScreenshot;
use App\Profile;
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

            $verifiedMelava = MelavaRegistration::where('profile_id', $profile->id)
                ->where(function ($query) {
                    $query->where('payment_verified', true)
                        ->orWhere('booklet_payment_verified', true);
                })
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

            $gender = strtolower((string) $profile->groomOrBride);
            $issuedAt = now();
            return response()->json([
                'success' => true,
                'data' => [
                    'receipt_no' => 'REC/'.$issuedAt->format('Y').'/'.$profile->id,
                    'receipt_date' => $issuedAt->format('d-m-Y'),
                    'registration_type' => $verifiedMelava ? 'Melava Registration' : 'Registration',
                    'candidate_name' => $profile->fullName,
                    'mobile' => $profile->user?->username ?? $profile->phone,
                    'profile_for' => match ($gender) {
                        'groom' => 'Groom',
                        'bride' => 'Bride',
                        default => null,
                    },
                    'city' => $verifiedMelava?->city
                        ?: $profile->location
                        ?: $profile->professionalCity
                        ?: $profile->cityOfEducation,
                    'registration_fee' => null,
                    'booklet_fee' => null,
                    'total' => null,
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
        return $user && ((int) $user->role_id === 1 || (string) $user->user_type === '00');
    }
}
