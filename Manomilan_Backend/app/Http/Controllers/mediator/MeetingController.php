<?php

namespace App\Http\Controllers\mediator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Profile;
use App\User;
use App\Preference;
use App\Meeting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\MeetingScheduled;
use Carbon\Carbon;

class MeetingController extends Controller
{
    // Meditor Login
    public function meetingSchedulars()
    {
        $q = request('q');
        $gender = request('gender');
        $reg_type = request('reg_type');

        $profiles = Profile::where('profileVerificationStatus', 'Completed')
            ->where('active', 'active')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profile_id', 'like', "%{$q}%")
                        ->orWhere('fullName', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(created_at, "%d-%m-%Y") like ?', ["%{$q}%"]);
                });
            })
            ->when($gender && $gender !== 'all', function ($query) use ($gender) {
                $query->where('groomOrBride', $gender);
            })
            ->when($reg_type && $reg_type !== 'all', function ($query) use ($reg_type) {
                $query->where('registration_type', $reg_type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends(['q' => $q, 'gender' => $gender, 'reg_type' => $reg_type]);

        return view('admin.profileMeetings.list', compact('profiles', 'q', 'gender', 'reg_type'));
    }
    
    public function inactiveMeetingSchedulars(Request $request)
    {
        $q = $request->q;

        $profiles = Profile::where('profileVerificationStatus', 'Completed')
            ->where('active', 'deactive')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profile_id', 'like', "%{$q}%")
                        ->orWhere('fullName', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(created_at, "%d-%m-%Y") like ?', ["%{$q}%"]);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends(['q' => $q]);

        return view('admin.profileMeetings.inactiveList', compact('profiles', 'q'));
    }



public function showMeetingSchedulars($profileId)
{

/*
|--------------------------------------------------------------------------
| PRIMARY PROFILE
|--------------------------------------------------------------------------
*/

$profile = Profile::findOrFail($profileId);

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

$user = User::where(
    'profile_id',
    $profile->id
)->firstOrFail();

/*
|--------------------------------------------------------------------------
| PREFERENCES
|--------------------------------------------------------------------------
*/

$preferences = Preference::where(
    'user_id',
    $user->id
)->first();

/*
|--------------------------------------------------------------------------
| SENT ACCEPTED INTEREST PROFILE IDS
|--------------------------------------------------------------------------
*/

$sentInterestProfileIds = DB::table(
    'user_interests'
)

->where(
    'user_id',
    $user->id
)

->where(
    'status',
    'accepted'
)

->pluck(
    'profile_id'
)

->toArray();

/*
|--------------------------------------------------------------------------
| RECEIVED ACCEPTED INTEREST PROFILE IDS
|--------------------------------------------------------------------------
*/

$receivedInterestProfileIds = DB::table(
    'user_interests'
)

->join(
    'users',
    'user_interests.user_id',
    '=',
    'users.id'
)

->where(
    'user_interests.profile_id',
    $profileId
)

->where(
    'user_interests.status',
    'accepted'
)

->pluck(
    'users.profile_id'
)

->toArray();

/*
|--------------------------------------------------------------------------
| MERGE BOTH IDS
|--------------------------------------------------------------------------
*/

$allMatchedProfileIds = array_unique(
    array_merge(
        $sentInterestProfileIds,
        $receivedInterestProfileIds
    )
);

/*
|--------------------------------------------------------------------------
| REMOVE CURRENT PROFILE ID
|--------------------------------------------------------------------------
*/

$allMatchedProfileIds = array_filter(
    $allMatchedProfileIds,
    function ($id) use ($profileId) {

        return $id != $profileId;
    }
);

/*
|--------------------------------------------------------------------------
| ALREADY SCHEDULED PROFILE IDS
|--------------------------------------------------------------------------
*/

$scheduledOtherIds = Meeting::where(
    'candidate_id',
    $profileId
)

->pluck(
    'other_candidate_id'
)

->toArray();

/*
|--------------------------------------------------------------------------
| FINAL MATCHED PROFILES
|--------------------------------------------------------------------------
*/

$matchedProfiles = Profile::whereIn(
    'id',
    $allMatchedProfileIds
)

->whereNotIn(
    'id',
    $scheduledOtherIds
)

->where(
    'active',
    'active'
)

->where(
    'profileVerificationStatus',
    'Completed'
)

->get();

/*
|--------------------------------------------------------------------------
| BOOKED TIME SLOTS
|--------------------------------------------------------------------------
*/

$bookedSlots = Meeting::where(
    'candidate_id',
    $profileId
)

->pluck(
    'time_slot'
)

->filter()

->all();

/*
|--------------------------------------------------------------------------
| NO ACCEPTED INTEREST FOUND
|--------------------------------------------------------------------------
*/

if ($matchedProfiles->count() == 0) {

    return view(
        'admin.profileMeetings.showMeetingSchedulars',
        compact(
            'profile',
            'preferences',
            'matchedProfiles',
            'bookedSlots'
        )
    )->with(
        'no_interest',
        'No Accepted Interest Found'
    );
}

/*
|--------------------------------------------------------------------------
| RETURN VIEW
|--------------------------------------------------------------------------
*/

return view(
    'admin.profileMeetings.showMeetingSchedulars',
    compact(
        'profile',
        'preferences',
        'matchedProfiles',
        'bookedSlots'
    )
);


}



     public function storeMeetingSchedulars(Request $request, $id)
    {
        $request->validate([
            'candidate_branch' => 'nullable|string|max:255',
            'candidate_location' => 'nullable|string|max:255',
            'candidate_meeting_date' => 'nullable|date',
            'candidate_day' => 'nullable|string|max:255',
            'candidate_meeting_time' => 'nullable|string|max:255',
            'candidate_remarks' => 'nullable|string',
            'candidate_spoke_to' => 'nullable|string|max:255',
            'matched_profile_id' => 'nullable|exists:profiles,id',
            'matched_branch' => 'nullable|string|max:255',
            'matched_location' => 'nullable|string|max:255',
            'matched_meeting_date' => 'nullable|date',
            'matched_day' => 'nullable|string|max:255',
            'matched_meeting_time' => 'nullable|string|max:255',
            'matched_remarks' => 'nullable|string',
            'matched_spoke_to' => 'nullable|string|max:255',
            'time_slot_finalised' => 'nullable|string|max:255',
        ]);
        // Resolve candidate profile by id from route
        $candidateProfile = Profile::findOrFail($id);

        // Update candidate profile with meeting details
        $candidateData = [];
        if ($request->filled('candidate_branch'))
            $candidateData['branch'] = $request->candidate_branch;
        if ($request->filled('candidate_location'))
            $candidateData['location'] = $request->candidate_location;
        if ($request->filled('candidate_meeting_date'))
            $candidateData['meeting_date'] = $request->candidate_meeting_date;
        if ($request->filled('candidate_day'))
            $candidateData['day'] = $request->candidate_day;
        if ($request->filled('candidate_meeting_time'))
            $candidateData['meeting_time'] = $request->candidate_meeting_time;
        if ($request->filled('candidate_remarks'))
            $candidateData['remarks'] = $request->candidate_remarks;
        if ($request->filled('candidate_spoke_to'))
            $candidateData['spoke_to'] = $request->candidate_spoke_to;

        if (!empty($candidateData)) {
            $candidateProfile->update($candidateData);
        }

        // Update matched profile with meeting details if matched profile is selected
        if ($request->filled('matched_profile_id')) {
            $matchedProfile = Profile::find($request->matched_profile_id);
            if ($matchedProfile) {
                $matchedData = [];
                if ($request->filled('matched_branch'))
                    $matchedData['branch'] = $request->matched_branch;
                if ($request->filled('matched_location'))
                    $matchedData['location'] = $request->matched_location;
                if ($request->filled('matched_meeting_date'))
                    $matchedData['meeting_date'] = $request->matched_meeting_date;
                if ($request->filled('matched_day'))
                    $matchedData['day'] = $request->matched_day;
                if ($request->filled('matched_meeting_time'))
                    $matchedData['meeting_time'] = $request->matched_meeting_time;
                if ($request->filled('matched_remarks'))
                    $matchedData['remarks'] = $request->matched_remarks;
                if ($request->filled('matched_spoke_to'))
                    $matchedData['spoke_to'] = $request->matched_spoke_to;

                if (!empty($matchedData)) {
                    $matchedProfile->update($matchedData);
                }
                // Finalised time slot
                $finalSlot = $request->input('time_slot_finalised');
                if ($finalSlot) {
                    // Persist final slot to both profiles so it is disabled next render
                    $candidateProfile->update(['meeting_time' => $finalSlot]);
                    $matchedProfile->update(['meeting_time' => $finalSlot]);

                    // Send email to profile reference emails with safe guards
                    $emailsSent = false;
                    try {
                        // Fetch emails directly from users table via profile_id mapping
                        $candidateEmail = User::where('profile_id', $candidateProfile->id)->value('email');
                        $matchedEmail = User::where('profile_id', $matchedProfile->id)->value('email');
                        \Log::info('MeetingSchedulars mail recipients', [
                            'candidate_email' => $candidateEmail,
                            'matched_email' => $matchedEmail,
                            'candidate_id' => $candidateProfile->id,
                            'matched_id' => $matchedProfile->id,
                        ]);
                        \Log::info('Mail configuration (non-sensitive)', [
                            'driver' => config('mail.driver'),
                            'host' => config('mail.host'),
                            'port' => config('mail.port'),
                            'encryption' => config('mail.encryption'),
                            'from_address' => data_get(config('mail.from'), 'address'),
                            'from_name' => data_get(config('mail.from'), 'name'),
                        ]);

                        Meeting::create([
                            'candidate_id' => $candidateProfile->id,
                            'place' => $request->candidate_location,
                            'date' => $request->candidate_meeting_date,
                            'time_slot' => $finalSlot,
                            'spoke_to' => $request->candidate_spoke_to,
                            'spoke_to_candidate' => $request->has('spokeToBride') ? 1 : 0,
                            'other_candidate_id' => $request->matched_profile_id,
                            'remark' => $request->candidate_remarks,
                        ]);

                        $meetingDetails = [
                            'time' => $finalSlot,
                            'date' => $request->matched_meeting_date,
                            'day' => $request->matched_day,
                            'location' => $request->matched_location,
                        ];

                        if (!empty($candidateEmail)) {
                            Mail::to($candidateEmail)->send(new MeetingScheduled($meetingDetails));
                            $emailsSent = true;
                        }
                        if (!empty($matchedEmail)) {
                            Mail::to($matchedEmail)->send(new MeetingScheduled($meetingDetails));
                            $emailsSent = true;
                        }
                    } catch (\Throwable $e) {
                        // Do not block redirect if mail fails; optionally log
                        \Log::error('MeetingScheduled mail failed: ' . $e->getMessage());
                    }
                }

                // Guard the interest update; avoid failing if mapping is unavailable
                try {
                    DB::table('user_interests')
                        ->where('profile_id', $matchedProfile->id)
                        ->update(['meeting_scheduled' => 'Yes']);
                } catch (\Throwable $e) {
                    \Log::warning('Meeting scheduled flag update skipped: ' . $e->getMessage());
                }
            }
        }

        $successMsg = 'Meeting scheduled successfully' . (isset($emailsSent) && $emailsSent ? '. Email sent Successfully' : '');
        return redirect()->back()->with('success', $successMsg);
    }



    public function deactivateProfile($id)
    {
        DB::beginTransaction();

        try {

            $profile = Profile::findOrFail($id);

            // Profile inactive
            $profile->active = 'deactive';
            $profile->save();

            // User inactive
            $user = User::where('profile_id', $profile->id)->first();

            if ($user) {

                $user->status = 'inactive';
                $user->save();
            }

            DB::commit();

            return redirect()->back()
                ->with('success', 'User deactivated successfully.');

        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }


        // ✅ ACTIVATE PROFILE
    public function activateProfile($id)
    {
        DB::beginTransaction();

        try {

            $profile = Profile::findOrFail($id);

            // Profile active
            $profile->active = 'active';
            $profile->save();

            // User active
            $user = User::where('profile_id', $profile->id)->first();

            if ($user) {

                $user->status = 'active';
                $user->save();
            }

            DB::commit();

            return redirect()->back()
                ->with('success', 'User activated successfully.');

        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

        public function storeMeeting(Request $request)
    {
        // Sanitize incoming payload to avoid 'undefined' and non-numeric IDs
        $data = $request->all();

        // Normalize potential 'undefined' or empty strings to null for IDs
        foreach (['other_candidate_id', 'matched_profile_id'] as $key) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === 'undefined') {
                $data[$key] = null;
            }
        }

        // Map matched_profile_id to other_candidate_id only when valid numeric
        if (empty($data['other_candidate_id']) && !empty($data['matched_profile_id']) && is_numeric($data['matched_profile_id'])) {
            $data['other_candidate_id'] = (int) $data['matched_profile_id'];
        }

        // Ensure boolean fields are coerced to 0/1
        $data['spoke_to_candidate'] = (isset($data['spoke_to_candidate']) && in_array($data['spoke_to_candidate'], [1, '1', true, 'true'], true)) ? 1 : 0;
        $data['spoke_to_matched_profile'] = (isset($data['spoke_to_matched_profile']) && in_array($data['spoke_to_matched_profile'], [1, '1', true, 'true'], true)) ? 1 : 0;

        // Validate sanitized data
        \Validator::make($data, [
            'candidate_id' => 'required|exists:profiles,id',
            'place' => 'required|string|max:255',
            'date' => 'required|date',
            'time_slot' => 'required|string|max:255',
            'spoke_to_matched_parents' => 'nullable|string|in:mother,father,guardians',
            'spoke_to_candidate' => 'required|boolean',
            'other_candidate_id' => 'nullable|exists:profiles,id',
            'day' => 'required|string|max:255',
            'spoke_to_matched_profile' => 'required|boolean',
            'spoke_to_cand_parents' => 'nullable|string|in:mother,father,guardians',
            'remark' => 'nullable|string',
        ])->validate();

        // Create meeting
        $meeting = Meeting::create($data);

        // Prepare and send emails to both candidates
        $emailsSent = false;

        // Fetch emails directly from users table via profile_id mapping
        $candidateEmail = User::where('profile_id', $meeting->candidate_id)->value('email');
        $matchedEmail = $meeting->other_candidate_id ? User::where('profile_id', $meeting->other_candidate_id)->value('email') : null;

        // Build meeting details payload expected by the mail view
        $meetingDetails = [
            'time' => $meeting->time_slot,
            'date' => $meeting->date,
            'day' => $meeting->day,
            'location' => $meeting->place,
        ];

        \Log::info('Meeting mail recipients', [
            'candidate_email' => $candidateEmail,
            'matched_email' => $matchedEmail,
            'meeting_id' => $meeting->id,
        ]);
        \Log::info('Mail configuration (non-sensitive)', [
            'driver' => config('mail.driver'),
            'host' => config('mail.host'),
            'port' => config('mail.port'),
            'encryption' => config('mail.encryption'),
            'from_address' => data_get(config('mail.from'), 'address'),
            'from_name' => data_get(config('mail.from'), 'name'),
        ]);

        // Send email to candidate
        if (!empty($candidateEmail)) {
            try {
                Mail::to($candidateEmail)->send(new MeetingScheduled($meetingDetails));
                $emailsSent = true;
                \Log::info('Email sent successfully to candidate: ' . $candidateEmail);
            } catch (\Throwable $e) {
                \Log::error('Candidate email failed: ' . $e->getMessage());
            }
        } else {
            \Log::warning('No email found for candidate profile_id: ' . $meeting->candidate_id);
        }

        // Send email to matched profile
        if (!empty($matchedEmail)) {
            try {
                Mail::to($matchedEmail)->send(new MeetingScheduled($meetingDetails));
                $emailsSent = true;
                \Log::info('Email sent successfully to matched profile: ' . $matchedEmail);
            } catch (\Throwable $e) {
                \Log::error('Matched email failed: ' . $e->getMessage());
            }
        } else if ($meeting->other_candidate_id) {
            \Log::warning('No email found for matched profile_id: ' . $meeting->other_candidate_id);
        }

        $successMsg = 'Meeting scheduled successfully' . ($emailsSent ? '. Emails sent successfully' : '. However, emails could not be sent');
        return redirect()->back()->with('success', $successMsg);
    }

   public function getProfileDetails($profileId)
{
/*
|--------------------------------------------------------------------------
| PRIMARY PROFILE
|--------------------------------------------------------------------------
*/


$profile = Profile::findOrFail($profileId);

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

$user = User::where(
    'profile_id',
    $profile->id
)->firstOrFail();

/*
|--------------------------------------------------------------------------
| PREFERENCES
|--------------------------------------------------------------------------
*/

$preferences = Preference::where(
    'user_id',
    $user->id
)->first();

/*
|--------------------------------------------------------------------------
| SENT ACCEPTED INTEREST PROFILE IDS
|--------------------------------------------------------------------------
*/

$sentInterestProfileIds = DB::table(
    'user_interests'
)

->where(
    'user_id',
    $user->id
)

->where(
    'status',
    'accepted'
)

->pluck(
    'profile_id'
)

->toArray();

/*
|--------------------------------------------------------------------------
| RECEIVED ACCEPTED INTEREST PROFILE IDS
|--------------------------------------------------------------------------
*/

$receivedInterestProfileIds = DB::table(
    'user_interests'
)

->join(
    'users',
    'user_interests.user_id',
    '=',
    'users.id'
)

->where(
    'user_interests.profile_id',
    $profileId
)

->where(
    'user_interests.status',
    'accepted'
)

->pluck(
    'users.profile_id'
)

->toArray();

/*
|--------------------------------------------------------------------------
| MERGE BOTH IDS
|--------------------------------------------------------------------------
*/

$allMatchedProfileIds = array_unique(
    array_merge(
        $sentInterestProfileIds,
        $receivedInterestProfileIds
    )
);

/*
|--------------------------------------------------------------------------
| REMOVE CURRENT PROFILE
|--------------------------------------------------------------------------
*/

$allMatchedProfileIds = array_filter(
    $allMatchedProfileIds,
    function ($id)
    use ($profileId) {

        return $id != $profileId;
    }
);

/*
|--------------------------------------------------------------------------
| ALREADY SCHEDULED PROFILE IDS
|--------------------------------------------------------------------------
*/

$scheduledOtherIds = Meeting::where(
    'candidate_id',
    $profileId
)

->pluck(
    'other_candidate_id'
)

->filter()

->all();

/*
|--------------------------------------------------------------------------
| FINAL MATCHED PROFILES
|--------------------------------------------------------------------------
*/

$matchedProfiles = Profile::whereIn(
    'id',
    $allMatchedProfileIds
)

->when(
    !empty($scheduledOtherIds),

    function ($query)
    use ($scheduledOtherIds) {

        return $query->whereNotIn(
            'id',
            $scheduledOtherIds
        );
    }
)

->where(
    'active',
    'active'
)

->where(
    'profileVerificationStatus',
    'Completed'
)

->get();

/*
|--------------------------------------------------------------------------
| BOOKED TIME SLOTS
|--------------------------------------------------------------------------
*/

$bookedSlots = Meeting::where(
    'candidate_id',
    $profileId
)

->pluck(
    'time_slot'
)

->filter()

->all();

/*
|--------------------------------------------------------------------------
| NO ACCEPTED INTEREST FOUND
|--------------------------------------------------------------------------
*/

if ($matchedProfiles->count() == 0) {

    return view(
        'admin.profileMeetings.showMeetingSchedulars',
        compact(
            'profile',
            'preferences',
            'matchedProfiles',
            'bookedSlots'
        )
    )->with(
        'no_interest',
        'No Accepted Interest Found'
    );
}

/*
|--------------------------------------------------------------------------
| RETURN VIEW
|--------------------------------------------------------------------------
*/

return view(
    'admin.profileMeetings.showMeetingSchedulars',
    compact(
        'profile',
        'preferences',
        'matchedProfiles',
        'bookedSlots'
    )
);


}


    public function showMatchmakingDashboard($profileId)
    {
        // Find the primary profile or return a 404 error if not found.
        $profile = Profile::findOrFail($profileId);

        // Fetch all profiles that have an accepted interest from the primary profile.
        $matchedProfiles = Profile::join('user_interests', 'profiles.id', '=', 'user_interests.profile_id')
            ->where('user_interests.user_id', $profile->id)
            ->where('user_interests.status', 'accepted')
            ->select('profiles.*')
            ->get();

        return view('admin.profileMeetings.showMeetingSchedulars', compact('profile', 'matchedProfiles'));
    }


public function getMatchedProfileDetails(Profile $profile)
{
/*
|--------------------------------------------------------------------------
| CANDIDATE PROFILE
|--------------------------------------------------------------------------
*/


$candidateId = request()->input(
    'candidate_id'
);

$candidateProfile = Profile::findOrFail(
    $candidateId
);

$candidateUser = User::where('profile_id', $candidateId)->first();
$candidatePreferences = $candidateUser ? Preference::where('user_id', $candidateUser->id)->first() : null;

/*
|--------------------------------------------------------------------------
| MATCHED PROFILE USER
|--------------------------------------------------------------------------
*/

$user = User::where(
    'profile_id',
    $profile->id
)->first();

/*
|--------------------------------------------------------------------------
| MATCHED PROFILE PREFERENCES
|--------------------------------------------------------------------------
*/

$preferences = $user
    ? Preference::where(
        'user_id',
        $user->id
    )->first()
    : null;

/*
|--------------------------------------------------------------------------
| NORMALIZE FUNCTION
|--------------------------------------------------------------------------
*/

$normalize = function ($value) {

    return strtolower(
        trim(
            (string) $value
        )
    );
};

/*
|--------------------------------------------------------------------------
| AGE FUNCTION
|--------------------------------------------------------------------------
*/

$computeAge = function ($birthDate) {

    if (!$birthDate) {

        return null;
    }

    try {

        return Carbon::parse(
            $birthDate
        )->age;

    } catch (\Exception $e) {

        return null;
    }
};

/*
|--------------------------------------------------------------------------
| PROFILE VS PROFILE MATCHING
|--------------------------------------------------------------------------
*/

$criteria = [

    'religion' =>

        $normalize(
            $candidateProfile->religion
        )

        ==

        $normalize(
            $profile->religion
        ),

    'caste' =>

        $normalize(
            $candidateProfile->caste
        )

        ==

        $normalize(
            $profile->caste
        ),

    'sub_caste' =>

        $normalize(
            $candidateProfile->subCaste
        )

        ==

        $normalize(
            $profile->subCaste
        ),

    'mother_tongue' =>

        $normalize(
            $candidateProfile->motherTongue
        )

        ==

        $normalize(
            $profile->motherTongue
        ),

    'education' =>

        $normalize(
            $candidateProfile->education
        )

        ==

        $normalize(
            $profile->education
        ),

    'profession' =>

        $normalize(
            $candidateProfile->profession
        )

        ==

        $normalize(
            $profile->profession
        ),

    'working_field' =>

        $normalize(
            $candidateProfile->fieldOfWorking
        )

        ==

        $normalize(
            $profile->fieldOfWorking
        ),

    'city' =>

        $normalize(
            $candidateProfile->professionalCity
        )

        ==

        $normalize(
            $profile->professionalCity
        ),

    'family_values' =>

        $normalize(
            $candidateProfile->familyValues
        )

        ==

        $normalize(
            $profile->familyValues
        ),

    'diet' =>

        $normalize(
            $candidateProfile->diet
        )

        ==

        $normalize(
            $profile->diet
        ),

    'smoking' =>

        $normalize(
            $candidateProfile->smoking
        )

        ==

        $normalize(
            $profile->smoking
        ),

    'drinking' =>
        $normalize($candidateProfile->drinking) == $normalize($profile->drinking),

    'marital_status' =>
        $normalize($candidateProfile->maritalStatus) == $normalize($profile->maritalStatus),

    'monthly_income' =>
        $normalize($candidateProfile->monthlyIncome) == $normalize($profile->monthlyIncome),

    'annual_income' =>
        $normalize($candidateProfile->annualIncome) == $normalize($profile->annualIncome),

    'family_financial_status' =>
        $normalize($candidateProfile->familyFinancialStatus) == $normalize($profile->familyFinancialStatus),

    'family_annual_income' =>
        $normalize($candidateProfile->familyAnnualIncome) == $normalize($profile->familyAnnualIncome),
        
    'preferred_religion' =>
        $candidatePreferences && $preferences
        ? $normalize($candidatePreferences->preferred_religion) == $normalize($preferences->preferred_religion)
        : false,

    'preferred_caste' =>
        $candidatePreferences && $preferences
        ? $normalize($candidatePreferences->preferred_caste) == $normalize($preferences->preferred_caste)
        : false,

    'preferred_sub_caste' =>
        $candidatePreferences && $preferences
        ? $normalize($candidatePreferences->preferred_sub_caste) == $normalize($preferences->preferred_sub_caste)
        : false,

    'preferred_location' =>
        $candidatePreferences && $preferences
        ? $normalize($candidatePreferences->preferred_location) == $normalize($preferences->preferred_location)
        : false,

    'preferred_country' =>
        $candidatePreferences && $preferences
        ? $normalize($candidatePreferences->preferred_country) == $normalize($preferences->preferred_country)
        : false,

    'preferred_state' =>
        $candidatePreferences && $preferences
        ? $normalize($candidatePreferences->preferred_state) == $normalize($preferences->preferred_state)
        : false,

    'preferred_city' =>
        $candidatePreferences && $preferences
        ? $normalize($candidatePreferences->preferred_city) == $normalize($preferences->preferred_city)
        : false,
];

/*
|--------------------------------------------------------------------------
| AGE MATCH
|--------------------------------------------------------------------------
*/

$candidateAge = $computeAge(
    $candidateProfile->birthDate
);

$matchedAge = $computeAge(
    $profile->birthDate
);

$criteria['age'] =

    $candidateAge
    &&
    $matchedAge

    ?

    abs(
        $candidateAge
        -
        $matchedAge
    ) <= 2

    :

    false;

/*
|--------------------------------------------------------------------------
| REMOVE NULL VALUES
|--------------------------------------------------------------------------
*/

$criteria = array_filter(

    $criteria,

    function ($value) {

        return $value !== null;
    }
);

/*
|--------------------------------------------------------------------------
| FINAL MATCH SCORE
|--------------------------------------------------------------------------
*/

$totalCriteria = count(
    $criteria
);

$matchedCriteria = count(
    array_filter(
        $criteria
    )
);

$score = $totalCriteria > 0

    ?

    round(
        (
            $matchedCriteria
            /
            $totalCriteria
        ) * 100
    )

    :

    0;

/*
|--------------------------------------------------------------------------
| COMPARISON DATA
|--------------------------------------------------------------------------
*/

$comparison = [

    [
        'field' => 'Religion',
        'candidate' =>
            $candidateProfile->religion,
        'matched' =>
            $profile->religion,
        'matched_status' =>
            $criteria['religion']
    ],

    [
        'field' => 'Caste',
        'candidate' =>
            $candidateProfile->caste,
        'matched' =>
            $profile->caste,
        'matched_status' =>
            $criteria['caste']
    ],

    [
        'field' => 'Sub Caste',
        'candidate' =>
            $candidateProfile->subCaste,
        'matched' =>
            $profile->subCaste,
        'matched_status' =>
            $criteria['sub_caste']
    ],

    [
        'field' => 'Mother Tongue',
        'candidate' =>
            $candidateProfile->motherTongue,
        'matched' =>
            $profile->motherTongue,
        'matched_status' =>
            $criteria['mother_tongue']
    ],

    [
        'field' => 'Education',
        'candidate' =>
            $candidateProfile->education,
        'matched' =>
            $profile->education,
        'matched_status' =>
            $criteria['education']
    ],

    [
        'field' => 'Profession',
        'candidate' =>
            $candidateProfile->profession,
        'matched' =>
            $profile->profession,
        'matched_status' =>
            $criteria['profession']
    ],

    [
        'field' => 'Working Field',
        'candidate' =>
            $candidateProfile->fieldOfWorking,
        'matched' =>
            $profile->fieldOfWorking,
        'matched_status' =>
            $criteria['working_field']
    ],

    [
        'field' => 'City',
        'candidate' =>
            $candidateProfile->professionalCity,
        'matched' =>
            $profile->professionalCity,
        'matched_status' =>
            $criteria['city']
    ],

    [
        'field' => 'Family Values',
        'candidate' =>
            $candidateProfile->familyValues,
        'matched' =>
            $profile->familyValues,
        'matched_status' =>
            $criteria['family_values']
    ],

    [
        'field' => 'Diet',
        'candidate' =>
            $candidateProfile->diet,
        'matched' =>
            $profile->diet,
        'matched_status' =>
            $criteria['diet']
    ],

    [
        'field' => 'Smoking',
        'candidate' =>
            $candidateProfile->smoking,
        'matched' =>
            $profile->smoking,
        'matched_status' =>
            $criteria['smoking']
    ],

    [
        'field' => 'Drinking',
        'candidate' =>
            $candidateProfile->drinking,
        'matched' =>
            $profile->drinking,
        'matched_status' =>
            $criteria['drinking']
    ],

    [
        'field' => 'Age',
        'candidate' =>
            $candidateAge,
        'matched' =>
            $matchedAge,
        'matched_status' =>
            $criteria['age']
    ],
    [
        'field' => 'Marital Status',
        'candidate' => $candidateProfile->maritalStatus,
        'matched' => $profile->maritalStatus,
        'matched_status' => $criteria['marital_status']
    ],
    [
        'field' => 'Monthly Income',
        'candidate' => $candidateProfile->monthlyIncome,
        'matched' => $profile->monthlyIncome,
        'matched_status' => $criteria['monthly_income']
    ],
    [
        'field' => 'Annual Income',
        'candidate' => $candidateProfile->annualIncome,
        'matched' => $profile->annualIncome,
        'matched_status' => $criteria['annual_income']
    ],
    [
        'field' => 'Family Financial Status',
        'candidate' => $candidateProfile->familyFinancialStatus,
        'matched' => $profile->familyFinancialStatus,
        'matched_status' => $criteria['family_financial_status']
    ],
    [
        'field' => 'Family Annual Income',
        'candidate' => $candidateProfile->familyAnnualIncome,
        'matched' => $profile->familyAnnualIncome,
        'matched_status' => $criteria['family_annual_income'] ?? false
    ],
    [
        'field' => 'Preferred Religion',
        'candidate' => $candidatePreferences ? $candidatePreferences->preferred_religion : null,
        'matched' => $preferences ? $preferences->preferred_religion : null,
        'matched_status' => $criteria['preferred_religion'] ?? false
    ],
    [
        'field' => 'Preferred Caste',
        'candidate' => $candidatePreferences ? $candidatePreferences->preferred_caste : null,
        'matched' => $preferences ? $preferences->preferred_caste : null,
        'matched_status' => $criteria['preferred_caste'] ?? false
    ],
    [
        'field' => 'Preferred Sub Caste',
        'candidate' => $candidatePreferences ? $candidatePreferences->preferred_sub_caste : null,
        'matched' => $preferences ? $preferences->preferred_sub_caste : null,
        'matched_status' => $criteria['preferred_sub_caste'] ?? false
    ],
    [
        'field' => 'Preferred Location',
        'candidate' => $candidatePreferences ? $candidatePreferences->preferred_location : null,
        'matched' => $preferences ? $preferences->preferred_location : null,
        'matched_status' => $criteria['preferred_location'] ?? false
    ],
    [
        'field' => 'Preferred Country',
        'candidate' => $candidatePreferences ? $candidatePreferences->preferred_country : null,
        'matched' => $preferences ? $preferences->preferred_country : null,
        'matched_status' => $criteria['preferred_country'] ?? false
    ],
    [
        'field' => 'Preferred State',
        'candidate' => $candidatePreferences ? $candidatePreferences->preferred_state : null,
        'matched' => $preferences ? $preferences->preferred_state : null,
        'matched_status' => $criteria['preferred_state'] ?? false
    ],
    [
        'field' => 'Preferred City',
        'candidate' => $candidatePreferences ? $candidatePreferences->preferred_city : null,
        'matched' => $preferences ? $preferences->preferred_city : null,
        'matched_status' => $criteria['preferred_city'] ?? false
    ],
];

/*
|--------------------------------------------------------------------------
| RETURN RESPONSE
|--------------------------------------------------------------------------
*/

return response()->json(array_merge($profile->toArray(), [
    'profilePhoto' => asset('storage/' . $profile->profilePhoto),
    'match' => [
        'score' => $score,
        'primaryScore' => $score . '%',
        'secondaryScore' => $score . '%',
    ],
    'comparison' => $comparison,
    'preferences' => $preferences,
]));


}


}
