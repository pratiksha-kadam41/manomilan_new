<?php

namespace App\Http\Controllers\Candidate;

use App\DocumentVerification;
use App\Models\CandidateNotification;
use App\Profile;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Requests\Candidate\UploadDocumentsRequest;
use App\Http\Requests\Candidate\UploadPhotoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileController extends CandidateController
{
    private const FIELD_MAP = [
        'full_name' => 'fullName',
        'gender' => 'groomOrBride',
        'birth_date' => 'birthDate',
        'religion' => 'religion',
        'caste' => 'caste',
        'sub_caste' => 'subCaste',
        'marital_status' => 'maritalStatus',
        'height' => 'height',
        'weight' => 'weight',
        'having_specs_lenses' => 'havingSpecsLenses',
        'blood_group' => 'bloodGroup',
        'mother_tongue' => 'motherTongue',
        'language' => 'language',
        'about' => 'knowAboutMyself',
        'address' => 'address',
        'permanent_address' => 'permanent_address',
        'source_of_information' => 'source_of_information',
        'education' => 'education',
        'degree' => 'degreeDiplomaName1',
        'degree_2' => 'degreeDiplomaName2',
        'specialisation' => 'specialisation1',
        'college' => 'universityBoardCollege',
        'additional_education' => 'additionalEducation1',
        'city_of_education' => 'cityOfEducation',
        'profession' => 'profession',
        'field_of_working' => 'fieldOfWorking',
        'organisation' => 'organisationWorkingIn',
        'professional_city' => 'professionalCity',
        'designation' => 'designation',
        'length_of_employment' => 'lengthOfEmployment',
        'monthly_income' => 'monthlyIncome',
        'annual_income' => 'annualIncome',
        'hobbies' => 'hobbies',
        'extra_curricular' => 'extraCurricularActivities',
        'achievements' => 'achievements',
        'sports' => 'sportsAndFitnessLikings',
        'cooking_skills' => 'cookingSkills',
        'diet' => 'diet',
        'smoking' => 'smoking',
        'drinking' => 'drinking',
        'partying' => 'partying',
        'father_name' => 'fathersName',
        'father_alive' => 'fatherAlive',
        'father_occupation' => 'fathersOccupation',
        'father_contact' => 'fatherContactNumber',
        'mother_name' => 'mothersName',
        'mother_alive' => 'motherAlive',
        'mother_occupation' => 'mothersOccupation',
        'mother_contact' => 'motherContactNumber',
        'brothers' => 'noOfBrothers',
        'brothers_married' => 'brothersMarried',
        'sisters' => 'noOfSisters',
        'sisters_married' => 'sistersMarried',
        'family_values' => 'familyValues',
        'family_financial_status' => 'familyFinancialStatus',
        'family_annual_income' => 'familyAnnualIncome',
        'place_of_birth' => 'placeOfBirth',
        'time_of_birth' => 'timeOfBirth',
        'birth_time_ampm' => 'birthTime_ampm',
        'rashi' => 'rashi',
        'nakshatra' => 'nakshatra',
        'gotra' => 'gotra',
        'nadi' => 'nadi',
        'home_status' => 'homeStatus',
        'has_vehicle' => 'hasVehicle',
        'vehicle_type' => 'vehicleType',
        'instagram' => 'instagram',
        'facebook' => 'facebook',
    ];

    public function show(Request $request)
    {
        try {
            $user = $this->candidate($request);
            $profile = $this->candidateProfile($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->presentUser($user),
                    'profile' => $profile ? $this->presentOwnProfile($profile, $user) : null,
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load your profile right now.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $this->candidate($request);

        if ($user->profile_id) {
            return response()->json([
                'success' => false,
                'message' => 'A profile is already linked to your account.',
            ], 422);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['groom', 'bride'])],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        try {
            $profile = DB::transaction(function () use ($user, $data) {
                $profile = Profile::create([
                    'fullName' => $data['full_name'],
                    'groomOrBride' => $data['gender'],
                    'birthDate' => $data['birth_date'],
                    'email' => $data['email'] ?? $user->email,
                    'phone' => $user->username,
                    'registration_type' => 'quick',
                ]);

                $user->profile_id = $profile->id;
                if (! empty($data['email'])) {
                    $user->email = $data['email'];
                }
                $user->name = $data['full_name'];
                $user->save();

                $profile->load('user');
                $this->refreshCompletion($profile);

                return $profile;
            });

            $profile = Profile::with(['user', 'preference'])->find($profile->id);

            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully.',
                'data' => [
                    'user' => $this->presentUser($user->fresh()),
                    'profile' => $this->presentOwnProfile($profile, $user->fresh()),
                ],
            ], 201);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create your profile right now.',
            ], 500);
        }
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($user, $profile, $validated) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $columns = Schema::getColumnListing((new Profile)->getTable());
                $payload = [];

                foreach (self::FIELD_MAP as $input => $column) {
                    if (array_key_exists($input, $validated) && in_array($column, $columns, true)) {
                        $payload[$column] = $validated[$input];
                    }
                }

                if ($payload) {
                    $locked->fill($payload);
                    $locked->save();
                }

                $userChanged = false;
                if (array_key_exists('full_name', $validated)) {
                    $user->name = $validated['full_name'];
                    $userChanged = true;
                }
                if (array_key_exists('email', $validated) && $validated['email']) {
                    $user->email = $validated['email'];
                    $userChanged = true;
                }
                if (array_key_exists('email', $validated) && ! $validated['email']) {
                    $user->email = $user->email ?: $user->username.'@candidate.local';
                }
                if ($userChanged) {
                    $user->save();
                }

                $this->refreshCompletion($locked);
            });

            $freshUser = $user->fresh();
            $freshProfile = Profile::with(['user', 'preference'])->find($profile->id);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'user' => $this->presentUser($freshUser),
                    'profile' => $this->presentOwnProfile($freshProfile, $freshUser),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update your profile right now.',
            ], 500);
        }
    }

    public function uploadPhoto(UploadPhotoRequest $request)
    {
        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);
        $newPath = null;

        try {
            $newPath = $request->file('photo')->store('candidate/profile_photos/'.$profile->id, 'public');

            DB::transaction(function () use ($profile, $newPath) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $oldPath = $locked->profilePhoto;

                $locked->profilePhoto = $newPath;
                $locked->save();

                $this->deleteStoredFile($oldPath);
                $this->refreshCompletion($locked);
            });

            $freshProfile = Profile::with(['user', 'preference'])->find($profile->id);

            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully.',
                'data' => [
                    'profile' => $this->presentOwnProfile($freshProfile, $user),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload the profile photo right now.',
            ], 500);
        }
    }

    public function deletePhoto(Request $request)
    {
        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);

        try {
            DB::transaction(function () use ($profile) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $oldPath = $locked->profilePhoto;
                $locked->profilePhoto = null;
                $locked->save();

                $this->deleteStoredFile($oldPath);
                $this->refreshCompletion($locked);
            });

            $freshProfile = Profile::with(['user', 'preference'])->find($profile->id);

            return response()->json([
                'success' => true,
                'message' => 'Profile photo removed.',
                'data' => [
                    'profile' => $this->presentOwnProfile($freshProfile, $user),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to remove the profile photo right now.',
            ], 500);
        }
    }

    public function uploadDocuments(UploadDocumentsRequest $request)
    {
        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);
        $stored = [];

        try {
            foreach (self::DOCUMENT_KEYS as $key) {
                if ($request->hasFile($key)) {
                    $stored[$key] = $request->file($key)->store(
                        'candidate/documents/'.$profile->id,
                        'public'
                    );
                }
            }

            if (! $stored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Choose at least one document to upload.',
                ], 422);
            }

            DB::transaction(function () use ($profile, $stored) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $oldFiles = [];

                foreach ($stored as $key => $path) {
                    $oldFiles[$key] = $locked->{$key};
                    $locked->{$key} = $path;
                }

                $locked->save();

                $verification = DocumentVerification::firstOrCreate(['profile_id' => $locked->id]);
                foreach ($stored as $key => $path) {
                    $verification->{$key.'_status'} = 'pending';
                    $verification->{$key.'_remarks'} = null;
                }
                $verification->save();

                foreach ($oldFiles as $path) {
                    $this->deleteStoredFile($path);
                }

                $this->refreshCompletion($locked);
            });

            $freshProfile = Profile::with(['user', 'preference'])->find($profile->id);
            $this->notifyUser(
                $user,
                'documents_uploaded',
                'Documents submitted for review',
                count($stored).' document(s) were uploaded and are awaiting verification.',
                ['profile_id' => $profile->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully. Verification is now pending.',
                'data' => [
                    'profile' => $this->presentOwnProfile($freshProfile, $user),
                ],
            ], 201);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            foreach ($stored as $path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload the documents right now.',
            ], 500);
        }
    }

    public function deleteDocument(Request $request, string $key)
    {
        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);

        if (! in_array($key, self::DOCUMENT_KEYS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown document type.',
            ], 404);
        }

        try {
            DB::transaction(function () use ($profile, $key) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $oldPath = $locked->{$key};
                $locked->{$key} = null;
                $locked->save();

                $verification = DocumentVerification::firstOrCreate(['profile_id' => $locked->id]);
                $verification->{$key.'_status'} = 'pending';
                $verification->{$key.'_remarks'} = null;
                $verification->save();

                $this->deleteStoredFile($oldPath);
                $this->refreshCompletion($locked);
            });

            $freshProfile = Profile::with(['user', 'preference'])->find($profile->id);

            return response()->json([
                'success' => true,
                'message' => 'Document removed.',
                'data' => [
                    'profile' => $this->presentOwnProfile($freshProfile, $user),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to remove the document right now.',
            ], 500);
        }
    }

    private function refreshCompletion(Profile $profile): void
    {
        try {
            $profile->calculateCompletionPercentage();
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);
        }
    }

    private function deleteStoredFile(?string $path): void
    {
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        $path = ltrim(trim($path), '/');

        if (str_starts_with($path, 'candidate/')) {
            Storage::disk('public')->delete($path);

            return;
        }

        if (str_starts_with($path, 'storage/candidate/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));

            return;
        }

        if (str_starts_with($path, 'uploads/')) {
            $fullPath = public_path($path);
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
}
