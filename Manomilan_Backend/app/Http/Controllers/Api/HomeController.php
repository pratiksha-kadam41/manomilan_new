<?php

namespace App\Http\Controllers\Api;

use App\Profile;
use App\ProfileVisitor;
use App\UserInterest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

                $profileQuery = Profile::where('status', 'active');
                $datedProfileQuery = clone $profileQuery;
                $userQuery = User::query();
                $campaignQuery = DB::table('melava_campaigns');
                $registrationQuery = DB::table('profile_melava_registrations');
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
                    $trendProfiles[] = Profile::where('status', 'active')
                        ->whereDate('created_at', $day->toDateString())->count();
                }
                $recentProfiles = Profile::where('status', 'active')
                    ->whereBetween('created_at', [now()->subDays(10), now()])
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->with(['educations', 'employments'])
                    ->get()
                    ->map(function (Profile $profile): array {
                        $employment = $profile->employments->sortByDesc('id')->first();
                        $education = $profile->educations->sortByDesc('id')->first();
                        $photo = is_array($profile->photos) ? ($profile->photos['profile_photo'] ?? $profile->photos[0] ?? null) : null;
                        $photo = $profile->profilePhotoWithFallback($photo);

                        return [
                            'id' => $profile->id,
                            'name' => $profile->full_name ?: 'Candidate',
                            'photo' => $this->profilePhotoUrl(is_string($photo) ? $photo : null),
                            'type' => ucfirst((string) $profile->groomorbride),
                            'location' => $employment?->employment_city
                                ?: $education?->education_city
                                ?: $this->profileAddressCity($profile)
                                ?: 'Not set',
                            'registration_number' => $profile->reg_id ?: '',
                            'status' => $profile->verification_status === 'Completed' ? 'Verified' : 'Pending',
                            'created_at' => $profile->created_at?->toDateTimeString(),
                        ];
                    });
                $upcomingEvents = DB::table('melava_campaigns')
                    ->where('status', 'active')
                    ->where(function ($query) {
                        $today = now()->toDateString();
                        $query->where('start_date', '>=', $today)
                            ->orWhere(function ($ongoing) use ($today) {
                                $ongoing->where('start_date', '<', $today)
                                    ->where('end_date', '>=', $today);
                            });
                    })
                    ->orderBy('start_date')
                    ->get()
                    ->map(function ($campaign) use ($dated) {
                        $campaign->registrations_count = DB::table('profile_melava_registrations')
                            ->where('melava_campaign_id', $campaign->id)
                            ->count();
                        $eventRegistrationQuery = DB::table('profile_melava_registrations')
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
                            ->where('verification_status', 'Completed')
                            ->count(),
                        'pending_profiles' => (clone $datedProfileQuery)
                            ->where('verification_status', 'Pending')
                            ->count(),
                        'groom_profiles' => (clone $datedProfileQuery)
                            ->where('groomorbride', 'Groom')->count(),
                        'bride_profiles' => (clone $datedProfileQuery)
                            ->where('groomorbride', 'Bride')->count(),
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

    public function reports(Request $request)
    {
        $data = $request->validate([
            'period' => ['nullable', \Illuminate\Validation\Rule::in(['today', 'week', 'month', 'all'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'gender' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'Groom', 'Bride'])],
            'status' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'active', 'deactive', 'verified', 'pending'])],
            'source' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Administrator access is required.'], 403);
        }

        try {
            $period = $data['period'] ?? 'all';
            $now = now();
            $from = match ($period) {
                'today' => $now->copy()->startOfDay(),
                'week' => $now->copy()->startOfWeek(),
                'month' => $now->copy()->startOfMonth(),
                default => null,
            };
            $to = $now->copy()->endOfDay();
            if (! empty($data['from'])) {
                $from = Carbon::parse($data['from'])->startOfDay();
                $to = Carbon::parse($data['to'] ?? $data['from'])->endOfDay();
            }

            $applyDates = static function ($query, string $column = 'created_at') use ($from, $to): void {
                if ($from) {
                    $query->whereBetween($column, [$from, $to]);
                }
            };

            $profilesForSourceOptions = DB::table('profiles');
            $applyDates($profilesForSourceOptions);
            if (($data['gender'] ?? 'all') !== 'all') {
                $profilesForSourceOptions->where('groomorbride', $data['gender']);
            }
            $status = $data['status'] ?? 'all';
            if (in_array($status, ['active', 'deactive'], true)) {
                $profilesForSourceOptions->where('status', $status);
            } elseif ($status === 'verified') {
                $profilesForSourceOptions->where('verification_status', 'Completed');
            } elseif ($status === 'pending') {
                $profilesForSourceOptions->where('verification_status', 'Pending');
            }

            $profiles = clone $profilesForSourceOptions;
            if (! empty($data['source']) && Schema::hasColumn('profiles', 'source_of_information')) {
                $profiles->where('source_of_information', $data['source']);
            }
            $gender = $data['gender'] ?? 'all';
            if ($gender !== 'all') {
                $profiles->where('groomorbride', $gender);
            }

            $profileTotal = (clone $profiles)->count();
            $activeProfiles = (clone $profiles)->where('status', 'active');
            $verifiedProfiles = (clone $activeProfiles)->where('verification_status', 'Completed')->count();
            $pendingProfiles = (clone $activeProfiles)->where('verification_status', 'Pending')->count();
            $inactiveProfiles = (clone $profiles)->where('status', 'deactive')->count();
            $grooms = (clone $profiles)->where('groomorbride', 'Groom')->count();
            $brides = (clone $profiles)->where('groomorbride', 'Bride')->count();

            $users = DB::table('users');
            $applyDates($users);
            if ($gender !== 'all' || ! empty($data['source']) || in_array($status, ['active', 'deactive', 'verified', 'pending'], true)) {
                $users->whereIn('profile_id', (clone $profiles)->select('id'));
            }

            $events = DB::table('melava_campaigns');
            $applyDates($events);
            $registrations = DB::table('profile_melava_registrations');
            $applyDates($registrations);
            $registrations->whereIn('profile_id', (clone $profiles)->select('id'));
            $melavaRegistrationCount = (clone $registrations)->count();
            $verifiedMelavaPayments = (clone $registrations)->where('payment_verified', true)->count();
            $pendingMelavaPayments = (clone $registrations)->where('payment_verified', false)->count();
            $verifiedBookletPayments = Schema::hasColumn('profile_melava_registrations', 'booklet_payment_verified')
                ? (clone $registrations)->where('is_booklet_req', true)->where('booklet_payment_verified', true)->count()
                : 0;
            $pendingBookletPayments = Schema::hasColumn('profile_melava_registrations', 'booklet_payment_verified')
                ? (clone $registrations)->where('is_booklet_req', true)->where('booklet_payment_verified', false)->count()
                : 0;
            $melavaRegistrationCounts = (clone $registrations)
                ->select('melava_campaign_id')
                ->selectRaw('COUNT(*) as registration_count')
                ->groupBy('melava_campaign_id');
            $melavaEventDetails = DB::table('melava_campaigns as campaign')
                ->leftJoinSub($melavaRegistrationCounts, 'registration_counts', 'campaign.id', '=', 'registration_counts.melava_campaign_id')
                ->select(
                    'campaign.id',
                    'campaign.melava_code as code',
                    'campaign.title',
                    'campaign.start_date',
                    'campaign.end_date',
                    'campaign.status',
                )
                ->selectRaw('COALESCE(registration_counts.registration_count, 0) as registrations');
            $applyDates($melavaEventDetails, 'campaign.created_at');
            $melavaEventDetails = $melavaEventDetails->orderByDesc('registrations')->limit(10)->get();

            $workshopEvents = Schema::hasTable('workshop_events') ? DB::table('workshop_events') : null;
            if ($workshopEvents) {
                $applyDates($workshopEvents);
            }
            $workshopRegistrations = Schema::hasTable('workshop_registrations')
                ? DB::table('workshop_registrations')
                : null;
            if ($workshopRegistrations) {
                $applyDates($workshopRegistrations);
                if (Schema::hasColumn('workshop_registrations', 'profile_id')) {
                    $workshopRegistrations->whereIn('profile_id', (clone $profiles)->select('id'));
                }
            }
            $verifiedWorkshopRegistrations = $workshopRegistrations
                ? (clone $workshopRegistrations)->where('status', 'Verified')->count()
                : 0;
            $pendingWorkshopRegistrations = $workshopRegistrations
                ? (clone $workshopRegistrations)->where('status', 'Pending')->count()
                : 0;
            $rejectedWorkshopRegistrations = $workshopRegistrations
                ? (clone $workshopRegistrations)->where('status', 'Rejected')->count()
                : 0;
            $workshopEventDetails = collect();
            if ($workshopEvents && $workshopRegistrations && Schema::hasColumn('workshop_registrations', 'workshop_event_id')) {
                $workshopRegistrationCounts = (clone $workshopRegistrations)
                    ->select('workshop_event_id')
                    ->selectRaw('COUNT(*) as registration_count')
                    ->groupBy('workshop_event_id');
                $workshopEventDetails = DB::table('workshop_events as event')
                    ->leftJoinSub($workshopRegistrationCounts, 'registration_counts', 'event.id', '=', 'registration_counts.workshop_event_id')
                    ->select(
                        'event.id',
                        'event.workshop_code as code',
                        'event.title',
                        'event.start_date',
                        'event.end_date',
                        'event.status',
                    )
                    ->selectRaw('COALESCE(registration_counts.registration_count, 0) as registrations')
                    ->when($from, fn ($query) => $query->whereBetween('event.created_at', [$from, $to]))
                    ->orderByDesc('registrations')
                    ->limit(10)
                    ->get();
            }

            $completionAvailable = Schema::hasColumn('profiles', 'profile_completion');
            $completion = $completionAvailable
                ? (clone $profiles)->selectRaw('AVG(profile_completion) as average')
                    ->selectRaw('SUM(CASE WHEN profile_completion < 100 THEN 1 ELSE 0 END) as incomplete')
                ->selectRaw('SUM(CASE WHEN profile_completion IS NULL THEN 1 ELSE 0 END) as not_scored')
                ->first()
                : null;
            $completionBands = [];
            if ($completionAvailable) {
                foreach ([
                    ['label' => '0-24%', 'min' => 0, 'max' => 25],
                    ['label' => '25-49%', 'min' => 25, 'max' => 50],
                    ['label' => '50-74%', 'min' => 50, 'max' => 75],
                    ['label' => '75-99%', 'min' => 75, 'max' => 100],
                    ['label' => '100%', 'min' => 100, 'max' => 101],
                ] as $band) {
                    $completionBands[] = [
                        'label' => $band['label'],
                        'count' => (clone $profiles)->where('profile_completion', '>=', $band['min'])
                            ->where('profile_completion', '<', $band['max'])->count(),
                    ];
                }
            }

            $sources = [];
            if (Schema::hasColumn('profiles', 'source_of_information')) {
                $sources = (clone $profilesForSourceOptions)
                    ->whereNotNull('source_of_information')
                    ->where('source_of_information', '!=', '')
                    ->selectRaw('source_of_information as label, COUNT(*) as count')
                    ->groupBy('source_of_information')
                    ->orderBy('source_of_information')
                    ->get()
                    ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
                    ->values();
            }

            $activity = [
                'profile_views' => 0,
                'unique_profiles_viewed' => 0,
                'interests' => 0,
                'pending_interests' => 0,
            ];
            if (Schema::hasTable('profile_visitors')) {
                $visitors = DB::table('profile_visitors')->whereIn('profile_id', (clone $profiles)->select('id'));
                $applyDates($visitors);
                $activity['profile_views'] = (clone $visitors)->count();
                $activity['unique_profiles_viewed'] = (clone $visitors)->distinct('profile_id')->count('profile_id');
            }
            if (Schema::hasTable('user_interests')) {
                $interests = DB::table('user_interests')->whereIn('profile_id', (clone $profiles)->select('id'));
                $applyDates($interests);
                $activity['interests'] = (clone $interests)->count();
                $activity['pending_interests'] = (clone $interests)->where('status', 'pending')->count();
            }

            $paymentMetrics = [
                'entries' => 0,
                'verified_entries' => 0,
                'pending_entries' => 0,
            ];
            if (Schema::hasTable('payment_screenshots')) {
                $payments = DB::table('payment_screenshots')
                    ->whereIn('profile_id', (clone $profiles)->select('id'));
                $applyDates($payments);
                $paymentMetrics['entries'] = (clone $payments)->count();
                $paymentMetrics['verified_entries'] = (clone $payments)->where('payment_verified', true)->count();
                $paymentMetrics['pending_entries'] = (clone $payments)->where('payment_verified', false)->count();
            }
            $profilesWithPayments = Schema::hasTable('payment_screenshots')
                ? (clone $payments)->distinct()->count('profile_id')
                : 0;

            $trendFrom = $from ?: $now->copy()->subMonths(11)->startOfMonth();
            $trendTo = $to;
            $days = $trendFrom->diffInDays($trendTo);
            $granularity = $period === 'today' ? 'hour' : ($days <= 31 ? 'day' : 'month');
            $trendQuery = clone $profiles;
            $trendQuery->whereBetween('created_at', [$trendFrom, $trendTo]);
            $bucketExpression = match ($granularity) {
                'hour' => 'HOUR(created_at)',
                'day' => 'DATE(created_at)',
                default => "DATE_FORMAT(created_at, '%Y-%m-01')",
            };
            $trendRows = $trendQuery
                ->selectRaw("$bucketExpression as bucket, groomorbride as gender, COUNT(*) as count")
                ->groupBy('bucket', 'gender')
                ->get();
            $trend = [];
            if ($granularity === 'hour') {
                for ($hour = 0; $hour <= (int) $now->format('G'); $hour++) {
                    $key = (string) $hour;
                    $trend[] = [
                        'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
                        'profiles' => (int) $trendRows->where('bucket', $hour)->sum('count'),
                        'grooms' => (int) $trendRows->where('bucket', $hour)->where('gender', 'Groom')->sum('count'),
                        'brides' => (int) $trendRows->where('bucket', $hour)->where('gender', 'Bride')->sum('count'),
                        'key' => $key,
                    ];
                }
            } else {
                $cursor = $granularity === 'day' ? $trendFrom->copy()->startOfDay() : $trendFrom->copy()->startOfMonth();
                $last = $granularity === 'day' ? $trendTo->copy()->startOfDay() : $trendTo->copy()->startOfMonth();
                while ($cursor <= $last) {
                    $key = $granularity === 'day' ? $cursor->toDateString() : $cursor->format('Y-m-01');
                    $rows = $trendRows->where('bucket', $key);
                    $trend[] = [
                        'label' => $granularity === 'day'
                            ? $cursor->format('M j')
                            : ($days > 365 ? $cursor->format('M Y') : $cursor->format('M')),
                        'profiles' => (int) $rows->sum('count'),
                        'grooms' => (int) $rows->where('gender', 'Groom')->sum('count'),
                        'brides' => (int) $rows->where('gender', 'Bride')->sum('count'),
                        'key' => $key,
                    ];
                    $cursor->add($granularity === 'day' ? 1 : 1, $granularity === 'day' ? 'day' : 'month');
                }
            }

            $locations = [];
            if (Schema::hasTable('profile_employment') && Schema::hasTable('profile_education')) {
                $latestEmploymentIds = DB::table('profile_employment')->selectRaw('MAX(id) as id')->groupBy('profile_id');
                $latestEducationIds = DB::table('profile_education')->selectRaw('MAX(id) as id')->groupBy('profile_id');
                $employmentCities = DB::table('profile_employment as employment')
                    ->joinSub($latestEmploymentIds, 'latest_employment', 'employment.id', '=', 'latest_employment.id')
                    ->select('employment.profile_id', 'employment.employment_city');
                $educationCities = DB::table('profile_education as education')
                    ->joinSub($latestEducationIds, 'latest_education', 'education.id', '=', 'latest_education.id')
                    ->select('education.profile_id', 'education.education_city');
                $cityExpression = "COALESCE(NULLIF(employment.employment_city, ''), NULLIF(education.education_city, ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(filtered_profiles.addresses, '$.current.city')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(filtered_profiles.addresses, '$.city')), ''))";
                $locations = DB::query()
                    ->fromSub((clone $profiles)->select('id', 'addresses'), 'filtered_profiles')
                    ->leftJoinSub($employmentCities, 'employment', 'filtered_profiles.id', '=', 'employment.profile_id')
                    ->leftJoinSub($educationCities, 'education', 'filtered_profiles.id', '=', 'education.profile_id')
                    ->selectRaw("$cityExpression as label, COUNT(*) as count")
                    ->whereRaw("$cityExpression IS NOT NULL")
                    ->groupBy('label')
                    ->orderByDesc('count')
                    ->limit(6)
                    ->get()
                    ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
                    ->values();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'range' => ['from' => $from?->toDateString(), 'to' => $from ? $to->toDateString() : null],
                    'trend_range' => [
                        'from' => $trendFrom->toDateString(),
                        'to' => $trendTo->toDateString(),
                        'granularity' => $granularity,
                    ],
                    'generated_at' => now()->toIso8601String(),
                    'total_users' => $users->count(),
                    'total_profiles' => $profileTotal,
                    'active_profiles' => (clone $profiles)->where('status', 'active')->count(),
                    'inactive_profiles' => $inactiveProfiles,
                    'verified_profiles' => $verifiedProfiles,
                    'pending_profiles' => $pendingProfiles,
                    'groom_profiles' => $grooms,
                    'bride_profiles' => $brides,
                    'melava_campaigns' => (clone $events)->count(),
                    'melava_registrations' => $melavaRegistrationCount,
                    'verified_melava_payments' => $verifiedMelavaPayments,
                    'pending_melava_payments' => $pendingMelavaPayments,
                    'verified_booklet_payments' => $verifiedBookletPayments,
                    'pending_booklet_payments' => $pendingBookletPayments,
                    'workshop_events' => $workshopEvents ? (clone $workshopEvents)->count() : 0,
                    'workshop_registrations' => $workshopRegistrations ? (clone $workshopRegistrations)->count() : 0,
                    'verified_workshop_registrations' => $verifiedWorkshopRegistrations,
                    'pending_workshop_registrations' => $pendingWorkshopRegistrations,
                    'rejected_workshop_registrations' => $rejectedWorkshopRegistrations,
                    'profiles_with_payments' => $profilesWithPayments,
                    'melava_event_details' => $melavaEventDetails,
                    'workshop_event_details' => $workshopEventDetails,
                    'profile_completion' => [
                        'available' => $completionAvailable,
                        'average' => $completion?->average === null ? null : round((float) $completion->average, 1),
                        'incomplete' => (int) ($completion->incomplete ?? 0),
                        'not_scored' => (int) ($completion->not_scored ?? 0),
                        'bands' => $completionBands,
                    ],
                    'trend' => $trend,
                    'profile_status_distribution' => [
                        ['label' => 'Verified', 'count' => $verifiedProfiles],
                        ['label' => 'Pending verification', 'count' => $pendingProfiles],
                        ['label' => 'Inactive', 'count' => $inactiveProfiles],
                        ['label' => 'Other / unclassified', 'count' => max($profileTotal - $verifiedProfiles - $pendingProfiles - $inactiveProfiles, 0)],
                    ],
                    'gender_distribution' => [
                        ['label' => 'Groom', 'count' => $grooms],
                        ['label' => 'Bride', 'count' => $brides],
                    ],
                    'sources' => $sources,
                    'locations' => $locations,
                    'payments' => $paymentMetrics,
                    'activity' => $activity,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load report analytics right now.',
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
                        'registration_number' => $profile->reg_id,
                        'profile_id' => $profile->reg_id,
                        'full_name' => $profile->full_name,
                        'gender' => $profile->groomorbride,
                        'photo_url' => $this->profilePhotoUrl(
                            is_array($profile->photos)
                                ? ($profile->photos['profile_photo'] ?? $profile->photos[0] ?? null)
                                : null
                        ),
                        'profile_completion' => null,
                        'verification_status' => $profile->verification_status,
                        'birth_date' => $profile->birth_date,
                        'city' => $profile->employments()->latest('id')->value('employment_city')
                            ?: $profile->educations()->latest('id')->value('education_city')
                            ?: $this->profileAddressCity($profile),
                        'education' => $profile->educations()->latest('id')->value('degree_name'),
                        'profession' => $profile->employments()->latest('id')->value('profession'),
                        'has_family' => $profile->familyDetails()->exists(),
                        'has_education' => $profile->educations()->exists(),
                        'has_photos' => ! empty($profile->photos),
                        'has_preferences' => $profile->partnerPreferences()->exists()
                            || $user->preference()->exists(),
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

    private function profilePhotoUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }

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

        if (str_starts_with($path, 'additional_photos/') || str_starts_with($path, 'profile_photos/')) {
            return url('storage/'.$path);
        }

        return url($path);
    }

    private function profileAddressCity(Profile $profile): ?string
    {
        $addresses = $profile->addresses;
        if (! is_array($addresses)) {
            return null;
        }

        if (filled($addresses['city'] ?? null)) {
            return $addresses['city'];
        }

        foreach ($addresses as $address) {
            if (is_array($address) && filled($address['city'] ?? null)) {
                return $address['city'];
            }
        }

        return null;
    }
}
