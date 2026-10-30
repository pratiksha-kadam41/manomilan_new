<?php

namespace App\Http\Controllers\Api;

use App\Profile;
use App\ProfileVisitor;
use App\UserInterest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Throwable;

class HomeController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        try {
            if ($user->isAdmin()) {
                $from = null;
                $to = null;
                if ($request->input('period') === 'today') {
                    $from = now()->startOfDay();
                    $to = now()->endOfDay();
                } elseif ($request->input('period') === 'week') {
                    $from = now()->startOfWeek();
                    $to = now()->endOfDay();
                } elseif ($request->filled('from')) {
                    $from = Carbon::parse($request->input('from'))->startOfDay();
                    $to = $request->filled('to')
                        ? Carbon::parse($request->input('to'))->endOfDay()
                        : Carbon::parse($request->input('from'))->endOfDay();
                }

                $dated = function ($query) use ($from, $to) {
                    if ($from) {
                        $query->where('created_at', '>=', $from);
                    }
                    if ($to) {
                        $query->where('created_at', '<=', $to);
                    }
                };

                $profileQuery = Profile::where('active', 'active');
                $datedProfileQuery = clone $profileQuery;
                $userQuery = User::query();
                $campaignQuery = DB::table('melava_campaigns');
                $registrationQuery = DB::table('melava_registrations');
                $datedUserQuery = clone $userQuery;
                $datedCampaignQuery = clone $campaignQuery;
                $datedRegistrationQuery = clone $registrationQuery;
                $dated($datedProfileQuery);
                $dated($datedUserQuery);
                $dated($datedCampaignQuery);
                $dated($datedRegistrationQuery);
                $trendLabels = [];
                $trendUsers = [];
                $trendProfiles = [];
                for ($offset = 6; $offset >= 0; $offset--) {
                    $day = now()->subDays($offset)->startOfDay();
                    $trendLabels[] = $day->format('M d');
                    $trendUsers[] = User::whereDate('created_at', $day->toDateString())->count();
                    $trendProfiles[] = Profile::where('active', 'active')
                        ->whereDate('created_at', $day->toDateString())->count();
                }
                $recentProfiles = Profile::where('active', 'active')
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get(['id', 'fullName', 'groomOrBride', 'location', 'created_at', 'registration_number', 'profileVerificationStatus'])
                    ->map(fn ($profile) => [
                        'id' => $profile->id,
                        'name' => $profile->fullName ?: 'Candidate',
                        'type' => ucfirst((string) $profile->groomOrBride),
                        'location' => $profile->location ?: 'Not set',
                        'registration_number' => $profile->registration_number ?: '',
                        'status' => strtolower((string) $profile->profileVerificationStatus) === 'completed' ? 'Verified' : 'Pending',
                        'created_at' => $profile->created_at?->toDateTimeString(),
                    ]);
                $upcomingEvents = DB::table('melava_campaigns')
                    ->where('status', 'active')
                    ->where(function ($query) {
                        $today = now()->toDateString();
                        $query->whereNull('start_date')->orWhere('start_date', '>=', $today);
                    })
                    ->orderBy('start_date')
                    ->get()
                    ->map(function ($campaign) use ($dated) {
                        $campaign->registrations_count = DB::table('melava_registrations')
                            ->where('melava_campaign_id', $campaign->id)
                            ->count();
                        $eventRegistrationQuery = DB::table('melava_registrations')
                            ->where('melava_campaign_id', $campaign->id);
                        $dated($eventRegistrationQuery);
                        $campaign->registrations_count = (clone $eventRegistrationQuery)->count();
                        $campaign->payment_verified_count = (clone $eventRegistrationQuery)
                            ->where('melava_campaign_id', $campaign->id)
                            ->where('payment_verified', true)
                            ->count();
                        $today = now()->toDateString();
                        if (! $campaign->start_date) {
                            $campaign->schedule_status = 'Undated';
                        } elseif ($campaign->start_date > $today) {
                            $campaign->schedule_status = 'Upcoming';
                        } elseif (($campaign->end_date ?: $campaign->start_date) < $today) {
                            $campaign->schedule_status = 'Completed';
                        } else {
                            $campaign->schedule_status = 'Ongoing';
                        }
                        return $campaign;
                    });
                return response()->json([
                    'success' => true,
                    'data' => [
                        'role' => 'admin',
                        'total_users' => $datedUserQuery->count(),
                        'total_profiles' => $datedProfileQuery->count(),
                        'verified_profiles' => (clone $datedProfileQuery)
                            ->where('profileVerificationStatus', 'Completed')
                            ->count(),
                        'pending_profiles' => (clone $datedProfileQuery)
                            ->where(function ($query) {
                                $query->where('profileVerificationStatus', 'Pending')
                                    ->orWhere('profileVerificationStatus', 'pending');
                            })
                            ->count(),
                        'groom_profiles' => (clone $datedProfileQuery)
                            ->whereIn('groomOrBride', ['Groom', 'groom'])->count(),
                        'bride_profiles' => (clone $datedProfileQuery)
                            ->whereIn('groomOrBride', ['Bride', 'bride'])->count(),
                        'melava_campaigns' => $datedCampaignQuery->count(),
                        'upcoming_melava_campaigns' => (clone $datedCampaignQuery)
                            ->where('status', 'active')
                            ->where(function ($query) {
                                $today = now()->toDateString();
                                $query->whereNull('start_date')
                                    ->orWhere('start_date', '>=', $today);
                            })
                            ->count(),
                        'melava_registrations' => $datedRegistrationQuery->count(),
                        'pending_melava_payments' => (clone $datedRegistrationQuery)
                            ->where('payment_verified', false)->count(),
                        'verified_melava_payments' => (clone $datedRegistrationQuery)
                            ->where('payment_verified', true)->count(),
                        'upcoming_melava_events' => $upcomingEvents,
                        'trend_labels' => $trendLabels,
                        'trend_users' => $trendUsers,
                        'trend_profiles' => $trendProfiles,
                        'recent_profiles' => $recentProfiles,
                    ],
                ]);
            }

