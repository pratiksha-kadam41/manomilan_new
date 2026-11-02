<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MelavaPendingRegistration;
use App\Models\User;
use App\PaymentScreenshot;
use App\Profile;
use App\ProfileDocument;
use App\ProfileEducation;
use App\ProfileEmployment;
use App\ProfileFamilyDetail;
use App\ProfileMelavaRegistration;
use App\ProfilePartnerPreference;
use App\ProfileReligionDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MelavaRegistrationController extends Controller
{
    public function settings(Request $request)
    {
        $campaigns = DB::table('melava_campaigns')->where('status', 'active')->latest('id')->get()
            ->map(fn ($campaign) => [
                'id' => (int) $campaign->id,
                'melava_code' => $campaign->melava_code,
                'title' => $campaign->title,
                'description' => $campaign->description,
                'currency' => $campaign->currency,
                'fee' => (float) $campaign->registration_fee,
                'booklet_available' => (bool) $campaign->booklet_available,
                'booklet_fee' => (float) $campaign->booklet_fee,
                'qr_image_url' => $this->fileUrl($request, $campaign->qr_image_path),
            ])->values();

        return response()->json(['success' => true, 'data' => ['campaigns' => $campaigns]]);
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', Rule::in(['pending', 'verified'])],
            'booklet' => ['nullable', Rule::in(['yes', 'no'])],
            'gender' => ['nullable', Rule::in(['groom', 'bride'])],
            'melava_number' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ProfileMelavaRegistration::with(['profile', 'campaign'])
            ->when($filters['q'] ?? null, function ($query, $term) {
                $term = trim($term);
                $query->whereHas('profile', fn ($profile) => $profile
                    ->where('full_name', 'like', "%{$term}%")
                    ->orWhere('reg_id', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"));
            })
            ->when(isset($filters['payment_status']), fn ($query) => $query
                ->where('payment_verified', $filters['payment_status'] === 'verified'))
            ->when(isset($filters['booklet']), fn ($query) => $query
                ->where('is_booklet_req', $filters['booklet'] === 'yes'))
            ->when(isset($filters['gender']), fn ($query) => $query->whereHas(
                'profile',
                fn ($profile) => $profile->whereRaw('LOWER(groomorbride) = ?', [$filters['gender']])
            ))
            ->when($filters['melava_number'] ?? null, fn ($query, $number) => $query->whereHas(
                'campaign',
                fn ($campaign) => $campaign->where('melava_code', $number)
            ));

        $registrations = $query->latest()->paginate((int) ($filters['per_page'] ?? 20));
        $registrations->getCollection()->transform(fn (ProfileMelavaRegistration $registration) => $this->present($registration));

        return response()->json([
            'success' => true,
            'data' => $registrations,
            'stats' => [
                'total' => $registrations->total(),
                'groom' => (clone $query)->whereHas('profile', fn ($p) => $p->where('groomorbride', 'Groom'))->count(),
                'bride' => (clone $query)->whereHas('profile', fn ($p) => $p->where('groomorbride', 'Bride'))->count(),
                'booklet' => (clone $query)->where('is_booklet_req', true)->count(),
                'verified' => (clone $query)->where('payment_verified', true)->count(),
                'pending' => (clone $query)->where('payment_verified', false)->count(),
            ],
        ]);
    }

    public function check(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'digits:10'],
            'melava_campaign_id' => ['required'],
        ]);

        $campaign = $this->campaign($data['melava_campaign_id']);
        $profile = Profile::where('phone', $data['mobile'])->first();
        $registration = $profile && $campaign
            ? ProfileMelavaRegistration::where('profile_id', $profile->id)
                ->where('melava_campaign_id', $campaign->id)->first()
            : null;
        $pending = MelavaPendingRegistration::where('mobile', $data['mobile'])
            ->where('melava_campaign_id', $campaign?->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $registration && $pending) {
            $registrationData = json_decode($pending->registration_data, true) ?: [];
            $files = $registrationData['_pending_files'] ?? [];

            return response()->json([
                'success' => true,
                'type' => 'pending_registration',
                'pending_registration' => [
                    'token' => $pending->token,
                    'mobile' => $pending->mobile,
                    'is_existing_website_user' => (bool) $pending->is_existing_website_user,
                    'user_id' => $pending->user_id,
                    'profile_id' => $pending->profile_id,
                    'registration_data' => $registrationData,
                    'profile_photo_url' => $this->fileUrl(request(), $files['profilePhoto'] ?? null),
                    'full_body_photo_url' => $this->fileUrl(request(), $files['full_body_photo'] ?? null),
                    'half_body_photo_url' => $this->fileUrl(request(), $files['half_body_photo'] ?? null),
                    'selfie_photo_url' => $this->fileUrl(request(), $files['selfie_photo'] ?? null),
                    'identification_proof_url' => $this->fileUrl(request(), $files['identificationProof'] ?? null),
                    'pan_card_url' => $this->fileUrl(request(), $files['pan_card'] ?? null),
                    'passport_url' => $this->fileUrl(request(), $files['passport'] ?? null),
                    'birth_date_proof_url' => $this->fileUrl(request(), $files['birthDateProof'] ?? null),
                    'address_proof_url' => $this->fileUrl(request(), $files['address_proof'] ?? null),
                    'education_proof_url' => $this->fileUrl(request(), $files['educationProof'] ?? null),
                    'profession_proof_url' => $this->fileUrl(request(), $files['profession_proof'] ?? null),
                    'income_proof_url' => $this->fileUrl(request(), $files['income_proof'] ?? null),
                    'caste_certificate_url' => $this->fileUrl(request(), $files['caste_certificate'] ?? null),
                    'medical_fitness_certificate_url' => $this->fileUrl(request(), $files['medical_fitness_certificate'] ?? null),
                    'candidate_photos_urls' => $this->fileUrls(request(), $files['candidate_photos'] ?? []),
                    'additional_photos_urls' => $this->fileUrls(request(), $files['additional_photos'] ?? []),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'exists' => (bool) $registration,
            'payment_verified' => (bool) $registration?->payment_verified,
        ]);
    }

    public function saveDraft(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'digits:10'],
            'registration_data' => ['required'],
            'melava_campaign_id' => ['required'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);
        $campaign = $this->campaign($data['melava_campaign_id']);
        if (! $campaign) {
            throw ValidationException::withMessages(['melava_campaign_id' => ['Invalid Melava campaign.']]);
        }

        $draft = MelavaPendingRegistration::firstOrNew(['token' => $data['token'] ?? null]);
        if (! $draft->token) {
            $draft->token = bin2hex(random_bytes(32));
        }
        $previousData = is_string($draft->registration_data)
            ? json_decode($draft->registration_data, true)
            : [];
        $previousData = is_array($previousData) ? $previousData : [];
        $registrationData = is_string($data['registration_data'])
            ? json_decode($data['registration_data'], true)
            : $data['registration_data'];
        if (! is_array($registrationData)) {
            throw ValidationException::withMessages([
                'registration_data' => ['Registration data must be valid JSON.'],
            ]);
        }
        $registrationData['_pending_files'] = $previousData['_pending_files'] ?? [];
        $registrationData = $this->storeDraftFiles($request, $registrationData, $draft->token);
        $draft->fill([
            'mobile' => $data['mobile'],
            'is_existing_website_user' => $request->boolean('is_existing_website_user'),
            'user_id' => $request->input('user_id'),
            'profile_id' => $request->input('profile_id'),
            'registration_data' => json_encode($registrationData, JSON_UNESCAPED_UNICODE),
            'melava_campaign_id' => $campaign->id,
            'melava_number' => $campaign->melava_code,
            'status' => 'pending',
        ])->save();

        return response()->json(['success' => true, 'token' => $draft->token]);
    }

    private function storeDraftFiles(Request $request, array $registrationData, string $token): array
    {
        $request->validate([
            'profilePhoto' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'full_body_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'half_body_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'selfie_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'identificationProof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'pan_card' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'passport' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'birthDateProof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'address_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'educationProof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'profession_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'income_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'caste_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'medical_fitness_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'candidate_photos' => ['nullable', 'array', 'max:5'],
            'candidate_photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'additionalPhotos' => ['nullable', 'array', 'max:4'],
            'additionalPhotos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'payment_screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $directory = 'melava_pending/'.$token;
        $singleFiles = [
            'profilePhoto',
            'full_body_photo',
            'half_body_photo',
            'selfie_photo',
            'identificationProof',
            'pan_card',
            'passport',
            'birthDateProof',
            'address_proof',
            'educationProof',
            'profession_proof',
            'income_proof',
            'caste_certificate',
            'medical_fitness_certificate',
        ];

        foreach ($singleFiles as $field) {
            if ($request->hasFile($field)) {
                $registrationData['_pending_files'][$field] = $request->file($field)->store($directory, 'public');
            }
        }

        foreach (['candidate_photos' => 'candidate_photos', 'additionalPhotos' => 'additional_photos'] as $input => $key) {
            if ($request->hasFile($input)) {
                $registrationData['_pending_files'][$key] = collect($request->file($input))
                    ->map(fn ($file) => $file->store($directory, 'public'))
                    ->values()
                    ->all();
            }
        }

        if ($request->hasFile('payment_screenshot')) {
            $registrationData['_pending_files']['payment_screenshot'] =
                $request->file('payment_screenshot')->store($directory, 'public');
        }

        return $registrationData;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $campaign = $this->campaign($data['melava_campaign_id']);
        if (! $campaign || $campaign->status !== 'active') {
            throw ValidationException::withMessages(['melava_campaign_id' => ['This Melava campaign is unavailable.']]);
        }

        $profile = DB::transaction(function () use ($request, $data, $campaign) {
            $user = User::where('username', $data['username'])->first();
            if (! $user) {
                $roleId = DB::table('roles')->where('user_type', '21')->value('id');
                if (! $roleId) {
                    throw new \RuntimeException('Candidate role is not configured.');
                }
                $user = User::create([
                    'name' => $data['fullName'],
                    'email' => $data['email'] ?? "{$data['username']}@melava.local",
                    'username' => $data['username'],
                    'password' => Hash::make('manomilana'),
                    'user_type' => '21',
                    'role_id' => $roleId,
                    'status' => 'active',
                    'is_password_changed' => false,
                ]);
            }

            $profile = $user->profile_id ? Profile::find($user->profile_id) : null;
            $profile = $this->saveProfile($profile, $request, $data, $user);
            $user->profile_id = $profile->id;
            $user->save();

            $paymentPath = $request->hasFile('payment_screenshot')
                ? $request->file('payment_screenshot')->store('payment_screenshots', 'public')
                : null;
            $registration = ProfileMelavaRegistration::updateOrCreate(
                ['profile_id' => $profile->id, 'melava_campaign_id' => $campaign->id],
                [
                    'is_booklet_req' => $request->boolean('booklet_required'),
                    'payment_screenshot' => $paymentPath,
                    'payment_verified' => false,
                ]
            );
            if ($paymentPath) {
                PaymentScreenshot::updateOrCreate(
                    ['profile_id' => $profile->id, 'screenshot_path' => $paymentPath],
                    ['payment_verified' => false]
                );
            }

            return [$profile, $registration];
        });

        return response()->json([
            'success' => true,
            'message' => 'Melava registration submitted and is pending payment verification.',
            'data' => $this->present($profile[1]),
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }
        $registration = ProfileMelavaRegistration::with(['profile', 'campaign'])->find($id);
        return $registration
            ? response()->json(['success' => true, 'data' => $this->present($registration, true)])
            : response()->json(['success' => false, 'message' => 'Registration not found.'], 404);
    }

    public function updatePayment(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }
        $registration = ProfileMelavaRegistration::find($id);
        if (! $registration) {
            return response()->json(['success' => false, 'message' => 'Registration not found.'], 404);
        }
        $data = $request->validate([
            'payment_screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payment_verified' => ['nullable', 'boolean'],
            'booklet_required' => ['nullable', 'boolean'],
        ]);
        if ($request->hasFile('payment_screenshot')) {
            $registration->payment_screenshot = $request->file('payment_screenshot')->store('payment_screenshots', 'public');
        }
        if (array_key_exists('payment_verified', $data)) {
            $registration->payment_verified = (bool) $data['payment_verified'];
        }
        if (array_key_exists('booklet_required', $data)) {
            $registration->is_booklet_req = (bool) $data['booklet_required'];
        }
        $registration->save();
        return response()->json(['success' => true, 'data' => $this->present($registration->fresh(['profile', 'campaign']))]);
    }

    public function updatePaymentStatus(Request $request, int $id)
    {
        return $this->updatePayment($request->merge([
            'payment_verified' => $request->boolean('payment_verified'),
        ]), $id);
    }

    public function update(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }
        $registration = ProfileMelavaRegistration::with('profile')->find($id);
        if (! $registration?->profile) {
            return response()->json(['success' => false, 'message' => 'Registration not found.'], 404);
        }
        $data = $this->validated($request, false);
        DB::transaction(fn () => $this->saveProfile($registration->profile, $request, $data, $registration->profile->user));
        $registration->is_booklet_req = $request->boolean('booklet_required', $registration->is_booklet_req);
        $registration->save();
        return response()->json(['success' => true, 'data' => $this->present($registration->fresh(['profile', 'campaign']))]);
    }

    private function saveProfile(?Profile $profile, Request $request, array $data, User $user): Profile
    {
        $profile ??= new Profile();
        $pendingFiles = $this->pendingFiles($request);
        $photos = is_array($profile->photos) ? $profile->photos : [];
        foreach ([
            'profilePhoto' => 'profile_photo',
            'full_body_photo' => 'full_body_photo',
            'half_body_photo' => 'half_body_photo',
            'selfie_photo' => 'selfie_photo',
        ] as $input => $key) {
            if ($request->hasFile($input)) {
                $photos[$key] = $request->file($input)->store('profile_photos', 'public');
            } elseif (! empty($pendingFiles[$input])) {
                $photos[$key] = $this->promotePendingFile($pendingFiles[$input], 'profile_photos');
            }
        }
        if ($request->hasFile('candidate_photos')) {
            $photos['candidate_photos'] = collect($request->file('candidate_photos'))
                ->map(fn ($file) => $file->store('profile_photos/candidate', 'public'))
                ->values()
                ->all();
        } elseif (! empty($pendingFiles['candidate_photos'])) {
            $photos['candidate_photos'] = array_map(
                fn (string $path) => $this->promotePendingFile($path, 'profile_photos/candidate'),
                $pendingFiles['candidate_photos']
            );
        }
        if ($request->hasFile('additionalPhotos')) {
            $photos['additional_photos'] = collect($request->file('additionalPhotos'))
                ->map(fn ($file) => $file->store('profile_photos/additional', 'public'))
                ->values()
                ->all();
        } elseif (! empty($pendingFiles['additional_photos'])) {
            $photos['additional_photos'] = array_map(
                fn (string $path) => $this->promotePendingFile($path, 'profile_photos/additional'),
                $pendingFiles['additional_photos']
            );
        }

        $profile->fill([
            'full_name' => $data['fullName'] ?? $profile->full_name,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['username'] ?? $profile->phone,
            'groomorbride' => ucfirst($data['groomOrBride'] ?? $profile->groomorbride ?? 'Groom'),
            'birth_date' => $data['birthDate'] ?? $profile->birth_date,
            'height_cm' => $this->heightCm($data['height'] ?? null),
            'weight_kg' => $data['weight'] ?? null,
            'has_spectacles' => isset($data['havingSpecsLenses']) ? $data['havingSpecsLenses'] === 'Yes' : null,
            'blood_group' => $data['bloodGroup'] ?? null,
            'mother_tongue' => $data['motherTongue'] ?? null,
            'languages' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['language'] ?? ''))))),
            'special_cases' => $data['specialCases'] ?? null,
            'about_me' => $data['knowAboutMyself'] ?? null,
            'monthly_income' => $data['monthlyIncome'] ?? null,
            'annual_income' => $data['annualIncome'] ?? null,
            'personal_medical_history' => $data['personalMedicalHistory'] ?? null,
            'current_medical_problems' => $data['currentMedicalProblems'] ?? null,
            'medications' => $data['medications'] ?? null,
            'has_medical_history' => $this->booleanValue($data['hasMedicalHistory'] ?? null),
            'birth_place' => $data['placeOfBirth'] ?? null,
            'birth_time' => $data['timeOfBirth'] ?? null,
            'source_of_information' => $data['source_of_information'] ?? null,
            'marital_status' => $data['maritalStatus'] ?? null,
            'photos' => $photos,
            'created_by' => $profile->created_by ?: $user->id,
            'profile_creator_name' => $data['profileCreatorName'] ?? $profile->profile_creator_name,
            'creator_relation' => $data['profileCreatorRelation'] ?? $profile->creator_relation,
            'addresses' => [
                'current' => [
                    'address' => $data['address'] ?? null,
                    'pincode' => $data['current_address_pincode'] ?? null,
                    'city' => $data['current_address_city'] ?? null,
                    'district' => $data['current_address_district'] ?? null,
                    'state' => $data['current_address_state'] ?? null,
                    'country' => $data['current_address_country'] ?? null,
                ],
                'permanent' => ['address' => $data['permanent_address'] ?? null],
                'reference' => [
                    'name' => $data['referenceName'] ?? null,
                    'relation' => $data['referenceRelation'] ?? null,
                    'address' => $data['referenceAddress'] ?? null,
                    'contact' => $data['referenceContactNumber'] ?? null,
                    'email' => $data['referenceEmail'] ?? null,
                ],
                'metadata' => [
                    'marital_status' => $data['maritalStatus'] ?? null,
                    'source_of_information' => $data['source_of_information'] ?? null,
                    'profile_creator_name' => $data['profileCreatorName'] ?? null,
                ],
            ],
        ])->save();

        ProfileFamilyDetail::updateOrCreate(['profile_id' => $profile->id], [
            'father_name' => $data['fathersName'] ?? null,
            'father_status' => $this->familyStatus($data['fatherAlive'] ?? null),
            'mother_name' => $data['mothersName'] ?? null,
            'mother_status' => $this->familyStatus($data['motherAlive'] ?? null),
            'father_contact' => $data['fatherContactNumber'] ?? null,
            'mother_contact' => $data['motherContactNumber'] ?? null,
            'father_native_place' => $data['fathersNativePlace'] ?? null,
            'mother_native_place' => $data['mothersNativePlace'] ?? null,
            'father_occupation' => $data['fathersOccupation'] ?? null,
            'mother_occupation' => $data['mothersOccupation'] ?? null,
            'brothers_count' => $data['noOfBrothers'] ?? null,
            'married_brothers_count' => $data['brothersMarried'] ?? null,
            'sisters_count' => $data['noOfSisters'] ?? null,
            'married_sisters_count' => $data['sistersMarried'] ?? null,
            'family_values' => $data['familyValues'] ?? null,
            'family_financial_status' => $data['familyFinancialStatus'] ?? null,
            'family_annual_income' => $data['familyAnnualIncome'] ?? null,
            'parents_intercaste_marriage' => $this->booleanValue($data['parentsIntercasteMarriage'] ?? null),
            'parents_different_caste_details' => $data['parentsDifferentCaste'] ?? null,
        ]);

        $educationRows = $this->educationRows($request, $data);
        $educationIds = [];
        foreach ($educationRows as $index => $row) {
            $education = $profile->educations()->orderBy('id')->skip($index)->first()
                ?? new ProfileEducation();
            $education->fill([
                'profile_id' => $profile->id,
                ...$row,
            ])->save();
            $educationIds[] = $education->id;
        }
        $profile->educations()->when($educationIds, fn ($query) => $query->whereNotIn('id', $educationIds))
            ->when(! $educationIds, fn ($query) => $query)
            ->delete();

        $employmentIds = [];
        foreach ($this->employmentRows($request, $data) as $index => $row) {
            $employment = $profile->employments()->orderBy('id')->skip($index)->first()
                ?? new ProfileEmployment();
            $employment->fill(['profile_id' => $profile->id, ...$row])->save();
            $employmentIds[] = $employment->id;
        }
        $profile->employments()->when($employmentIds, fn ($query) => $query->whereNotIn('id', $employmentIds))
            ->when(! $employmentIds, fn ($query) => $query)
            ->delete();
        ProfileReligionDetail::updateOrCreate(['profile_id' => $profile->id], [
            'religion' => $data['religion'] ?? null,
            'caste' => $data['caste'] ?? null,
            'sub_caste' => $data['subCaste'] ?? null,
            'community' => $data['community'] ?? null,
            'horoscope' => $data['horoscope'] ?? null,
            'horoscope_match_required' => $data['horoscope_match_required'] ?? null,
            'manglik_status' => $data['manglik_status'] ?? null,
            'rashi' => $data['rashi'] ?? null,
            'nakshatra' => $data['nakshatra'] ?? null,
            'charan' => $data['charan'] ?? null,
            'nadi' => $data['nadi'] ?? null,
            'gan' => $data['gan'] ?? null,
            'gotra' => $data['gotra'] ?? null,
            'horoscope' => $data['horoscope'] ?? null,
            'manglik_status' => $data['manglik_status'] ?? null,
            'rashi' => $data['rashi'] ?? null,
            'nakshatra' => $data['nakshatra'] ?? null,
        ]);
        ProfilePartnerPreference::updateOrCreate(['profile_id' => $profile->id], [
            'age_from' => $data['age_from'] ?? null,
            'age_to' => $data['age_to'] ?? null,
            'height_from' => $data['height_from'] ?? null,
            'height_to' => $data['height_to'] ?? null,
            'preferred_education' => $data['preferred_education'] ?? null,
            'preferred_religion' => $data['preferred_religion'] ?? null,
            'preferred_working_field' => $data['preferred_working_field'] ?? null,
            'preferred_caste' => $data['preferred_caste'] ?? null,
            'preferred_sub_caste' => $data['preferred_sub_caste'] ?? null,
            'preferred_location' => $data['preferred_location'] ?? null,
            'working_partner' => $data['working_partner'] ?? null,
            'expected_income' => $data['expected_income'] ?? null,
            'family_type' => $data['family_type'] ?? null,
            'financial_background' => $data['financial_background'] ?? null,
            'family_values' => $data['family_values'] ?? null,
            'preferred_country' => $data['preferred_country'] ?? null,
            'preferred_state' => $data['preferred_state'] ?? null,
            'preferred_city' => $data['preferred_city'] ?? null,
            'other_expectations' => $data['other_expectations'] ?? null,
        ]);

        $documentMap = [
            'aadhaar_card' => 'aadhaar_card',
            'pan_card' => 'pan_card',
            'passport' => 'passport',
            'birth_certificate' => 'birth_certificate',
            'address_proof' => 'address_proof',
            'education_certificate' => 'education_certificate',
            'profession_proof' => 'profession_proof',
            'income_proof' => 'income_proof',
            'caste_certificate' => 'caste_certificate',
            'medical_fitness_certificate' => 'medical_fitness_certificate',
            'identificationProof' => 'aadhaar_card',
            'birthDateProof' => 'birth_certificate',
            'educationProof' => 'education_certificate',
            'professionProof' => 'profession_proof',
            'incomeProof' => 'income_proof',
            'casteCertificate' => 'caste_certificate',
            'medicalFitnessCertificate' => 'medical_fitness_certificate',
        ];
        $documentData = [];
        $educationCertificatePath = null;
        foreach ($documentMap as $input => $column) {
            if ($request->hasFile($input) && ! isset($documentData[$column])) {
                $documentData[$column] = $request->file($input)->store('profile_documents', 'public');
                if ($column === 'education_certificate') {
                    $educationCertificatePath = $documentData[$column];
                }
            } elseif (! empty($pendingFiles[$input]) && ! isset($documentData[$column])) {
                $documentData[$column] = $this->promotePendingFile($pendingFiles[$input], 'profile_documents');
                if ($column === 'education_certificate') {
                    $educationCertificatePath = $documentData[$column];
                }
            }
        }
        if ($documentData) {
            ProfileDocument::updateOrCreate(['profile_id' => $profile->id], $documentData);
        }
        if ($educationCertificatePath) {
            $profile->educations()->latest('id')->first()?->update([
                'education_certificate' => $educationCertificatePath,
            ]);
        }

        return $profile->fresh();
    }

    private function validated(Request $request, bool $required): array
    {
        foreach (['educations', 'employments'] as $collection) {
            if (is_string($request->input($collection))) {
                $decoded = json_decode($request->input($collection), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge([$collection => $decoded]);
                }
            }
        }
        $rules = [
            'melava_campaign_id' => [$required ? 'required' : 'nullable'],
            'username' => [$required ? 'required' : 'nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'fullName' => [$required ? 'required' : 'nullable', 'string', 'max:255'],
            'groomOrBride' => [$required ? 'required' : 'nullable', Rule::in(['groom', 'bride'])],
            'birthDate' => [$required ? 'required' : 'nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'max:255'],
            'caste' => ['nullable', 'string', 'max:255'],
            'height' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:5000'],
            'education' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:5000'],
            'professionalCity' => ['nullable', 'string', 'max:5000'],
            'payment_screenshot' => [$required ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'booklet_required' => ['nullable', 'boolean'],
        ];
        foreach ([
            'source_of_information', 'maritalStatus', 'subCaste', 'havingSpecsLenses', 'bloodGroup',
            'personalMedicalHistory', 'currentMedicalProblems', 'medications', 'hasMedicalHistory',
            'motherTongue', 'language', 'specialCases', 'knowAboutMyself', 'placeOfBirth',
            'timeOfBirth', 'fieldOfWorking', 'organisationWorkingIn', 'designation',
            'lengthOfEmployment', 'income_currency', 'fathersName', 'fatherAlive',
            'fatherContactNumber', 'fathersOccupation', 'fathersNativePlace', 'mothersName',
            'motherAlive', 'motherContactNumber', 'mothersOccupation', 'mothersNativePlace',
            'parentsIntercasteMarriage', 'parentsDifferentCaste', 'familyValues',
            'familyFinancialStatus', 'profileCreatorName', 'profileCreatorRelation',
            'referenceName', 'referenceRelation', 'referenceAddress', 'referenceContactNumber',
            'referenceEmail', 'preferred_religion', 'preferred_sub_caste', 'preferred_location',
            'preferred_working_field', 'working_partner', 'expected_income', 'family_type',
            'financial_background', 'preferred_country', 'preferred_state', 'preferred_city',
            'other_expectations', 'community', 'horoscope',
            'manglik_status', 'rashi', 'nakshatra', 'charan', 'nadi', 'gan', 'gotra',
            'degreeDiplomaName1', 'degreeDiplomaName2', 'specialisation1', 'specialisation2',
            'universityBoardCollege', 'additionalEducation1', 'additionalEducation2',
            'cityOfEducation', 'preferred_education', 'height_from', 'height_to',
            'permanent_address', 'current_address_pincode', 'current_address_city',
            'current_address_district', 'current_address_state', 'current_address_country',
        ] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:5000'];
        }
        foreach ([
            'monthlyIncome', 'annualIncome', 'familyAnnualIncome', 'age_from', 'age_to',
            'noOfBrothers', 'brothersMarried', 'noOfSisters', 'sistersMarried',
        ] as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:0'];
        }
        $rules['horoscope_match_required'] = ['nullable', 'boolean'];
        $rules['educations'] = ['nullable', 'array', 'max:20'];
        $rules['educations.*'] = ['array'];
        $rules['employments'] = ['nullable', 'array', 'max:20'];
        $rules['employments.*'] = ['array'];
        return $request->validate($rules);
    }

    private function familyStatus($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->booleanValue($value) ? 'Alive' : 'Deceased';
    }

    private function booleanValue($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function pendingFiles(Request $request): array
    {
        $token = $request->input('token');
        if (! $token) {
            return [];
        }

        $draft = MelavaPendingRegistration::where('token', $token)->first();
        if (! $draft) {
            return [];
        }

        $data = json_decode((string) $draft->registration_data, true);
        return is_array($data) && is_array($data['_pending_files'] ?? null)
            ? $data['_pending_files']
            : [];
    }

    private function promotePendingFile(string $path, string $directory): string
    {
        if (! Storage::disk('public')->exists($path)) {
            return $path;
        }

        $filename = basename($path);
        $target = $directory.'/'.$filename;
        if ($path !== $target) {
            Storage::disk('public')->move($path, $target);
        }

        return $target;
    }

    private function educationRows(Request $request, array $data): array
    {
        if (! empty($data['educations'])) {
            return array_map(fn (array $row) => [
                'education_level' => $row['education_level'] ?? null,
                'degree_name' => $row['degree_name'] ?? null,
                'specialization' => $row['specialization'] ?? null,
                'university_name' => $row['university_name'] ?? null,
                'college_name' => $row['college_name'] ?? null,
                'passing_year' => $row['passing_year'] ?? null,
                'percentage' => $row['percentage'] ?? null,
                'grade' => $row['grade'] ?? null,
                'education_city' => $row['education_city'] ?? null,
                'additional_qualification' => $row['additional_qualification'] ?? null,
            ], $data['educations']);
        }

        return array_values(array_filter([
            [
                'education_level' => $data['education'] ?? null,
                'degree_name' => $data['degreeDiplomaName1'] ?? null,
                'specialization' => $data['specialisation1'] ?? null,
                'university_name' => $data['universityBoardCollege'] ?? null,
                'education_city' => $data['cityOfEducation'] ?? null,
                'additional_qualification' => $data['additionalEducation1'] ?? null,
            ],
            [
                'education_level' => $data['education'] ?? null,
                'degree_name' => $data['degreeDiplomaName2'] ?? null,
                'specialization' => $data['specialisation2'] ?? null,
                'university_name' => $data['universityBoardCollege'] ?? null,
                'education_city' => $data['cityOfEducation'] ?? null,
                'additional_qualification' => $data['additionalEducation2'] ?? null,
            ],
        ], fn (array $row) => filled($row['degree_name'])));
    }

    private function employmentRows(Request $request, array $data): array
    {
        if (! empty($data['employments'])) {
            return array_map(fn (array $row) => [
                'employment_type' => $row['employment_type'] ?? null,
                'profession' => $row['profession'] ?? null,
                'working_field' => $row['working_field'] ?? null,
                'organisation_name' => $row['organisation_name'] ?? null,
                'designation' => $row['designation'] ?? null,
                'employment_city' => $row['employment_city'] ?? null,
                'employment_duration' => $row['employment_duration'] ?? null,
                'monthly_income' => $row['monthly_income'] ?? null,
                'annual_income' => $row['annual_income'] ?? null,
                'income_currency' => $row['income_currency'] ?? 'INR',
                'original_monthly_income' => $row['original_monthly_income'] ?? null,
                'original_annual_income' => $row['original_annual_income'] ?? null,
            ], $data['employments']);
        }

        return [[
            'employment_type' => $data['profession'] ?? null,
            'profession' => $data['profession'] ?? null,
            'working_field' => $data['fieldOfWorking'] ?? null,
            'organisation_name' => $data['organisationWorkingIn'] ?? null,
            'designation' => $data['designation'] ?? null,
            'employment_city' => $data['professionalCity'] ?? null,
            'employment_duration' => $data['lengthOfEmployment'] ?? null,
            'monthly_income' => $data['monthlyIncome'] ?? null,
            'annual_income' => $data['annualIncome'] ?? null,
            'income_currency' => $data['income_currency'] ?? 'INR',
            'original_monthly_income' => $data['original_monthly_income'] ?? null,
            'original_annual_income' => $data['original_annual_income'] ?? null,
        ]];
    }

    private function present(ProfileMelavaRegistration $registration, bool $includeForm = false): array
    {
        $registration->loadMissing(['profile', 'campaign']);
        $profile = $registration->profile;
        $data = [
            'id' => $registration->id,
            'profile_id' => $profile?->id,
            'fullName' => $profile?->full_name,
            'groomOrBride' => $profile?->groomorbride,
            'birthDate' => $profile?->birth_date,
            'username' => $profile?->phone,
            'payment_verified' => (bool) $registration->payment_verified,
            'payment_screenshot' => $registration->payment_screenshot,
            'payment_screenshot_url' => $this->fileUrl(request(), $registration->payment_screenshot),
            'is_booklet_req' => (bool) $registration->is_booklet_req,
            'melava_number' => $registration->campaign?->melava_code,
            'melava_title' => $registration->campaign?->title,
            'melava_campaign_id' => $registration->melava_campaign_id,
            'profile_photo_url' => $this->fileUrl(request(), $profile?->photos['profile_photo'] ?? null),
        ];
        if ($includeForm) {
            $data['edit_form'] = ['profile' => $profile?->toArray() ?? []];
        }
        return $data;
    }

    private function campaign(string|int $value): ?object
    {
        $query = DB::table('melava_campaigns');
        return ctype_digit((string) $value)
            ? $query->where('id', (int) $value)->first()
            : $query->whereRaw('UPPER(melava_code) = ?', [strtoupper((string) $value)])->first();
    }

    private function heightCm(?string $height): ?float
    {
        if (! $height) {
            return null;
        }
        if (preg_match('/^(\d+)\s*ft\s*(\d+)?\s*in$/i', trim($height), $m)) {
            return round((((int) $m[1] * 12) + (int) ($m[2] ?? 0)) * 2.54, 2);
        }
        return is_numeric($height) ? round((float) $height * 30.48, 2) : null;
    }

    private function fileUrl(Request $request, ?string $path): string
    {
        return $path ? Storage::disk('public')->url($path) : '';
    }

    private function fileUrls(Request $request, array $paths): array
    {
        return array_values(array_filter(array_map(
            fn ($path) => $this->fileUrl($request, is_string($path) ? $path : null),
            $paths
        )));
    }

    private function isAdmin($user): bool
    {
        return $user && $user->isAdmin();
    }

    private function forbidden()
    {
        return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
    }
}
