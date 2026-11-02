<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaPendingRegistration;
use App\MelavaRegistration;
use App\Models\User;
use App\PaymentScreenshot;
use App\Preference;
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

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', Rule::in(['pending', 'verified'])],
            'booklet' => ['nullable', Rule::in(['yes', 'no'])],
            'gender' => ['nullable', Rule::in(['groom', 'bride'])],
            'melava_number' => ['nullable', 'string', 'max:255'],
            'include_filters' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'age_from' => ['nullable', 'integer', 'min:1', 'max:120'],
            'age_to' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $ageFrom = $request->filled('age_from') ? (int) $request->input('age_from') : null;
        $ageTo = $request->filled('age_to') ? (int) $request->input('age_to') : null;

        if ($ageFrom !== null && $ageTo !== null && $ageFrom > $ageTo) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum age cannot be greater than maximum age.',
                'errors' => ['age_from' => ['Minimum age cannot be greater than maximum age.']],
            ], 422);
        }

        $today = Carbon::today();

        $baseQuery = MelavaRegistration::query()
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $term = trim($filters['q']);
                $query->where(function ($search) use ($term) {
                    if (ctype_digit($term)) {
                        $numberMatch = strlen($term) === 10
                            ? ['=', $term]
                            : ['like', $term.'%'];
                        $search->where('username', $numberMatch[0], $numberMatch[1])
                            ->orWhereHas('profile', function ($profile) use ($numberMatch) {
                                $profile->where('reg_id', $numberMatch[0], $numberMatch[1])
                                    ->orWhere('phone', $numberMatch[0], $numberMatch[1]);
                            });

                        return;
                    }

                    $search->where('fullName', 'like', '%' . $term . '%')
                        ->orWhere('source_of_information', 'like', '%' . $term . '%')
                        ->orWhere('city', 'like', '%' . $term . '%')
                        ->orWhere('melava_title', 'like', '%' . $term . '%')
                        ->orWhere('melava_number', 'like', '%' . $term . '%')
                        ->orWhereHas('profile', function ($profile) use ($term) {
                            $profile->where('reg_id', 'like', '%' . $term . '%')
                                ->orWhereHas('employments', fn ($employment) => $employment
                                    ->where('employment_city', 'like', '%' . $term . '%'));
                        });
                });
            })
            ->when(isset($filters['payment_status']), function ($query) use ($filters) {
                $query->where('payment_verified', $filters['payment_status'] === 'verified');
            })
            ->when(isset($filters['booklet']), function ($query) use ($filters) {
                $query->where('is_booklet_req', $filters['booklet'] === 'yes');
            })
            ->when(isset($filters['gender']), function ($query) use ($filters) {
                $genders = $filters['gender'] === 'bride'
                    ? ['bride', 'female', 'f', 'vadhu']
                    : ['groom', 'male', 'm', 'var'];
                $query->whereIn(DB::raw('LOWER(groomOrBride)'), $genders);
            })
            ->when(isset($filters['melava_number']), function ($query) use ($filters) {
                $query->where('melava_number', $filters['melava_number']);
            })
            ->when($ageFrom !== null, function ($query) use ($ageFrom, $today) {
                $query->whereNotNull('birthDate')
                    ->whereDate('birthDate', '<=', $today->copy()->subYears($ageFrom)->toDateString());
            })
            ->when($ageTo !== null, function ($query) use ($ageTo, $today) {
                $query->whereNotNull('birthDate')
                    ->whereDate('birthDate', '>', $today->copy()->subYears($ageTo + 1)->toDateString());
            });

        $stats = (clone $baseQuery)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN LOWER(groomOrBride) IN ('groom', 'male', 'm', 'var') THEN 1 ELSE 0 END) AS groom")
            ->selectRaw("SUM(CASE WHEN LOWER(groomOrBride) IN ('bride', 'female', 'f', 'vadhu') THEN 1 ELSE 0 END) AS bride")
            ->selectRaw('SUM(CASE WHEN is_booklet_req = 1 THEN 1 ELSE 0 END) AS booklet')
            ->selectRaw('SUM(CASE WHEN payment_verified = 1 THEN 1 ELSE 0 END) AS verified')
            ->selectRaw('SUM(CASE WHEN payment_verified = 0 THEN 1 ELSE 0 END) AS pending')
            ->selectRaw("SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) AS attended")
            ->first();

        $registrations = (clone $baseQuery)
            ->with('profile')
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 20));

        $registrations->getCollection()->transform(
            fn (MelavaRegistration $registration) => $this->presentRegistration($registration)
        );

        $response = [
            'success' => true,
            'data' => $registrations,
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'groom' => (int) ($stats->groom ?? 0),
                'bride' => (int) ($stats->bride ?? 0),
                'booklet' => (int) ($stats->booklet ?? 0),
                'verified' => (int) ($stats->verified ?? 0),
                'pending' => (int) ($stats->pending ?? 0),
                'attended' => (int) ($stats->attended ?? 0),
            ],
        ];

        if ($request->boolean('include_filters')) {
            $response['melava_numbers'] = DB::table('melava_registrations')
                ->whereNotNull('melava_number')
                ->where('melava_number', '<>', '')
                ->distinct()
                ->orderBy('melava_number')
                ->pluck('melava_number')
                ->values();
        }

        return response()->json($response);
    }

    public function updatePayment(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $registration = MelavaRegistration::find($id);
        if (! $registration) {
            return response()->json([
                'success' => false,
                'message' => 'Melava registration not found.',
            ], 404);
        }

        $data = $request->validate([
            'payment_type' => ['required', Rule::in(['registration', 'booklet'])],
            'payment_verified' => ['required', 'boolean'],
            'payment_screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        if ($data['payment_type'] === 'booklet' && ! $registration->is_booklet_req) {
            throw ValidationException::withMessages([
                'payment_type' => ['This candidate did not request a booklet.'],
            ]);
        }

        if (! $request->hasFile('payment_screenshot') && ! $registration->getAttribute(
            $data['payment_type'] === 'booklet' ? 'booklet_payment_screenshot' : 'payment_screenshot'
        )) {
            throw ValidationException::withMessages([
                'payment_screenshot' => ['Upload a payment proof before saving this payment.'],
            ]);
        }

        $newPath = null;
        try {
            if ($request->hasFile('payment_screenshot')) {
                $newPath = $this->storeUpload($request->file('payment_screenshot'), 'melava_payments');
            }

            DB::transaction(function () use ($registration, $data, $newPath) {
                $screenshotField = $data['payment_type'] === 'booklet'
                    ? 'booklet_payment_screenshot'
                    : 'payment_screenshot';
                $verifiedField = $data['payment_type'] === 'booklet'
                    ? 'booklet_payment_verified'
                    : 'payment_verified';

                if ($newPath) {
                    $registration->{$screenshotField} = $newPath;
                }
                $registration->{$verifiedField} = (bool) $data['payment_verified'];
                $registration->save();

                if ($data['payment_type'] === 'registration' && $registration->profile_id && $registration->melava_campaign_id) {
                    ProfileMelavaRegistration::where('profile_id', $registration->profile_id)
                        ->where('melava_campaign_id', $registration->melava_campaign_id)
                        ->update([
                            'payment_screenshot' => $registration->payment_screenshot,
                            'payment_verified' => (bool) $data['payment_verified'],
                        ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Melava payment updated successfully.',
                'data' => [
                    'registration_id' => $registration->id,
                    'payment_type' => $data['payment_type'],
                    'payment_verified' => (bool) $data['payment_verified'],
                    'payment_screenshot_url' => $newPath
                        ? $this->fileUrl($newPath)
                        : $this->fileUrl($registration->getAttribute(
                            $data['payment_type'] === 'booklet' ? 'booklet_payment_screenshot' : 'payment_screenshot'
                        )),
                ],
            ]);
        } catch (Throwable $e) {
            if ($newPath) {
                $absolutePath = public_path($newPath);
                if (is_file($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            Log::error('Melava payment update failed', [
                'registration_id' => $id,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the Melava payment right now.',
            ], 500);
        }
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

                    if ($registration->melava_campaign_id) {
                        ProfileMelavaRegistration::where('profile_id', $registration->profile_id)
                            ->where('melava_campaign_id', $registration->melava_campaign_id)
                            ->update(['payment_verified' => (bool) $data['payment_verified']]);
                    }
                }
            });

            if ($registration->profile_id) {
                Profile::find($registration->profile_id)?->syncDocumentVerificationStatus();
            }

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
        $websiteUser = User::where('username', $mobile)->first();
        $websiteProfile = $websiteUser && $websiteUser->profile_id
            ? Profile::find($websiteUser->profile_id)
            : null;

        $campaign = $this->resolveCampaign((string) $request->input('melava_campaign_id', ''));
        $campaignId = (int) ($campaign->id ?? 0);
        $registrationQuery = MelavaRegistration::where('username', $mobile);
        if ($campaignId > 0) {
            $registrationQuery->where('melava_campaign_id', $campaignId);
        } else {
            $registrationQuery->where('melava_batch', self::CURRENT_MELAVA_BATCH);
        }
        $registration = $registrationQuery->latest('id')->first();

        $pending = MelavaPendingRegistration::where('mobile', $mobile)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pending && ! $websiteProfile && ! $registration) {
            $pendingData = json_decode((string) $pending->registration_data, true);

            if (is_array($pendingData)) {
                return response()->json([
                    'success' => true,
                    'type' => 'pending_registration',
                    'message' => 'Your previous Melava registration was found. Your saved details can be restored.',
                    'pending_registration' => [
                        'token' => $pending->token,
                        'registration_data' => $pendingData,
                        'profile_photo_url' => $this->fileUrl($pendingData['profilePhoto'] ?? null),
                        'full_body_photo_url' => $this->fileUrl($pendingData['full_body_photo'] ?? null),
                        'half_body_photo_url' => $this->fileUrl($pendingData['half_body_photo'] ?? null),
                        'selfie_photo_url' => $this->fileUrl($pendingData['selfie_photo'] ?? null),
                        'identification_proof_url' => $this->fileUrl($pendingData['identificationProof'] ?? null),
                        'birth_date_proof_url' => $this->fileUrl($pendingData['birthDateProof'] ?? null),
                        'education_proof_url' => $this->fileUrl($pendingData['educationProof'] ?? null),
                        'candidate_photos_urls' => collect(is_array($pendingData['candidate_photos'] ?? null) ? $pendingData['candidate_photos'] : [])
                            ->filter()
                            ->map(fn ($path) => $this->fileUrl($path))
                            ->values()
                            ->all(),
                        'additional_photos_urls' => collect(is_array($pendingData['additionalPhotos'] ?? null) ? $pendingData['additionalPhotos'] : [])
                            ->filter()
                            ->map(fn ($path) => $this->fileUrl($path))
                            ->values()
                            ->all(),
                        'is_existing_website_user' => (bool) $pending->is_existing_website_user,
                        'user_id' => $pending->user_id,
                        'profile_id' => $pending->profile_id,
                    ],
                ]);
            }
        }

        $user = $websiteUser;

        if (! $user) {
            return response()->json([
                'success' => false,
                'type' => 'new_candidate',
                'message' => 'No previous registration was found. You can continue as a new candidate.',
            ]);
        }

        $profile = $websiteProfile;

        if (! $profile && ! $registration) {
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

        return response()->json([
            'success' => true,
            'type' => 'website_user',
            'message' => 'Website registration found. Your details have been filled in for you.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'profile_id' => $user->profile_id,
            ],
            'profile' => $profile ? $this->profileForRestore($profile) : [],
            'melava_registration' => $registration ? $registration->toArray() : null,
            'profile_photo_url' => $this->fileUrl($this->profilePhotoPath($profile, 'profile_photo')),
            'full_body_photo_url' => $this->fileUrl($this->profilePhotoPath($profile, 'full_body_photo')),
            'half_body_photo_url' => $this->fileUrl($this->profilePhotoPath($profile, 'half_body_photo')),
            'selfie_photo_url' => $this->fileUrl($this->profilePhotoPath($profile, 'selfie_photo')),
            'identification_proof_url' => $this->fileUrl($this->profileDocumentPath($profile, 'aadhaar_card')),
            'birth_date_proof_url' => $this->fileUrl(
                $this->profileDocumentPath($profile, 'birth_certificate')
                    ?: ($registration?->birth_verification_doc ?: null)
            ),
            'education_proof_url' => $this->fileUrl($this->profileDocumentPath($profile, 'education_certificate')),
            'candidate_photos_urls' => collect(
                $this->profilePhotoList($profile, 'candidate_photos')
                    ?: $this->normalizePathList($registration?->getRawOriginal('candidate_photos'))
            )
                ->filter()->map(fn ($path) => $this->fileUrl($path))->values()->all(),
            'additional_photos_urls' => collect($this->profilePhotoList($profile, 'additional_photos'))
                ->filter()->map(fn ($path) => $this->fileUrl($path))->values()->all(),
            'preference' => $user->preference,
            'has_registration' => (bool) $registration,
            'registration' => $registration
                ? [
                    'id' => $registration->id,
                    'melava_batch' => $registration->melava_batch,
                    'melava_registered_at' => $registration->melava_registered_at,
                    'is_booklet_req' => (bool) $registration->is_booklet_req,
                    'payment_verified' => (bool) $registration->payment_verified,
                ]
                : null,
        ]);
    }

    public function saveDraft(Request $request)
    {
        if (is_string($request->input('registration_data'))) {
            $decoded = json_decode($request->input('registration_data'), true);
            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'registration_data' => ['Registration details are invalid.'],
                ]);
            }
            $request->merge(['registration_data' => $decoded]);
        }

        $validated = $request->validate([
            'mobile' => ['required', 'digits:10'],
            'registration_data' => ['required', 'array'],
            'token' => ['nullable', 'string', 'max:64'],
            'melava_campaign_id' => ['required', 'string', 'max:20'],
            'profilePhoto' => ['nullable', 'image', 'max:5120'],
            'full_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'half_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'identificationProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'birthDateProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'educationProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'candidate_photos' => ['nullable', 'array', 'max:5'],
            'candidate_photos.*' => ['image', 'max:5120'],
            'additionalPhotos' => ['nullable', 'array', 'max:4'],
            'additionalPhotos.*' => ['image', 'max:5120'],
        ]);

        $campaign = $this->resolveCampaign($validated['melava_campaign_id']);
        if (! $campaign) {
            throw ValidationException::withMessages([
                'melava_campaign_id' => ['The selected Melava campaign is invalid.'],
            ]);
        }

        $request->merge(['melava_campaign_id' => $campaign->id]);
        $validated['melava_campaign_id'] = $campaign->id;
        $melavaNumber = strtoupper((string) $campaign->melava_code);

        $token = trim((string) ($validated['token'] ?? ''));
        $draft = $token
            ? MelavaPendingRegistration::where('token', $token)
                ->where('status', 'pending')
                ->first()
            : null;

        if (! $draft && ! empty($validated['melava_campaign_id'])) {
            $draft = MelavaPendingRegistration::where('mobile', $validated['mobile'])
                ->where('status', 'pending')
                ->where('melava_campaign_id', $validated['melava_campaign_id'])
                ->latest('id')
                ->first();
        }

        if (! $draft) {
            $draft = new MelavaPendingRegistration();
            $draft->token = Str::random(64);
            $draft->mobile = $validated['mobile'];
        }

        $registrationData = $validated['registration_data'];
        $photoFields = [
            'profilePhoto' => 'profile_photos',
            'full_body_photo' => 'full_body_photos',
            'half_body_photo' => 'half_body_photos',
            'selfie_photo' => 'selfie_photos',
        ];
        foreach ($photoFields as $field => $directory) {
            if ($request->hasFile($field)) {
                $registrationData[$field] = $this->storeUpload($request->file($field), $directory);
            }
        }

        foreach ([
            'identificationProof' => 'melava_documents',
            'birthDateProof' => 'melava_documents',
            'educationProof' => 'melava_documents',
        ] as $field => $directory) {
            if ($request->hasFile($field)) {
                $registrationData[$field] = $this->storeUpload($request->file($field), $directory);
            }
        }
        if ($request->hasFile('candidate_photos')) {
            $registrationData['candidate_photos'] = $this->storeUploads($request->file('candidate_photos'), 'melava_candidate_photos');
        }
        if ($request->hasFile('additionalPhotos')) {
            $registrationData['additionalPhotos'] = $this->storeUploads($request->file('additionalPhotos'), 'melava_additional_photos');
        }

        $existingUser = User::where('username', $validated['mobile'])->first();
        $draftRegistrationData = json_decode((string) $draft->registration_data, true);
        if (is_array($draftRegistrationData)) {
            $registrationData = array_replace($draftRegistrationData, $registrationData);
        }

        DB::transaction(function () use ($draft, $validated, $registrationData, $existingUser, $melavaNumber) {
            $draft->fill([
            'mobile' => $validated['mobile'],
            'registration_data' => json_encode($registrationData, JSON_UNESCAPED_UNICODE),
            'is_existing_website_user' => (bool) ($validated['registration_data']['is_existing_website_user'] ?? false),
            'user_id' => $existingUser?->id,
            'profile_id' => $existingUser?->profile_id,
            'melava_campaign_id' => $validated['melava_campaign_id'],
            'melava_number' => strtoupper($melavaNumber),
            'status' => 'pending',
            'expires_at' => null,
            ]);
            $draft->save();
        });

        return response()->json([
            'success' => true,
            'token' => $draft->token,
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeInput($request);

        $data = $request->validate([
            'melava_campaign_id' => ['required', 'string', 'max:20'],
            'is_existing_website_user' => ['nullable', 'boolean'],
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
            'current_address_pincode' => ['nullable', 'digits:6'],
            'current_address_city' => ['nullable', 'string', 'max:191'],
            'current_address_district' => ['nullable', 'string', 'max:191'],
            'current_address_state' => ['nullable', 'string', 'max:191'],
            'current_address_country' => ['nullable', 'string', 'max:191'],
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
            'monthlyIncome' => ['nullable', 'numeric', 'min:0'],
            'annualIncome' => ['nullable', 'numeric', 'min:0'],
            'income_currency' => ['nullable', 'string', 'max:10'],
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
            'familyAnnualIncome' => ['nullable', 'numeric', 'min:0'],
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
            'full_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'half_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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

        $campaign = $this->resolveCampaign($data['melava_campaign_id']);
        if (! $campaign) {
            throw ValidationException::withMessages([
                'melava_campaign_id' => ['The selected Melava campaign is invalid.'],
            ]);
        }
        $data['melava_campaign_id'] = $campaign->id;
        $campaign = DB::table('melava_campaigns')
            ->where('id', $campaign->id)
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

        $paidRegistration = MelavaRegistration::where('username', $data['username'])
            ->where('melava_campaign_id', $campaign->id)
            ->where('payment_verified', true)
            ->latest('id')
            ->first();
        if ($paidRegistration) {
            throw ValidationException::withMessages([
                'payment_screenshot' => ['Payment for this Melava registration is already verified.'],
            ]);
        }

        $registrationFee = (float) $campaign->registration_fee;
        $bookletFee = $request->boolean('booklet_required')
            ? (float) $campaign->booklet_fee
            : 0;
        $totalAmount = $registrationFee + $bookletFee;

        $mobile = $data['username'];
        $existingUser = User::where('username', $mobile)->first();
        $isExisting = (bool) $existingUser;

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
                $fullBodyPhoto = $request->hasFile('full_body_photo')
                    ? $this->storeUpload($request->file('full_body_photo'), 'full_body_photos')
                    : null;
                $halfBodyPhoto = $request->hasFile('half_body_photo')
                    ? $this->storeUpload($request->file('half_body_photo'), 'half_body_photos')
                    : null;
                $selfiePhoto = $request->hasFile('selfie_photo')
                    ? $this->storeUpload($request->file('selfie_photo'), 'selfie_photos')
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
                    $candidateRoleId = DB::table('roles')
                        ->where('user_type', '21')
                        ->value('id');
                    if (! $candidateRoleId) {
                        throw new \RuntimeException('Candidate role is not configured.');
                    }

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
                        'user_type' => '21',
                        'role_id' => $candidateRoleId,
                        'status' => 'active',
                        'is_password_changed' => 0,
                    ]);
                } else {
                    $user = $existingUser;
                    $user->name = $data['fullName'];
                    $user->save();
                }

                $profile = $this->persistNormalizedProfile(
                    $profile,
                    $request,
                    $user,
                    [
                        'profile_photo' => $profilePhoto,
                        'full_body_photo' => $fullBodyPhoto,
                        'half_body_photo' => $halfBodyPhoto,
                        'selfie_photo' => $selfiePhoto,
                        'additional_photos' => $additionalPhotos,
                        'candidate_photos' => $candidatePhotos,
                    ],
                    [
                        'identificationProof' => $identificationProof,
                        'birthDateProof' => $birthDateProof,
                        'educationProof' => $educationProof,
                        'biodata' => $biodata,
                    ],
                );
                if ((int) $user->profile_id !== (int) $profile->id) {
                    $user->profile_id = $profile->id;
                    $user->save();
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
                    'melava_number' => strtoupper($campaign->melava_code),
                    'registration_fee' => $registrationFee,
                    'booklet_fee' => $bookletFee,
                    'total_amount' => $totalAmount,
                    'payment_screenshot' => $paymentScreenshot,
                    'payment_verified' => false,
                ]);
                $registration->save();

                ProfileMelavaRegistration::updateOrCreate(
                    [
                        'profile_id' => $profile->id,
                        'melava_campaign_id' => $campaign->id,
                    ],
                    [
                        'is_booklet_req' => $request->boolean('booklet_required'),
                        'payment_screenshot' => $paymentScreenshot,
                        'payment_verified' => false,
                    ]
                );

                PaymentScreenshot::updateOrCreate(
                    ['profile_id' => $profile->id],
                    [
                        'screenshot_path' => $paymentScreenshot,
                        'payment_verified' => false,
                    ]
                );

                if ($request->filled('pending_registration_token')) {
                    MelavaPendingRegistration::where('token', $request->input('pending_registration_token'))
                        ->update(['status' => 'completed']);
                }

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
                    'registration_number' => $profile->reg_id,
                    'serial_no' => null,
                    'melava_batch' => $registration->melava_batch,
                    'melava_title' => $registration->melava_title,
                    'melava_number' => $registration->melava_number,
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
            'data' => $this->presentRegistration($registration, true),
        ]);
    }

    public function update(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $registration = MelavaRegistration::with('profile')->find($id);
        if (! $registration || ! $registration->profile) {
            return response()->json([
                'success' => false,
                'message' => 'Melava registration or candidate profile was not found.',
            ], 404);
        }

        $request->merge(['username' => $registration->username]);
        $this->normalizeInput($request);
        foreach (['existing_candidate_photos', 'existing_additional_photos', 'removed_files'] as $field) {
            if (is_string($request->input($field))) {
                $decoded = json_decode($request->input($field), true);
                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([
                        $field => ['Registration file details are invalid.'],
                    ]);
                }
                $request->merge([$field => $decoded]);
            }
        }

        $profileFields = [
            'weight', 'havingSpecsLenses', 'bloodGroup', 'motherTongue', 'language',
            'specialCases', 'knowAboutMyself', 'permanent_address', 'placeOfBirth',
            'current_address_pincode', 'current_address_city', 'current_address_district',
            'current_address_state', 'current_address_country',
            'degreeDiplomaName1', 'degreeDiplomaName2', 'specialisation1', 'specialisation2',
            'universityBoardCollege', 'additionalEducation1', 'additionalEducation2',
            'cityOfEducation', 'fieldOfWorking', 'organisationWorkingIn', 'designation',
            'lengthOfEmployment', 'monthlyIncome', 'annualIncome', 'income_currency',
            'personalMedicalHistory', 'currentMedicalProblems', 'medications', 'parents_contact',
            'fathersName', 'fatherAlive', 'fatherContactNumber', 'fathersOccupation',
            'fathersNativePlace', 'mothersName', 'motherAlive', 'motherContactNumber',
            'mothersOccupation', 'mothersNativePlace', 'noOfBrothers', 'brothersMarried',
            'noOfSisters', 'sistersMarried', 'parentsIntercasteMarriage', 'parentsDifferentCaste',
            'familyValues', 'familyFinancialStatus', 'familyAnnualIncome', 'profileCreatorName',
            'profileCreatorRelation', 'referenceName', 'referenceRelation', 'referenceAddress',
            'referenceContactNumber', 'referenceEmail',
        ];
        $preferenceFields = [
            'preferred_religion', 'preferred_caste', 'preferred_sub_caste', 'height_from',
            'height_to', 'preferred_education', 'working_partner', 'preferred_working_field',
            'expected_income', 'family_type', 'financial_background', 'family_values',
            'preferred_country', 'preferred_state', 'preferred_city', 'preferred_location',
            'other_expectations',
        ];
        $rules = [
            'username' => ['required', 'digits:10'],
            'fullName' => ['required', 'string', 'max:255'],
            'groomOrBride' => ['required', Rule::in(['groom', 'bride'])],
            'birthDate' => ['required', 'date', 'before:today'],
            'maritalStatus' => ['required', 'string', 'max:50'],
            'religion' => ['required', 'string', 'max:255'],
            'caste' => ['required', 'string', 'max:255'],
            'height' => ['required', 'string', 'max:50'],
            'source_of_information' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:5000'],
            'education' => ['required', 'string', 'max:255'],
            'profession' => ['required', 'string', 'max:5000'],
            'professionalCity' => ['required', 'string', 'max:5000'],
            'timeOfBirth' => ['nullable', 'date_format:H:i'],
            'birthTime_ampm' => ['nullable', Rule::in(['AM', 'PM'])],
            'age_from' => ['nullable', 'integer', 'min:18', 'max:99'],
            'age_to' => ['nullable', 'integer', 'min:18', 'max:99'],
            'booklet_required' => ['nullable', 'boolean'],
            'profilePhoto' => ['nullable', 'image', 'max:5120'],
            'full_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'half_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'identificationProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'birthDateProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'educationProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'biodata' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'additionalPhotos' => ['nullable', 'array', 'max:4'],
            'additionalPhotos.*' => ['image', 'max:5120'],
            'candidate_photos' => ['nullable', 'array', 'max:5'],
            'candidate_photos.*' => ['image', 'max:5120'],
            'existing_candidate_photos' => ['nullable', 'array', 'max:5'],
            'existing_candidate_photos.*' => ['string', 'max:2048'],
            'existing_additional_photos' => ['nullable', 'array', 'max:4'],
            'existing_additional_photos.*' => ['string', 'max:2048'],
            'removed_files' => ['nullable', 'array'],
            'removed_files.*' => [
                Rule::in([
                    'profilePhoto', 'full_body_photo', 'half_body_photo', 'selfie_photo',
                    'identificationProof', 'birthDateProof', 'educationProof',
                    'candidate_photos', 'additional_photos',
                ]),
            ],
        ];
        foreach (array_merge($profileFields, $preferenceFields) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:5000'];
        }
        foreach (['current_address_pincode'] as $field) {
            $rules[$field] = ['nullable', 'digits:6'];
        }
        foreach (['monthlyIncome', 'annualIncome', 'familyAnnualIncome'] as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:0'];
        }
        foreach (['noOfBrothers', 'brothersMarried', 'noOfSisters', 'sistersMarried'] as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:0', 'max:20'];
        }
        $rules['referenceEmail'] = ['nullable', 'email', 'max:255'];
        $data = $request->validate($rules);

        try {
            DB::transaction(function () use ($request, $data, $registration) {
                $profile = $registration->profile;
                $user = User::find($registration->user_id);
                if (! $user) {
                    throw new \RuntimeException('The Melava candidate account no longer exists.');
                }
                $removedFiles = $request->input('removed_files', []);
                $existingCandidatePhotos = array_values(array_intersect(
                    $this->normalizePathList($registration->getRawOriginal('candidate_photos')),
                    $request->input('existing_candidate_photos', [])
                ));
                $existingAdditionalPhotos = array_values(array_intersect(
                    $this->profilePhotoList($profile, 'additional_photos'),
                    $request->input('existing_additional_photos', [])
                ));
                $candidateUploadCount = count((array) $request->file('candidate_photos', []));
                $additionalUploadCount = count((array) $request->file('additionalPhotos', []));

                if (count($existingCandidatePhotos) + $candidateUploadCount > 5) {
                    throw ValidationException::withMessages([
                        'candidate_photos' => ['You can keep or upload up to 5 candidate photos.'],
                    ]);
                }
                if (count($existingAdditionalPhotos) + $additionalUploadCount > 4) {
                    throw ValidationException::withMessages([
                        'additionalPhotos' => ['You can keep or upload up to 4 additional photos.'],
                    ]);
                }

                $uploads = [
                    'profile_photo' => ['profilePhoto', 'profile_photos'],
                    'full_body_photo' => ['full_body_photo', 'full_body_photos'],
                    'half_body_photo' => ['half_body_photo', 'half_body_photos'],
                    'selfie_photo' => ['selfie_photo', 'selfie_photos'],
                ];
                $photoPaths = [];
                foreach ($uploads as $field => [$input, $directory]) {
                    if ($request->hasFile($input)) {
                        $photoPaths[$field] = $this->storeUpload($request->file($input), $directory);
                    } elseif (in_array($input, $removedFiles, true)) {
                        $photoPaths[$field] = null;
                    }
                }

                $additionalPhotos = $this->storeUploads($request->file('additionalPhotos', []), 'melava_additional_photos');
                if ($request->exists('existing_additional_photos') || $additionalPhotos || in_array('additional_photos', $removedFiles, true)) {
                    $photoPaths['additional_photos'] = array_merge($existingAdditionalPhotos, $additionalPhotos);
                }
                $newCandidatePhotos = $this->storeUploads($request->file('candidate_photos', []), 'melava_candidate_photos');
                if ($request->exists('existing_candidate_photos') || $newCandidatePhotos || in_array('candidate_photos', $removedFiles, true)) {
                    $photoPaths['candidate_photos'] = array_merge($existingCandidatePhotos, $newCandidatePhotos);
                }

                $documentPaths = [];
                foreach ([
                    'identificationProof' => 'identificationProof',
                    'birthDateProof' => 'birthDateProof',
                    'educationProof' => 'educationProof',
                ] as $field => $input) {
                    if ($request->hasFile($input)) {
                        $documentPaths[$field] = $this->storeUpload($request->file($input), 'melava_documents');
                    } elseif (in_array($input, $removedFiles, true)) {
                        $documentPaths[$field] = null;
                    }
                }
                if ($request->hasFile('biodata')) {
                    $documentPaths['biodata'] = $this->storeUpload($request->file('biodata'), 'melava_biodata');
                }

                $this->persistNormalizedProfile($profile, $request, $user, $photoPaths, $documentPaths);
                $user->name = $data['fullName'];
                $user->save();

                $preferenceData = $this->preferencePayload($request);
                $preferenceData['preferred_location'] = $data['preferred_location']
                    ?? trim(implode(', ', array_filter([
                        $data['preferred_city'] ?? null,
                        $data['preferred_state'] ?? null,
                        $data['preferred_country'] ?? null,
                    ])));
                Preference::updateOrCreate(['user_id' => $user->id], $preferenceData);

                $registration->fill([
                    'fullName' => $data['fullName'],
                    'groomOrBride' => $data['groomOrBride'],
                    'birthDate' => $data['birthDate'],
                    'maritalStatus' => $data['maritalStatus'],
                    'caste' => $data['caste'],
                    'subCaste' => $data['subCaste'] ?? null,
                    'education' => $data['education'],
                    'profession' => $data['profession'],
                    'city' => $data['professionalCity'],
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
                    'birth_verification_doc' => $request->hasFile('birthDateProof')
                        ? $this->profileDocumentPath($profile, 'birth_certificate')
                        : $registration->birth_verification_doc,
                    'candidate_photos' => $request->exists('existing_candidate_photos')
                        || $request->hasFile('candidate_photos')
                        || in_array('candidate_photos', $removedFiles, true)
                        ? array_merge(
                            $existingCandidatePhotos,
                            $this->storeUploads($request->file('candidate_photos', []), 'melava_candidate_photos')
                        )
                        : $registration->candidate_photos,
                    'biodata' => $request->hasFile('biodata')
                        ? $this->storeUpload($request->file('biodata'), 'melava_biodata')
                        : $registration->biodata,
                    'is_booklet_req' => $request->boolean('booklet_required'),
                    'total_amount' => (float) $registration->registration_fee
                        + ($request->boolean('booklet_required') ? (float) $registration->booklet_fee : 0),
                    'updated_by' => $request->user()->id,
                ]);
                $registration->save();

                $normalizedRegistration = ProfileMelavaRegistration::firstOrNew([
                    'profile_id' => $profile->id,
                    'melava_campaign_id' => $registration->melava_campaign_id,
                ]);
                $normalizedRegistration->fill([
                    'is_booklet_req' => $request->boolean('booklet_required'),
                    'payment_screenshot' => $registration->payment_screenshot
                        ?: $normalizedRegistration->payment_screenshot,
                    'payment_verified' => (bool) $registration->payment_verified,
                ]);
                $normalizedRegistration->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Melava registration updated successfully.',
                'data' => ['registration_id' => $registration->id],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Melava registration admin update failed', [
                'registration_id' => $id,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the Melava registration right now.',
            ], 500);
        }
    }

    private function presentRegistration(MelavaRegistration $registration, bool $includeFormData = false): array
    {
        $data = array_merge($registration->toArray(), [
            'payment_screenshot_url' => $this->fileUrl($registration->payment_screenshot),
            'booklet_payment_screenshot_url' => $this->fileUrl($registration->booklet_payment_screenshot),
            'profile_photo_url' => $this->fileUrl($this->profilePhotoPath($registration->profile, 'profile_photo')),
        ]);

        if ($includeFormData) {
            $profile = $registration->profile;
            $user = User::with('preference')->find($registration->user_id);
            $campaign = DB::table('melava_campaigns')->where('id', $registration->melava_campaign_id)->first();
            $profilePhoto = $this->profilePhotoPath($profile, 'profile_photo');
            $candidatePhotos = $this->normalizePathList($registration->getRawOriginal('candidate_photos'));
            $candidatePhotos = $this->profilePhotoList($profile, 'candidate_photos') ?: $candidatePhotos;
            $additionalPhotos = $this->profilePhotoList($profile, 'additional_photos');
            $documents = $profile?->profileDocuments;
            $identificationProof = $documents?->aadhaar_card;
            $birthDateProof = $documents?->birth_certificate ?: $registration->birth_verification_doc;
            $educationProof = $documents?->education_certificate;
            $photos = $profile?->photos ?? [];

            $data['edit_form'] = [
                'profile' => $profile ? $this->profileForRestore($profile) : [],
                'preference' => $user?->preference?->toArray() ?? [],
                'profile_photo_url' => $this->fileUrl($profilePhoto),
                'profile_photo_path' => $profilePhoto,
                'full_body_photo_url' => $this->fileUrl($photos['full_body_photo'] ?? null),
                'full_body_photo_path' => $photos['full_body_photo'] ?? null,
                'half_body_photo_url' => $this->fileUrl($photos['half_body_photo'] ?? null),
                'half_body_photo_path' => $photos['half_body_photo'] ?? null,
                'selfie_photo_url' => $this->fileUrl($photos['selfie_photo'] ?? null),
                'selfie_photo_path' => $photos['selfie_photo'] ?? null,
                'identification_proof_url' => $this->fileUrl($identificationProof),
                'identification_proof_path' => $identificationProof,
                'birth_date_proof_url' => $this->fileUrl($birthDateProof),
                'birth_date_proof_path' => $birthDateProof,
                'education_proof_url' => $this->fileUrl($educationProof),
                'education_proof_path' => $educationProof,
                'candidate_photos_urls' => array_map(fn ($path) => $this->fileUrl($path), $candidatePhotos),
                'candidate_photos_paths' => $candidatePhotos,
                'additional_photos_urls' => array_map(fn ($path) => $this->fileUrl($path), $additionalPhotos),
                'additional_photos_paths' => $additionalPhotos,
                'campaign' => $campaign ? [
                    'id' => (int) $campaign->id,
                    'title' => $campaign->title,
                    'melava_code' => $campaign->melava_code,
                    'currency' => $campaign->currency,
                    'fee' => (float) $campaign->registration_fee,
                    'booklet_available' => (bool) $campaign->booklet_available,
                    'booklet_fee' => (float) $campaign->booklet_fee,
                    'qr_image_url' => $this->fileUrl($campaign->qr_image_path),
                ] : null,
            ];
        }

        return $data;
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

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        if (str_starts_with($path, 'uploads/') || str_starts_with($path, 'public/')) {
            return url(ltrim($path, '/'));
        }

        return url('storage/'.$path);
    }

    private function normalizePathList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : null, $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : null, $decoded)));
        }

        return [trim(str_replace(['\\"', '\\\\'], ['"', '\\'], $value))];
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

    private function persistNormalizedProfile(
        ?Profile $profile,
        Request $request,
        User $user,
        array $photoUploads = [],
        array $documentUploads = [],
    ): Profile {
        $profile ??= new Profile();
        $addresses = $profile->addresses ?? [];
        $addresses = is_array($addresses) ? $addresses : [];
        $addresses['current'] = [
            'address' => $request->input('address'),
            'pincode' => $request->input('current_address_pincode'),
            'city' => $request->input('current_address_city'),
            'district' => $request->input('current_address_district'),
            'state' => $request->input('current_address_state'),
            'country' => $request->input('current_address_country'),
        ];
        $addresses['permanent'] = [
            'address' => $request->input('permanent_address'),
        ];
        $addresses['reference'] = [
            'name' => $request->input('referenceName'),
            'relation' => $request->input('referenceRelation'),
            'address' => $request->input('referenceAddress'),
            'phone' => $request->input('referenceContactNumber'),
            'email' => $request->input('referenceEmail'),
        ];

        $photos = $profile->photos ?? [];
        $photos = is_array($photos) ? $photos : [];
        foreach (['profile_photo', 'full_body_photo', 'half_body_photo', 'selfie_photo'] as $key) {
            if (array_key_exists($key, $photoUploads)) {
                if ($photoUploads[$key]) {
                    $photos[$key] = $photoUploads[$key];
                } else {
                    unset($photos[$key]);
                }
            }
        }
        foreach (['additional_photos', 'candidate_photos'] as $key) {
            if (array_key_exists($key, $photoUploads)) {
                $photos[$key] = array_values(array_filter((array) $photoUploads[$key]));
            } elseif (! isset($photos[$key])) {
                $photos[$key] = [];
            }
        }

        $profile->fill([
            'full_name' => $request->input('fullName'),
            'groomorbride' => ucfirst((string) $request->input('groomOrBride')),
            'email' => $request->input('email') ?: $user->email,
            'phone' => $request->input('username'),
            'birth_date' => $request->input('birthDate'),
            'height_cm' => $this->heightInCentimeters($request->input('height')),
            'weight_kg' => $this->numericInput($request->input('weight')),
            'has_spectacles' => $request->input('havingSpecsLenses') === 'Yes'
                ? true
                : ($request->input('havingSpecsLenses') === 'No' ? false : null),
            'blood_group' => $request->input('bloodGroup'),
            'mother_tongue' => $request->input('motherTongue'),
            'languages' => collect(explode(',', (string) $request->input('language', '')))
                ->map(fn ($language) => trim($language))
                ->filter()
                ->values()
                ->all(),
            'special_cases' => $request->input('specialCases'),
            'about_me' => $request->input('knowAboutMyself'),
            'photos' => $photos,
            'monthly_income' => $this->numericInput($request->input('monthlyIncome')),
            'annual_income' => $this->numericInput($request->input('annualIncome')),
            'personal_medical_history' => $request->input('personalMedicalHistory'),
            'current_medical_problems' => $request->input('currentMedicalProblems'),
            'medications' => $request->input('medications'),
            'birth_place' => $request->input('placeOfBirth'),
            'birth_time' => $request->input('timeOfBirth'),
            'created_by' => $profile->created_by ?: $user->id,
            'creator_relation' => $request->input('profileCreatorRelation'),
            'addresses' => $addresses,
            'verification_status' => $profile->verification_status ?: 'Pending',
            'status' => $profile->status ?: 'active',
        ]);
        $profile->save();

        ProfileFamilyDetail::updateOrCreate(
            ['profile_id' => $profile->id],
            [
                'father_name' => $request->input('fathersName'),
                'father_status' => $this->aliveStatus($request->input('fatherAlive')),
                'father_native_place' => $request->input('fathersNativePlace'),
                'father_occupation' => $request->input('fathersOccupation'),
                'father_contact' => $request->input('fatherContactNumber'),
                'mother_name' => $request->input('mothersName'),
                'mother_status' => $this->aliveStatus($request->input('motherAlive')),
                'mother_native_place' => $request->input('mothersNativePlace'),
                'mother_occupation' => $request->input('mothersOccupation'),
                'mother_contact' => $request->input('motherContactNumber'),
                'brothers_count' => $request->input('noOfBrothers'),
                'married_brothers_count' => $request->input('brothersMarried'),
                'sisters_count' => $request->input('noOfSisters'),
                'married_sisters_count' => $request->input('sistersMarried'),
                'parents_intercaste_marriage' => $this->yesNoBoolean($request->input('parentsIntercasteMarriage')),
                'parents_different_caste_details' => $request->input('parentsDifferentCaste'),
                'family_values' => $request->input('familyValues'),
                'family_financial_status' => $request->input('familyFinancialStatus'),
                'family_annual_income' => $this->numericInput($request->input('familyAnnualIncome')),
            ]
        );

        $educationRows = $profile->educations()->orderBy('id')->get();
        foreach ([1, 2] as $index => $slot) {
            $degreeName = $request->input('degreeDiplomaName'.$slot);
            if (! filled($degreeName)) {
                continue;
            }

            $education = $educationRows->get($index) ?? new ProfileEducation();
            $education->fill([
                'profile_id' => $profile->id,
                'education_level' => $request->input('education'),
                'degree_name' => $degreeName,
                'specialization' => $request->input('specialisation'.$slot),
                'university_name' => $request->input('universityBoardCollege'),
                'education_city' => $request->input('cityOfEducation'),
                'additional_qualification' => $request->input('additionalEducation'.$slot),
            ]);
            $education->save();
        }

        $employment = $profile->employments()->orderBy('id')->first() ?? new ProfileEmployment();
        $employment->fill([
            'profile_id' => $profile->id,
            'employment_type' => $request->input('profession'),
            'profession' => $request->input('profession'),
            'working_field' => $request->input('fieldOfWorking'),
            'organisation_name' => $request->input('organisationWorkingIn'),
            'designation' => $request->input('designation'),
            'employment_city' => $request->input('professionalCity') ?: $request->input('city'),
            'employment_duration' => $request->input('lengthOfEmployment'),
            'monthly_income' => $this->numericInput($request->input('monthlyIncome')),
            'annual_income' => $this->numericInput($request->input('annualIncome')),
            'income_currency' => $request->input('income_currency') ?: 'INR',
        ]);
        $employment->save();

        ProfileReligionDetail::updateOrCreate(
            ['profile_id' => $profile->id],
            [
                'religion' => $request->input('religion'),
                'caste' => $request->input('caste'),
                'sub_caste' => $request->input('subCaste'),
            ]
        );

        $documentColumns = [
            'identificationProof' => 'aadhaar_card',
            'birthDateProof' => 'birth_certificate',
            'educationProof' => 'education_certificate',
            'biodata' => 'profession_proof',
        ];
        $documentData = [];
        foreach ($documentColumns as $input => $column) {
            if (array_key_exists($input, $documentUploads)) {
                $documentData[$column] = $documentUploads[$input];
            }
        }
        if ($documentData) {
            $documents = $profile->profileDocuments()->firstOrNew([]);
            $documents->fill($documentData);
            $documents->profile_id = $profile->id;
            $documents->save();
        }

        $preferredCaste = trim(implode(' / ', array_filter([
            $request->input('preferred_caste'),
            $request->input('preferred_sub_caste'),
        ])));
        $preferredLocation = trim(implode(', ', array_filter([
            $request->input('preferred_city'),
            $request->input('preferred_state'),
            $request->input('preferred_country'),
        ])));
        ProfilePartnerPreference::updateOrCreate(
            ['profile_id' => $profile->id],
            [
                'age_from' => $request->input('age_from'),
                'age_to' => $request->input('age_to'),
                'height_from' => $request->input('height_from'),
                'height_to' => $request->input('height_to'),
                'preferred_education' => $request->input('preferred_education'),
                'preferred_working_field' => $request->input('preferred_working_field'),
                'preferred_caste' => $preferredCaste ?: null,
                'preferred_location' => $preferredLocation ?: $request->input('preferred_location'),
                'other_expectations' => $request->input('other_expectations'),
            ]
        );

        return $profile;
    }

    private function heightInCentimeters($height): ?float
    {
        if (! preg_match('/^\s*(\d{1,2})\s*ft\s*(\d{1,2})?\s*in\s*$/i', (string) $height, $matches)) {
            return null;
        }

        return round((((int) $matches[1] * 12) + (int) ($matches[2] ?? 0)) * 2.54, 2);
    }

    private function numericInput($value): ?float
    {
        return is_numeric($value) && $value !== '' ? (float) $value : null;
    }

    private function aliveStatus($value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'yes', 'alive' => 'Alive',
            'no', 'deceased' => 'Deceased',
            default => null,
        };
    }

    private function yesNoBoolean($value): ?bool
    {
        return match (strtolower(trim((string) $value))) {
            'yes', '1', 'true' => true,
            'no', '0', 'false' => false,
            default => null,
        };
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

    private function resolveCampaign(string|int $campaign): ?object
    {
        $campaign = trim((string) $campaign);
        if ($campaign === '') {
            return null;
        }

        $query = DB::table('melava_campaigns');
        if (ctype_digit($campaign)) {
            return $query->where('id', (int) $campaign)->first();
        }

        return $query->whereRaw('UPPER(melava_code) = ?', [strtoupper($campaign)])->first();
    }

    private function profileForRestore(Profile $profile): array
    {
        $family = $profile->familyDetails;
        $education = $profile->educations()->orderBy('id')->get();
        $employment = $profile->employments()->orderBy('id')->first();
        $religion = $profile->religionDetails;
        $preference = $profile->partnerPreferences;
        $addresses = is_array($profile->addresses) ? $profile->addresses : [];
        $currentAddress = $addresses['current'] ?? [];
        $permanentAddress = $addresses['permanent'] ?? [];
        $reference = $addresses['reference'] ?? [];
        $employmentIncome = $employment?->monthly_income;
        $heightInches = $profile->height_cm
            ? (int) round((float) $profile->height_cm / 2.54)
            : null;

        $data = [
            'fullName' => $profile->full_name,
            'groomOrBride' => strtolower((string) $profile->groomorbride),
            'birthDate' => $profile->birth_date,
            'religion' => $religion?->religion,
            'caste' => $religion?->caste,
            'subCaste' => $religion?->sub_caste,
            'height' => $heightInches
                ? intdiv($heightInches, 12).'ft '.($heightInches % 12).'in'
                : null,
            'weight' => $profile->weight_kg,
            'havingSpecsLenses' => $profile->has_spectacles ? 'Yes' : 'None',
            'bloodGroup' => $profile->blood_group,
            'motherTongue' => $profile->mother_tongue,
            'language' => implode(', ', $profile->languages ?? []),
            'specialCases' => $profile->special_cases,
            'knowAboutMyself' => $profile->about_me,
            'address' => $currentAddress['address'] ?? null,
            'current_address_pincode' => $currentAddress['pincode'] ?? null,
            'current_address_city' => $currentAddress['city'] ?? null,
            'current_address_district' => $currentAddress['district'] ?? null,
            'current_address_state' => $currentAddress['state'] ?? null,
            'current_address_country' => $currentAddress['country'] ?? null,
            'permanent_address' => $permanentAddress['address'] ?? null,
            'timeOfBirth' => $profile->birth_time,
            'placeOfBirth' => $profile->birth_place,
            'education' => $education->first()?->education_level,
            'degreeDiplomaName1' => $education->get(0)?->degree_name,
            'degreeDiplomaName2' => $education->get(1)?->degree_name,
            'specialisation1' => $education->get(0)?->specialization,
            'specialisation2' => $education->get(1)?->specialization,
            'universityBoardCollege' => $education->first()?->university_name,
            'additionalEducation1' => $education->get(0)?->additional_qualification,
            'additionalEducation2' => $education->get(1)?->additional_qualification,
            'cityOfEducation' => $education->first()?->education_city,
            'profession' => $employment?->profession,
            'fieldOfWorking' => $employment?->working_field,
            'organisationWorkingIn' => $employment?->organisation_name,
            'professionalCity' => $employment?->employment_city,
            'designation' => $employment?->designation,
            'lengthOfEmployment' => $employment?->employment_duration,
            'monthlyIncome' => $employmentIncome,
            'annualIncome' => $employment?->annual_income,
            'income_currency' => $employment?->income_currency,
            'personalMedicalHistory' => $profile->personal_medical_history,
            'currentMedicalProblems' => $profile->current_medical_problems,
            'medications' => $profile->medications,
            'fathersName' => $family?->father_name,
            'fatherAlive' => $family?->father_status === null
                ? null
                : ($family->father_status === 'Alive' ? 'Yes' : 'No'),
            'fatherContactNumber' => $family?->father_contact,
            'fathersOccupation' => $family?->father_occupation,
            'fathersNativePlace' => $family?->father_native_place,
            'mothersName' => $family?->mother_name,
            'motherAlive' => $family?->mother_status === null
                ? null
                : ($family->mother_status === 'Alive' ? 'Yes' : 'No'),
            'motherContactNumber' => $family?->mother_contact,
            'mothersOccupation' => $family?->mother_occupation,
            'mothersNativePlace' => $family?->mother_native_place,
            'noOfBrothers' => $family?->brothers_count,
            'brothersMarried' => $family?->married_brothers_count,
            'noOfSisters' => $family?->sisters_count,
            'sistersMarried' => $family?->married_sisters_count,
            'parentsIntercasteMarriage' => $family?->parents_intercaste_marriage === null
                ? null
                : ($family->parents_intercaste_marriage ? 'Yes' : 'No'),
            'parentsDifferentCaste' => $family?->parents_different_caste_details,
            'familyValues' => $family?->family_values,
            'familyFinancialStatus' => $family?->family_financial_status,
            'familyAnnualIncome' => $family?->family_annual_income,
            'profileCreatorRelation' => $profile->creator_relation,
            'referenceName' => $reference['name'] ?? null,
            'referenceRelation' => $reference['relation'] ?? null,
            'referenceAddress' => $reference['address'] ?? null,
            'referenceContactNumber' => $reference['phone'] ?? null,
            'referenceEmail' => $reference['email'] ?? null,
            'phone' => $profile->phone,
            'profile_id' => $profile->id,
            'reg_id' => $profile->reg_id,
            'age_from' => $preference?->age_from,
            'age_to' => $preference?->age_to,
            'height_from' => $preference?->height_from,
            'height_to' => $preference?->height_to,
            'preferred_education' => $preference?->preferred_education,
            'preferred_working_field' => $preference?->preferred_working_field,
            'preferred_caste' => $preference?->preferred_caste,
            'preferred_location' => $preference?->preferred_location,
            'other_expectations' => $preference?->other_expectations,
        ];

        $data['hasMedicalHistory'] = collect([
            $profile->personal_medical_history,
            $profile->current_medical_problems,
            $profile->medications,
        ])->filter(fn ($value) => filled($value))->isNotEmpty() ? 'Yes' : null;

        return $data;
    }

    private function profilePhotos(?Profile $profile): array
    {
        $photos = $profile?->photos;

        return is_array($photos) ? $photos : [];
    }

    private function profilePhotoPath(?Profile $profile, string $key): ?string
    {
        $path = $this->profilePhotos($profile)[$key] ?? null;

        return is_string($path) && trim($path) !== '' ? $path : null;
    }

    private function profilePhotoList(?Profile $profile, string $key): array
    {
        return $this->normalizePathList($this->profilePhotos($profile)[$key] ?? null);
    }

    private function profileDocumentPath(?Profile $profile, string $column): ?string
    {
        $path = $profile?->profileDocuments?->{$column};

        return is_string($path) && trim($path) !== '' ? $path : null;
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
        return $user && $user->isAdmin();
    }

    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden. Administrator access is required.',
        ], 403);
    }
}