            $profile = $user->profile_id ? Profile::find($user->profile_id) : null;
            $profileId = $profile?->id;

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => 'candidate',
                    'profile_views' => $profileId ? ProfileVisitor::where('profile_id', $profileId)->count() : 0,
                    'interests_received' => $profileId ? UserInterest::where('profile_id', $profileId)->count() : 0,
                    'interests_sent' => UserInterest::where('user_id', $user->id)->count(),
                    'profile_completion' => (int) ($profile->profile_completion ?? 0),
                    'profile_id' => $profileId,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load dashboard data right now.',
            ], 500);
        }
    }

    public function homeData(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        try {
            $profile = $user->profile_id
                ? Profile::with('user')->find($user->profile_id)
                : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'role_id' => $user->role_id,
                        'user_type' => $user->user_type,
                        'status' => $user->status,
                    ],
                    'profile' => $profile ? [
                        'id' => $profile->id,
                        'registration_number' => $profile->registration_number ?: $profile->profile_id,
                        'profile_id' => $profile->profile_id,
                        'full_name' => $profile->fullName,
                        'gender' => $profile->groomOrBride,
                        'photo_url' => $profile->profilePhoto ? $this->profilePhotoUrl($profile->profilePhoto) : '',
                        'profile_completion' => (int) $profile->profile_completion,
                        'verification_status' => $profile->profileVerificationStatus,
                        'birth_date' => $profile->birthDate,
                        'city' => $profile->location ?: $profile->professionalCity,
                        'education' => $profile->education,
                        'profession' => $profile->profession,
                        'has_family' => filled($profile->fathersName) && filled($profile->mothersName),
                        'has_education' => filled($profile->education) && filled($profile->profession),
                        'has_photos' => filled($profile->profilePhoto),
                        'has_preferences' => $user->preference()->exists(),
                    ] : null,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load profile data right now.',
            ], 500);
        }
    }

    private function profilePhotoUrl(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        if (str_starts_with($path, 'candidate/')) {
            return url('storage/'.$path);
        }

        return url($path);
    }
}
