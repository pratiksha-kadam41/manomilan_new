<?php

namespace App\Http\Controllers\Candidate;

use App\ProfileMelavaRegistration;
use Illuminate\Http\Request;
use Throwable;

class MelavaController extends CandidateController
{
    public function registrations(Request $request)
    {
        try {
            $profile = $this->candidateProfile($request);
            if (! $profile) {
                return response()->json(['success' => true, 'data' => ['registrations' => []]]);
            }

            $registrations = ProfileMelavaRegistration::with('campaign')
                ->where('profile_id', $profile->id)
                ->latest()
                ->get()
                ->map(fn (ProfileMelavaRegistration $registration) => [
                    'id' => $registration->id,
                    'registration_id' => $profile->reg_id ?: 'MM-'.$registration->id,
                    'receipt_no' => 'REC/'.now()->format('Y').'/'.$registration->id,
                    'receipt_date' => now()->format('d-m-Y'),
                    'candidate_name' => $profile->full_name,
                    'mobile' => $profile->phone ?: $profile->user?->username,
                    'profile_for' => match (strtolower((string) $profile->groomorbride)) {
                        'groom' => 'Groom',
                        'bride' => 'Bride',
                        default => null,
                    },
                    'city' => data_get($profile->addresses, 'current.city')
                        ?: $profile->employments()->value('employment_city')
                        ?: $profile->educations()->value('education_city'),
                    'registered_at' => $registration->created_at?->format(DATE_ATOM),
                    'event_name' => $registration->campaign?->title ?: 'Melava event',
                    'event_start_date' => $registration->campaign?->start_date?->format('Y-m-d'),
                    'event_end_date' => $registration->campaign?->end_date?->format('Y-m-d'),
                    'venue' => $registration->campaign?->description,
                    'currency' => $registration->campaign?->currency ?: 'INR',
                    'amount' => (float) ($registration->campaign?->registration_fee ?? 0)
                        + ($registration->is_booklet_req ? (float) ($registration->campaign?->booklet_fee ?? 0) : 0),
                    'payment_status' => $registration->payment_verified ? 'paid' : 'pending',
                    'payment_submitted' => filled($registration->payment_screenshot)
                        || ($registration->is_booklet_req && filled($registration->booklet_payment_screenshot)),
                    'receipt_available' => (bool) $registration->payment_verified,
                ])
                ->values();

            return response()->json([
                'success' => true,
                'data' => ['registrations' => $registrations],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load your Melava registrations right now.',
            ], 500);
        }
    }
}
