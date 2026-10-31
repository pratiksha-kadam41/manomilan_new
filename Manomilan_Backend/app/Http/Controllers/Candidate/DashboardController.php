<?php

namespace App\Http\Controllers\Candidate;

use App\Models\CandidateNotification;
use App\Profile;
use App\ProfileVisitor;
use App\UserInterest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends CandidateController
{
    public function index(Request $request)
    {
        try {
            $user = $this->candidate($request);
            $profile = $user->profile_id
                ? Profile::with('preference')->find($user->profile_id)
                : null;
            $preference = $profile?->preference;

            $stats = [
                'profile_completion' => $profile ? (int) $profile->profile_completion : 0,
                'profile_views' => 0,
                'matches_available' => 0,
                'interests_sent' => 0,
            ];

            $missing = [];

            if ($profile) {
                $stats['profile_views'] = ProfileVisitor::where('profile_id', $profile->id)
                    ->distinct('visitor_id')
                    ->count('visitor_id');
                $stats['matches_available'] = Profile::query()
                    ->where('groomOrBride', $profile->groomOrBride === 'groom' ? 'bride' : 'groom')
                    ->where('active', 'active')
                    ->where('id', '!=', $profile->id)
                    ->count();
                $stats['interests_sent'] = UserInterest::where('user_id', $user->id)->count();

                $missing = $this->completionGaps($profile, $preference);
            }

            $recentNotifications = CandidateNotification::where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get(['id', 'type', 'title', 'message', 'read_at', 'created_at'])
                ->map(fn (CandidateNotification $notification) => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'read' => $notification->read_at !== null,
                    'created_at' => optional($notification->created_at)->toIso8601String(),
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->presentUser($user),
                    'profile' => $profile ? $this->presentDashboardProfile($profile) : null,
                    'stats' => $stats,
                    'completion' => [
                        'percentage' => $profile ? (int) $profile->profile_completion : 0,
                        'missing' => $missing,
                    ],
                    'recent_notifications' => $recentNotifications,
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load your dashboard right now.',
            ], 500);
        }
    }

    private function presentDashboardProfile(Profile $profile): array
    {
        return [
            'id' => $profile->id,
            'registration_number' => $profile->registration_number ?: $profile->profile_id,
            'profile_id' => $profile->profile_id,
            'full_name' => $profile->fullName,
            'gender' => $profile->groomOrBride,
            'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
            'photo_url' => $this->fileUrl($profile->profilePhoto),
            'city' => $this->profileCity($profile),
            'profile_completion' => (int) $profile->profile_completion,
            'has_documents' => collect(self::documentKeys())
                ->contains(fn (string $key) => filled($profile->{$key} ?? null)),
            'details' => [
                'education' => $profile->education,
                'profession' => $profile->profession,
            ],
            'preference' => $this->presentPreference($profile->preference),
        ];
    }

    private function completionGaps(Profile $profile, $preference): array
    {
        $checks = [
            ['label' => 'Profile photo', 'done' => filled($profile->profilePhoto)],
            ['label' => 'About you', 'done' => filled($profile->knowAboutMyself)],
            ['label' => 'Education details', 'done' => filled($profile->education) && filled($profile->degreeDiplomaName1)],
            ['label' => 'Career details', 'done' => filled($profile->profession) && filled($profile->organisationWorkingIn)],
            ['label' => 'Family details', 'done' => filled($profile->fathersName) && filled($profile->mothersName)],
            ['label' => 'Contact address', 'done' => filled($profile->address) || filled($profile->permanent_address)],
            ['label' => 'Partner preferences', 'done' => $preference !== null],
            ['label' => 'Identity documents', 'done' => filled($profile->identificationProof) || filled($profile->birthDateProof)],
        ];

        return collect($checks)
            ->reject(fn (array $check) => $check['done'])
            ->pluck('label')
            ->values()
            ->all();
    }
}
