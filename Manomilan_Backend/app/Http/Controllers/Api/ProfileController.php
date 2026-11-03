<?php

namespace App\Http\Controllers\Api;

use App\DocumentVerification;
use App\Http\Controllers\Controller;
use App\ProfileMelavaRegistration;
use App\ProfileDocument;
use App\ProfileFamilyDetail;
use App\ProfileEducation;
use App\ProfileEmployment;
use App\ProfileReligionDetail;
use App\ProfilePartnerPreference;
use App\Preference;
use App\Profile;
use App\Support\NriProfileScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends Controller
{
    private const DOCUMENT_KEYS = [
        'identificationProof',
        'birthDateProof',
        'addressProof',
        'educationProof',
        'professionProof',
        'incomeProof',
        'casteCertificate',
        'medicalFitnessCertificate',
        'selfieVerification',
    ];

    private const STATUSES = ['pending', 'verified', 'rejected', 'not_eligible'];

    private const DOCUMENT_COLUMN_MAP = [
        'identificationProof' => 'aadhaar_card',
        'birthDateProof' => 'birth_certificate',
        'addressProof' => 'address_proof',
        'educationProof' => 'education_certificate',
        'professionProof' => 'profession_proof',
        'incomeProof' => 'income_proof',
        'casteCertificate' => 'caste_certificate',
        'medicalFitnessCertificate' => 'medical_fitness_certificate',
    ];

    private const ADMIN_FIELD_MAP = [
        'full_name' => 'full_name',
        'birth_date' => 'birth_date',
        'marital_status' => 'marital_status',
        'weight' => 'weight_kg',
        'blood_group' => 'blood_group',
        'mother_tongue' => 'mother_tongue',
        'special_cases' => 'special_cases',
        'about' => 'about_me',
        'has_medical_history' => 'has_medical_history',
        'personal_medical_history' => 'personal_medical_history',
        'current_medical_problems' => 'current_medical_problems',
        'medications' => 'medications',
        'profile_creator_name' => 'profile_creator_name',
        'birth_time' => 'birth_time',
        'birth_place' => 'birth_place',
    ];

    private const ADMIN_PREFERENCE_MAP = [
        'pref_religion' => 'preferred_religion',
        'pref_caste' => 'preferred_caste',
        'pref_sub_caste' => 'preferred_sub_caste',
        'pref_age_from' => 'age_from',
        'pref_age_to' => 'age_to',
        'pref_height_from' => 'height_from',
        'pref_height_to' => 'height_to',
        'pref_education' => 'preferred_education',
        'pref_working_partner' => 'working_partner',
        'pref_working_field' => 'preferred_working_field',
        'pref_income' => 'expected_income',
        'pref_family_type' => 'family_type',
        'pref_financial_background' => 'financial_background',
        'pref_family_values' => 'family_values',
        'pref_body_type' => 'preferred_body_type',
        'pref_location' => 'preferred_location',
        'pref_country' => 'preferred_country',
        'pref_state' => 'preferred_state',
        'pref_city' => 'preferred_city',
        'other_expectations' => 'other_expectations',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Profile::query()
            ->with([
                'user',
                'paymentScreenshots',
                'melavaRegistrations',
                'familyDetails',
                'educations',
                'employments',
                'religionDetails',
                'profileDocuments',
                'partnerPreferences',
                'profileMelavaRegistrations.campaign',
            ])
            ->where('status', 'active');

        if ($user && $user->profile_id) {
            $query->where('id', '!=', $user->profile_id);
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('reg_id', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('religionDetails', fn ($religion) => $religion
                        ->where('religion', 'like', "%{$term}%")
                        ->orWhere('caste', 'like', "%{$term}%"))
                    ->orWhereHas('educations', fn ($education) => $education
                        ->where('degree_name', 'like', "%{$term}%"))
                    ->orWhereHas('employments', fn ($employment) => $employment
                        ->where('profession', 'like', "%{$term}%"));
            });
        }

        $gender = strtolower(trim((string) $request->input('groomOrBride', '')));
        if ($gender !== '' && $gender !== 'all') {
            $query->where('groomorbride', ucfirst($gender));
        }

        if ($request->filled('religion')) {
            $query->whereHas('religionDetails', fn ($religion) => $religion
                ->where('religion', $request->input('religion')));
        }

        if ($request->filled('caste')) {
            $query->whereHas('religionDetails', fn ($religion) => $religion
                ->where('caste', $request->input('caste')));
        }

        $profiles = $query->latest()->paginate((int) $request->input('per_page', 20));

        $profiles->getCollection()->transform(fn (Profile $profile) => $this->presentProfile($profile));

        return response()->json(['success' => true, 'data' => $profiles]);
    }

    public function allProfiles(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $request->validate([
            'verification_status' => ['nullable', Rule::in(['verified', 'unverified'])],
            'nri' => ['nullable', 'boolean'],
            'age_from' => ['nullable', 'integer', 'min:1', 'max:120'],
            'age_to' => ['nullable', 'integer', 'min:1', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = trim((string) $request->input('q', ''));
        $gender = $request->input('gender');
        $regType = $request->input('reg_type');
        $verificationStatus = $request->input('verification_status');
        $nri = $request->boolean('nri');
        $ageFrom = $request->filled('age_from') ? (int) $request->input('age_from') : null;
        $ageTo = $request->filled('age_to') ? (int) $request->input('age_to') : null;

        if ($ageFrom !== null && $ageTo !== null && $ageFrom > $ageTo) {
            return response()->json([
                'message' => 'Minimum age cannot be greater than maximum age.',
                'errors' => ['age_from' => ['Minimum age cannot be greater than maximum age.']],
            ], 422);
        }

        $sortBy = $request->input('sort_by', 'registration_date');
        if (! in_array($sortBy, ['registration_number', 'registration_date'], true)) {
            $sortBy = 'registration_date';
        }

        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $today = Carbon::today();

        $applyFilters = function ($query) use ($q, $gender, $regType, $verificationStatus, $nri, $ageFrom, $ageTo, $today) {
            return $query
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($sub) use ($q) {
                        $sub->where('profiles.reg_id', 'like', "%{$q}%")
                            ->orWhere('profiles.full_name', 'like', "%{$q}%")
                            ->orWhere('profiles.verification_status', 'like', "%{$q}%")
                            ->orWhere('profiles.phone', 'like', "%{$q}%")
                            ->orWhere('profiles.email', 'like', "%{$q}%")
                            ->orWhereHas('religionDetails', fn ($detail) => $detail
                                ->where('religion', 'like', "%{$q}%")
                                ->orWhere('caste', 'like', "%{$q}%")
                                ->orWhere('sub_caste', 'like', "%{$q}%"))
                            ->orWhereHas('educations', fn ($education) => $education
                                ->where('degree_name', 'like', "%{$q}%")
                                ->orWhere('education_level', 'like', "%{$q}%"))
                            ->orWhereHas('employments', fn ($employment) => $employment
                                ->where('profession', 'like', "%{$q}%")
                                ->orWhere('working_field', 'like', "%{$q}%"))
                            ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                            ->orWhereHas('user', fn ($userQuery) => $userQuery->where('username', 'like', "%{$q}%"));
                    });
                })
                ->when($gender && $gender !== 'all', fn ($query) => $query->where('profiles.groomorbride', ucfirst(strtolower($gender))))
                ->when($regType && $regType !== 'all', function ($query) use ($regType) {
                    if ($regType === 'melava_existing') {
                        $query->whereHas('profileMelavaRegistrations')
                            ->where('profiles.reg_id', 'like', 'MM3%');
                    } elseif ($regType === 'melava') {
                        $query->whereHas('profileMelavaRegistrations')
                            ->where('profiles.reg_id', 'like', 'MM3%');
                    } else {
                        $query->whereDoesntHave('profileMelavaRegistrations');
                    }
                })
                ->when($verificationStatus === 'verified', function ($query) {
                    $query->where('profiles.verification_status', 'Completed');
                })
                ->when($verificationStatus === 'unverified', function ($query) {
                    $query->where('profiles.verification_status', 'Pending');
                })
                ->when($ageFrom !== null, function ($query) use ($ageFrom, $today) {
                    $query->whereNotNull('profiles.birth_date')
                        ->whereDate('profiles.birth_date', '<=', $today->copy()->subYears($ageFrom)->toDateString());
                })
                ->when($ageTo !== null, function ($query) use ($ageTo, $today) {
                    $query->whereNotNull('profiles.birth_date')
                        ->whereDate('profiles.birth_date', '>', $today->copy()->subYears($ageTo + 1)->toDateString());
                })
                ->when($nri, fn ($query) => NriProfileScope::apply($query));
        };

        $baseAll = $applyFilters(Profile::query());
        $inactiveView = $request->boolean('inactive');
        $base = (clone $baseAll)->where('profiles.status', $inactiveView ? 'deactive' : 'active');

        $stats = (clone $base)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN profiles.verification_status = 'Completed' THEN 1 ELSE 0 END) AS verified")
            ->selectRaw("SUM(CASE WHEN profiles.verification_status = 'Pending' THEN 1 ELSE 0 END) AS unverified")
            ->first();

        $inactiveTotal = (clone $baseAll)
            ->where('profiles.status', '!=', 'active')
            ->count();

        $query = (clone $base)
            ->select([
                'profiles.id',
                'profiles.reg_id',
                'profiles.groomorbride',
                'profiles.full_name',
                'profiles.birth_date',
                'profiles.height_cm',
                'profiles.weight_kg',
                'profiles.has_spectacles',
                'profiles.blood_group',
                'profiles.mother_tongue',
                'profiles.languages',
                'profiles.special_cases',
                'profiles.about_me',
                'profiles.photos',
                'profiles.monthly_income',
                'profiles.annual_income',
                'profiles.personal_medical_history',
                'profiles.current_medical_problems',
                'profiles.medications',
                'profiles.birth_place',
                'profiles.birth_time',
                'profiles.creator_relation',
                'profiles.addresses',
                'profiles.verification_status',
                'profiles.status',
                'profiles.phone',
                'profiles.email',
                'profiles.created_at',
            ])
            ->with([
                'user:profile_id,username,email',
                'documentVerification',
                'familyDetails',
                'educations',
                'employments',
                'religionDetails',
                'profileDocuments',
                'partnerPreferences',
                'profileMelavaRegistrations.campaign',
            ])
            ->withExists([
                'paymentScreenshots as has_verified_payment_screenshot' => fn ($paymentQuery) => $paymentQuery
                    ->where('payment_verified', true),
                'profileMelavaRegistrations as has_verified_melava_payment' => fn ($registrationQuery) => $registrationQuery
                    ->where('payment_verified', true),
                'melavaRegistrations as melava_registration_exists',
            ]);

        if ($sortBy === 'registration_number') {
            $query->orderBy('profiles.reg_id', $sortOrder);
        } else {
            $query->orderBy('profiles.created_at', $sortOrder);
        }

        $profiles = $query->paginate((int) $request->input('per_page', 10));

        $total = (int) ($stats->total ?? 0);

        return response()->json([
            'data' => $profiles->getCollection()->map(fn (Profile $profile) => $this->presentProfile($profile, true))->values(),
            'current_page' => $profiles->currentPage(),
            'last_page' => $profiles->lastPage(),
            'per_page' => $profiles->perPage(),
            'from' => $profiles->firstItem(),
            'to' => $profiles->lastItem(),
            'stats' => [
                'total' => $total,
                'active' => $total,
                'verified' => (int) ($stats->verified ?? 0),
                'unverified' => (int) ($stats->unverified ?? 0),
                'inactive' => $inactiveTotal,
            ],
        ]);
    }

    public function export(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $request->validate([
            'verification_status' => ['nullable', Rule::in(['verified', 'unverified'])],
            'nri' => ['nullable', 'boolean'],
            'age_from' => ['nullable', 'integer', 'min:1', 'max:120'],
            'age_to' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $q = trim((string) $request->input('q', ''));
        $gender = $request->input('gender');
        $regType = $request->input('reg_type');
        $verificationStatus = $request->input('verification_status');
        $nri = $request->boolean('nri');
        $ageFrom = $request->filled('age_from') ? (int) $request->input('age_from') : null;
        $ageTo = $request->filled('age_to') ? (int) $request->input('age_to') : null;
        $today = Carbon::today();
        $sortBy = $request->input('sort_by', 'registration_date');
        if (! in_array($sortBy, ['registration_number', 'registration_date'], true)) {
            $sortBy = 'registration_date';
        }

        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Profile::query()
            ->with([
                'user',
                'familyDetails',
                'educations',
                'employments',
                'religionDetails',
                'profileDocuments',
                'partnerPreferences',
                'profileMelavaRegistrations.campaign',
            ])
            ->where('profiles.status', 'active')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('profiles.reg_id', 'like', "%{$q}%")
                        ->orWhere('profiles.full_name', 'like', "%{$q}%")
                        ->orWhere('profiles.verification_status', 'like', "%{$q}%")
                        ->orWhere('profiles.phone', 'like', "%{$q}%")
                        ->orWhere('profiles.email', 'like', "%{$q}%")
                        ->orWhereHas('religionDetails', fn ($religion) => $religion
                            ->where('religion', 'like', "%{$q}%")
                            ->orWhere('caste', 'like', "%{$q}%"))
                        ->orWhereHas('educations', fn ($education) => $education
                            ->where('degree_name', 'like', "%{$q}%"))
                        ->orWhereHas('employments', fn ($employment) => $employment
                            ->where('profession', 'like', "%{$q}%"))
                        ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('username', 'like', "%{$q}%"));
                });
            })
            ->when($gender && $gender !== 'all', fn ($query) => $query->where('profiles.groomorbride', ucfirst(strtolower($gender))))
            ->when($regType && $regType !== 'all', function ($query) use ($regType) {
                if (in_array($regType, ['melava_existing', 'melava'], true)) {
                    $query->whereHas('profileMelavaRegistrations')
                        ->where('profiles.reg_id', 'like', 'MM3%');
                } else {
                    $query->whereDoesntHave('profileMelavaRegistrations');
                }
            })
            ->when($verificationStatus === 'verified', function ($query) {
                $query->where('profiles.verification_status', 'Completed');
            })
            ->when($verificationStatus === 'unverified', function ($query) {
                $query->where('profiles.verification_status', 'Pending');
            })
            ->when($ageFrom !== null, function ($query) use ($ageFrom, $today) {
                $query->whereNotNull('profiles.birth_date')
                    ->whereDate('profiles.birth_date', '<=', $today->copy()->subYears($ageFrom)->toDateString());
            })
            ->when($ageTo !== null, function ($query) use ($ageTo, $today) {
                $query->whereNotNull('profiles.birth_date')
                    ->whereDate('profiles.birth_date', '>', $today->copy()->subYears($ageTo + 1)->toDateString());
            });

        if ($nri) {
            $query = NriProfileScope::apply($query);
        }

        if ($sortBy === 'registration_number') {
            $query->orderBy('profiles.reg_id', $sortOrder);
        } else {
            $query->orderBy('profiles.created_at', $sortOrder);
        }

        $filename = 'profiles_export_'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Serial No',
                'Registration No',
                'Full Name',
                'Mobile Number',
                'Email',
                'DOB',
                'Date of Registration',
                'Gender',
                'Registration Type',
                'Booklet Required',
                'Marital Status',
                'Caste',
                'Education',
                'Profession',
                'City',
                'Mother Name',
                'Mother Phone Number',
                'Father Name',
                'Father Phone Number',
                'Source of Information',
                'Status',
            ]);

            $missing = static fn ($value) => $value === null || (is_string($value) && trim($value) === '')
                ? 'N/A'
                : $value;
            $formatDate = static fn ($value) => $value
                ? Carbon::parse($value)->format('d-m-Y')
                : 'N/A';

            $query->chunk(500, function ($profiles) use ($out, $missing, $formatDate) {
                foreach ($profiles as $profile) {
                    $registration = $profile->profileMelavaRegistrations->sortByDesc('id')->first();
                    $family = $profile->familyDetails;
                    $education = $profile->educations->sortByDesc('id')->first();
                    $employment = $profile->employments->sortByDesc('id')->first();
                    $religion = $profile->religionDetails;
                    $registrationType = $registration ? 'Melava Registration' : 'Profile Registration';
                    $bookletRequired = $registration
                        ? ($registration->is_booklet_req ? 'Yes' : 'No')
                        : 'N/A';

                    fputcsv($out, [
                        'N/A',
                        $missing($profile->reg_id),
                        $missing($profile->full_name),
                        $missing($profile->phone ?? $profile->user?->username),
                        $missing($profile->email),
                        $formatDate($profile->birth_date),
                        $formatDate($profile->created_at),
                        $missing($profile->groomorbride),
                        $registrationType,
                        $bookletRequired,
                        'N/A',
                        $missing($religion?->caste),
                        $missing($education?->degree_name),
                        $missing($employment?->profession),
                        $missing($employment?->employment_city ?? $education?->education_city),
                        $family?->mother_name ?? 'N/A',
                        $family?->mother_contact ?? 'N/A',
                        $family?->father_name ?? 'N/A',
                        $family?->father_contact ?? 'N/A',
                        'N/A',
                        $missing($profile->verification_status),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }

        if (! $this->validPositiveId($id)) {
            return response()->json(['message' => 'Invalid profile id.'], 400);
        }

        try {
            $profile = Profile::with([
                'user.preference',
                'documentVerification',
                'paymentScreenshots',
                'profileMelavaRegistrations.campaign',
                'profileDocuments',
                'familyDetails',
                'educations',
                'employments',
                'religionDetails',
                'partnerPreferences',
            ])->find((int) $id);

            if (! $profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $preference = $profile->user?->preference;
            $verification = $profile->documentVerification;
            $verificationSummary = $profile->verificationSummary();
            $registrationSummary = $profile->registrationSummary();
            $family = $profile->familyDetails;
            $educationRows = $profile->educations->sortBy('id')->values();
            $employment = $profile->employments->sortBy('id')->first();
            $religion = $profile->religionDetails;
            $partnerPreference = $profile->partnerPreferences;
            $addresses = is_array($profile->addresses) ? $profile->addresses : [];
            $currentAddress = $addresses['current'] ?? [];
            $permanentAddress = $addresses['permanent'] ?? [];
            $referenceAddress = $addresses['reference'] ?? [];
            $latestMelava = $profile->profileMelavaRegistrations->sortByDesc('id')->first();
            $birthDate = $profile->birth_date ?: $latestMelava?->birthDate;
            $heightInches = $profile->height_cm
                ? (int) round((float) $profile->height_cm / 2.54)
                : null;
            $documents = [];
            $documentColumns = [
                'identificationProof' => ['aadhaar_card', 'pan_card', 'passport'],
                'birthDateProof' => ['birth_certificate'],
                'addressProof' => ['address_proof'],
                'educationProof' => ['education_certificate'],
                'professionProof' => ['profession_proof'],
                'incomeProof' => ['income_proof'],
                'casteCertificate' => ['caste_certificate'],
                'medicalFitnessCertificate' => ['medical_fitness_certificate'],
                'selfieVerification' => [],
            ];

            foreach (self::DOCUMENT_KEYS as $key) {
                $path = $profile->{$key};
                if (! filled($path)) {
                    $path = collect($documentColumns[$key] ?? [])
                        ->map(fn (string $column) => $profile->profileDocuments?->{$column})
                        ->first(fn ($documentPath) => filled($documentPath));
                }
                if (! is_string($path) || trim($path) === '') {
                    continue;
                }

                $status = strtolower(trim((string) ($verification?->{$key.'_status'} ?: 'pending')));

                $documents[] = [
                    'id' => $key,
                    'label' => $this->documentLabel($key),
                    'url' => $this->fileUrl($path),
                    'status' => $status,
                    'verified' => in_array($status, ['verified', 'approved', 'approve', 'complete', 'completed'], true),
                ];
            }

            $photos = [];
            $addPhoto = function (string $label, $path) use (&$photos): void {
                if (! is_string($path) || trim($path) === '') {
                    return;
                }

                $url = $this->fileUrl($path);
                if ($url === '' || in_array($url, array_column($photos, 'url'), true)) {
                    return;
                }

                $photos[] = [
                    'id' => 'photo-'.count($photos),
                    'label' => $label,
                    'url' => $url,
                ];
            };

            $storedPhotos = is_array($profile->photos) ? $profile->photos : [];
            foreach ([
                'Profile Photo' => $storedPhotos['profile_photo'] ?? $profile->profilePhoto,
                'Full Body Photo' => $storedPhotos['full_body_photo'] ?? $profile->full_body_photo,
                'Half Body Photo' => $storedPhotos['half_body_photo'] ?? $profile->half_body_photo,
                'Selfie Photo' => $storedPhotos['selfie_photo'] ?? $profile->selfie_photo,
            ] as $label => $path) {
                $addPhoto($label, $path);
            }

            $additionalPhotos = $storedPhotos['additional_photos'] ?? $profile->additional_photos;
            if (is_string($additionalPhotos)) {
                $additionalPhotos = json_decode($additionalPhotos, true) ?: [];
            }
            foreach (array_values(is_array($additionalPhotos) ? $additionalPhotos : []) as $index => $path) {
                $addPhoto('Additional Photo '.($index + 1), $path);
            }

            foreach (array_values($storedPhotos['candidate_photos'] ?? []) as $index => $path) {
                $addPhoto('Candidate Photo '.($index + 1), $path);
            }

            if (
                Schema::hasTable('profile_melava_registrations')
                && Schema::hasColumn('profile_melava_registrations', 'candidate_photos')
            ) {
                $candidatePhotos = ProfileMelavaRegistration::query()
                    ->where('profile_id', $profile->id)
                    ->get(['candidate_photos']);

                foreach ($candidatePhotos as $registration) {
                    $paths = $registration->candidate_photos;
                    if (is_string($paths)) {
                        $paths = json_decode($paths, true) ?: [];
                    }

                    foreach (array_values(is_array($paths) ? $paths : []) as $index => $path) {
                        $addPhoto('Candidate Photo '.($index + 1), $path);
                    }
                }
            }

            $data = [
                'id' => $profile->id,
                'registration_no' => $profile->reg_id,
                'registration_number' => $profile->reg_id,
                'profile_id' => $profile->reg_id,
                'serial_no' => null,
                'full_name' => $profile->full_name,
                'profile_photo' => $storedPhotos['profile_photo'] ?? $profile->profilePhoto,
                'photo' => $storedPhotos['profile_photo'] ?? $profile->profilePhoto,
                'photos' => $photos,
                'mobile' => $profile->user?->username ?? $profile->phone,
                'email' => $profile->email ?? $profile->user?->email,
                'gender' => $profile->groomorbride,
                'dob' => $birthDate,
                'birth_date' => $birthDate,
                'birthDate' => $birthDate,
                'age' => $birthDate ? Carbon::parse($birthDate)->age : null,
                'height' => $heightInches
                    ? intdiv($heightInches, 12).'ft '.($heightInches % 12).'in'
                    : null,
                'weight' => $profile->weight_kg,
                'city' => $employment?->employment_city ?: ($educationRows->first()?->education_city),
                'address' => $currentAddress['address'] ?? null,
                'permanent_address' => $permanentAddress['address'] ?? null,
                'country' => $currentAddress['country'] ?? null,
                'state' => $currentAddress['state'] ?? null,
                'district' => $currentAddress['district'] ?? null,
                'taluka' => null,
                'village' => null,
                'pincode' => $currentAddress['pincode'] ?? null,
                'source_of_information' => $profile->source_of_information,
                'registration_type' => $registrationSummary['is_melava'] ? 'melava' : 'profile',
                'registration_label' => $registrationSummary['label'],
                'registration_is_profile' => $registrationSummary['is_profile'],
                'registration_is_melava' => $registrationSummary['is_melava'],
                'status' => $verificationSummary['display_status'],
                'verification_status' => $verificationSummary['display_status'],
                'stored_verification_status' => $profile->verification_status,
                'is_verified' => $verificationSummary['is_verified'],
                'payment_verified' => $verificationSummary['payment_verified'],
                'documents_verified' => $verificationSummary['documents_verified'],
                'documents_total' => $verificationSummary['documents_total'],
                'documents_pending' => $verificationSummary['documents_pending'],
                'verification' => $verificationSummary,
                'active' => $profile->status,
                'melava_batch' => $latestMelava?->melava_batch,
                'created_at' => $profile->created_at,
                'profile_completion' => null,

                'marital_status' => $profile->marital_status,
                'caste' => $religion?->caste,
                'sub_caste' => $religion?->sub_caste,
                'mother_tongue' => $profile->mother_tongue,
                'languages_known' => implode(', ', $profile->languages ?? []),
                'special_cases' => $profile->special_cases,
                'about' => $profile->about_me,
                'religion' => $religion?->religion,
                'father_phone' => $family?->father_contact,
                'mother_phone' => $family?->mother_contact,
                'reference_phone' => $referenceAddress['contact'] ?? $referenceAddress['phone'] ?? null,
                'blood_group' => $profile->blood_group,
                'spectacles' => $profile->has_spectacles ? 'Yes' : 'None',
                'has_medical_history' => collect([
                    $profile->personal_medical_history,
                    $profile->current_medical_problems,
                    $profile->medications,
                ])->filter(fn ($value) => filled($value))->isNotEmpty() ? 'Yes' : 'No',
                'personal_medical_history' => $profile->personal_medical_history,
                'current_medical_problems' => $profile->current_medical_problems,
                'medications' => $profile->medications,

                'education' => $educationRows->first()?->education_level,
                'degree_diploma_name_1' => $educationRows->get(0)?->degree_name,
                'degree_diploma_name_2' => $educationRows->get(1)?->degree_name,
                'specialisation_1' => $educationRows->get(0)?->specialization,
                'specialisation_2' => $educationRows->get(1)?->specialization,
                'university' => $educationRows->first()?->university_name,
                'additional_education_1' => $educationRows->get(0)?->additional_qualification,
                'additional_education_2' => $educationRows->get(1)?->additional_qualification,
                'city_of_education' => $educationRows->first()?->education_city,
                'profession' => $employment?->profession,
                'designation' => $employment?->designation,
                'organisation' => $employment?->organisation_name,
                'field_of_working' => $employment?->working_field,
                'length_of_employment' => $employment?->employment_duration,
                'monthly_income' => $employment?->monthly_income,
                'annual_income' => $employment?->annual_income,
                'income_currency' => $employment?->income_currency,
                'original_monthly_income' => $employment?->original_monthly_income,
                'original_annual_income' => $employment?->original_annual_income,

                'father_name' => $family?->father_name,
                'father_living' => $family?->father_status,
                'father_occupation' => $family?->father_occupation,
                'father_native_place' => $family?->father_native_place,
                'mother_name' => $family?->mother_name,
                'mother_living' => $family?->mother_status,
                'mother_occupation' => $family?->mother_occupation,
                'mother_native_place' => $family?->mother_native_place,
                'brothers' => $family?->brothers_count,
                'brothers_married' => $family?->married_brothers_count,
                'sisters' => $family?->sisters_count,
                'sisters_married' => $family?->married_sisters_count,
                'parents_intercaste' => $family?->parents_intercaste_marriage,
                'parents_different_caste' => $family?->parents_different_caste_details,
                'family_financial_status' => $family?->family_financial_status,
                'family_income' => $family?->family_annual_income,
                'family_values' => $family?->family_values,
                'parents_intercaste_marriage' => $family?->parents_intercaste_marriage,
                'profile_creator_name' => $profile->profile_creator_name,
                'profile_creator_relation' => $profile->creator_relation,
                'reference_name' => $referenceAddress['name'] ?? null,
                'reference_relation' => $referenceAddress['relation'] ?? null,
                'reference_address' => $referenceAddress['address'] ?? null,
                'reference_email' => $referenceAddress['email'] ?? null,

                'horoscope_dob' => $birthDate,
                'birth_time' => $profile->birth_time,
                'birth_time_ampm' => $profile->birth_time
                    ? (Carbon::parse($profile->birth_time)->format('A'))
                    : null,
                'birth_place' => $profile->birth_place,
                'horoscope_caste' => $religion?->horoscope,
                'horoscope_sub_caste' => $religion?->sub_caste,
                'horoscope' => $religion?->horoscope,
                'horoscope_match_required' => $religion?->horoscope_match_required,
                'manglik_status' => $religion?->manglik_status,
                'rashi' => $religion?->rashi,
                'nakshatra' => $religion?->nakshatra,
                'charan' => $religion?->charan,
                'nadi' => $religion?->nadi,
                'gan' => $religion?->gan,
                'gotra' => $religion?->gotra,

                'pref_age_range' => $this->rangeText(
                    $partnerPreference?->age_from ?? $preference?->age_from,
                    $partnerPreference?->age_to ?? $preference?->age_to
                ),
                'pref_age_from' => $partnerPreference?->age_from ?? $preference?->age_from,
                'pref_age_to' => $partnerPreference?->age_to ?? $preference?->age_to,
                'pref_height_range' => $this->rangeText(
                    $partnerPreference?->height_from ?? $preference?->height_from,
                    $partnerPreference?->height_to ?? $preference?->height_to
                ),
                'pref_height_from' => $partnerPreference?->height_from ?? $preference?->height_from,
                'pref_height_to' => $partnerPreference?->height_to ?? $preference?->height_to,
                'pref_religion' => $partnerPreference?->preferred_religion ?? $preference?->preferred_religion,
                'pref_caste' => $partnerPreference?->preferred_caste ?? $preference?->preferred_caste,
                'pref_sub_caste' => $partnerPreference?->preferred_sub_caste ?? $preference?->preferred_sub_caste,
                'pref_education' => $partnerPreference?->preferred_education ?? $preference?->preferred_education,
                'pref_working_partner' => $partnerPreference?->working_partner ?? $preference?->working_partner,
                'pref_working_field' => $partnerPreference?->preferred_working_field ?? $preference?->preferred_working_field,
                'pref_income' => $partnerPreference?->expected_income ?? $preference?->expected_income,
                'pref_family_type' => $partnerPreference?->family_type ?? $preference?->family_type,
                'pref_financial_background' => $partnerPreference?->financial_background ?? $preference?->financial_background,
                'pref_family_values' => $partnerPreference?->family_values ?? $preference?->family_values,
                'pref_body_type' => $partnerPreference?->preferred_body_type ?? $preference?->preferred_body_type,
                'pref_location' => $partnerPreference?->preferred_location ?? $preference?->preferred_location,
                'pref_country' => $partnerPreference?->preferred_country ?? $preference?->preferred_country,
                'pref_state' => $partnerPreference?->preferred_state ?? $preference?->preferred_state,
                'pref_city' => $partnerPreference?->preferred_city ?? $preference?->preferred_city,
                'other_expectations' => $partnerPreference?->other_expectations ?? $preference?->other_expectations,
                'documents' => $documents,
                'education_records' => $educationRows->map(fn ($record) => [
                    'education_level' => $record->education_level,
                    'degree_name' => $record->degree_name,
                    'specialization' => $record->specialization,
                    'university_name' => $record->university_name,
                    'college_name' => $record->college_name,
                    'passing_year' => $record->passing_year,
                    'percentage' => $record->percentage,
                    'grade' => $record->grade,
                    'education_city' => $record->education_city,
                    'additional_qualification' => $record->additional_qualification,
                ])->values(),
                'employment_records' => $profile->employments->map(fn ($record) => [
                    'employment_type' => $record->employment_type,
                    'profession' => $record->profession,
                    'working_field' => $record->working_field,
                    'organisation_name' => $record->organisation_name,
                    'designation' => $record->designation,
                    'employment_city' => $record->employment_city,
                    'employment_duration' => $record->employment_duration,
                    'monthly_income' => $record->monthly_income,
                    'annual_income' => $record->annual_income,
                    'income_currency' => $record->income_currency,
                ])->values(),
                'religion_details' => $religion?->toArray(),
                'family_details' => $family?->toArray(),
                'partner_preferences' => $partnerPreference?->toArray(),
                'payment_screenshots' => $profile->paymentScreenshots
                    ->sortByDesc('created_at')
                    ->map(function ($payment) use ($profile) {
                        $melavaRegistration = $profile->profileMelavaRegistrations->first(
                            fn ($registration) => $registration->payment_screenshot === $payment->screenshot_path
                                || $registration->booklet_payment_screenshot === $payment->screenshot_path
                        );

                        return [
                            'id' => $payment->id,
                            'type' => $melavaRegistration ? 'combined' : 'payment',
                            'melava_id' => $melavaRegistration?->id,
                            'category' => data_get($payment->payment_details, 'category'),
                            'amount' => data_get($payment->payment_details, 'amount'),
                            'payment_date' => data_get($payment->payment_details, 'payment_date'),
                            'payment_method' => data_get($payment->payment_details, 'payment_method'),
                            'reference_number' => data_get($payment->payment_details, 'reference_number'),
                            'remarks' => data_get($payment->payment_details, 'remarks'),
                            'payment_verified' => (bool) $payment->payment_verified,
                            'payment_screenshot' => $payment->screenshot_path,
                            'payment_screenshot_url' => $this->fileUrl($payment->screenshot_path),
                            'created_at' => $payment->created_at?->toIso8601String(),
                        ];
                    })->values(),
                'melava_registrations' => $profile->profileMelavaRegistrations->map(fn ($registration) => [
                    'id' => $registration->id,
                    'campaign_title' => $registration->campaign?->title,
                    'melava_number' => $registration->campaign?->melava_code,
                    'melava_campaign_id' => $registration->melava_campaign_id,
                    'registered_at' => $registration->created_at?->toIso8601String(),
                    'is_booklet_req' => (bool) $registration->is_booklet_req,
                    'payment_screenshot' => $registration->payment_screenshot,
                    'payment_verified' => (bool) $registration->payment_verified,
                    'booklet_payment_screenshot' => $registration->booklet_payment_screenshot,
                    'booklet_payment_verified' => (bool) $registration->booklet_payment_verified,
                ])->values(),
            ];

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to load this profile.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        if (! $this->validPositiveId($id)) {
            return response()->json(['message' => 'Invalid profile id.'], 400);
        }

        $profile = Profile::with('user')->find((int) $id);
        if (! $profile) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        $rules = [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', Rule::in(['groom', 'bride', 'Groom', 'Bride'])],
            'birth_date' => ['sometimes', 'required', 'date', 'before:today'],
            'mobile' => [
                'sometimes', 'nullable', 'string', 'max:30',
                Rule::unique('users', 'username')->ignore($profile->user?->id),
            ],
            'email' => [
                'sometimes', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($profile->user?->id),
            ],
            'religion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caste' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sub_caste' => ['sometimes', 'nullable', 'string', 'max:255'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'height' => ['sometimes', 'nullable', 'string', 'max:50'],
            'weight' => ['sometimes', 'nullable', 'string', 'max:50'],
            'blood_group' => ['sometimes', 'nullable', 'string', 'max:10'],
            'mother_tongue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'languages_known' => ['sometimes', 'nullable', 'string', 'max:255'],
            'special_cases' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'permanent_address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'taluka' => ['sometimes', 'nullable', 'string', 'max:255'],
            'village' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'spectacles' => ['sometimes', 'nullable', 'string', 'max:10'],
            'has_medical_history' => ['sometimes', 'nullable', Rule::in(['Yes', 'No'])],
            'personal_medical_history' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'current_medical_problems' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'medications' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'education' => ['sometimes', 'nullable', 'string', 'max:255'],
            'university' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'degree_diploma_name_1' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'degree_diploma_name_2' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'specialisation_1' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'specialisation_2' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'additional_education_1' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'additional_education_2' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'city_of_education' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'organisation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'field_of_working' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'length_of_employment' => ['sometimes', 'nullable', 'string', 'max:255'],
            'monthly_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'annual_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'income_currency' => ['sometimes', 'nullable', Rule::in(['INR', 'USD', 'EUR', 'GBP'])],
            'father_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_living' => ['sometimes', 'nullable', 'string', 'max:10'],
            'father_occupation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'father_native_place' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'father_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'mother_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mother_living' => ['sometimes', 'nullable', 'string', 'max:10'],
            'mother_occupation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'mother_native_place' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'mother_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'brothers' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'brothers_married' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50', 'lte:brothers'],
            'sisters' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'sisters_married' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50', 'lte:sisters'],
            'parents_intercaste' => ['sometimes', 'nullable', 'string', 'max:10'],
            'parents_different_caste' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'family_financial_status' => ['sometimes', 'nullable', 'string', 'max:255'],
            'family_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'family_values' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_relation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'reference_phone' => ['sometimes', 'nullable', 'regex:/^\d{10}$/'],
            'reference_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'profile_creator_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile_creator_relation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'horoscope_dob' => ['sometimes', 'nullable', 'date'],
            'birth_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'birth_time_ampm' => ['sometimes', 'nullable', Rule::in(['AM', 'PM'])],
            'birth_place' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'horoscope_caste' => ['sometimes', 'nullable', 'string', 'max:191'],
            'horoscope_sub_caste' => ['sometimes', 'nullable', 'string', 'max:191'],
            'pref_religion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_caste' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_sub_caste' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_age_from' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'pref_age_to' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'pref_height_from' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_height_to' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_education' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_working_partner' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_working_field' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_family_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_financial_background' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_family_values' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_body_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pref_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'other_expectations' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'documents' => ['sometimes', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:51200'],
            'profilePhoto' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'full_body_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'half_body_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'candidate_photos' => ['sometimes', 'array', 'max:10'],
            'candidate_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photos' => ['sometimes', 'array'],
            'remove_photos.*' => ['required', 'string', 'max:2000'],
        ];

        $data = $request->validate($rules);

        if (
            array_key_exists('pref_age_from', $data) && array_key_exists('pref_age_to', $data)
            && $data['pref_age_from'] !== null && $data['pref_age_to'] !== null
            && (int) $data['pref_age_from'] > (int) $data['pref_age_to']
        ) {
            throw ValidationException::withMessages([
                'pref_age_to' => ['Preferred maximum age cannot be less than the minimum age.'],
            ]);
        }

        $storedDocuments = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            if ($request->hasFile('documents.'.$key)) {
                $storedDocuments[$key] = $request->file('documents.'.$key)->store('candidate_documents', 'public');
            }
        }

        $storedPhotos = [];
        foreach ([
            'profilePhoto' => 'profile_photo',
            'full_body_photo' => 'full_body_photo',
            'half_body_photo' => 'half_body_photo',
            'selfie_photo' => 'selfie_photo',
        ] as $input => $key) {
            if ($request->hasFile($input)) {
                $storedPhotos[$key] = $request->file($input)->store('profile_photos/candidate', 'public');
            }
        }
        if ($request->hasFile('candidate_photos')) {
            $storedPhotos['candidate_photos'] = collect($request->file('candidate_photos'))
                ->map(fn ($file) => $file->store('profile_photos/candidate', 'public'))
                ->values()
                ->all();
        }

        $oldDocuments = [];
        $oldPhotoPaths = [];

        try {
            DB::transaction(function () use ($profile, $data, $storedDocuments, $storedPhotos, &$oldDocuments, &$oldPhotoPaths) {
                $locked = Profile::lockForUpdate()->findOrFail($profile->id);
                $columns = Schema::getColumnListing((new Profile)->getTable());
                $payload = [];

                foreach (self::ADMIN_FIELD_MAP as $input => $column) {
                    if (array_key_exists($input, $data) && in_array($column, $columns, true)) {
                        $payload[$column] = $data[$input];
                    }
                }

                if (array_key_exists('mobile', $data)) {
                    $payload['phone'] = $data['mobile'];
                }
                if (array_key_exists('email', $data)) {
                    $payload['email'] = $data['email'];
                }

                if (array_key_exists('gender', $data)) {
                    $payload['groomorbride'] = ucfirst(strtolower((string) $data['gender']));
                }
                if (array_key_exists('height', $data) && in_array('height_cm', $columns, true)) {
                    $payload['height_cm'] = $this->heightToCentimeters($data['height']);
                }
                if (array_key_exists('languages_known', $data) && in_array('languages', $columns, true)) {
                    $payload['languages'] = array_values(array_filter(array_map(
                        'trim',
                        explode(',', (string) $data['languages_known'])
                    )));
                }
                if (array_key_exists('spectacles', $data) && in_array('has_spectacles', $columns, true)) {
                    $payload['has_spectacles'] = $data['spectacles'] === '' ? null : $data['spectacles'] === 'Yes';
                }
                if (array_key_exists('has_medical_history', $data)) {
                    $payload['has_medical_history'] = $data['has_medical_history'] === ''
                        ? null
                        : $data['has_medical_history'] === 'Yes';
                }

                foreach ($storedDocuments as $key => $path) {
                    $column = self::DOCUMENT_COLUMN_MAP[$key] ?? null;
                    if ($column) {
                        $oldDocuments[$column] = $locked->profileDocuments?->{$column};
                    }
                }

                $locked->fill($payload);

                $addresses = is_array($locked->addresses) ? $locked->addresses : [];
                $currentAddress = is_array($addresses['current'] ?? null) ? $addresses['current'] : [];
                foreach ([
                    'address' => 'address',
                    'country' => 'country',
                    'state' => 'state',
                    'district' => 'district',
                    'city' => 'city',
                    'taluka' => 'taluka',
                    'village' => 'village',
                    'pincode' => 'pincode',
                ] as $input => $key) {
                    if (array_key_exists($input, $data)) {
                        $currentAddress[$key] = $data[$input];
                    }
                }
                $addresses['current'] = $currentAddress;
                if (array_key_exists('permanent_address', $data)) {
                    $permanent = is_array($addresses['permanent'] ?? null) ? $addresses['permanent'] : [];
                    $permanent['address'] = $data['permanent_address'];
                    $addresses['permanent'] = $permanent;
                }
                $referenceMap = [
                    'reference_name' => 'name',
                    'reference_relation' => 'relation',
                    'reference_address' => 'address',
                    'reference_phone' => 'contact',
                    'reference_email' => 'email',
                ];
                $reference = is_array($addresses['reference'] ?? null) ? $addresses['reference'] : [];
                foreach ($referenceMap as $input => $key) {
                    if (array_key_exists($input, $data)) {
                        $reference[$key] = $data[$input];
                    }
                }
                $addresses['reference'] = $reference;
                $locked->addresses = $addresses;

                $photoPaths = is_array($locked->photos) ? $locked->photos : [];
                $knownPhotoPaths = $this->profilePhotoPaths($locked, $photoPaths);
                $removePhotoPaths = array_values(array_intersect(
                    $knownPhotoPaths,
                    array_map(fn ($path) => $this->normalizePhotoPath($path), $data['remove_photos'] ?? [])
                ));
                if ($removePhotoPaths) {
                    foreach ($photoPaths as $key => $value) {
                        if (is_array($value)) {
                            $photoPaths[$key] = array_values(array_filter(
                                $value,
                                fn ($path) => ! in_array($this->normalizePhotoPath($path), $removePhotoPaths, true)
                            ));
                        } elseif (in_array($this->normalizePhotoPath($value), $removePhotoPaths, true)) {
                            unset($photoPaths[$key]);
                        }
                    }
                    if (in_array('additional_photos', $columns, true)) {
                        $locked->additional_photos = array_values(array_filter(
                            $locked->additional_photos ?? [],
                            fn ($path) => ! in_array($this->normalizePhotoPath($path), $removePhotoPaths, true)
                        ));
                    }
                    foreach (['profilePhoto', 'full_body_photo', 'half_body_photo', 'selfie_photo'] as $column) {
                        if (in_array($column, $columns, true)
                            && in_array($this->normalizePhotoPath($locked->getAttribute($column)), $removePhotoPaths, true)) {
                            $locked->setAttribute($column, null);
                        }
                    }
                    if (Schema::hasTable('profile_melava_registrations')
                        && Schema::hasColumn('profile_melava_registrations', 'candidate_photos')) {
                        $registrations = ProfileMelavaRegistration::where('profile_id', $locked->id)->get();
                        foreach ($registrations as $registration) {
                            $paths = $registration->candidate_photos;
                            if (is_string($paths)) {
                                $paths = json_decode($paths, true) ?: [];
                            }
                            $registration->candidate_photos = array_values(array_filter(
                                is_array($paths) ? $paths : [],
                                fn ($path) => ! in_array($this->normalizePhotoPath($path), $removePhotoPaths, true)
                            ));
                            $registration->save();
                        }
                    }
                    $oldPhotoPaths = array_merge($oldPhotoPaths, $removePhotoPaths);
                }

                foreach ($storedPhotos as $key => $path) {
                    if ($key === 'candidate_photos') {
                        $photoPaths[$key] = array_values(array_merge(
                            is_array($photoPaths[$key] ?? null) ? $photoPaths[$key] : [],
                            $path
                        ));
                    } else {
                        $oldPath = $photoPaths[$key] ?? null;
                        if (is_string($oldPath) && $oldPath !== $path) {
                            $oldPhotoPaths[] = $oldPath;
                        }
                        $photoPaths[$key] = $path;
                    }
                }
                $locked->photos = $photoPaths;
                $locked->save();

                if ($storedDocuments) {
                    $documentPayload = [];
                    foreach ($storedDocuments as $key => $path) {
                        $column = self::DOCUMENT_COLUMN_MAP[$key] ?? null;
                        if ($column) {
                            $documentPayload[$column] = $path;
                        }
                    }
                    if ($documentPayload) {
                        ProfileDocument::updateOrCreate(
                            ['profile_id' => $locked->id],
                            $documentPayload
                        );
                    }
                }

                    $normalized = [
                        ProfileFamilyDetail::class => [
                            'father_name' => 'father_name', 'father_status' => 'father_living',
                            'father_native_place' => 'father_native_place', 'father_occupation' => 'father_occupation',
                            'father_contact' => 'father_phone', 'mother_name' => 'mother_name',
                            'mother_status' => 'mother_living', 'mother_native_place' => 'mother_native_place',
                            'mother_occupation' => 'mother_occupation', 'mother_contact' => 'mother_phone',
                            'brothers_count' => 'brothers', 'married_brothers_count' => 'brothers_married',
                            'sisters_count' => 'sisters', 'married_sisters_count' => 'sisters_married',
                            'parents_intercaste_marriage' => 'parents_intercaste',
                            'parents_different_caste_details' => 'parents_different_caste',
                            'family_values' => 'family_values', 'family_financial_status' => 'family_financial_status',
                            'family_annual_income' => 'family_income',
                        ],
                        ProfileEducation::class => [
                            'education_level' => 'education', 'degree_name' => 'degree_diploma_name_1',
                            'specialization' => 'specialisation_1', 'university_name' => 'university',
                            'education_city' => 'city_of_education', 'additional_qualification' => 'additional_education_1',
                        ],
                        ProfileEmployment::class => [
                            'profession' => 'profession', 'working_field' => 'field_of_working',
                            'organisation_name' => 'organisation', 'designation' => 'designation',
                            'employment_duration' => 'length_of_employment', 'monthly_income' => 'monthly_income',
                            'annual_income' => 'annual_income', 'income_currency' => 'income_currency',
                        ],
                        ProfileReligionDetail::class => [
                            'religion' => 'religion', 'caste' => 'caste', 'sub_caste' => 'sub_caste',
                            'horoscope' => 'horoscope_caste', 'gotra' => 'horoscope_sub_caste',
                        ],
                    ];

                    foreach ($normalized as $model => $mapping) {
                        $attributes = [];
                        foreach ($mapping as $column => $input) {
                            if (array_key_exists($input, $data)) {
                                $value = $data[$input];
                                if ($column === 'parents_intercaste_marriage') {
                                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                                } elseif (in_array($column, [
                                    'brothers_count', 'married_brothers_count', 'sisters_count', 'married_sisters_count',
                                ], true)) {
                                    $value = $value === null || $value === '' ? null : (int) $value;
                                }
                                $attributes[$column] = $value;
                            }
                        }
                        if ($attributes) {
                            $model::updateOrCreate(['profile_id' => $locked->id], $attributes);
                        }
                    }

                if ($storedDocuments) {
                    $verification = DocumentVerification::firstOrCreate(['profile_id' => $locked->id]);
                    foreach (array_keys($storedDocuments) as $key) {
                        $verification->{$key.'_status'} = 'pending';
                        $verification->{$key.'_remarks'} = null;
                    }
                    $verification->save();
                }

                $user = User::where('profile_id', $locked->id)->first();
                if (! $user) {
                    $username = $locked->phone ?: $locked->profile_id ?: uniqid('user_');
                    $user = User::create([
                        'username' => $username,
                        'name' => $locked->fullName ?: 'Unknown',
                        'email' => $locked->email ?: ($username.'@candidate.local'),
                        'password' => bcrypt('manomilana'),
                        'user_type' => '21',
                        'role_id' => 3,
                        'status' => 'active',
                        'profile_id' => $locked->id,
                    ]);
                } else {
                    $user->name = $locked->fullName ?: $user->name;
                    if (array_key_exists('mobile', $data) && $data['mobile']) {
                        $user->username = $data['mobile'];
                    }
                    if (array_key_exists('email', $data) && $data['email']) {
                        $user->email = $data['email'];
                    }
                    $user->save();
                }

                $preferenceData = [];
                foreach (self::ADMIN_PREFERENCE_MAP as $input => $column) {
                    if (array_key_exists($input, $data)) {
                        $preferenceData[$column] = $data[$input];
                    }
                }
                if ($preferenceData) {
                    Preference::updateOrCreate(['user_id' => $user->id], $preferenceData);
                    ProfilePartnerPreference::updateOrCreate(
                        ['profile_id' => $locked->id],
                        $preferenceData
                    );
                }

                if (Schema::hasColumn($locked->getTable(), 'profile_completion')) {
                    $locked->calculateCompletionPercentage();
                }
                $locked->syncDocumentVerificationStatus();
            });

            foreach ($oldDocuments as $path) {
                $this->deleteStoredFile($path);
            }
            foreach (array_unique($oldPhotoPaths) as $path) {
                $this->deleteStoredFile($path);
            }

            return $this->show($request, $id);
        } catch (ValidationException $e) {
            foreach ($storedDocuments as $path) {
                Storage::disk('public')->delete($path);
            }
            foreach ($storedPhotos as $value) {
                foreach (is_array($value) ? $value : [$value] as $path) {
                    Storage::disk('public')->delete($path);
                }
            }
            throw $e;
        } catch (Throwable $e) {
            foreach ($storedDocuments as $path) {
                Storage::disk('public')->delete($path);
            }
            foreach ($storedPhotos as $value) {
                foreach (is_array($value) ? $value : [$value] as $path) {
                    Storage::disk('public')->delete($path);
                }
            }
            report($e);

            return response()->json([
                'message' => 'Unable to update this profile right now.',
            ], 500);
        }
    }

    public function biodataPreview(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }

        if (! $this->validPositiveId($id)) {
            return response()->json(['message' => 'Invalid profile id.'], 400);
        }

        try {
            $profile = Profile::with(['user.preference'])->find((int) $id);

            if (! $profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $preference = $profile->user?->preference;

            return response()->json([
                'data' => [
                    'id' => $profile->id,
                    'full_name' => $profile->fullName,
                    'photo_url' => $this->fileUrl($profile->profilePhoto),
                    'gender' => $profile->groomOrBride,
                    'dob' => $profile->birthDate,
                    'mobile' => $profile->user?->username ?? $profile->phone,
                    'height' => $profile->height,
                    'caste' => $profile->caste,
                    'sub_caste' => $profile->subCaste,
                    'home_town' => $profile->professionalCity ?: $profile->cityOfEducation,
                    'current_city' => $profile->professionalCity ?: $profile->cityOfEducation,
                    'education' => $profile->education,
                    'profession' => $profile->profession,
                    'monthly_income' => $profile->monthlyIncome,
                    'father_name' => $profile->fathersName,
                    'father_occupation' => $profile->fathersOccupation,
                    'mother_name' => $profile->mothersName,
                    'mother_occupation' => $profile->mothersOccupation,
                    'brothers' => $profile->noOfBrothers,
                    'sisters' => $profile->noOfSisters,
                    'pref_caste' => $preference?->preferred_caste,
                    'pref_age_range' => $this->rangeText($preference?->age_from, $preference?->age_to),
                    'other_expectations' => $preference?->other_expectations,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to prepare this BioData.'], 500);
        }
    }

    public function deleteDocument(Request $request, $id, string $docId)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }

        if (! $this->validPositiveId($id)) {
            return response()->json(['message' => 'Invalid profile id.'], 400);
        }

        $documentKeys = array_fill_keys(self::DOCUMENT_KEYS, true);
        if (
            ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $docId)
            || ! isset($documentKeys[$docId])
        ) {
            return response()->json(['message' => 'Invalid document id.'], 400);
        }

        try {
            if (! Schema::hasColumn('profiles', $docId)) {
                return response()->json(['message' => 'Invalid document id.'], 400);
            }

            $profile = Profile::find((int) $id);

            if (! $profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $path = $profile->{$docId};
            if (! is_string($path) || trim($path) === '') {
                return response()->json(['message' => 'Document not found.'], 404);
            }

            $deleted = DB::transaction(function () use ($profile, $docId) {
                $lockedProfile = Profile::whereKey($profile->id)->lockForUpdate()->first();
                if (! $lockedProfile || empty($lockedProfile->{$docId})) {
                    return false;
                }

                $lockedProfile->{$docId} = null;
                $lockedProfile->save();
                return true;
            });

            if (! $deleted) {
                return response()->json(['message' => 'Document not found.'], 404);
            }

            $filePath = $this->localUploadPath($path);
            if ($filePath && is_file($filePath) && ! unlink($filePath)) {
                report(new \RuntimeException('Deleted document reference but failed to remove its uploaded file.'));

                return response()->json(['message' => 'Document reference was removed, but its file could not be deleted.'], 500);
            }

            return response()->json(['message' => 'Document deleted successfully.']);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to delete this document.'], 500);
        }
    }

    public function showDocument(Request $request, $id, string $docId)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }

        if (! $this->validPositiveId($id)) {
            return response()->json(['message' => 'Invalid profile id.'], 400);
        }

        if (! in_array($docId, self::DOCUMENT_KEYS, true)) {
            return response()->json(['message' => 'Invalid document id.'], 400);
        }

        try {
            $profile = Profile::find((int) $id);

            if (! $profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $documentColumns = [
                'identificationProof' => ['aadhaar_card', 'pan_card', 'passport'],
                'birthDateProof' => ['birth_certificate'],
                'addressProof' => ['address_proof'],
                'educationProof' => ['education_certificate'],
                'professionProof' => ['profession_proof'],
                'incomeProof' => ['income_proof'],
                'casteCertificate' => ['caste_certificate'],
                'medicalFitnessCertificate' => ['medical_fitness_certificate'],
                'selfieVerification' => [],
            ];
            $path = $profile->{$docId};
            if (! filled($path)) {
                $profile->loadMissing('profileDocuments');
                $path = collect($documentColumns[$docId] ?? [])
                    ->map(fn (string $column) => $profile->profileDocuments?->{$column})
                    ->first(fn ($documentPath) => filled($documentPath));
            }

            $filePath = $this->localUploadPath($path);
            if (! $filePath) {
                return response()->json(['message' => 'Document not found.'], 404);
            }

            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: 'application/octet-stream';

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="profile-document"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to open this document.'], 500);
        }
    }

    public function downloadBiodata(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Administrator access is required.'], 403);
        }

        if (! $this->validPositiveId($id)) {
            return response()->json(['message' => 'Invalid profile id.'], 400);
        }

        try {
            $profile = Profile::find((int) $id);

            if (! $profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $path = ProfileMelavaRegistration::where('profile_id', $profile->id)
                ->whereNotNull('biodata')
                ->where('biodata', '<>', '')
                ->latest()
                ->value('biodata');

            $filePath = $this->localUploadPath($path);
            $mimeType = $filePath ? (new \finfo(FILEINFO_MIME_TYPE))->file($filePath) : false;
            if (
                ! $filePath
                || ! is_file($filePath)
                || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'pdf'
                || $mimeType !== 'application/pdf'
            ) {
                return response()->json(['message' => 'BioData PDF not found.'], 404);
            }

            return response()->download($filePath, 'BioData_'.$profile->id.'.pdf', [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to download this BioData.'], 500);
        }
    }

    public function myProfile(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->profile_id) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $profile = Profile::with(['user', 'paymentScreenshots', 'melavaRegistrations'])
            ->find($user->profile_id);

        return response()->json([
            'success' => true,
            'data' => $profile ? $this->presentProfile($profile, true) : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        if ($user->profile_id) {
            return response()->json([
                'success' => false,
                'message' => 'A profile is already linked to this account.',
            ], 422);
        }

        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'groomOrBride' => ['required', Rule::in(['bride', 'groom'])],
            'birthDate' => ['required', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'registration_type' => ['nullable', Rule::in(['quick', 'melava', 'existing'])],
        ]);

        try {
            $profile = DB::transaction(function () use ($data, $user) {
                $profile = Profile::create($data);
                $user->profile_id = $profile->id;
                $user->save();

                return $profile;
            });

            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully.',
                'data' => $this->presentProfile($profile->fresh(['user']), true),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create the profile right now.',
            ], 500);
        }
    }

    public function deactivate(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        if ($profile->status === 'deactive') {
            return response()->json(['success' => true, 'message' => 'Profile is already inactive.']);
        }

        try {
            DB::transaction(function () use ($profile) {
                $profile->status = 'deactive';
                $profile->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Profile deactivated successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to deactivate this profile right now.',
            ], 500);
        }
    }

    public function activate(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);
        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        try {
            DB::transaction(function () use ($profile) {
                $profile->status = 'active';
                $profile->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Profile activated successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to activate this profile right now.',
            ], 500);
        }
    }

    public function verification(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        $profile->loadMissing(['profileDocuments', 'user']);
        $storedPhotos = is_array($profile->photos) ? $profile->photos : [];
        $profilePhoto = $storedPhotos['profile_photo'] ?? $storedPhotos[0] ?? $profile->profilePhoto;
        $verification = DocumentVerification::firstOrCreate(['profile_id' => $profile->id]);
        $documentColumns = [
            'identificationProof' => ['aadhaar_card', 'pan_card', 'passport'],
            'birthDateProof' => ['birth_certificate'],
            'addressProof' => ['address_proof'],
            'educationProof' => ['education_certificate'],
            'professionProof' => ['profession_proof'],
            'incomeProof' => ['income_proof'],
            'casteCertificate' => ['caste_certificate'],
            'medicalFitnessCertificate' => ['medical_fitness_certificate'],
            'selfieVerification' => [],
        ];

        $documents = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            $path = array_key_exists($key, $profile->getAttributes()) ? $profile->{$key} : null;
            if (! filled($path)) {
                $path = collect($documentColumns[$key] ?? [])
                    ->map(fn (string $column) => $profile->profileDocuments?->{$column})
                    ->first(fn ($documentPath) => filled($documentPath));
            }

            $documents[$key] = [
                'file_url' => $this->fileUrl($path),
                'status' => $this->cleanStatus($verification->{$key.'_status'} ?? 'pending'),
                'remarks' => (string) ($verification->{$key.'_remarks'} ?? ''),
            ];
        }

        return response()->json([
            'profile' => [
                'id' => $profile->id,
                'full_name' => $profile->full_name ?: $profile->fullName ?: $profile->user?->name ?: 'Unnamed profile',
                'registration_number' => $profile->reg_id ?: $profile->profile_id ?: $profile->registration_number,
                'registration_date' => optional($profile->created_at)->format('d M, Y'),
                'photo_url' => $this->fileUrl(is_string($profilePhoto) ? $profilePhoto : null),
                'profile_photo' => is_string($profilePhoto) ? $profilePhoto : null,
            ],
            'documents' => $documents,
        ]);
    }

    public function verify(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        $rules = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            $rules[$key.'_status'] = ['required', Rule::in(self::STATUSES)];
            $rules[$key.'_remarks'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        try {
            DB::transaction(function () use ($profile, $data) {
                DocumentVerification::updateOrCreate(['profile_id' => $profile->id], $data);
                $profile->syncDocumentVerificationStatus();
            });

            return response()->json([
                'success' => true,
                'message' => 'Verification saved successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save the verification right now.',
            ], 500);
        }
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

    private function presentProfile(Profile $profile, bool $detailed = false): array
    {
        $user = $profile->user;
        $verificationSummary = $profile->verificationSummary();
        $registrationSummary = $profile->registrationSummary();
        $family = $profile->familyDetails;
        $education = $profile->educations->sortByDesc('id')->first();
        $employment = $profile->employments->sortByDesc('id')->first();
        $religion = $profile->religionDetails;
        $preference = $profile->partnerPreferences;
        $photoPaths = is_array($profile->photos) ? $profile->photos : (is_string($profile->photos) && $profile->photos ? json_decode($profile->photos, true) : []);
        if (! is_array($photoPaths)) {
            $photoPaths = [];
        }
        $addresses = is_array($profile->addresses) ? $profile->addresses : (is_string($profile->addresses) && $profile->addresses ? json_decode($profile->addresses, true) : []);
        if (! is_array($addresses)) {
            $addresses = [];
        }
        $profilePhoto = $photoPaths['profile_photo'] ?? $photoPaths['profilePicture'] ?? $photoPaths[0] ?? $profile->profile_photo ?? null;

        $normalizeList = function ($value) {
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
            return [trim($value)];
        };

        $additionalList = $photoPaths['additional_photos'] ?? $photoPaths['additionalPhotos'] ?? $normalizeList($profile->additional_photos ?? null);

        $data = [
            'id' => $profile->id,
            'registration_number' => $profile->reg_id,
            'profile_id' => $profile->reg_id,
            'serial_no' => null,
            'photo_url' => $this->fileUrl(is_string($profilePhoto) ? $profilePhoto : null),
            'full_name' => $profile->full_name,
            'mobile' => $user->username ?? $profile->phone,
            'email' => $profile->email ?? $user->email ?? null,
            'registration_date' => optional($profile->created_at)->format('d-m-Y'),
            'status' => $profile->verification_status === 'Completed' ? 'Verified' : 'Pending',
            'is_verified' => $profile->verification_status === 'Completed',
            'payment_verified' => $verificationSummary['payment_verified'],
            'documents_verified' => $verificationSummary['documents_verified'],
            'documents_total' => $verificationSummary['documents_total'],
            'gender' => $profile->groomorbride,
            'reg_type' => $registrationSummary['is_melava'] ? 'melava' : 'profile',
            'registration_label' => $registrationSummary['label'],
            'registration_is_profile' => $registrationSummary['is_profile'],
            'registration_is_melava' => $registrationSummary['is_melava'],
            'city' => $employment?->employment_city ?: $education?->education_city ?: ($addresses['city'] ?? null),
            'education' => $education?->degree_name ?: $education?->education_level,
            'profession' => $employment?->profession,
            'age' => $profile->birth_date ? Carbon::parse($profile->birth_date)->age : null,
            'full_body_photo_url' => $this->fileUrl($photoPaths['full_body_photo'] ?? $profile->full_body_photo ?? null),
            'half_body_photo_url' => $this->fileUrl($photoPaths['half_body_photo'] ?? $profile->half_body_photo ?? null),
            'selfie_photo_url' => $this->fileUrl($photoPaths['selfie_photo'] ?? $profile->selfie_photo ?? null),
            'profile_photo_url' => $this->fileUrl(is_string($profilePhoto) ? $profilePhoto : null),
            'additional_photo_urls' => collect($additionalList)
                ->map(fn ($path) => $this->fileUrl(is_string($path) ? $path : null))
                ->filter()
                ->values()
                ->all(),
            'photos' => $photoPaths,
        ];

        if ($detailed) {
            $data['profile_completion'] = null;
            $data['caste'] = $religion?->caste;
            $data['marital_status'] = null;
            $data['religion'] = $religion?->religion;
            $data['height'] = $profile->height_cm;
            $data['about'] = $profile->about_me;
            $data['family_details'] = $family?->only([
                'father_name', 'father_status', 'father_native_place', 'father_occupation',
                'father_contact', 'mother_name', 'mother_status', 'mother_native_place',
                'mother_occupation', 'mother_contact', 'brothers_count', 'married_brothers_count',
                'sisters_count', 'married_sisters_count', 'parents_intercaste_marriage',
                'parents_different_caste_details', 'family_values', 'family_financial_status',
                'family_annual_income',
            ]);
            $data['education_records'] = $profile->educations->map(fn ($record) => $record->only([
                'education_level', 'degree_name', 'specialization', 'university_name', 'college_name',
                'passing_year', 'percentage', 'grade', 'education_city', 'additional_qualification',
                'education_certificate',
            ]))->values();
            $data['employment_records'] = $profile->employments->map(fn ($record) => $record->only([
                'employment_type', 'profession', 'working_field', 'organisation_name', 'designation',
                'employment_city', 'employment_duration', 'monthly_income', 'annual_income',
                'income_currency', 'original_monthly_income', 'original_annual_income',
            ]))->values();
            $data['religion_details'] = $religion?->only([
                'religion', 'caste', 'sub_caste', 'community', 'horoscope',
                'horoscope_match_required', 'manglik_status', 'rashi', 'nakshatra',
                'charan', 'nadi', 'gan', 'gotra',
            ]);
            $data['documents'] = $profile->profileDocuments?->only([
                'aadhaar_card', 'pan_card', 'passport', 'birth_certificate', 'address_proof',
                'education_certificate', 'profession_proof', 'income_proof', 'caste_certificate',
                'medical_fitness_certificate',
            ]);
            $data['partner_preferences'] = $preference?->only([
                'age_from', 'age_to', 'height_from', 'height_to', 'preferred_education',
                'preferred_working_field', 'preferred_caste', 'preferred_location',
                'other_expectations',
            ]);
            $data['melava_registrations'] = $profile->profileMelavaRegistrations
                ->map(fn ($registration) => [
                    'melava_campaign_id' => $registration->melava_campaign_id,
                    'campaign_title' => $registration->campaign?->title,
                    'is_booklet_req' => (bool) $registration->is_booklet_req,
                    'payment_verified' => (bool) $registration->payment_verified,
                ])->values();
        }

        return $data;
    }

    private function cleanStatus($status): string
    {
        return in_array($status, self::STATUSES, true) ? $status : 'pending';
    }

    private function validPositiveId($id): bool
    {
        return is_scalar($id)
            && preg_match('/^[1-9]\d{0,11}$/', (string) $id) === 1;
    }

    private function documentLabel(string $key): string
    {
        return [
            'identificationProof' => 'Identification Proof',
            'birthDateProof' => 'Birth Date Proof',
            'addressProof' => 'Address Proof',
            'educationProof' => 'Education Proof',
            'professionProof' => 'Profession Proof',
            'incomeProof' => 'Income Proof',
            'casteCertificate' => 'Caste Certificate',
            'medicalFitnessCertificate' => 'Medical Fitness Certificate',
            'selfieVerification' => 'Selfie Verification',
        ][$key] ?? 'Document';
    }

    private function rangeText($from, $to): ?string
    {
        if (($from === null || $from === '') && ($to === null || $to === '')) {
            return null;
        }

        return trim((string) $from).' - '.trim((string) $to);
    }

    private function localUploadPath($path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return null;
        }

        $candidates = [];

        if (str_starts_with($path, 'uploads/')) {
            $candidates[] = public_path($path);
        }

        $candidates[] = public_path($path);
        $candidates[] = storage_path('app/public/'.$path);

        foreach ($candidates as $candidate) {
            $file = realpath($candidate);
            if (! $file || ! is_file($file)) {
                continue;
            }

            $allowedRoots = array_filter([
                realpath(public_path()),
                realpath(storage_path('app/public')),
            ]);

            foreach ($allowedRoots as $root) {
                $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                if (strncmp($file, $rootPrefix, strlen($rootPrefix)) === 0) {
                    return $file;
                }
            }
        }

        return null;
    }

    private function deleteStoredFile($path): void
    {
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        $file = $this->localUploadPath($path);
        if ($file && is_file($file)) {
            @unlink($file);
            return;
        }

        Storage::disk('public')->delete(ltrim(str_replace('\\', '/', $path), '/'));
    }

    private function profilePhotoPaths(Profile $profile, array $photos): array
    {
        $paths = [];
        foreach ($photos as $value) {
            foreach (is_array($value) ? $value : [$value] as $path) {
                if (is_string($path) && trim($path) !== '') {
                    $paths[] = $this->normalizePhotoPath($path);
                }
            }
        }

        foreach (['profilePhoto', 'full_body_photo', 'half_body_photo', 'selfie_photo'] as $column) {
            $path = $profile->getAttribute($column);
            if (is_string($path) && trim($path) !== '') {
                $paths[] = $this->normalizePhotoPath($path);
            }
        }

        if (Schema::hasColumn($profile->getTable(), 'additional_photos')) {
            $additional = $profile->getAttribute('additional_photos');
            if (is_string($additional)) {
                $additional = json_decode($additional, true) ?: [];
            }
            foreach (is_array($additional) ? $additional : [] as $path) {
                if (is_string($path) && trim($path) !== '') {
                    $paths[] = $this->normalizePhotoPath($path);
                }
            }
        }

        if (Schema::hasTable('profile_melava_registrations')
            && Schema::hasColumn('profile_melava_registrations', 'candidate_photos')) {
            foreach (ProfileMelavaRegistration::where('profile_id', $profile->id)->get(['candidate_photos']) as $registration) {
                $candidatePhotos = $registration->candidate_photos;
                if (is_string($candidatePhotos)) {
                    $candidatePhotos = json_decode($candidatePhotos, true) ?: [];
                }
                foreach (is_array($candidatePhotos) ? $candidatePhotos : [] as $path) {
                    if (is_string($path) && trim($path) !== '') {
                        $paths[] = $this->normalizePhotoPath($path);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function normalizePhotoPath($path): string
    {
        if (! is_string($path) || trim($path) === '') {
            return '';
        }

        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
        return preg_replace('#^storage/#i', '', $path);
    }

    private function heightToCentimeters(?string $height): ?float
    {
        $height = trim((string) $height);
        if ($height === '') {
            return null;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*cm$/i', $height, $matches)) {
            return round((float) $matches[1], 2);
        }
        if (preg_match("/^(\\d+)\\s*(?:ft|')\\s*(\\d+)?\\s*(?:in|\")?$/i", $height, $matches)) {
            return round((((int) $matches[1] * 12) + (int) ($matches[2] ?? 0)) * 2.54, 2);
        }
        if (is_numeric($height)) {
            $value = (float) $height;
            return round($value <= 10 ? $value * 30.48 : $value, 2);
        }

        return null;
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

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        if (str_starts_with($path, 'candidate/')) {
            return url('storage/'.$path);
        }

        return Storage::disk('public')->url($path);
    }
}
