<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Requests\Candidate\SavePreferenceRequest;
use App\Models\CandidateNotification;
use App\Preference;
use Illuminate\Http\Request;
use Throwable;

class PreferenceController extends CandidateController
{
    public function show(Request $request)
    {
        try {
            $user = $this->candidate($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'preference' => $this->presentPreference($user->preference),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load your preferences right now.',
            ], 500);
        }
    }

    public function update(SavePreferenceRequest $request)
    {
        try {
            $user = $this->candidate($request);
            $wasEmpty = ! Preference::where('user_id', $user->id)->exists();

            $preference = Preference::updateOrCreate(
                ['user_id' => $user->id],
                $request->validated()
            );

            $profile = $user->profile;
            if ($profile) {
                try {
                    $profile->calculateCompletionPercentage();
                } catch (Throwable $e) {
                    report($e);
                }
            }

            if ($wasEmpty) {
                try {
                    CandidateNotification::pushTo(
                        $user,
                        'preferences_saved',
                        'Partner preferences saved',
                        'Your partner preferences are set. We will use them to rank your matches.',
                        []
                    );
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Partner preferences saved successfully.',
                'data' => [
                    'preference' => $this->presentPreference($preference->fresh()),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save your preferences right now.',
            ], 500);
        }
    }
}
