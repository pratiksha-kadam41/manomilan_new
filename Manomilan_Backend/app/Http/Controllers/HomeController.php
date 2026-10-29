<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Exception;

use App\Profile;
use App\Preference;
use App\User;
use App\UserInterest;
use App\ProfileVisitor;
use App\PaymentScreenshot;
use App\SuccessStory;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->only([
            'dashboard',
            'clientDashboard',
            'registration',
        ]);
    }

    public function home()
    {
        $user = Auth::user();

        $totalUsers = User::count();

        $isAdmin = $user && ($user->role_id == 1 || $user->user_type == '00');

        $totalProfilesQuery = Profile::where('active', 'active');
        $verifiedProfilesQuery = Profile::where('active', 'active')
            ->where('profileVerificationStatus', 'Completed');
        $unverifiedProfilesQuery = Profile::where('active', 'active')
            ->where(function ($q) {
                $q->where('profileVerificationStatus', '!=', 'Completed')
                    ->orWhereNull('profileVerificationStatus');
            });
        $genderDistributionQuery = Profile::where('active', 'active');

        if ($user && !$isAdmin) {
            $this->applyRegistrationTypeScope($totalProfilesQuery);
            $this->applyRegistrationTypeScope($verifiedProfilesQuery);
            $this->applyRegistrationTypeScope($unverifiedProfilesQuery);
            $this->applyRegistrationTypeScope($genderDistributionQuery);
        }

        $totalProfiles = $totalProfilesQuery->count();
        $verifiedProfiles = $verifiedProfilesQuery->count();
        $unverifiedProfiles = $unverifiedProfilesQuery->count();

        $genderDistribution = $genderDistributionQuery
            ->selectRaw("groomOrBride, COUNT(*) as count")
            ->groupBy('groomOrBride')
            ->pluck('count', 'groomOrBride');

        $maleCount = $genderDistribution['groom'] ?? 0;
        $femaleCount = $genderDistribution['bride'] ?? 0;

        $genderDistribution = [
            'male' => $maleCount,
            'female' => $femaleCount,
        ];

        $totalMatches = 0;
        $profilesViewed = 0;
        $profileCompletion = 0;

        if ($user && $user->profile_id) {
            $userProfile = Profile::find($user->profile_id);

            if ($userProfile) {
                $query = Profile::query();

                $targetGender = ($userProfile->groomOrBride == 'groom') ? 'bride' : 'groom';
                $query->where('groomOrBride', $targetGender);
                $query->where('id', '!=', $userProfile->id);
                $query->whereHas('user');
                $query->where('profileVerificationStatus', 'Completed');

                // REGISTRATION TYPE VISIBILITY
                if (Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00') {
                    $this->applyRegistrationTypeScope($query);
                }

                $preference = Preference::where('user_id', $user->id)->first();

                if ($preference) {
                    // STRICT FILTER: Age Range Only (caste and religion are not strictly filtered, as per suggestions logic)

                    if ($preference->age_from && $preference->age_to) {
                        $minDate = \Carbon\Carbon::today()->subYears($preference->age_to + 1)->format('Y-m-d');
                        $maxDate = \Carbon\Carbon::today()->subYears($preference->age_from)->format('Y-m-d');
                        $query->whereBetween('birthDate', [$minDate, $maxDate]);
                    }
                }

                $totalMatches = $query->count();

                $visitorUserIds = ProfileVisitor::where('profile_id', $userProfile->id)->pluck('visitor_id');
                $visitorProfileIds = \App\User::whereIn('id', $visitorUserIds)->pluck('profile_id')->filter(function ($val) use ($userProfile) {
                    return $val != $userProfile->id;
                });
                $visitorsQuery = Profile::whereIn('id', $visitorProfileIds)
                    ->where('active', 'active');

                if (Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00') {
                    $this->applyRegistrationTypeScope($visitorsQuery);
                }

                $profilesViewed = $visitorsQuery->count();

                $profileCompletion = $userProfile->calculateCompletionPercentage();
            }
        }

        if ($user && $user->user_type == '21') {
            if ($user->profile_id == null) {
                return redirect()->route('profileRegistration');
            } else {
                $pendingInterestsCount = \App\UserInterest::where('profile_id', $userProfile->id)->where('status', 'pending')->count();
                $lastSeenCount = session('last_seen_pending_interests_count', -1);

                if ($pendingInterestsCount > $lastSeenCount) {
                    if ($pendingInterestsCount > 0) {
                        session()->now('success', "You have received {$pendingInterestsCount} pending interest(s)! Check your Interests section.");
                    }
                    session(['last_seen_pending_interests_count' => $pendingInterestsCount]);
                } else if ($pendingInterestsCount < $lastSeenCount) {
                    // Just update silently if they accepted/rejected one
                    session(['last_seen_pending_interests_count' => $pendingInterestsCount]);
                }

                return view('admin.home', compact(
                    'totalUsers',
                    'totalProfiles',
                    'verifiedProfiles',
                    'unverifiedProfiles',
                    'genderDistribution',
                    'totalMatches',
                    'profilesViewed',
                    'profileCompletion'
                ));
            }
        }
        return view('admin.home', compact(
            'totalUsers',
            'totalProfiles',
            'verifiedProfiles',
            'unverifiedProfiles',
            'genderDistribution',
            'totalMatches',
            'profilesViewed',
            'profileCompletion'
        ));
    }

    public function registrationRedirect(Request $request)
    {
        $type = $request->query('type', 'quick');
        if ($type === 'quick') {
            return view('registration_plan');
        }
        return redirect()->route('profileRegistration', ['type' => $type]);
    }

    public function index()
    {
        return view('welcome');
    }

    public function profileRegistration(Request $request)
    {
        $type = $request->query('type', 'quick');
        $user = Auth::user();

        // If melava, allow guest. If quick, require auth (standard flow)
        if ($type === 'quick' && !$user) {
            return redirect()->route('login');
        }

        $hide_layout = ($type === 'melava');
        return view('admin.registration', compact('user', 'type', 'hide_layout'));
    }

    public function registrationSuccess($id)
    {
        $profile = Profile::findOrFail($id);
        return view('admin.registration_success', compact('profile'));
    }

    /**
     * ==========================================================
     * SIMPLE REGISTRATION FORM SAVE
     * ==========================================================
     */
    public function updateRegistration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female',
            'candidateType' => 'required|in:single,divorced,widowed,Widower',
            'stdCode' => 'nullable|numeric',
            'stdNumber' => 'nullable|numeric',
            'mobileNumber' => 'required|digits:10',
            'email' => 'required|email|max:255',
            'education' => 'nullable|string|max:255',
            'jobType' => 'nullable|in:0,1',
            'workPlace' => 'nullable|string|max:255',
            'monthlyIncome' => 'nullable|numeric',
            'income_currency' => 'nullable|string|in:INR,USD,EUR,GBP',
            'DOB' => 'required|date',
            'birthTime' => 'nullable',
            'birthPlace' => 'nullable|string|max:255',
            'height' => 'nullable|string|max:255',
            'feet' => 'nullable|string|max:255',
            'inch' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
            'kgGram' => 'nullable|string|max:255',
            'subCast' => 'nullable|string|max:255',
            'anySarcasm' => 'nullable|string|max:255',
            'parentName' => 'nullable|string|max:255',
            'parentJobType' => 'nullable|string|max:255',
            'parentMonthlyIncome' => 'nullable|numeric',
            'isGlasses' => 'nullable|in:0,1',
            'isHoroscope' => 'nullable|in:0,1',
            'isNonJain' => 'nullable|in:0,1',
            'nonJainReligion' => 'nullable|string|max:255',
            'whoNonJain' => 'nullable|string|max:255',
            'expectation' => 'nullable|string',
            'extraName' => 'nullable|string|max:255',
            'extraCity' => 'nullable|string|max:255',
            'extraMobileNo' => 'nullable|string|max:255',
            'skinColor' => 'nullable|string|max:255',
            'bloodGroup' => 'nullable|string|max:255',
            'motherTongue' => 'nullable|string|max:255',
            'otherTongue' => 'nullable|string|max:255',
            'bortherMarriedNo' => 'nullable|string|max:255',
            'bortherNotMarriedNo' => 'nullable|string|max:255',
            'sisterMarriedNo' => 'nullable|string|max:255',
            'sisterNotMarriedNo' => 'nullable|string|max:255',
            'companyName' => 'nullable|string|max:255',
            'companyDesignationAndNumber' => 'nullable|string|max:255',
            'hobbies' => 'nullable|string|max:255',
            'profilePhoto' => 'nullable|image|mimes:jpeg,png,jpg|max:51200',

            // NEW CHECKBOX VALIDATION
            'privacyPolicy' => 'required|accepted',
            'termsConditions' => 'required|accepted',
            'acknowledge' => 'required|accepted',
        ], [
            'name.required' => 'Please enter your full name.',
            'gender.required' => 'Please select gender.',
            'candidateType.required' => 'Please select candidate type.',
            'mobileNumber.required' => 'Please enter mobile number.',
            'mobileNumber.digits' => 'Mobile number must be exactly 10 digits.',
            'email.required' => 'Please enter email address.',
            'email.email' => 'Please enter a valid email address.',
            'DOB.required' => 'Please select date of birth.',

            // NEW CHECKBOX MESSAGES
            'privacyPolicy.accepted' => 'Please accept the Privacy Policy.',
            'termsConditions.accepted' => 'Please accept the Terms & Conditions.',
            'acknowledge.accepted' => 'Please accept the declaration before submitting.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $validatedData = $validator->validated();

            // Handle profile photo upload
            $profilePhotoName = null;
            if ($request->hasFile('profilePhoto')) {
                $path = $request->file('profilePhoto')->store('profile_photos', 'public');
                $profilePhotoName = basename($path);
            }

            // Update User table
            $user->name = $validatedData['name'];
            $user->email = $validatedData['email'];

            if ($profilePhotoName) {
                $user->profile_photo = $profilePhotoName;
            }

            $user->save();

            // Convert registration form fields into profiles table columns
            $currency = $request->input('income_currency', 'INR');
            if (!in_array($currency, ['INR', 'USD', 'EUR', 'GBP'])) {
                $currency = 'INR';
            }

            $rate = 1;
            if ($currency === 'USD')
                $rate = 96.56;
            elseif ($currency === 'EUR')
                $rate = 110.73;
            elseif ($currency === 'GBP')
                $rate = 130.53;

            $originalMonthly = $validatedData['monthlyIncome'] ?? null;
            $convertedMonthly = null;
            if (is_numeric($originalMonthly)) {
                $convertedMonthly = floatval($originalMonthly) * $rate;
            }

            $profileData = [
                'user_id' => $user->id,
                'profile_id' => $user->profile_id ?: 'AM' . Str::upper(Str::random(8)),

                // IMPORTANT MAPPING
                'fullName' => $validatedData['name'],
                'birthDate' => $validatedData['DOB'],
                'mobileNumber' => $validatedData['mobileNumber'],
                'bloodGroup' => $validatedData['bloodGroup'] ?? null,
                'motherTongue' => $validatedData['motherTongue'] ?? null,
                'education' => $validatedData['education'] ?? null,
                'monthlyIncome' => $convertedMonthly,
                'income_currency' => $currency,
                'original_monthly_income' => is_numeric($originalMonthly) ? floatval($originalMonthly) : null,
                'original_annual_income' => null,
                'hobbies' => $validatedData['hobbies'] ?? null,
                'height' => $validatedData['height'] ?? null,
                'weight' => $validatedData['weight'] ?? null,
                'subCaste' => $validatedData['subCast'] ?? null,
                'birthPlace' => $validatedData['birthPlace'] ?? null,
                'expectation' => $validatedData['expectation'] ?? null,

                // Save raw form values too (only if these columns exist in DB)
                'stdCode' => $validatedData['stdCode'] ?? null,
                'stdNumber' => $validatedData['stdNumber'] ?? null,
                'candidateType' => $validatedData['candidateType'] ?? null,
                'jobType' => $validatedData['jobType'] ?? null,
                'workPlace' => $validatedData['workPlace'] ?? null,
                'birthTime' => $validatedData['birthTime'] ?? null,
                'feet' => $validatedData['feet'] ?? null,
                'inch' => $validatedData['inch'] ?? null,
                'kgGram' => $validatedData['kgGram'] ?? null,
                'anySarcasm' => $validatedData['anySarcasm'] ?? null,
                'parentName' => $validatedData['parentName'] ?? null,
                'parentJobType' => $validatedData['parentJobType'] ?? null,
                'parentMonthlyIncome' => $validatedData['parentMonthlyIncome'] ?? null,
                'isGlasses' => $validatedData['isGlasses'] ?? null,
                'isHoroscope' => $validatedData['isHoroscope'] ?? null,
                'isNonJain' => $validatedData['isNonJain'] ?? null,
                'nonJainReligion' => $validatedData['nonJainReligion'] ?? null,
                'whoNonJain' => $validatedData['whoNonJain'] ?? null,
                'extraName' => $validatedData['extraName'] ?? null,
                'extraCity' => $validatedData['extraCity'] ?? null,
                'extraMobileNo' => $validatedData['extraMobileNo'] ?? null,
                'skinColor' => $validatedData['skinColor'] ?? null,
                'otherTongue' => $validatedData['otherTongue'] ?? null,
                'bortherMarriedNo' => $validatedData['bortherMarriedNo'] ?? null,
                'bortherNotMarriedNo' => $validatedData['bortherNotMarriedNo'] ?? null,
                'sisterMarriedNo' => $validatedData['sisterMarriedNo'] ?? null,
                'sisterNotMarriedNo' => $validatedData['sisterNotMarriedNo'] ?? null,
                'companyName' => $validatedData['companyName'] ?? null,
                'companyDesignationAndNumber' => $validatedData['companyDesignationAndNumber'] ?? null,
            ];

            if ($profilePhotoName) {
                $profileData['profile_photo'] = $profilePhotoName;

                // keep this only if this column exists in your DB
                $profileData['profilePhoto'] = 'profile_photos/' . $profilePhotoName;
            }

            // Map gender to groomOrBride
            if (($validatedData['gender'] ?? null) === 'Male') {
                $profileData['groomOrBride'] = 'groom';
            } elseif (($validatedData['gender'] ?? null) === 'Female') {
                $profileData['groomOrBride'] = 'bride';
            }

            // Map candidateType to maritalStatus
            if (!empty($validatedData['candidateType'])) {
                $profileData['maritalStatus'] = ucfirst($validatedData['candidateType']);
            }

            $profile = Profile::where('user_id', $user->id)->first();

            if ($profile) {
                $profile->update($profileData);
            } else {
                $profile = Profile::create($profileData);
            }

            if (!$user->profile_id) {
                $user->profile_id = $profile->id;
                $user->save();
            }

            DB::commit();

            return redirect()->route('paymentpage')->with('success', 'Registration completed successfully! Please complete the payment to proceed.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Update Registration Error: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'An error occurred while saving your registration. Please try again.')
                ->withInput();
        }
    }

    public function showProfile(Profile $profile)
    {
        $profile->load('user', 'preference');

        $templatePath = public_path('landingPageAssets/img/templates');
        $templateFiles = [];

        if (File::isDirectory($templatePath)) {
            $files = File::files($templatePath);
            foreach ($files as $file) {
                $templateFiles[] = $file->getFilename();
            }
        }

        return view('admin.show', [
            'profile' => $profile,
            'preference' => $profile->preference,
            'templates' => $templateFiles,
        ]);
    }

    /**
     * ==========================================================
     * MAIN PROFILE FORM SAVE
     * ==========================================================
     */

    public function storeProfile(Request $request)
    {
        // Ensure timeOfBirth is strictly in 24-hour format
        if ($request->has('timeOfBirth') && $request->input('timeOfBirth')) {
            $time = $request->input('timeOfBirth');
            if (preg_match('/(\d{1,2}):(\d{2})(?:\s*(AM|PM))?/i', $time, $matches)) {
                $hh = (int) $matches[1];
                $mm = $matches[2];
                if (isset($matches[3]) && $matches[3]) {
                    $ampm = strtoupper(trim($matches[3]));
                    if ($ampm === 'PM' && $hh < 12)
                        $hh += 12;
                    if ($ampm === 'AM' && $hh === 12)
                        $hh = 0;
                }
                $request->merge(['timeOfBirth' => sprintf('%02d:%02d:00', $hh, $mm)]);
            }
        }

        $regType = $request->input(
            'registration_type',
            'quick'
        );

        // =====================================
        // PREVENT DUPLICATE PROFILES OF SAME TYPE
        // =====================================
        $phoneToCheck = $request->input('phone');
        if (!$phoneToCheck && Auth::check()) {
            $phoneToCheck = Auth::user()->username;
        }

        if ($phoneToCheck) {
            $existingProfile = \App\Profile::where('phone', $phoneToCheck)
                ->where('registration_type', $regType)
                ->first();
            if ($existingProfile) {
                $regTypeName = $regType === 'melava' ? 'Melava' : 'Website';
                return redirect()->back()
                    ->with('error', "A $regTypeName registration with this mobile number already exists.")
                    ->withInput();
            }
        }

        $rules = [

            // BASIC DETAILS
            'groomOrBride' =>
                'required|in:groom,bride',

            'fullName' =>
                'required|string|max:255',

            'profilePhoto' =>
                'required|image|mimes:jpeg,png,jpg|max:51200',

            'religion' =>
                'required|string|max:255',

            'caste' =>
                'required|string|max:255',

            'subCaste' =>
                'required_if:caste,Shwetambar,Digambar|nullable|string|max:255',

            'maritalStatus' =>
                'required|string|max:255',

            'birthDate' =>
                'required|date',

            'height' =>
                'required|string|max:255',

            'weight' =>
                'required|string|max:255',

            'bloodGroup' =>
                'required|string|max:10',

            'havingSpecsLenses' =>
                'required|string|max:255',

            'motherTongue' =>
                'required|string|max:255',

            'language' =>
                'required|string|max:255',

            'address' =>
                'required|string',

            'permanent_address' =>
                'required|string',

            'timeOfBirth' =>
                'required|string|max:255',

            'birthTime_ampm' =>
                'required|string|max:255',

            'placeOfBirth' =>
                'required|string|max:255',

            // EDUCATION
            'education' =>
                'required|string|max:255',

            'degreeDiplomaName1' =>
                'required|string|max:255',

            // PROFESSION
            'profession' =>
                'required|string|max:255',

            'professionalCity' =>
                'required|string|max:255',

            'monthlyIncome' =>
                'required|string|max:255',

            'income_currency' =>
                'nullable|string|in:INR,USD,EUR,GBP',

            'annualIncome' =>
                'nullable|numeric',

            // FAMILY
            'fathersName' =>
                'required|string|max:255',

            'fatherAlive' =>
                'required|in:Yes,No',

            'fathersOccupation' =>
                'required_if:fatherAlive,Yes|nullable|string|max:255',

            'fatherContactNumber' =>
                'required_if:fatherAlive,Yes|nullable|digits:10',

            'mothersName' =>
                'required|string|max:255',

            'motherAlive' =>
                'required|in:Yes,No',

            'mothersOccupation' =>
                'required_if:motherAlive,Yes|nullable|string|max:255',

            'motherContactNumber' =>
                'required_if:motherAlive,Yes|nullable|digits:10',

            'noOfBrothers' =>
                'required|integer',

            'noOfSisters' =>
                'required|integer',

            // REFERENCE DETAILS
            'referenceName' =>
                'required|string|max:255',

            'referenceRelation' =>
                'required|string|max:255',

            'referenceAddress' =>
                'required|string',

            'referenceContactNumber' =>
                'required|string|max:255',

            'referenceEmail' =>
                'nullable|email|max:255',

            // PARTNER PREFERENCES
            'preferred_religion' =>
                'nullable|string|max:255',

            'preferred_caste' =>
                'required|string|max:255',

            'preferred_sub_caste' =>
                'nullable|string|max:255',

            'age_from' =>
                'required|integer|min:18|max:55',

            'age_to' =>
                'required|integer|min:18|max:55',

            'height_from' =>
                'nullable|string|max:255',

            'height_to' =>
                'nullable|string|max:255',

            'preferred_education' =>
                'nullable|string|max:255',

            'working_partner' =>
                'required|string|in:Yes,No,Any',

            'preferred_working_field' =>
                'nullable|string|max:255',

            'expected_income' =>
                'nullable|string|max:255',

            'family_type' =>
                'nullable|string|max:255',

            'financial_background' =>
                'required|string|in:Lower,Middle,Upper',

            'family_values' =>
                'nullable|string|max:255',

            'preferred_country' =>
                'required|string|max:255',

            'preferred_state' =>
                'required|string|max:255',

            'preferred_city' =>
                'nullable|string|max:255',

            'other_expectations' =>
                'nullable|string',

            // FILES
            'identificationProof' =>
                'required|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'birthDateProof' =>
                'required|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'addressProof' =>
                'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'educationProof' =>
                'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'professionProof' =>
                'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'incomeProof' =>
                'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'casteCertificate' =>
                'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'medicalFitnessCertificate' =>
                'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200',

            'additionalPhotos' =>
                'sometimes|array|max:4',

            'additionalPhotos.*' =>
                'image|mimes:jpg,jpeg,png|max:51200',
        ];

        // MELAVA OR GUEST EXTRA VALIDATION
        if ($regType === 'melava' || !Auth::check()) {

            $rules['email'] =
                'required|email';

            $rules['phone'] =
                'required|digits:10';

            if ($regType === 'melava') {
                $rules['source_of_information'] =
                    'required|string|max:255';
            }
        }

        $validator = Validator::make(
            $request->all(),
            $rules
        );

        if ($validator->fails()) {

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {

            $validatedData =
                $validator->validated();

            $currency = $request->input('income_currency', 'INR');
            if (!in_array($currency, ['INR', 'USD', 'EUR', 'GBP'])) {
                $currency = 'INR';
            }

            $rate = 1;
            if ($currency === 'USD')
                $rate = 96.56;
            elseif ($currency === 'EUR')
                $rate = 110.73;
            elseif ($currency === 'GBP')
                $rate = 130.53;

            $originalMonthly = $validatedData['monthlyIncome'] ?? null;
            $originalAnnual = $validatedData['annualIncome'] ?? null;

            $validatedData['income_currency'] = $currency;
            $validatedData['original_monthly_income'] = is_numeric($originalMonthly) ? floatval($originalMonthly) : null;
            $validatedData['original_annual_income'] = is_numeric($originalAnnual) ? floatval($originalAnnual) : null;

            if (is_numeric($originalMonthly)) {
                $validatedData['monthlyIncome'] = floatval($originalMonthly) * $rate;
            }
            if (is_numeric($originalAnnual)) {
                $validatedData['annualIncome'] = floatval($originalAnnual) * $rate;
            }

            // =====================================
            // TEMP FILE STORAGE
            // =====================================

            $fileFields = [

                'profilePhoto',
                'identificationProof',
                'birthDateProof',
                'addressProof',
                'educationProof',
                'professionProof',
                'incomeProof',
                'casteCertificate',
                'medicalFitnessCertificate',
            ];

            foreach ($fileFields as $field) {

                if ($request->hasFile($field)) {

                    $path = $request
                        ->file($field)
                        ->store(
                            'temp_registration',
                            'public'
                        );

                    $validatedData[$field] =
                        $path;
                }
            }

            // ADDITIONAL PHOTOS
            $photoPaths = [];

            if ($request->hasFile('additionalPhotos')) {

                foreach (
                    $request->file(
                        'additionalPhotos'
                    ) as $photo
                ) {

                    $path = $photo->store(
                        'temp_registration',
                        'public'
                    );

                    $photoPaths[] = $path;
                }
            }

            $validatedData['additional_photos'] =
                $photoPaths;

            // PREVENT SERIALIZATION ERROR
            if (isset($validatedData['additionalPhotos'])) {
                unset($validatedData['additionalPhotos']);
            }

            // REGISTRATION TYPE
            $validatedData['registration_type'] =
                $regType;

            // QUICK REGISTRATION USER
            if (Auth::check()) {

                $user = Auth::user();

                $validatedData['user_id'] =
                    $user->id;

                if ($regType === 'quick') {

                    $validatedData['email'] =
                        $user->email;

                    $validatedData['phone'] =
                        $user->username;
                }
            }

            // =====================================
            // STORE SESSION
            // =====================================

            session([
                'registration_form_data' => $validatedData
            ]);

            // Normal Registration (both Groom and Bride - paid flow)
            return view(
                'admin.registration_success',
                [
                    'registration_type' => $regType
                ]
            );

        } catch (\Exception $e) {

            \Log::error(
                'Store Profile Error: ' .
                $e->getMessage()
            );

            return redirect()
                ->back()
                ->with(
                    'error',
                    $e->getMessage()
                )
                ->withInput();
        }
    }


    public function completeFreeRegistration()
    {
        DB::beginTransaction();
        try {

            $tempFiles = session('registration_temp_files');

            if ($tempFiles) {

                $formData = session('registration_form_data');

                foreach ($tempFiles as $key => $path) {
                    $formData[$key] = $path;
                }

                session([
                    'registration_form_data' => $formData
                ]);

            }

            $profile = $this->createProfileFromSession();
            
            DB::commit();

            session()->forget('registration_form_data');
            session()->forget('registration_temp_files');

            return redirect()->route(
                'registration.success',
                $profile->id
            );

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error(
                'Free Registration Error : '.$e->getMessage()
            );
            return redirect()->back()->with(
                'error',
                $e->getMessage()
            );
        }
    }


private function createProfileFromSession($paymentScreenshot = null)
{
    try {

        $formData = session('registration_form_data');

        if (!$formData) {
            throw new \Exception(
                'Registration session expired.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Screenshot
        |--------------------------------------------------------------------------
        */

        if ($paymentScreenshot) {
            $formData['payment_screenshot'] = $paymentScreenshot;
        }

        /*
        |--------------------------------------------------------------------------
        | MELAVA SERIAL NUMBER GENERATION
        |--------------------------------------------------------------------------
        | Format: A3 001, B3 001, C3 001, D3 001, E3 001
        | Uses the same rules as Profile.php.
        |--------------------------------------------------------------------------
        */

        $registrationType = $formData['registration_type'] ?? null;

        $currentMelavaBatch =
            \App\Http\Controllers\Controller::CURRENT_MELAVA_BATCH;

        // Generate a Melava serial number only for the current batch.
        if (
            $registrationType === 'melava' &&
            ($formData['melava_batch'] ?? $currentMelavaBatch)
                === $currentMelavaBatch
        ) {

            if (empty($formData['birthDate'])) {
                throw new \Exception(
                    'Birth date is required to generate the serial number.'
                );
            }

            $birthYear = \Carbon\Carbon::parse(
                $formData['birthDate']
            )->year;

            $maritalStatus = strtolower(
                trim($formData['maritalStatus'] ?? '')
            );

            /*
            |--------------------------------------------------------------------------
            | Determine Prefix
            |--------------------------------------------------------------------------
            */

            $specialStatuses = [
                'widow',
                'widower',
                'divorcee',
                'divorced',
                'widowed',
                'separated',
            ];

            if (in_array($maritalStatus, $specialStatuses, true)) {

                $serialPrefix = 'E3';

            } elseif ($birthYear >= 1998) {

                $serialPrefix = 'A3';

            } elseif ($birthYear >= 1992 && $birthYear <= 1997) {

                $serialPrefix = 'B3';

            } elseif ($birthYear >= 1984 && $birthYear <= 1991) {

                $serialPrefix = 'C3';

            } else {

                $serialPrefix = 'D3';

            }

            /*
            |--------------------------------------------------------------------------
            | Find Highest Existing Serial Number
            |--------------------------------------------------------------------------
            */

            $lastSerialProfile = Profile::where(
                'melava_batch',
                $currentMelavaBatch
            )
                ->where(
                    'serial_no',
                    'LIKE',
                    $serialPrefix . ' %'
                )
                ->orderByRaw(
                    'CAST(SUBSTRING(serial_no, ' .
                    (strlen($serialPrefix) + 2) .
                    ') AS UNSIGNED) DESC'
                )
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Generate Next Serial Number
            |--------------------------------------------------------------------------
            */

            $nextSerialNumber = 1;

            if (
                $lastSerialProfile &&
                preg_match(
                    '/^' .
                    preg_quote($serialPrefix, '/') .
                    ' (\d+)$/',
                    $lastSerialProfile->serial_no,
                    $matches
                )
            ) {
                $nextSerialNumber = (int) $matches[1] + 1;
            }

            /*
            |--------------------------------------------------------------------------
            | Final Format
            |--------------------------------------------------------------------------
            */

            $formData['melava_batch'] = $currentMelavaBatch;

            $formData['serial_no'] = $serialPrefix . ' ' .
                str_pad(
                    $nextSerialNumber,
                    3,
                    '0',
                    STR_PAD_LEFT
                );
        }

        /*
        |--------------------------------------------------------------------------
        | User Creation
        |--------------------------------------------------------------------------
        */

        $phone = $formData['phone'] ?? null;

        $targetUser = User::where(
            'username',
            $phone
        )->first();

        if (!$targetUser) {

            $targetUser = User::create([

                'username' => $phone,

                'name' => $formData['fullName'],

                'password' => Hash::make('manomilana'),

                'user_type' => 21,

                'role_id' => 3,

                'status' => 'active',

                'is_password_changed' => 1,

                'email' => $formData['email']
                    ?? ($phone . '@aaryansmanomilan.com'),

            ]);
        }

        $formData['user_id'] = $targetUser->id;

        /*
        |--------------------------------------------------------------------------
        | Create Profile
        |--------------------------------------------------------------------------
        */

        $profile = Profile::create($formData);

        $targetUser->profile_id = $profile->id;

        $targetUser->save();

        /*
        |--------------------------------------------------------------------------
        | Create Preference
        |--------------------------------------------------------------------------
        */

        Preference::create($formData);

        /*
        |--------------------------------------------------------------------------
        | Payment Screenshot
        |--------------------------------------------------------------------------
        */

        if ($paymentScreenshot) {

            PaymentScreenshot::create([

                'profile_id' => $profile->id,

                'screenshot_path' => $paymentScreenshot,

            ]);
        }

        return $profile;

    } catch (\Exception $e) {

        throw $e;

    }
}



    public function storePreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'preferred_religion' => 'nullable|string|max:255',
            'preferred_caste' => 'nullable|string|max:255',
            'preferred_sub_caste' => 'nullable|string|max:255',
            'age_from' => 'nullable|integer|min:18',
            'age_to' => 'nullable|integer|gte:age_from',
            'height_from' => 'nullable|string|max:255',
            'height_to' => 'nullable|string|max:255',
            'preferred_education' => 'nullable|string|max:255',
            'working_partner' => 'nullable|string|max:255',
            'preferred_working_field' => 'nullable|string|max:255',
            'expected_income' => 'nullable|string|max:255',
            'family_type' => 'nullable|string|max:255',
            'financial_background' => 'nullable|string|max:255',
            'family_values' => 'nullable|string|max:255',
            'preferred_location' => 'nullable|string|max:255',
            'preferred_country' => 'nullable|string|max:255',
            'preferred_state' => 'nullable|string|max:255',
            'preferred_city' => 'nullable|string|max:255',
            'other_expectations' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        Preference::updateOrCreate(
            ['user_id' => Auth::id()],
            $validator->validated()
        );

        return redirect()->back()->with('success', 'Your preferences have been saved successfully!');
    }

    public function showPreference()
    {
        $profile = Profile::find(Auth::user()->profile_id);

        if (!$profile) {
            return redirect()->route('preferenceForm')->with('error', 'You must create a profile first.');
        }

        $preferences = Preference::where('user_id', Auth::id())->first();

        if (!$preferences) {
            return redirect()->route('preferenceForm')->with('info', 'You have not saved any preferences yet.');
        }

        return view('admin.showPreference', compact('preferences'));
    }

    public function signUp(Request $request)
    {
        try {
            $request->validate([
                'gender' => 'nullable|string|in:Male,Female',
                'name' => 'nullable|string|regex:/^[a-zA-Z\s\'-]+$/|min:2',
                'surname' => 'nullable|string|regex:/^[a-zA-Z\s\'-]+$/|min:2',
                'marital_status' => 'nullable|string|in:Unmarried,Married,Divorced,Widowed,Separated',
                'caste' => 'nullable|string|min:2',
                'religion' => 'nullable|string|min:2',
                'state' => 'nullable|string|min:2',
                'city' => 'nullable|string|min:2',
                'mobile' => 'nullable|digits:10',
            ]);

            $user = User::create([
                'name' => $request->name . ' ' . $request->surname,
                'email' => $request->mobile . '@temp.com',
                'username' => $request->mobile,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'caste' => $request->caste,
                'religion' => $request->religion,
                'state' => $request->state,
                'city' => $request->city,
                'mobile_number' => $request->mobile,
                'user_type' => '21',
                'role_id' => '3',
                'password' => Hash::make('password'),
            ]);

            Auth::login($user);

            return redirect()->route('profileRegistration', ['type' => 'quick'])->with('success', 'Registration completed successfully! Please complete your profile.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (Exception $e) {
            Log::error('SignUp Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Registration failed. Please try again.')->withInput();
        }
    }

    public function FAQ()
    {
        return view('FAQ');
    }
    

    public function aboutus()
    {
        return view('aboutus');
    }

    public function howItWorks()
    {
        return view('howItWorks');
    }

    public function ourServices()
    {
        return view('ourServices');
    }

    public function eventsWorkshops()
    {
        return view('eventsWorkshops');
    }

    public function contactus()
    {
        return view('contactus');
    }

    public function transactionProccess()
    {
        return view('transactionProccess');
    }

    public function preferenceForm()
    {
        $user = Auth::user();
        $preference = Preference::where('user_id', $user->id)->first();
        return view('admin.preferenceForm', compact('user', 'preference'));
    }

    public function termsAndCondition()
    {
        return view('termsAndCondition');
    }

    public function legalPolicy()
    {
        return view('legalPolicy');
    }

    public function refundPolicy()
    {
        return view('refundPolicy');
    }

    public function disclaimer()
    {
        return view('disclaimer');
    }

    public function paymentPage()
    {
        return view('paymentpage');
    }


    public function uploadPaymentScreenshot(Request $request)
    {
        // VALIDATE
        $validated = $request->validate([
            'payment_screenshot' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        // GET SESSION DATA
        $formData = session('registration_form_data');

        $tempFiles = session('registration_temp_files');

        if (!$formData) {

            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please fill the form again.'
            ]);
        }

        // MERGE TEMP FILES
        if ($tempFiles) {
            foreach ($tempFiles as $key => $path) {
                $formData[$key] = $path;
            }
            session([
                'registration_form_data' => $formData
            ]);
        }

        // PREVENT DUPLICATE SUBMISSIONS (ATOMIC LOCK)
        $phoneForCheck = $formData['phone'] ?? (Auth::check() ? Auth::user()->username : null);
        if ($phoneForCheck) {
            $lockKey = 'submit_payment_' . $phoneForCheck;
            if (!\Illuminate\Support\Facades\Cache::add($lockKey, true, 60)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your submission is already processing. Please wait.'
                ]);
            }
        }

        DB::beginTransaction();

        try {

            // =====================================
            // UPLOAD PAYMENT SCREENSHOT
            // =====================================

            $filename = null;

            if ($request->hasFile('payment_screenshot')) {

                $file = $request->file('payment_screenshot');

                $filename =
                    time() .
                    '_payment_screenshot.' .
                    $file->getClientOriginalExtension();

                if (!file_exists(public_path('uploads/payment_screenshots'))) {
                    mkdir(public_path('uploads/payment_screenshots'), 0777, true);
                }
                $file->move(
                    public_path('uploads/payment_screenshots'),
                    $filename
                );
            }

            // $formData['payment_screenshot'] =
            //     'uploads/payment_screenshots/' . $filename;

            $profile = $this->createProfileFromSession(
    'uploads/payment_screenshots/' . $filename
);

session()->forget('registration_form_data');
session()->forget('registration_temp_files');

DB::commit();

if (isset($lockKey)) {
    \Illuminate\Support\Facades\Cache::forget($lockKey);
}

return response()->json([

    'success' => true,

    'redirect_url' => route(
        'registration.success',
        $profile->id
    )

]);

        } catch (\Exception $e) {

            DB::rollBack();

            if (isset($lockKey)) {
                \Illuminate\Support\Facades\Cache::forget($lockKey);
            }

            \Log::error(
                'Payment Upload Error: ' .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

public function successStories()
{
    $stories = SuccessStory::where('status', 1)
                ->orderBy('display_order', 'ASC')
                ->paginate(10);

    return view('website.success-stories', compact('stories'));
}

// public function successStoryDetails($id)
// {
//     $story = SuccessStory::where('status',1)->findOrFail($id);

//     return view('website.success-story-details', compact('story'));
// }


    public function deleteProfile($id)
    {
        try {
            $profile = Profile::findOrFail($id);

            if ($profile->profilePhoto && Storage::disk('public')->exists($profile->profilePhoto)) {
                Storage::disk('public')->delete($profile->profilePhoto);
            }

            $profile->delete();

            return redirect()->back()->with('success', 'Profile deleted successfully!');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete profile.');
        }
    }
}