<?php

namespace App\Http\Controllers\Api;

use App\DocumentVerification;
use App\Http\Controllers\Controller;
use App\Profile;
use App\Support\NriProfileScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
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
        ]);

        $q = trim((string) $request->input('q', ''));
        $gender = $request->input('gender');
        $regType = $request->input('reg_type');
        $verificationStatus = $request->input('verification_status');
        $nri = $request->boolean('nri');

        $sortBy = $request->input('sort_by', 'registration_date');
        if (! in_array($sortBy, ['registration_number', 'registration_date', 'serial_no'], true)) {
            $sortBy = 'registration_date';
        }

        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Profile::query()
            ->with(['user', 'paymentScreenshots', 'melavaRegistrations'])
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
            });

        if ($nri) {
            $query = NriProfileScope::apply($query);
        }

        if ($sortBy === 'registration_number') {
            $query->orderByRaw("COALESCE(NULLIF(profiles.registration_number, ''), profiles.profile_id) {$sortOrder}");
        } elseif ($sortBy === 'serial_no') {
            $query->orderBy('profiles.serial_no', $sortOrder);
        } else {
            $query->orderBy('profiles.created_at', $sortOrder);
        }

        $profiles = $query->paginate(10);

        return response()->json([
            'data' => $profiles->getCollection()->map(fn (Profile $profile) => $this->presentProfile($profile))->values(),
            'current_page' => $profiles->currentPage(),
            'last_page' => $profiles->lastPage(),
            'per_page' => $profiles->perPage(),
            'total' => $profiles->total(),
            'from' => $profiles->firstItem(),
            'to' => $profiles->lastItem(),
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
        ]);

        $q = trim((string) $request->input('q', ''));
        $gender = $request->input('gender');
        $regType = $request->input('reg_type');
        $verificationStatus = $request->input('verification_status');
        $nri = $request->boolean('nri');
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
            });

        if ($nri) {
            $query = NriProfileScope::apply($query);
        }

        if ($sortBy === 'registration_number') {
            $query->orderByRaw("COALESCE(NULLIF(profiles.registration_number, ''), profiles.profile_id) {$sortOrder}");
        } elseif ($sortBy === 'serial_no') {
            $query->orderBy('profiles.serial_no', $sortOrder);
        } else {
            $query->orderBy('profiles.created_at', $sortOrder);
        }

        $filename = 'profiles_export-'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, implode(',', [
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
            ])."\r\n");

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
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            $profile = Profile::with(['user.preference'])->find((int) $id);

            if (! $profile) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $preference = $profile->user?->preference;
            $documents = [];

            foreach (self::DOCUMENT_KEYS as $key) {
                $path = $profile->{$key};
                if (! is_string($path) || trim($path) === '') {
                    continue;
                }

                $documents[] = [
                    'id' => $key,
                    'label' => $this->documentLabel($key),
                    'url' => $path,
                ];
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
                'mobile' => $profile->user?->username ?? $profile->phone,
                'email' => $profile->email ?? $profile->user?->email,
                'gender' => $profile->groomOrBride,
                'dob' => $profile->birthDate,
                'birth_date' => $profile->birthDate,
                'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
                'height' => $profile->height,
                'weight' => $profile->weight,
                'city' => $profile->location ?: $profile->professionalCity ?: $profile->cityOfEducation,
                'address' => $profile->address,
                'permanent_address' => $profile->permanent_address,
                'source_of_information' => $profile->source_of_information,
                'registration_type' => $profile->registration_type,
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
                'diet' => $profile->diet,
                'smoking' => $profile->smoking,
                'smoking_frequency' => $profile->smokingFrequency,
                'drinking' => $profile->drinking,
                'drinking_frequency' => $profile->drinkingFrequency,
                'partying' => $profile->partying,
                'active_on_social_sites' => $profile->activeOnSocialSites,
                'instagram' => $profile->instagram,
                'facebook' => $profile->facebook,
                'has_medical_history' => $profile->hasMedicalHistory,
                'personal_medical_history' => $profile->personalMedicalHistory,
                'current_medical_problems' => $profile->currentMedicalProblems,
                'medications' => $profile->medications,
                'hobbies' => $profile->hobbies,
                'extra_curricular_activities' => $profile->extraCurricularActivities,
                'achievements' => $profile->achievements,
                'sports_and_fitness_likings' => $profile->sportsAndFitnessLikings,
                'cooking_skills' => $profile->cookingSkills,

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
                'rashi' => $profile->rashi,
                'nakshatra' => $profile->nakshatra,
                'nadi' => $profile->nadi,
                'gan' => $profile->gan,
                'gotra' => $profile->gotra,
                'kundali' => $profile->kundali,
                'horoscope_caste' => $profile->horoscopeCaste,
                'horoscope_sub_caste' => $profile->horoscopeSubCaste,
                'charan' => $profile->charan,
                'match_patrika' => $profile->matchPatrika,
                'home_status' => $profile->homeStatus,
                'owned_real_estate' => $profile->ownedRealEstate,
                'has_vehicle' => $profile->hasVehicle,
                'vehicle_type' => $profile->vehicleType,
                'other_investments' => $profile->otherInvestments,

                'pref_age_range' => $this->rangeText($preference?->age_from, $preference?->age_to),
                'pref_height_range' => $this->rangeText($preference?->height_from, $preference?->height_to),
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
                    'home_town' => $profile->location,
                    'current_city' => $profile->professionalCity ?: $profile->location ?: $profile->cityOfEducation,
                    'education' => $profile->education,
                    'profession' => $profile->profession,
                    'monthly_income' => $profile->monthlyIncome,
                    'hobbies' => $profile->hobbies,
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
        return $user && ((int) $user->role_id === 1 || (string) $user->user_type === '00');
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
        $paymentVerified = $profile->paymentScreenshots->contains('payment_verified', true)
            || $profile->melavaRegistrations->contains('payment_verified', true);

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
            'status' => $profile->profileVerificationStatus,
            'payment_verified' => $paymentVerified,
            'gender' => $profile->groomOrBride,
            'reg_type' => $profile->registration_type,
            'city' => $profile->location ?: $profile->professionalCity ?: $profile->cityOfEducation,
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
        if (! is_string($path) || ! str_starts_with($path, 'uploads/')) {
            return null;
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return null;
        }

        $root = realpath(public_path());
        $file = realpath(public_path($path));
        if (!$root || !$file || !is_file($file)) {
            return null;
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (strncmp($file, $rootPrefix, strlen($rootPrefix)) !== 0) {
            return null;
        }

        return $file;
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
