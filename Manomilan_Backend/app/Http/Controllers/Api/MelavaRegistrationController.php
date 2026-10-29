<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaPendingRegistration;
use App\MelavaRegistration;
use App\Models\User;
use App\PaymentScreenshot;
use App\Preference;
use App\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MelavaRegistrationController extends Controller
{
    public function settings(Request $request)
    {
        $campaigns = DB::table('melava_campaigns')
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get()
            ->map(function ($campaign) {
                $qrPath = (string) ($campaign->qr_image_path ?? '');

                return [
                    'id' => (int) $campaign->id,
                    'title' => $campaign->title,
                    'description' => $campaign->description,
                    'currency' => $campaign->currency,
                    'fee' => (float) $campaign->registration_fee,
                    'booklet_available' => (bool) $campaign->booklet_available,
                    'booklet_fee' => (float) $campaign->booklet_fee,
                    'qr_image_url' => preg_match('#^https?://#i', $qrPath)
                        ? $qrPath
                        : ($qrPath !== '' ? $request->getSchemeAndHttpHost().'/'.ltrim($qrPath, '/') : ''),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'campaigns' => $campaigns,
            ],
        ]);
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $registrations = MelavaRegistration::with('profile')
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        $registrations->getCollection()->transform(
            fn (MelavaRegistration $registration) => $this->presentRegistration($registration)
        );

        return response()->json(['success' => true, 'data' => $registrations]);
    }

    public function updatePaymentStatus(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'payment_verified' => ['required', 'boolean'],
        ]);

        $registration = MelavaRegistration::with('profile')->find($id);

        if (! $registration) {
            return response()->json([
                'success' => false,
                'message' => 'Melava registration not found.',
            ], 404);
        }

        try {
            DB::transaction(function () use ($registration, $data) {
                $registration->payment_verified = (bool) $data['payment_verified'];
                $registration->save();

                if ($registration->profile_id) {
                    PaymentScreenshot::where('profile_id', $registration->profile_id)
                        ->update(['payment_verified' => (bool) $data['payment_verified']]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => $data['payment_verified']
                    ? 'Payment marked as verified.'
                    : 'Payment marked as pending.',
                'data' => [
                    'registration_id' => $registration->id,
                    'payment_verified' => (bool) $registration->fresh()->payment_verified,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Melava payment status update failed', [
                'registration_id' => $id,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the payment status right now.',
            ], 500);
        }
    }

    /**
     * Look up a mobile number so the public form can restore an existing
     * candidate's details or an unfinished Melava registration.
     */
    public function check(Request $request)
    {
        $validated = $request->validate([
            'mobile' => ['required', 'digits:10'],
        ]);

        $mobile = $validated['mobile'];

        $pending = MelavaPendingRegistration::where('mobile', $mobile)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if ($pending) {
            $pendingData = json_decode((string) $pending->registration_data, true);

            if (is_array($pendingData)) {
                return response()->json([
                    'success' => true,
                    'type' => 'pending_registration',
                    'message' => 'Your previous Melava registration was found. Your saved details can be restored.',
                    'pending_registration' => [
                        'token' => $pending->token,
                        'registration_data' => $pendingData,
                        'is_existing_website_user' => (bool) $pending->is_existing_website_user,
                        'user_id' => $pending->user_id,
                        'profile_id' => $pending->profile_id,
                    ],
                ]);
            }
        }

        $user = User::where('username', $mobile)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'type' => 'new_candidate',
                'message' => 'No previous registration was found. You can continue as a new candidate.',
            ]);
        }

        $profile = $user->profile_id ? Profile::find($user->profile_id) : null;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'type' => 'website_user_without_profile',
                'message' => 'A website account exists, but its profile information is unavailable.',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'profile_id' => $user->profile_id,
                ],
            ]);
        }

        $campaignId = $request->integer('melava_campaign_id');
        $registrationQuery = MelavaRegistration::where('username', $mobile);
        if ($campaignId > 0) {
            $registrationQuery->where('melava_campaign_id', $campaignId);
        } else {
            $registrationQuery->where('melava_batch', self::CURRENT_MELAVA_BATCH);
        }
        $registration = $registrationQuery->latest('id')->first();

        return response()->json([
            'success' => true,
            'type' => 'website_user',
            'message' => 'Website registration found. Your details have been filled in for you.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'profile_id' => $user->profile_id,
            ],
            'profile' => $this->profileForRestore($profile),
            'preference' => $user->preference,
            'has_registration' => (bool) $registration,
            'registration' => $registration
                ? [
                    'id' => $registration->id,
                    'melava_batch' => $registration->melava_batch,
                    'melava_registered_at' => $registration->melava_registered_at,
                    'is_booklet_req' => (bool) $registration->is_booklet_req,
                ]
                : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeInput($request);

        $data = $request->validate([
            'melava_campaign_id' => ['required', 'integer', 'exists:melava_campaigns,id'],
            'is_existing_website_user' => ['required', 'boolean'],
            'username' => ['required', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'fullName' => ['required', 'string', 'max:255'],
            'groomOrBride' => ['required', Rule::in(['groom', 'bride'])],
            'birthDate' => ['required', 'date', 'before:today'],
            'maritalStatus' => ['required', 'string', 'max:50'],
            'religion' => ['required', 'string', 'max:255'],
            'caste' => ['required', 'string', 'max:255'],
            'subCaste' => ['nullable', 'string', 'max:255'],
            'height' => ['required', 'string', 'max:50'],
            'weight' => ['nullable', 'string', 'max:50'],
            'havingSpecsLenses' => ['nullable', 'string', 'max:10'],
            'bloodGroup' => ['nullable', 'string', 'max:10'],
            'motherTongue' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:255'],
            'specialCases' => ['nullable', 'string', 'max:5000'],
            'knowAboutMyself' => ['nullable', 'string', 'max:5000'],
            'source_of_information' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:5000'],
            'permanent_address' => ['nullable', 'string', 'max:5000'],
            'timeOfBirth' => ['nullable', 'date_format:H:i'],
            'birthTime_ampm' => ['nullable', Rule::in(['AM', 'PM'])],
            'placeOfBirth' => ['nullable', 'string', 'max:5000'],
            'education' => ['required', 'string', 'max:255'],
            'degreeDiplomaName1' => ['nullable', 'string', 'max:5000'],
            'degreeDiplomaName2' => ['nullable', 'string', 'max:5000'],
            'specialisation1' => ['nullable', 'string', 'max:5000'],
            'specialisation2' => ['nullable', 'string', 'max:5000'],
            'universityBoardCollege' => ['nullable', 'string', 'max:5000'],
            'additionalEducation1' => ['nullable', 'string', 'max:5000'],
            'additionalEducation2' => ['nullable', 'string', 'max:5000'],
            'cityOfEducation' => ['nullable', 'string', 'max:5000'],
            'profession' => ['required', 'string', 'max:5000'],
            'fieldOfWorking' => ['nullable', 'string', 'max:5000'],
            'organisationWorkingIn' => ['nullable', 'string', 'max:5000'],
            'professionalCity' => ['required', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:5000'],
            'designation' => ['nullable', 'string', 'max:5000'],
            'lengthOfEmployment' => ['nullable', 'string', 'max:255'],
            'monthlyIncome' => ['nullable', 'string', 'max:255'],
            'annualIncome' => ['nullable', 'string', 'max:255'],
            'income_currency' => ['nullable', 'string', 'max:10'],
            'hobbies' => ['nullable', 'string', 'max:5000'],
            'extraCurricularActivities' => ['nullable', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
            'sportsAndFitnessLikings' => ['nullable', 'string', 'max:5000'],
            'cookingSkills' => ['nullable', 'string', 'max:255'],
            'activeOnSocialSites' => ['nullable', 'string', 'max:20'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'hasMedicalHistory' => ['nullable', 'string', 'max:20'],
            'personalMedicalHistory' => ['nullable', 'string', 'max:5000'],
            'currentMedicalProblems' => ['nullable', 'string', 'max:5000'],
            'medications' => ['nullable', 'string', 'max:5000'],
            'parents_contact' => ['nullable', 'string', 'max:20'],
            'fathersName' => ['nullable', 'string', 'max:255'],
            'fatherAlive' => ['nullable', 'string', 'max:20'],
            'fatherContactNumber' => ['nullable', 'string', 'max:30'],
            'fathersOccupation' => ['nullable', 'string', 'max:5000'],
            'fathersNativePlace' => ['nullable', 'string', 'max:5000'],
            'mothersName' => ['nullable', 'string', 'max:255'],
            'motherAlive' => ['nullable', 'string', 'max:20'],
            'motherContactNumber' => ['nullable', 'string', 'max:30'],
            'mothersOccupation' => ['nullable', 'string', 'max:5000'],
            'mothersNativePlace' => ['nullable', 'string', 'max:5000'],
            'noOfBrothers' => ['nullable', 'integer', 'min:0', 'max:20'],
            'brothersMarried' => ['nullable', 'integer', 'min:0', 'max:20'],
            'noOfSisters' => ['nullable', 'integer', 'min:0', 'max:20'],
            'sistersMarried' => ['nullable', 'integer', 'min:0', 'max:20'],
            'parentsIntercasteMarriage' => ['nullable', 'string', 'max:20'],
            'parentsDifferentCaste' => ['nullable', 'string', 'max:5000'],
            'familyValues' => ['nullable', 'string', 'max:255'],
            'familyFinancialStatus' => ['nullable', 'string', 'max:255'],
            'familyAnnualIncome' => ['nullable', 'string', 'max:255'],
            'profileCreatorName' => ['nullable', 'string', 'max:255'],
            'profileCreatorRelation' => ['nullable', 'string', 'max:255'],
            'referenceName' => ['nullable', 'string', 'max:255'],
            'referenceRelation' => ['nullable', 'string', 'max:255'],
            'referenceAddress' => ['nullable', 'string', 'max:5000'],
            'referenceContactNumber' => ['nullable', 'string', 'max:30'],
            'referenceEmail' => ['nullable', 'email', 'max:255'],
            'preferred_religion' => ['nullable', 'string', 'max:255'],
            'preferred_caste' => ['nullable', 'string', 'max:255'],
            'preferred_sub_caste' => ['nullable', 'string', 'max:255'],
            'age_from' => ['nullable', 'integer', 'min:18', 'max:99'],
            'age_to' => ['nullable', 'integer', 'min:18', 'max:99'],
            'height_from' => ['nullable', 'string', 'max:50'],
            'height_to' => ['nullable', 'string', 'max:50'],
            'preferred_education' => ['nullable', 'string', 'max:255'],
            'working_partner' => ['nullable', 'string', 'max:255'],
            'preferred_working_field' => ['nullable', 'string', 'max:255'],
            'expected_income' => ['nullable', 'string', 'max:255'],
            'family_type' => ['nullable', 'string', 'max:255'],
            'financial_background' => ['nullable', 'string', 'max:255'],
            'family_values' => ['nullable', 'string', 'max:255'],
            'preferred_country' => ['nullable', 'string', 'max:255'],
            'preferred_state' => ['nullable', 'string', 'max:255'],
            'preferred_city' => ['nullable', 'string', 'max:255'],
            'preferred_location' => ['nullable', 'string', 'max:255'],
            'other_expectations' => ['nullable', 'string', 'max:5000'],
            'booklet_required' => ['nullable', 'boolean'],
            'biodata' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'profilePhoto' => ['nullable', 'image', 'max:5120'],
            'payment_screenshot' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'identificationProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'birthDateProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'educationProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'additionalPhotos' => ['nullable', 'array', 'max:4'],
            'additionalPhotos.*' => ['image', 'max:5120'],
            'candidate_photos' => ['nullable', 'array', 'max:5'],
            'candidate_photos.*' => ['image', 'max:5120'],
            'privacyPolicyCheck' => ['accepted'],
            'termsCheck' => ['accepted'],
            'disclaimer' => ['nullable', 'in:0,1'],
        ]);

        $campaign = DB::table('melava_campaigns')
            ->where('id', $data['melava_campaign_id'])
            ->where('status', 'active')
            ->first();

        if (! $campaign) {
            throw ValidationException::withMessages([
                'melava_campaign_id' => ['This Melava event is no longer open for registration.'],
            ]);
        }

        if ($request->boolean('booklet_required') && ! $campaign->booklet_available) {
            throw ValidationException::withMessages([
                'booklet_required' => ['A booklet is not available for this Melava event.'],
            ]);
        }

        $registrationFee = (float) $campaign->registration_fee;
        $bookletFee = $request->boolean('booklet_required')
            ? (float) $campaign->booklet_fee
            : 0;
        $totalAmount = $registrationFee + $bookletFee;

        $mobile = $data['username'];
        $isExisting = (bool) $data['is_existing_website_user'];
        $existingUser = User::where('username', $mobile)->first();

        if ($isExisting && ! $existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'This mobile number is not registered on the website. Please continue as a new candidate.',
            ], 404);
        }

        if (! $isExisting && $existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'This mobile number is already website registered. Please select "Yes, I am already registered" and fetch your details.',
            ], 409);
        }

        if (! $isExisting && ! empty($data['email']) && User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered.'],
            ]);
        }

        try {
            $result = DB::transaction(function () use ($request, $data, $mobile, $isExisting, $existingUser, $campaign, $registrationFee, $bookletFee, $totalAmount) {
                $profile = $existingUser && $existingUser->profile_id
                    ? Profile::find($existingUser->profile_id)
                    : null;

                $profilePhoto = $request->hasFile('profilePhoto')
                    ? $this->storeUpload($request->file('profilePhoto'), 'profile_photos')
                    : null;
                $identificationProof = $request->hasFile('identificationProof')
                    ? $this->storeUpload($request->file('identificationProof'), 'melava_documents')
                    : null;
                $birthDateProof = $request->hasFile('birthDateProof')
                    ? $this->storeUpload($request->file('birthDateProof'), 'melava_documents')
                    : null;
                $educationProof = $request->hasFile('educationProof')
                    ? $this->storeUpload($request->file('educationProof'), 'melava_documents')
                    : null;
                $biodata = $request->hasFile('biodata')
                    ? $this->storeUpload($request->file('biodata'), 'melava_biodata')
                    : null;
                $additionalPhotos = $this->storeUploads($request->file('additionalPhotos', []), 'melava_additional_photos');
                $candidatePhotos = $this->storeUploads($request->file('candidate_photos', []), 'melava_candidate_photos');
                $paymentScreenshot = $this->storeUpload($request->file('payment_screenshot'), 'payment_screenshots');

                if (! $existingUser) {
                    $email = $data['email'] ?? null;
                    if (! $email) {
                        $email = $mobile.'@melava.com';
                        $suffix = 1;
                        while (User::where('email', $email)->exists()) {
                            $email = $mobile.'.'.$suffix.'@melava.com';
                            $suffix++;
                        }
                    }

                    $user = User::create([
                        'username' => $mobile,
                        'name' => $data['fullName'],
                        'email' => $email,
                        'password' => Hash::make('manomilana'),
                        'user_type' => 21,
                        'role_id' => 3,
                        'status' => 'active',
                        'is_password_changed' => 0,
                    ]);
                } else {
                    $user = $existingUser;
                    $user->name = $data['fullName'];
                    $user->save();
                }

                $profileData = $this->profilePayload($request);
                $profileData['phone'] = $mobile;
                $profileData['email'] = $data['email'] ?? $user->email;
                $profileData['source_of_information'] = $data['source_of_information'];
                $profileData['professionalCity'] = $data['professionalCity'] ?: ($data['city'] ?? '');
                $profileData['income_currency'] = $data['income_currency'] ?? 'INR';
                $profileData['profilePhoto'] = $profilePhoto
                    ?: ($profile?->profilePhoto ?: 'default.png');

                if ($identificationProof) {
                    $profileData['identificationProof'] = $identificationProof;
                }
                if ($birthDateProof) {
                    $profileData['birthDateProof'] = $birthDateProof;
                }
                if ($educationProof) {
                    $profileData['educationProof'] = $educationProof;
                }
                if ($additionalPhotos) {
                    $profileData['additional_photos'] = $additionalPhotos;
                }

                if (! $profile) {
                    $profileData['registration_type'] = 'melava';
                    $profileData['melava_batch'] = self::CURRENT_MELAVA_BATCH;
                    $profileData['profileVerificationStatus'] = 'Pending';
                    $profile = Profile::create($profileData);

                    $user->profile_id = $profile->id;
                    $user->save();
                } else {
                    $profileData['melava_batch'] = self::CURRENT_MELAVA_BATCH;

                    if (! in_array($profile->registration_type, ['melava', 'existing'], true)) {
                        $profileData['registration_type'] = 'existing';
                        $profileData['serial_no'] = null;
                    } elseif (! Profile::hasCurrentMelavaSerial($profile->serial_no, $profile->melava_batch)) {
                        $profileData['serial_no'] = null;
                    }

                    $profile->fill($profileData);
                    $profile->save();
                }

                $preferenceData = $this->preferencePayload($request);
                $preferenceData['preferred_location'] = $data['preferred_location']
                    ?? trim(implode(', ', array_filter([
                        $data['preferred_city'] ?? null,
                        $data['preferred_state'] ?? null,
                        $data['preferred_country'] ?? null,
                    ])));
                Preference::updateOrCreate(['user_id' => $user->id], $preferenceData);

                $registration = MelavaRegistration::firstOrNew([
                    'username' => $mobile,
                    'melava_campaign_id' => $campaign->id,
                ]);
                $wasUpdate = $registration->exists;

                if (! $registration->link_token) {
                    $registration->link_token = Str::random(40);
                }

                $registration->fill([
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                    'is_existing_website_user' => $isExisting ? 1 : 0,
                    'fullName' => $data['fullName'],
                    'groomOrBride' => $data['groomOrBride'],
                    'birthDate' => $data['birthDate'],
                    'username' => $mobile,
                    'maritalStatus' => $data['maritalStatus'],
                    'caste' => $data['caste'],
                    'subCaste' => $data['subCaste'] ?? null,
                    'education' => $data['education'],
                    'profession' => $data['profession'],
                    'city' => $data['professionalCity'] ?: ($data['city'] ?? ''),
                    'address' => $data['address'],
                    'height' => $data['height'],
                    'parents_contact' => $data['parents_contact'] ?? null,
                    'fathersName' => $data['fathersName'] ?? null,
                    'mothersName' => $data['mothersName'] ?? null,
                    'noOfBrothers' => $data['noOfBrothers'] ?? null,
                    'noOfSisters' => $data['noOfSisters'] ?? null,
                    'age_from' => $data['age_from'] ?? null,
                    'age_to' => $data['age_to'] ?? null,
                    'height_from' => $data['height_from'] ?? null,
                    'height_to' => $data['height_to'] ?? null,
                    'preferred_education' => $data['preferred_education'] ?? null,
                    'preferred_working_field' => $data['preferred_working_field'] ?? null,
                    'preferred_caste' => $data['preferred_caste'] ?? null,
                    'preferred_location' => $preferenceData['preferred_location'] ?: null,
                    'other_expectations' => $data['other_expectations'] ?? '',
                    'source_of_information' => $data['source_of_information'],
                    'birth_verification_doc' => $birthDateProof ?: $registration->birth_verification_doc,
                    'candidate_photos' => $candidatePhotos ?: $registration->candidate_photos,
                    'biodata' => $biodata ?: $registration->biodata,
                    'melava_batch' => self::CURRENT_MELAVA_BATCH,
                    'melava_registered_at' => now(),
                    'is_booklet_req' => $request->boolean('booklet_required'),
                    'melava_title' => $campaign->title,
                    'registration_fee' => $registrationFee,
                    'booklet_fee' => $bookletFee,
                    'total_amount' => $totalAmount,
                    'payment_screenshot' => $paymentScreenshot,
                    'payment_verified' => false,
                ]);
                $registration->save();

                PaymentScreenshot::updateOrCreate(
                    ['profile_id' => $profile->id],
                    [
                        'screenshot_path' => $paymentScreenshot,
                        'payment_verified' => false,
                    ]
                );

                return [
                    'registration' => $registration,
                    'profile' => $profile,
                    'user' => $user,
                    'was_update' => $wasUpdate,
                    'is_new_user' => ! $existingUser,
                ];
            });

            $registration = $result['registration'];
            $profile = $result['profile'];

            return response()->json([
                'success' => true,
                'message' => $result['was_update']
                    ? 'Your Melava registration has been updated successfully.'
                    : 'Melava registration completed successfully.',
                'data' => [
                    'registration_id' => $registration->id,
                    'user_id' => $result['user']->id,
                    'profile_id' => $profile->id,
                    'registration_number' => $profile->profile_id,
                    'serial_no' => $profile->serial_no,
                    'melava_batch' => $registration->melava_batch,
                    'melava_title' => $registration->melava_title,
                    'is_booklet_req' => (bool) $registration->is_booklet_req,
                    'total_amount' => (float) $registration->total_amount,
                    'payment_verified' => (bool) $registration->payment_verified,
                ],
                'credentials' => $result['is_new_user']
                    ? [
                        'username' => $mobile,
                        'password' => 'manomilana',
                    ]
                    : null,
            ], $result['was_update'] ? 200 : 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Melava registration failed', [
                'mobile' => $mobile,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete the Melava registration right now. Please try again.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $registration = MelavaRegistration::with('profile')->find($id);

        if (! $registration) {
            return response()->json(['success' => false, 'message' => 'Registration not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->presentRegistration($registration),
        ]);
    }

    private function presentRegistration(MelavaRegistration $registration): array
    {
        return array_merge($registration->toArray(), [
            'payment_screenshot_url' => $this->fileUrl($registration->payment_screenshot),
        ]);
    }

    private function fileUrl($path): string
    {
        if (! is_string($path) || trim($path) === '') {
            return '';
        }

        $path = trim($path);

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return url(ltrim($path, '/'));
    }

    private function normalizeInput(Request $request): void
    {
        $mobile = $this->normalizeMobile($request->input('username', $request->input('phone')));
        $request->merge(['username' => $mobile]);

        if ($request->filled('parents_contact')) {
            $request->merge(['parents_contact' => $this->normalizeMobile($request->input('parents_contact'))]);
        }

        $gender = strtolower(trim((string) $request->input('groomOrBride')));
        $genders = [
            'groom' => 'groom',
            'male' => 'groom',
            'm' => 'groom',
            'var' => 'groom',
            'bride' => 'bride',
            'female' => 'bride',
            'f' => 'bride',
            'vadhu' => 'bride',
        ];
        if (isset($genders[$gender])) {
            $request->merge(['groomOrBride' => $genders[$gender]]);
        }

        if (! $request->filled('height') && $request->filled('height_ft')) {
            $request->merge([
                'height' => (int) $request->input('height_ft').'ft '.(int) $request->input('height_in', 0).'in',
            ]);
        }
        if (! $request->filled('height_from') && $request->filled('height_from_ft')) {
            $request->merge([
                'height_from' => (int) $request->input('height_from_ft').'ft '.(int) $request->input('height_from_in', 0).'in',
            ]);
        }
        if (! $request->filled('height_to') && $request->filled('height_to_ft')) {
            $request->merge([
                'height_to' => (int) $request->input('height_to_ft').'ft '.(int) $request->input('height_to_in', 0).'in',
            ]);
        }

        if (! $request->filled('timeOfBirth') && $request->filled('birth_hour') && $request->filled('birth_minute')) {
            $hour = (int) $request->input('birth_hour');
            $minute = (int) $request->input('birth_minute');
            $ampm = strtoupper(trim((string) $request->input('birthTime_ampm')));

            if ($hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59 && in_array($ampm, ['AM', 'PM'], true)) {
                if ($ampm === 'AM' && $hour === 12) {
                    $hour = 0;
                } elseif ($ampm === 'PM' && $hour !== 12) {
                    $hour += 12;
                }

                $request->merge([
                    'timeOfBirth' => sprintf('%02d:%02d', $hour, $minute),
                    'birthTime_ampm' => $ampm,
                ]);
            }
        }
    }

    private function normalizeMobile($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    private function profilePayload(Request $request): array
    {
        $fields = [
            'fullName', 'groomOrBride', 'caste', 'subCaste', 'maritalStatus', 'birthDate',
            'height', 'weight', 'havingSpecsLenses', 'bloodGroup', 'motherTongue', 'language',
            'specialCases', 'knowAboutMyself', 'religion', 'address', 'permanent_address',
            'education', 'degreeDiplomaName1', 'degreeDiplomaName2', 'specialisation1',
            'specialisation2', 'universityBoardCollege', 'additionalEducation1',
            'additionalEducation2', 'cityOfEducation', 'profession', 'fieldOfWorking',
            'organisationWorkingIn', 'designation', 'lengthOfEmployment', 'monthlyIncome',
            'annualIncome', 'hobbies', 'extraCurricularActivities', 'achievements',
            'sportsAndFitnessLikings', 'cookingSkills', 'activeOnSocialSites', 'instagram',
            'facebook', 'personalMedicalHistory', 'currentMedicalProblems',
            'medications', 'fathersName', 'fatherAlive', 'fatherContactNumber',
            'fathersOccupation', 'fathersNativePlace', 'mothersName', 'motherAlive',
            'motherContactNumber', 'mothersOccupation', 'mothersNativePlace', 'noOfBrothers',
            'brothersMarried', 'noOfSisters', 'sistersMarried', 'parentsIntercasteMarriage',
            'parentsDifferentCaste', 'familyValues', 'familyFinancialStatus',
            'familyAnnualIncome', 'profileCreatorName', 'profileCreatorRelation',
            'referenceName', 'referenceRelation', 'referenceAddress', 'referenceContactNumber',
            'referenceEmail', 'timeOfBirth', 'birthTime_ampm', 'placeOfBirth',
        ];

        $data = [];

        foreach ($fields as $field) {
            if ($request->exists($field)) {
                $value = $request->input($field);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    private function preferencePayload(Request $request): array
    {
        $fields = [
            'preferred_religion', 'preferred_caste', 'preferred_sub_caste', 'age_from', 'age_to',
            'height_from', 'height_to', 'preferred_education', 'working_partner',
            'preferred_working_field', 'expected_income', 'family_type', 'financial_background',
            'family_values', 'preferred_country', 'preferred_state', 'preferred_city',
            'other_expectations',
        ];

        $data = [];

        foreach ($fields as $field) {
            if ($request->exists($field)) {
                $value = $request->input($field);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    private function profileForRestore(Profile $profile): array
    {
        $fields = [
            'fullName', 'groomOrBride', 'birthDate', 'maritalStatus', 'religion', 'caste',
            'subCaste', 'height', 'weight', 'havingSpecsLenses', 'bloodGroup', 'motherTongue',
            'language', 'specialCases', 'knowAboutMyself', 'address', 'permanent_address',
            'timeOfBirth', 'birthTime_ampm', 'placeOfBirth', 'education', 'degreeDiplomaName1',
            'degreeDiplomaName2', 'specialisation1', 'specialisation2', 'universityBoardCollege',
            'additionalEducation1', 'additionalEducation2', 'cityOfEducation', 'profession',
            'fieldOfWorking', 'organisationWorkingIn', 'professionalCity', 'designation',
            'lengthOfEmployment', 'monthlyIncome', 'annualIncome', 'income_currency', 'hobbies',
            'extraCurricularActivities', 'achievements', 'sportsAndFitnessLikings',
            'cookingSkills', 'activeOnSocialSites', 'instagram', 'facebook',
            'personalMedicalHistory', 'currentMedicalProblems', 'medications', 'fathersName',
            'fatherAlive', 'fatherContactNumber', 'fathersOccupation', 'fathersNativePlace',
            'mothersName', 'motherAlive', 'motherContactNumber', 'mothersOccupation',
            'mothersNativePlace', 'noOfBrothers', 'brothersMarried', 'noOfSisters',
            'sistersMarried', 'parentsIntercasteMarriage', 'parentsDifferentCaste',
            'familyValues', 'familyFinancialStatus', 'familyAnnualIncome', 'profileCreatorName',
            'profileCreatorRelation', 'referenceName', 'referenceRelation', 'referenceAddress',
            'referenceContactNumber', 'referenceEmail', 'source_of_information', 'phone',
            'profilePhoto', 'profile_id', 'serial_no', 'melava_batch',
        ];

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $profile->getAttribute($field);
        }

        $data['hasMedicalHistory'] = collect([
            $profile->personalMedicalHistory,
            $profile->currentMedicalProblems,
            $profile->medications,
        ])->filter(fn ($value) => filled($value))->isNotEmpty() ? 'Yes' : null;

        return $data;
    }

    private function storeUpload(UploadedFile $file, string $directory): string
    {
        $path = public_path('uploads/'.$directory);
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $name = time().'_'.Str::random(10).'.'.strtolower($file->getClientOriginalExtension() ?: 'bin');
        $file->move($path, $name);

        return 'uploads/'.$directory.'/'.$name;
    }

    private function storeUploads($files, string $directory): array
    {
        $paths = [];

        foreach ((array) $files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $paths[] = $this->storeUpload($file, $directory);
            }
        }

        return $paths;
    }

    private function isAdmin($user): bool
    {
        return $user && ((int) $user->role_id === 1 || (string) $user->user_type === '00');
    }

    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden. Administrator access is required.',
        ], 403);
    }
}
