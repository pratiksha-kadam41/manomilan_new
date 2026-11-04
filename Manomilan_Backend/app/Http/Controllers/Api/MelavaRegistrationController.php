<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaPendingRegistration;
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
    public function photoPreview(Request $request)
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);
        $path = ltrim(preg_replace('#^/?storage/#i', '', $data['path']), '/');

        if (! preg_match(
            '#\A(?:profile_photos(?:/candidate)?|additional_photos|full_body_photos|half_body_photos|selfie_photos|payment_screenshots|candidate_images|candidate/profile_photos|candidate/body_photos|melava_candidate_photos)/[A-Za-z0-9._-]+\.(?:jpe?g|png|webp|gif|bmp)\z#i',
            $path
        ) || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mimeType = Storage::disk('public')->mimeType($path);
        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'image/')) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

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

        $query = ProfileMelavaRegistration::with(['profile.educations', 'profile.employments', 'campaign'])
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
            'melava_numbers' => DB::table('melava_campaigns')
                ->whereNotNull('melava_code')
                ->where('melava_code', '<>', '')
                ->orderByDesc('id')
                ->pluck('melava_code')
                ->map(fn ($number) => strtoupper(trim((string) $number)))
                ->filter()
                ->unique()
                ->values(),
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
        $user = User::where('username', $data['mobile'])->first();
        $profile = $user?->profile ?: Profile::where('phone', $data['mobile'])->first();
        $pending = MelavaPendingRegistration::where('mobile', $data['mobile'])
            ->where('melava_campaign_id', $campaign?->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
        $profile ??= $pending?->profile_id ? Profile::find($pending->profile_id) : null;
        $registration = $profile && $campaign
            ? ProfileMelavaRegistration::where('profile_id', $profile->id)
                ->where('melava_campaign_id', $campaign->id)->first()
            : null;

        if (! $registration && $pending) {
            $registrationData = json_decode($pending->registration_data, true) ?: [];
            $files = $registrationData['_pending_files'] ?? [];
            $profile?->loadMissing('profileDocuments');
            $photos = is_array($profile?->photos) ? $profile->photos : [];
            $draftUrl = function (string $fileKey, string $dataKey, ?string $profilePath = null) use ($files, $registrationData): string {
                $path = $files[$fileKey] ?? $registrationData[$dataKey] ?? $profilePath;
                return $this->fileUrl(request(), is_string($path) ? $path : null);
            };
            $draftUrls = function (string $fileKey, string $dataKey, string $profileKey) use ($files, $registrationData, $profile, $photos): array {
                return $this->fileUrls(request(), array_values(array_unique(array_merge(
                    $profile ? $this->photoList($profile, $profileKey, $photos) : [],
                    $this->photoPaths($registrationData[$dataKey] ?? []),
                    $this->photoPaths($files[$fileKey] ?? [])
                ))));
            };

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
                    'payment_screenshot_url' => $this->fileUrl(request(), $files['payment_screenshot'] ?? null),
                    'profile_photo_url' => $draftUrl('profilePhoto', 'profilePhoto', $profile ? $this->profilePhotoPath($profile, 'profile_photo', $photos) : null),
                    'full_body_photo_url' => $draftUrl('full_body_photo', 'full_body_photo', $profile ? $this->profilePhotoPath($profile, 'full_body_photo', $photos) : null),
                    'half_body_photo_url' => $draftUrl('half_body_photo', 'half_body_photo', $profile ? $this->profilePhotoPath($profile, 'half_body_photo', $photos) : null),
                    'selfie_photo_url' => $draftUrl('selfie_photo', 'selfie_photo', $profile ? $this->profilePhotoPath($profile, 'selfie_photo', $photos) : null),
                    'identification_proof_url' => $draftUrl('identificationProof', 'identificationProof', $profile?->profileDocuments?->aadhaar_card ?: $profile?->getAttribute('identificationProof')),
                    'pan_card_url' => $draftUrl('pan_card', 'pan_card', $profile?->profileDocuments?->pan_card),
                    'passport_url' => $draftUrl('passport', 'passport', $profile?->profileDocuments?->passport),
                    'birth_date_proof_url' => $draftUrl('birthDateProof', 'birthDateProof', $profile?->profileDocuments?->birth_certificate ?: $profile?->getAttribute('birthDateProof')),
                    'address_proof_url' => $draftUrl('address_proof', 'address_proof', $profile?->profileDocuments?->address_proof ?: $profile?->getAttribute('addressProof')),
                    'education_proof_url' => $draftUrl('educationProof', 'educationProof', $profile?->profileDocuments?->education_certificate ?: $profile?->getAttribute('educationProof')),
                    'profession_proof_url' => $draftUrl('profession_proof', 'professionProof', $profile?->profileDocuments?->profession_proof ?: $profile?->getAttribute('professionProof')),
                    'income_proof_url' => $draftUrl('income_proof', 'incomeProof', $profile?->profileDocuments?->income_proof ?: $profile?->getAttribute('incomeProof')),
                    'caste_certificate_url' => $draftUrl('caste_certificate', 'caste_certificate', $profile?->profileDocuments?->caste_certificate ?: $profile?->getAttribute('casteCertificate')),
                    'medical_fitness_certificate_url' => $draftUrl('medical_fitness_certificate', 'medical_fitness_certificate', $profile?->profileDocuments?->medical_fitness_certificate ?: $profile?->getAttribute('medicalFitnessCertificate')),
                    'candidate_photos_urls' => $draftUrls('candidate_photos', 'candidate_photos', 'candidate_photos'),
                    'additional_photos_urls' => $draftUrls('additional_photos', 'additionalPhotos', 'additional_photos'),
                ],
            ]);
        }

        if ($profile) {
            $profile->loadMissing([
                'preference', 'partnerPreferences', 'educations', 'employments', 'profileDocuments',
                'religionDetails', 'familyDetails', 'user',
            ]);
            $photos = is_array($profile->photos) ? $profile->photos : [];
            $latestEducation = $profile->educations->last();
            $latestEmployment = $profile->employments->last();
            $religion = $profile->religionDetails;
            $family = $profile->familyDetails;

            return response()->json([
                'success' => true,
                'type' => 'website_user',
                'user' => [
                    'id' => $profile->user?->id,
                    'username' => $profile->phone,
                    'profile_id' => $profile->id,
                ],
                'profile' => [
                    'fullName' => $profile->full_name,
                    'email' => $profile->email,
                    'username' => $profile->phone,
                    'source_of_information' => $profile->source_of_information,
                    'groomOrBride' => strtolower($profile->groomorbride),
                    'birthDate' => $profile->birth_date ?: $registration?->birthDate,
                    'maritalStatus' => $profile->marital_status,
                    'height' => $this->heightTextFromCm($profile->height_cm),
                    'weight' => $profile->weight_kg,
                    'havingSpecsLenses' => $profile->has_spectacles ? 'Yes' : 'None',
                    'bloodGroup' => $profile->blood_group,
                    'motherTongue' => $profile->mother_tongue,
                    'language' => implode(', ', $profile->languages ?? []),
                    'specialCases' => $profile->special_cases,
                    'knowAboutMyself' => $profile->about_me,
                    'placeOfBirth' => $profile->birth_place,
                    'timeOfBirth' => $profile->birth_time,
                    'birthTime_ampm' => $this->timeMeridiem($profile->birth_time),
                    'address' => data_get($profile->addresses, 'current.address'),
                    'permanent_address' => data_get($profile->addresses, 'permanent.address'),
                    'current_address_pincode' => data_get($profile->addresses, 'current.pincode'),
                    'current_address_city' => data_get($profile->addresses, 'current.city'),
                    'current_address_district' => data_get($profile->addresses, 'current.district'),
                    'current_address_state' => data_get($profile->addresses, 'current.state'),
                    'current_address_country' => data_get($profile->addresses, 'current.country', 'India'),
                    'religion' => $religion?->religion,
                    'caste' => $religion?->caste,
                    'subCaste' => $religion?->sub_caste,
                    'community' => $religion?->community,
                    'horoscope' => $religion?->horoscope,
                    'horoscope_match_required' => $religion?->horoscope_match_required,
                    'manglik_status' => $religion?->manglik_status,
                    'rashi' => $religion?->rashi,
                    'nakshatra' => $religion?->nakshatra,
                    'charan' => $religion?->charan,
                    'nadi' => $religion?->nadi,
                    'gan' => $religion?->gan,
                    'gotra' => $religion?->gotra,
                    'fathersName' => $family?->father_name,
                    'fatherAlive' => $family?->father_status === 'Alive' ? 'Yes' : ($family?->father_status === 'Deceased' ? 'No' : null),
                    'fatherContactNumber' => $family?->father_contact,
                    'fathersOccupation' => $family?->father_occupation,
                    'fathersNativePlace' => $family?->father_native_place,
                    'mothersName' => $family?->mother_name,
                    'motherAlive' => $family?->mother_status === 'Alive' ? 'Yes' : ($family?->mother_status === 'Deceased' ? 'No' : null),
                    'motherContactNumber' => $family?->mother_contact,
                    'mothersOccupation' => $family?->mother_occupation,
                    'mothersNativePlace' => $family?->mother_native_place,
                    'noOfBrothers' => $family?->brothers_count,
                    'brothersMarried' => $family?->married_brothers_count,
                    'noOfSisters' => $family?->sisters_count,
                    'sistersMarried' => $family?->married_sisters_count,
                    'parentsIntercasteMarriage' => $family?->parents_intercaste_marriage,
                    'parentsDifferentCaste' => $family?->parents_different_caste_details,
                    'familyValues' => $family?->family_values,
                    'familyFinancialStatus' => $family?->family_financial_status,
                    'familyAnnualIncome' => $family?->family_annual_income,
                    'profileCreatorName' => $profile->profile_creator_name,
                    'profileCreatorRelation' => $profile->creator_relation,
                    'referenceName' => data_get($profile->addresses, 'reference.name'),
                    'referenceRelation' => data_get($profile->addresses, 'reference.relation'),
                    'referenceAddress' => data_get($profile->addresses, 'reference.address'),
                    'referenceContactNumber' => data_get($profile->addresses, 'reference.contact'),
                    'referenceEmail' => data_get($profile->addresses, 'reference.email'),
                    'education' => $latestEducation?->education_level,
                    'degreeDiplomaName1' => $latestEducation?->degree_name,
                    'specialisation1' => $latestEducation?->specialization,
                    'universityBoardCollege' => $latestEducation?->university_name ?: $latestEducation?->college_name,
                    'cityOfEducation' => $latestEducation?->education_city,
                    'profession' => $latestEmployment?->profession,
                    'fieldOfWorking' => $latestEmployment?->working_field,
                    'organisationWorkingIn' => $latestEmployment?->organisation_name,
                    'professionalCity' => $latestEmployment?->employment_city,
                    'designation' => $latestEmployment?->designation,
                    'lengthOfEmployment' => $latestEmployment?->employment_duration,
                    'monthlyIncome' => $latestEmployment?->monthly_income,
                    'annualIncome' => $latestEmployment?->annual_income,
                    'educations' => $profile->educations->toArray(),
                    'employments' => $profile->employments->toArray(),
                ],
                'preference' => ($profile->partnerPreferences ?: $profile->preference)?->toArray(),
                'registration' => $registration ? [
                    'id' => $registration->id,
                    'payment_verified' => (bool) $registration->payment_verified,
                    'payment_screenshot_url' => $this->fileUrl(request(), $registration->payment_screenshot),
                    'is_booklet_req' => (bool) $registration->is_booklet_req,
                    'registration_fee' => (float) $campaign->registration_fee,
                    'booklet_fee' => (float) $campaign->booklet_fee,
                    'currency' => $campaign->currency,
                    'total_amount' => (float) $campaign->registration_fee
                        + ($registration->is_booklet_req ? (float) $campaign->booklet_fee : 0),
                ] : null,
                'has_registration' => (bool) $registration,
                'profile_photo_url' => $this->fileUrl(request(), $this->profilePhotoPath($profile, 'profile_photo', $photos)),
                'full_body_photo_url' => $this->fileUrl(request(), $this->profilePhotoPath($profile, 'full_body_photo', $photos)),
                'half_body_photo_url' => $this->fileUrl(request(), $this->profilePhotoPath($profile, 'half_body_photo', $photos)),
                'selfie_photo_url' => $this->fileUrl(request(), $this->profilePhotoPath($profile, 'selfie_photo', $photos)),
                'candidate_photos_urls' => $this->fileUrls(request(), $this->photoList($profile, 'candidate_photos', $photos)),
                'additional_photos_urls' => $this->fileUrls(request(), $this->photoList($profile, 'additional_photos', $photos)),
                'identification_proof_url' => $this->fileUrl(request(), $profile->profileDocuments?->aadhaar_card ?: $profile->getAttribute('identificationProof')),
                'pan_card_url' => $this->fileUrl(request(), $profile->profileDocuments?->pan_card),
                'passport_url' => $this->fileUrl(request(), $profile->profileDocuments?->passport),
                'birth_date_proof_url' => $this->fileUrl(request(), $profile->profileDocuments?->birth_certificate ?: $profile->getAttribute('birthDateProof')),
                'address_proof_url' => $this->fileUrl(request(), $profile->profileDocuments?->address_proof ?: $profile->getAttribute('addressProof')),
                'education_proof_url' => $this->fileUrl(request(), $profile->profileDocuments?->education_certificate ?: $profile->getAttribute('educationProof')),
                'profession_proof_url' => $this->fileUrl(request(), $profile->profileDocuments?->profession_proof ?: $profile->getAttribute('professionProof')),
                'income_proof_url' => $this->fileUrl(request(), $profile->profileDocuments?->income_proof ?: $profile->getAttribute('incomeProof')),
                'caste_certificate_url' => $this->fileUrl(request(), $profile->profileDocuments?->caste_certificate ?: $profile->getAttribute('casteCertificate')),
                'medical_fitness_certificate_url' => $this->fileUrl(request(), $profile->profileDocuments?->medical_fitness_certificate ?: $profile->getAttribute('medicalFitnessCertificate')),
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
                $newPaths = collect($request->file($input))
                    ->map(fn ($file) => $file->store($directory, 'public'))
                    ->values()
                    ->all();
                $registrationData['_pending_files'][$key] = array_values(array_merge(
                    is_array($registrationData['_pending_files'][$key] ?? null)
                        ? $registrationData['_pending_files'][$key]
                        : [],
                    $newPaths
                ));
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
            $profile = Profile::where('phone', $data['username'])->first();

            if (! $user && $profile) {
                $user = $profile->user;
            }

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

            $profile ??= $user->profile_id ? Profile::find($user->profile_id) : null;
            $profile = $this->saveProfile($profile, $request, $data, $user);
            $user->profile_id = $profile->id;
            $user->save();

            $pendingFiles = $this->pendingFiles($request);
            $existingRegistration = ProfileMelavaRegistration::where('profile_id', $profile->id)
                ->where('melava_campaign_id', $campaign->id)
                ->first();
            $paymentPath = $request->hasFile('payment_screenshot')
                ? $request->file('payment_screenshot')->store('payment_screenshots', 'public')
                : (! empty($pendingFiles['payment_screenshot'])
                    ? $this->promotePendingFile($pendingFiles['payment_screenshot'], 'payment_screenshots')
                    : $existingRegistration?->payment_screenshot);
            $registration = ProfileMelavaRegistration::updateOrCreate(
                ['profile_id' => $profile->id, 'melava_campaign_id' => $campaign->id],
                [
                    'is_booklet_req' => $request->boolean('booklet_required'),
                    'payment_screenshot' => $paymentPath,
                    'payment_verified' => (bool) $existingRegistration?->payment_verified,
                ]
            );
            if ($paymentPath) {
                PaymentScreenshot::updateOrCreate(
                    ['profile_id' => $profile->id, 'screenshot_path' => $paymentPath],
                    ['payment_verified' => (bool) $registration->payment_verified]
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
            'payment_type' => ['nullable', Rule::in(['registration', 'booklet', 'both'])],
            'booklet_required' => ['nullable', 'boolean'],
        ]);
        $paymentType = $data['payment_type'] ?? 'registration';
        if (in_array($paymentType, ['booklet', 'both'], true) && ! $registration->is_booklet_req) {
            return response()->json([
                'success' => false,
                'message' => 'This registration does not include a booklet payment.',
            ], 422);
        }

        $paymentPath = $request->hasFile('payment_screenshot')
            ? $request->file('payment_screenshot')->store('payment_screenshots', 'public')
            : null;
        if (in_array($paymentType, ['registration', 'both'], true)) {
            if ($paymentPath) {
                $registration->payment_screenshot = $paymentPath;
            }
            if (array_key_exists('payment_verified', $data)) {
                $registration->payment_verified = (bool) $data['payment_verified'];
            }
        }
        if (in_array($paymentType, ['booklet', 'both'], true)) {
            if ($paymentPath) {
                $registration->booklet_payment_screenshot = $paymentPath;
            }
            if (array_key_exists('payment_verified', $data)) {
                $registration->booklet_payment_verified = (bool) $data['payment_verified'];
            }
        }
        if (array_key_exists('booklet_required', $data)) {
            $registration->is_booklet_req = (bool) $data['booklet_required'];
        }
        $registration->save();
        $registration->loadMissing('campaign');
        $pathsToSync = [];
        if (in_array($paymentType, ['registration', 'both'], true) && $registration->payment_screenshot) {
            $pathsToSync[$registration->payment_screenshot] = (bool) $registration->payment_verified;
        }
        if (in_array($paymentType, ['booklet', 'both'], true) && $registration->booklet_payment_screenshot) {
            $pathsToSync[$registration->booklet_payment_screenshot] = (bool) $registration->booklet_payment_verified;
        }
        foreach ($pathsToSync as $path => $verified) {
            $isRegistrationPath = $registration->payment_screenshot === $path;
            $isBookletPath = $registration->booklet_payment_screenshot === $path;
            $category = $isRegistrationPath && $isBookletPath
                ? 'combined'
                : ($isBookletPath ? 'booklet' : 'melava');
            $amount = ($isRegistrationPath ? (float) ($registration->campaign?->registration_fee ?? 0) : 0)
                + ($isBookletPath ? (float) ($registration->campaign?->booklet_fee ?? 0) : 0);
            $payment = PaymentScreenshot::firstOrNew([
                'profile_id' => $registration->profile_id,
                'screenshot_path' => $path,
            ]);
            $details = $payment->payment_details ?? [];
            $details = array_merge([
                'category' => $category,
                'amount' => $amount,
                'payment_date' => optional($registration->created_at)->toDateString(),
                'payment_method' => null,
                'reference_number' => null,
                'remarks' => null,
                'verification_status' => $verified ? 'verified' : 'pending',
                'proof_file' => $path,
            ], $details, [
                'category' => $category,
                'verification_status' => $verified ? 'verified' : 'pending',
                'proof_file' => $path,
            ]);
            $payment->payment_verified = $verified;
            $payment->payment_details = $details;
            $payment->save();
        }
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
                $photos[$key] = $request->file($input)->store('profile_photos/candidate', 'public');
            } elseif (! empty($pendingFiles[$input])) {
                $photos[$key] = $this->promotePendingFile($pendingFiles[$input], 'profile_photos/candidate');
            }
        }
        if ($request->hasFile('candidate_photos')) {
            $photos['candidate_photos'] = array_values(array_merge(
                $this->photoPaths($photos['candidate_photos'] ?? []),
                collect($request->file('candidate_photos'))
                    ->map(fn ($file) => $file->store('profile_photos/candidate', 'public'))
                    ->values()
                    ->all()
            ));
        } elseif (! empty($pendingFiles['candidate_photos'])) {
            $photos['candidate_photos'] = array_values(array_merge(
                $this->photoPaths($photos['candidate_photos'] ?? []),
                array_map(
                fn (string $path) => $this->promotePendingFile($path, 'profile_photos/candidate'),
                $pendingFiles['candidate_photos']
                )
            ));
        }
        if ($request->hasFile('additionalPhotos')) {
            $photos['additional_photos'] = array_values(array_merge(
                $this->photoPaths($photos['additional_photos'] ?? []),
                collect($request->file('additionalPhotos'))
                    ->map(fn ($file) => $file->store('profile_photos/candidate', 'public'))
                    ->values()
                    ->all()
            ));
        } elseif (! empty($pendingFiles['additional_photos'])) {
            $photos['additional_photos'] = array_values(array_merge(
                $this->photoPaths($photos['additional_photos'] ?? []),
                array_map(
                    fn (string $path) => $this->promotePendingFile($path, 'profile_photos/candidate'),
                    $pendingFiles['additional_photos']
                )
            ));
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
                    'address' => $data['address'] ?? data_get($profile->addresses, 'current.address'),
                    'pincode' => $data['current_address_pincode'] ?? data_get($profile->addresses, 'current.pincode'),
                    'city' => $data['current_address_city'] ?? data_get($profile->addresses, 'current.city'),
                    'district' => $data['current_address_district'] ?? data_get($profile->addresses, 'current.district'),
                    'state' => $data['current_address_state'] ?? data_get($profile->addresses, 'current.state'),
                    'country' => $data['current_address_country'] ?? data_get($profile->addresses, 'current.country'),
                ],
                'permanent' => [
                    'address' => $data['permanent_address'] ?? data_get($profile->addresses, 'permanent.address'),
                ],
                'reference' => [
                    'name' => $data['referenceName'] ?? data_get($profile->addresses, 'reference.name'),
                    'relation' => $data['referenceRelation'] ?? data_get($profile->addresses, 'reference.relation'),
                    'address' => $data['referenceAddress'] ?? data_get($profile->addresses, 'reference.address'),
                    'contact' => $data['referenceContactNumber'] ?? data_get($profile->addresses, 'reference.contact'),
                    'email' => $data['referenceEmail'] ?? data_get($profile->addresses, 'reference.email'),
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
            'payment_screenshot' => [
                $required && ! $this->hasReusablePaymentScreenshot($request) ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
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

    private function hasReusablePaymentScreenshot(Request $request): bool
    {
        $mobile = (string) $request->input('username', '');
        $campaignId = $request->input('melava_campaign_id');
        if (! preg_match('/^\d{10}$/', $mobile) || ! is_scalar($campaignId) || ! ctype_digit((string) $campaignId)) {
            return false;
        }

        $pendingToken = $request->input('pending_registration_token');
        if (is_string($pendingToken) && $pendingToken !== '') {
            $pending = MelavaPendingRegistration::where('token', $pendingToken)
                ->where('mobile', $mobile)
                ->where('melava_campaign_id', (int) $campaignId)
                ->where('status', 'pending')
                ->first();
            $pendingData = $pending ? json_decode((string) $pending->registration_data, true) : null;
            $pendingScreenshot = is_array($pendingData)
                ? ($pendingData['_pending_files']['payment_screenshot'] ?? null)
                : null;
            if (is_string($pendingScreenshot) && Storage::disk('public')->exists($pendingScreenshot)) {
                return true;
            }
        }

        $profile = Profile::where('phone', $mobile)->first();
        $screenshot = $profile
            ? ProfileMelavaRegistration::where('profile_id', $profile->id)
                ->where('melava_campaign_id', (int) $campaignId)
                ->value('payment_screenshot')
            : null;

        return is_string($screenshot) && Storage::disk('public')->exists($screenshot);
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

    private function photoPaths($photos): array
    {
        if (is_string($photos)) {
            $photos = json_decode($photos, true);
        }

        return array_values(array_filter(
            is_array($photos) ? $photos : [],
            static fn ($path): bool => is_string($path) && trim($path) !== ''
        ));
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
        $employment = $profile?->employments?->sortByDesc('id')->first();
        $education = $profile?->educations?->sortByDesc('id')->first();
        $data = [
            'id' => $registration->id,
            'updated_by_user_id' => $registration->updated_by_user_id,
            'profile_id' => $profile?->id,
            'fullName' => $profile?->full_name,
            'groomOrBride' => $profile?->groomorbride,
            'birthDate' => $profile?->birth_date,
            'username' => $profile?->phone,
            'city' => data_get($profile?->addresses, 'current.city')
                ?: $employment?->employment_city
                ?: $education?->education_city,
            'created_at' => $registration->created_at?->format(DATE_ATOM),
            'registration_type' => $profile?->registration_type,
            'payment_verified' => (bool) $registration->payment_verified,
            'payment_screenshot' => $registration->payment_screenshot,
            'payment_screenshot_url' => $this->fileUrl(request(), $registration->payment_screenshot),
            'booklet_payment_screenshot' => $registration->booklet_payment_screenshot,
            'booklet_payment_screenshot_url' => $this->fileUrl(request(), $registration->booklet_payment_screenshot),
            'booklet_payment_verified' => (bool) $registration->booklet_payment_verified,
            'is_booklet_req' => (bool) $registration->is_booklet_req,
            'melava_number' => $registration->campaign?->melava_code,
            'melava_title' => $registration->campaign?->title,
            'melava_campaign_id' => $registration->melava_campaign_id,
            'registration_fee' => (float) ($registration->campaign?->registration_fee ?? 0),
            'booklet_fee' => (float) ($registration->campaign?->booklet_fee ?? 0),
            'currency' => $registration->campaign?->currency,
            'total_amount' => (float) ($registration->campaign?->registration_fee ?? 0)
                + ($registration->is_booklet_req ? (float) ($registration->campaign?->booklet_fee ?? 0) : 0),
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

    private function heightTextFromCm($height): ?string
    {
        if ($height === null || $height === '') {
            return null;
        }

        $inches = round(((float) $height) / 2.54);
        $feet = intdiv((int) $inches, 12);

        return (string) $feet;
    }

    private function timeMeridiem(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return (int) substr($time, 0, 2) >= 12 ? 'PM' : 'AM';
    }

    private function fileUrl(Request $request, ?string $path): string
    {
        if (! $path) {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim(preg_replace('#^/?storage/#', '', $path), '/'));
    }

    private function fileUrls(Request $request, array $paths): array
    {
        return array_values(array_filter(array_map(
            fn ($path) => $this->fileUrl($request, is_string($path) ? $path : null),
            $paths
        )));
    }

    private function profilePhotoPath(Profile $profile, string $key, array $photos): ?string
    {
        $legacyKey = match ($key) {
            'profile_photo' => 'profilePhoto',
            default => $key,
        };

        return $photos[$key]
            ?? ($key === 'profile_photo' ? ($photos['profilePicture'] ?? $photos[0] ?? null) : null)
            ?? $profile->getAttribute($legacyKey)
            ?? ($key === 'profile_photo' ? $profile->getAttribute('profilePicture') : null)
            ?? $profile->getAttribute($key);
    }

    private function photoList(Profile $profile, string $key, array $photos): array
    {
        $legacyKey = $key === 'additional_photos' ? 'additionalPhotos' : 'candidatePhotos';
        $value = $photos[$key] ?? $photos[$legacyKey] ?? $profile->getAttribute($key) ?? $profile->getAttribute($legacyKey) ?? [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        return is_array($value)
            ? array_values(array_filter($value, fn ($path) => is_string($path) && $path !== ''))
            : [];
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
