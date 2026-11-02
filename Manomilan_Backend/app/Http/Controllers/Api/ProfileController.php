<?php

namespace App\Http\Controllers\Api;

use App\DocumentVerification;
use App\Http\Controllers\Controller;
use App\MelavaRegistration;
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

    private const ADMIN_FIELD_MAP = [
        'full_name' => 'fullName',
        'gender' => 'groomOrBride',
        'birth_date' => 'birthDate',
        'religion' => 'religion',
        'caste' => 'caste',
        'sub_caste' => 'subCaste',
        'marital_status' => 'maritalStatus',
        'height' => 'height',
        'weight' => 'weight',
        'blood_group' => 'bloodGroup',
        'mother_tongue' => 'motherTongue',
        'languages_known' => 'language',
        'special_cases' => 'specialCases',
        'about' => 'knowAboutMyself',
        'address' => 'address',
        'permanent_address' => 'permanent_address',
        'country' => 'country',
        'state' => 'state',
        'district' => 'district',
        'city' => 'professionalCity',
        'taluka' => 'taluka',
        'village' => 'village',
        'pincode' => 'pincode',
        'spectacles' => 'havingSpecsLenses',
        'has_medical_history' => 'hasMedicalHistory',
        'personal_medical_history' => 'personalMedicalHistory',
        'current_medical_problems' => 'currentMedicalProblems',
        'medications' => 'medications',
        'education' => 'education',
        'university' => 'universityBoardCollege',
        'degree_diploma_name_1' => 'degreeDiplomaName1',
        'degree_diploma_name_2' => 'degreeDiplomaName2',
        'specialisation_1' => 'specialisation1',
        'specialisation_2' => 'specialisation2',
        'additional_education_1' => 'additionalEducation1',
        'additional_education_2' => 'additionalEducation2',
        'city_of_education' => 'cityOfEducation',
        'profession' => 'profession',
        'designation' => 'designation',
        'organisation' => 'organisationWorkingIn',
        'field_of_working' => 'fieldOfWorking',
        'length_of_employment' => 'lengthOfEmployment',
        'monthly_income' => 'monthlyIncome',
        'annual_income' => 'annualIncome',
        'income_currency' => 'income_currency',
        'father_name' => 'fathersName',
        'father_living' => 'fatherAlive',
        'father_occupation' => 'fathersOccupation',
        'father_native_place' => 'fathersNativePlace',
        'father_phone' => 'fatherContactNumber',
        'mother_name' => 'mothersName',
        'mother_living' => 'motherAlive',
        'mother_occupation' => 'mothersOccupation',
        'mother_native_place' => 'mothersNativePlace',
        'mother_phone' => 'motherContactNumber',
        'brothers' => 'noOfBrothers',
        'brothers_married' => 'brothersMarried',
        'sisters' => 'noOfSisters',
        'sisters_married' => 'sistersMarried',
        'parents_intercaste' => 'parentsIntercasteMarriage',
        'parents_different_caste' => 'parentsDifferentCaste',
        'family_financial_status' => 'familyFinancialStatus',
        'family_income' => 'familyAnnualIncome',
        'family_values' => 'familyValues',
        'reference_name' => 'referenceName',
        'reference_relation' => 'referenceRelation',
        'reference_address' => 'referenceAddress',
        'reference_phone' => 'referenceContactNumber',
        'reference_email' => 'referenceEmail',
        'profile_creator_name' => 'profileCreatorName',
        'profile_creator_relation' => 'profileCreatorRelation',
        'horoscope_dob' => 'horoscopeDob',
        'birth_time' => 'timeOfBirth',
        'birth_time_ampm' => 'birthTime_ampm',
        'birth_place' => 'placeOfBirth',
        'horoscope_caste' => 'horoscopeCaste',
        'horoscope_sub_caste' => 'horoscopeSubCaste',
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
            ->with(['user', 'paymentScreenshots', 'melavaRegistrations'])
            ->where('active', 'active');

        if ($user && $user->profile_id) {
            $query->where('id', '!=', $user->profile_id);
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('fullName', 'like', "%{$term}%")
                    ->orWhere('profile_id', 'like', "%{$term}%")
                    ->orWhere('registration_number', 'like', "%{$term}%")
                    ->orWhere('caste', 'like', "%{$term}%")
                    ->orWhere('education', 'like', "%{$term}%")
                    ->orWhere('profession', 'like', "%{$term}%");
            });
        }

        $gender = strtolower(trim((string) $request->input('groomOrBride', '')));
        if ($gender !== '' && $gender !== 'all') {
            $query->whereRaw('LOWER(groomOrBride) = ?', [$gender]);
        }

        if ($request->filled('religion')) {
            $query->where('religion', $request->input('religion'));
        }

        if ($request->filled('caste')) {
            $query->where('caste', $request->input('caste'));
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
        if (! in_array($sortBy, ['registration_number', 'registration_date', 'serial_no'], true)) {
            $sortBy = 'registration_date';
        }

        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $today = Carbon::today();

        $applyFilters = function ($query) use ($q, $gender, $regType, $verificationStatus, $nri, $ageFrom, $ageTo, $today) {
            return $query
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($sub) use ($q) {
                        $sub->where('profiles.profile_id', 'like', "%{$q}%")
                            ->orWhere('profiles.registration_number', 'like', "%{$q}%")
                            ->orWhere('profiles.serial_no', 'like', "%{$q}%")
                            ->orWhere('profiles.fullName', 'like', "%{$q}%")
                            ->orWhere('profiles.profileVerificationStatus', 'like', "%{$q}%")
                            ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                            ->orWhereHas('user', fn ($userQuery) => $userQuery->where('username', 'like', "%{$q}%"));
                    });
                })
                ->when($gender && $gender !== 'all', fn ($query) => $query->where('profiles.groomOrBride', $gender))
                ->when($regType && $regType !== 'all', function ($query) use ($regType) {
                    if ($regType === 'melava_existing') {
                        $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                            ->whereIn('profiles.registration_type', ['melava', 'existing'])
                            ->where(function ($sub) {
                                $sub->where('profiles.registration_number', 'like', 'MM3%')
                                    ->orWhere('profiles.profile_id', 'like', 'MM3%');
                            });
                    } elseif ($regType === 'melava') {
                        $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                            ->where('profiles.registration_type', 'melava')
                            ->where(function ($sub) {
                                $sub->where('profiles.registration_number', 'like', 'MM3%')
                                    ->orWhere('profiles.profile_id', 'like', 'MM3%');
                            });
                    } else {
                        $query->where('profiles.registration_type', $regType);
                    }
                })
                ->when($verificationStatus === 'verified', function ($query) {
                    $query->whereRaw("LOWER(TRIM(COALESCE(profiles.profileVerificationStatus, ''))) = 'completed'");
                })
                ->when($verificationStatus === 'unverified', function ($query) {
                    $query->whereRaw("LOWER(TRIM(COALESCE(profiles.profileVerificationStatus, ''))) != 'completed'");
                })
                ->when($ageFrom !== null, function ($query) use ($ageFrom, $today) {
                    $query->whereNotNull('profiles.birthDate')
                        ->whereDate('profiles.birthDate', '<=', $today->copy()->subYears($ageFrom)->toDateString());
                })
                ->when($ageTo !== null, function ($query) use ($ageTo, $today) {
                    $query->whereNotNull('profiles.birthDate')
                        ->whereDate('profiles.birthDate', '>', $today->copy()->subYears($ageTo + 1)->toDateString());
                })
                ->when($nri, fn ($query) => NriProfileScope::apply($query));
        };

        $baseAll = $applyFilters(Profile::query());
        $base = (clone $baseAll)->where('profiles.active', 'active');

        $stats = (clone $base)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(profiles.profileVerificationStatus, ''))) = 'completed' THEN 1 ELSE 0 END) AS verified")
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(COALESCE(profiles.profileVerificationStatus, ''))) != 'completed' THEN 1 ELSE 0 END) AS unverified")
            ->first();

        $inactiveTotal = (clone $baseAll)
            ->where('profiles.active', '!=', 'active')
            ->count();

        $query = (clone $base)
            ->select([
                'profiles.id',
                'profiles.registration_number',
                'profiles.profile_id',
                'profiles.serial_no',
                'profiles.profilePhoto',
                'profiles.fullName',
                'profiles.phone',
                'profiles.email',
                'profiles.created_at',
                'profiles.profileVerificationStatus',
                'profiles.groomOrBride',
                'profiles.registration_type',
                'profiles.professionalCity',
                'profiles.cityOfEducation',
                'profiles.education',
                'profiles.profession',
                'profiles.birthDate',
                'profiles.identificationProof',
                'profiles.birthDateProof',
                'profiles.addressProof',
                'profiles.educationProof',
                'profiles.professionProof',
                'profiles.incomeProof',
                'profiles.casteCertificate',
                'profiles.medicalFitnessCertificate',
            ])
            ->with(['user:profile_id,username,email', 'documentVerification'])
            ->withExists([
                'paymentScreenshots as has_verified_payment_screenshot' => fn ($paymentQuery) => $paymentQuery
                    ->where('payment_verified', true),
                'melavaRegistrations as has_verified_melava_payment' => fn ($registrationQuery) => $registrationQuery
                    ->where('payment_verified', true),
                'melavaRegistrations as melava_registration_exists',
            ]);

        if ($sortBy === 'registration_number') {
            $query->orderByRaw("COALESCE(NULLIF(profiles.registration_number, ''), profiles.profile_id) {$sortOrder}");
        } elseif ($sortBy === 'serial_no') {
            $query->orderBy('profiles.serial_no', $sortOrder);
        } else {
            $query->orderBy('profiles.created_at', $sortOrder);
        }

        $profiles = $query->paginate((int) $request->input('per_page', 10));

        $total = (int) ($stats->total ?? 0);

        return response()->json([
            'data' => $profiles->getCollection()->map(fn (Profile $profile) => $this->presentProfile($profile))->values(),
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
        if (! in_array($sortBy, ['registration_number', 'registration_date', 'serial_no'], true)) {
            $sortBy = 'registration_date';
        }

        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Profile::query()
            ->with([
                'user',
                'melavaRegistrations' => function ($registrationQuery) {
                    $registrationQuery->where('melava_batch', 'october_2026')
                        ->orderBy('melava_registered_at', 'desc');
                },
            ])
            ->where('profiles.active', 'active')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('profiles.profile_id', 'like', "%{$q}%")
                        ->orWhere('profiles.registration_number', 'like', "%{$q}%")
                        ->orWhere('profiles.serial_no', 'like', "%{$q}%")
                        ->orWhere('profiles.fullName', 'like', "%{$q}%")
                        ->orWhere('profiles.profileVerificationStatus', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('username', 'like', "%{$q}%"));
                });
            })
            ->when($gender && $gender !== 'all', fn ($query) => $query->where('profiles.groomOrBride', $gender))
            ->when($regType && $regType !== 'all', function ($query) use ($regType) {
                if ($regType === 'melava_existing') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->whereIn('profiles.registration_type', ['melava', 'existing'])
                        ->where(function ($sub) {
                            $sub->where('profiles.registration_number', 'like', 'MM3%')
                                ->orWhere('profiles.profile_id', 'like', 'MM3%');
                        });
                } elseif ($regType === 'melava') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->where('profiles.registration_type', 'melava')
                        ->where(function ($sub) {
                            $sub->where('profiles.registration_number', 'like', 'MM3%')
                                ->orWhere('profiles.profile_id', 'like', 'MM3%');
                        });
                } else {
                    $query->where('profiles.registration_type', $regType);
                }
            })
            ->when($verificationStatus === 'verified', function ($query) {
                $query->whereRaw("LOWER(TRIM(COALESCE(profiles.profileVerificationStatus, ''))) = 'completed'");
            })
            ->when($verificationStatus === 'unverified', function ($query) {
                $query->whereRaw("LOWER(TRIM(COALESCE(profiles.profileVerificationStatus, ''))) != 'completed'");
            })
            ->when($ageFrom !== null, function ($query) use ($ageFrom, $today) {
                $query->whereNotNull('profiles.birthDate')
                    ->whereDate('profiles.birthDate', '<=', $today->copy()->subYears($ageFrom)->toDateString());
            })
            ->when($ageTo !== null, function ($query) use ($ageTo, $today) {
                $query->whereNotNull('profiles.birthDate')
                    ->whereDate('profiles.birthDate', '>', $today->copy()->subYears($ageTo + 1)->toDateString());
            });

        if ($nri) {
            $query = NriProfileScope::apply($query);
        }

        if ($sortBy === 'registration_number') {
            $query->orderByRaw("COALESCE(NULLIF(profiles.registration_number, ''), profiles.profile_id) {$sortOrder}");
        } elseif ($sortBy === 'serial_no') {
            $query->orderBy('profiles.serial_no', $sortOrder);
        } else {
            $query->orderByRaw("COALESCE((SELECT MAX(mr.melava_registered_at) FROM melava_registrations AS mr WHERE mr.profile_id = profiles.id AND mr.melava_batch = 'october_2026'), profiles.created_at) IS NULL ASC")
                ->orderByRaw("COALESCE((SELECT MAX(mr.melava_registered_at) FROM melava_registrations AS mr WHERE mr.profile_id = profiles.id AND mr.melava_batch = 'october_2026'), profiles.created_at) {$sortOrder}");
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
                    $latestMelavaRegistration = $profile->melavaRegistrations->first();
                    $registeredMelavaRegistration = $profile->melavaRegistrations
                        ->first(fn ($registration) => $registration->melava_registered_at !== null);
                    $registrationDate = $registeredMelavaRegistration?->melava_registered_at
                        ?: $profile->created_at;

                    $registrationType = match ($profile->registration_type) {
                        'melava' => 'Melava Registration',
                        'existing' => 'Existing Registration',
                        'quick' => 'Quick Registration',
                        default => $missing($profile->registration_type),
                    };

                    $bookletRequired = $latestMelavaRegistration
                        ? ((int) $latestMelavaRegistration->is_booklet_req === 1 ? 'Yes' : 'No')
                        : 'N/A';

                    fputcsv($out, [
                        $missing($profile->serial_no),
                        $missing($profile->registration_number ?? $profile->profile_id),
                        $missing($profile->fullName),
                        $missing($profile->phone ?? $profile->user?->username),
                        $missing($profile->email),
                        $formatDate($profile->birthDate),
                        $formatDate($registrationDate),
                        $missing($profile->groomOrBride === null ? null : ucfirst($profile->groomOrBride)),
                        $registrationType,
                        $bookletRequired,
                        $missing($profile->maritalStatus),
                        $missing($profile->caste),
                        $missing($profile->education),
                        $missing($profile->profession),
                        $missing($profile->professionalCity),
                        $profile->mothersName ?? 'N/A',
                        $profile->motherContactNumber ?? 'N/A',
                        $profile->fathersName ?? 'N/A',
                        $profile->fatherContactNumber ?? 'N/A',
                        $registeredMelavaRegistration?->source_of_information ?? 'N/A',
                        $missing($profile->profileVerificationStatus),
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
                'melavaRegistrations',
            ])->find((int) $id);

            if (! $profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $preference = $profile->user?->preference;
            $verification = $profile->documentVerification;
            $verificationSummary = $profile->verificationSummary();
            $registrationSummary = $profile->registrationSummary();
            $documents = [];

            foreach (self::DOCUMENT_KEYS as $key) {
                $path = $profile->{$key};
                if (! is_string($path) || trim($path) === '') {
                    continue;
                }

                $status = strtolower(trim((string) ($verification?->{$key.'_status'} ?: 'pending')));

                $documents[] = [
                    'id' => $key,
                    'label' => $this->documentLabel($key),
                    'url' => $path,
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

            foreach ([
                'Profile Photo' => $profile->profilePhoto,
                'Full Body Photo' => $profile->full_body_photo,
                'Half Body Photo' => $profile->half_body_photo,
                'Selfie Photo' => $profile->selfie_photo,
            ] as $label => $path) {
                $addPhoto($label, $path);
            }

            $additionalPhotos = $profile->additional_photos;
            if (is_string($additionalPhotos)) {
                $additionalPhotos = json_decode($additionalPhotos, true) ?: [];
            }
            foreach (array_values(is_array($additionalPhotos) ? $additionalPhotos : []) as $index => $path) {
                $addPhoto('Additional Photo '.($index + 1), $path);
            }

            if (Schema::hasTable('melava_registrations')) {
                $candidatePhotos = MelavaRegistration::query()
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
                'registration_no' => $profile->registration_number ?: $profile->profile_id,
                'registration_number' => $profile->registration_number ?: $profile->profile_id,
                'profile_id' => $profile->profile_id,
                'serial_no' => $profile->serial_no,
                'full_name' => $profile->fullName,
                'profile_photo' => $profile->profilePhoto,
                'photo' => $profile->profilePhoto,
                'photos' => $photos,
                'mobile' => $profile->user?->username ?? $profile->phone,
                'email' => $profile->email ?? $profile->user?->email,
                'gender' => $profile->groomOrBride,
                'dob' => $profile->birthDate,
                'birth_date' => $profile->birthDate,
                'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
                'height' => $profile->height,
                'weight' => $profile->weight,
                'city' => $profile->professionalCity ?: $profile->cityOfEducation,
                'address' => $profile->address,
                'permanent_address' => $profile->permanent_address,
                'country' => $profile->country,
                'state' => $profile->state,
                'district' => $profile->district,
                'taluka' => $profile->taluka,
                'village' => $profile->village,
                'pincode' => $profile->pincode,
                'source_of_information' => $profile->source_of_information,
                'registration_type' => $profile->registration_type,
                'registration_label' => $registrationSummary['label'],
                'registration_is_profile' => $registrationSummary['is_profile'],
                'registration_is_melava' => $registrationSummary['is_melava'],
                'status' => $verificationSummary['display_status'],
                'verification_status' => $verificationSummary['display_status'],
                'stored_verification_status' => $profile->profileVerificationStatus,
                'is_verified' => $verificationSummary['is_verified'],
                'payment_verified' => $verificationSummary['payment_verified'],
                'documents_verified' => $verificationSummary['documents_verified'],
                'documents_total' => $verificationSummary['documents_total'],
                'documents_pending' => $verificationSummary['documents_pending'],
                'verification' => $verificationSummary,
                'active' => $profile->active,
                'melava_batch' => $profile->melava_batch,
                'created_at' => $profile->created_at,
                'profile_completion' => $profile->profile_completion,

                'marital_status' => $profile->maritalStatus,
                'caste' => $profile->caste,
                'sub_caste' => $profile->subCaste,
                'mother_tongue' => $profile->motherTongue,
                'languages_known' => $profile->language,
                'special_cases' => $profile->specialCases,
                'about' => $profile->knowAboutMyself,
                'religion' => $profile->religion,
                'father_phone' => $profile->fatherContactNumber,
                'mother_phone' => $profile->motherContactNumber,
                'reference_phone' => $profile->referenceContactNumber,
                'blood_group' => $profile->bloodGroup,
                'spectacles' => $profile->havingSpecsLenses,
                'has_medical_history' => $profile->hasMedicalHistory,
                'personal_medical_history' => $profile->personalMedicalHistory,
                'current_medical_problems' => $profile->currentMedicalProblems,
                'medications' => $profile->medications,

                'education' => $profile->education,
                'degree_diploma_name_1' => $profile->degreeDiplomaName1,
                'degree_diploma_name_2' => $profile->degreeDiplomaName2,
                'specialisation_1' => $profile->specialisation1,
                'specialisation_2' => $profile->specialisation2,
                'university' => $profile->universityBoardCollege,
                'additional_education_1' => $profile->additionalEducation1,
                'additional_education_2' => $profile->additionalEducation2,
                'city_of_education' => $profile->cityOfEducation,
                'profession' => $profile->profession,
                'designation' => $profile->designation,
                'organisation' => $profile->organisationWorkingIn,
                'field_of_working' => $profile->fieldOfWorking,
                'length_of_employment' => $profile->lengthOfEmployment,
                'monthly_income' => $profile->monthlyIncome,
                'annual_income' => $profile->annualIncome,
                'income_currency' => $profile->income_currency,
                'original_monthly_income' => $profile->original_monthly_income,
                'original_annual_income' => $profile->original_annual_income,

                'father_name' => $profile->fathersName,
                'father_living' => $profile->fatherAlive,
                'father_occupation' => $profile->fathersOccupation,
                'father_native_place' => $profile->fathersNativePlace,
                'mother_name' => $profile->mothersName,
                'mother_living' => $profile->motherAlive,
                'mother_occupation' => $profile->mothersOccupation,
                'mother_native_place' => $profile->mothersNativePlace,
                'brothers' => $profile->noOfBrothers,
                'brothers_married' => $profile->brothersMarried,
                'sisters' => $profile->noOfSisters,
                'sisters_married' => $profile->sistersMarried,
                'parents_intercaste' => $profile->parentsIntercasteMarriage,
                'parents_different_caste' => $profile->parentsDifferentCaste,
                'family_financial_status' => $profile->familyFinancialStatus,
                'family_income' => $profile->familyAnnualIncome,
                'family_values' => $profile->familyValues,
                'parents_intercaste_marriage' => $profile->parentsIntercasteMarriage,
                'parents_different_caste' => $profile->parentsDifferentCaste,
                'profile_creator_name' => $profile->profileCreatorName,
                'profile_creator_relation' => $profile->profileCreatorRelation,
                'reference_name' => $profile->referenceName,
                'reference_relation' => $profile->referenceRelation,
                'reference_address' => $profile->referenceAddress,
                'reference_email' => $profile->referenceEmail,

                'horoscope_dob' => $profile->horoscopeDob,
                'birth_time' => $profile->timeOfBirth,
                'birth_time_ampm' => $profile->birthTime_ampm,
                'birth_place' => $profile->placeOfBirth,
                'horoscope_caste' => $profile->horoscopeCaste,
                'horoscope_sub_caste' => $profile->horoscopeSubCaste,

                'pref_age_range' => $this->rangeText($preference?->age_from, $preference?->age_to),
                'pref_age_from' => $preference?->age_from,
                'pref_age_to' => $preference?->age_to,
                'pref_height_range' => $this->rangeText($preference?->height_from, $preference?->height_to),
                'pref_height_from' => $preference?->height_from,
                'pref_height_to' => $preference?->height_to,
                'pref_religion' => $preference?->preferred_religion,
                'pref_caste' => $preference?->preferred_caste,
                'pref_sub_caste' => $preference?->preferred_sub_caste,
                'pref_education' => $preference?->preferred_education,
                'pref_working_partner' => $preference?->working_partner,
                'pref_working_field' => $preference?->preferred_working_field,
                'pref_income' => $preference?->expected_income,
                'pref_family_type' => $preference?->family_type,
                'pref_financial_background' => $preference?->financial_background,
                'pref_family_values' => $preference?->family_values,
                'pref_body_type' => $preference?->preferred_body_type,
                'pref_location' => $preference?->preferred_location,
                'pref_country' => $preference?->preferred_country,
                'pref_state' => $preference?->preferred_state,
                'pref_city' => $preference?->preferred_city,
                'other_expectations' => $preference?->other_expectations,
                'documents' => $documents,
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
            'mobile' => ['sometimes', 'nullable', 'string', 'max:30'],
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
            'brothers_married' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'sisters' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'sisters_married' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'parents_intercaste' => ['sometimes', 'nullable', 'string', 'max:10'],
            'parents_different_caste' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'family_financial_status' => ['sometimes', 'nullable', 'string', 'max:255'],
            'family_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'family_values' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_relation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'reference_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
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

        $oldDocuments = [];

        try {
            DB::transaction(function () use ($profile, $data, $storedDocuments, &$oldDocuments) {
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
                    $payload['groomOrBride'] = strtolower((string) $data['gender']);
                }

                foreach ($storedDocuments as $key => $path) {
                    $oldDocuments[$key] = $locked->{$key};
                    $payload[$key] = $path;
                }

                $locked->fill($payload);
                $locked->save();

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
                }

                $locked->calculateCompletionPercentage();
                $locked->syncDocumentVerificationStatus();
            });

            foreach ($oldDocuments as $path) {
                $this->deleteStoredFile($path);
            }

            return $this->show($request, $id);
        } catch (ValidationException $e) {
            foreach ($storedDocuments as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        } catch (Throwable $e) {
            foreach ($storedDocuments as $path) {
                Storage::disk('public')->delete($path);
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

            $filePath = $this->localUploadPath($profile->{$docId});
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

            $path = \App\MelavaRegistration::where('profile_id', $profile->id)
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

        if ($profile->active === 'deactive') {
            return response()->json(['success' => true, 'message' => 'Profile is already inactive.']);
        }

        try {
            DB::transaction(function () use ($profile) {
                $profile->active = 'deactive';
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

    public function verification(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        $verification = DocumentVerification::firstOrCreate(['profile_id' => $profile->id]);

        $documents = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            $documents[$key] = [
                'file_url' => $this->fileUrl($profile->{$key} ?? null),
                'status' => $this->cleanStatus($verification->{$key.'_status'} ?? 'pending'),
                'remarks' => (string) ($verification->{$key.'_remarks'} ?? ''),
            ];
        }

        return response()->json([
            'profile' => [
                'id' => $profile->id,
                'full_name' => $profile->fullName,
                'registration_number' => $profile->registration_number ?: $profile->profile_id,
                'registration_date' => optional($profile->created_at)->format('d M, Y'),
                'photo_url' => $this->fileUrl($profile->profilePhoto),
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

        $data = [
            'id' => $profile->id,
            'registration_number' => $profile->registration_number ?: $profile->profile_id,
            'profile_id' => $profile->profile_id,
            'serial_no' => $profile->serial_no,
            'photo_url' => $this->fileUrl($profile->profilePhoto),
            'full_name' => $profile->fullName,
            'mobile' => $user->username ?? $profile->phone,
            'email' => $profile->email ?? $user->email ?? null,
            'registration_date' => optional($profile->created_at)->format('d-m-Y'),
            'status' => $verificationSummary['display_status'],
            'is_verified' => $verificationSummary['is_verified'],
            'payment_verified' => $verificationSummary['payment_verified'],
            'documents_verified' => $verificationSummary['documents_verified'],
            'documents_total' => $verificationSummary['documents_total'],
            'gender' => $profile->groomOrBride,
            'reg_type' => $profile->registration_type,
            'registration_label' => $registrationSummary['label'],
            'registration_is_profile' => $registrationSummary['is_profile'],
            'registration_is_melava' => $registrationSummary['is_melava'],
            'city' => $profile->professionalCity ?: $profile->cityOfEducation,
            'education' => $profile->education,
            'profession' => $profile->profession,
            'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
        ];

        if ($detailed) {
            $data['profile_completion'] = (int) $profile->profile_completion;
            $data['caste'] = $profile->caste;
            $data['marital_status'] = $profile->maritalStatus;
            $data['religion'] = $profile->religion;
            $data['height'] = $profile->height;
            $data['about'] = $profile->knowAboutMyself;
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

        return url($path);
    }
}
