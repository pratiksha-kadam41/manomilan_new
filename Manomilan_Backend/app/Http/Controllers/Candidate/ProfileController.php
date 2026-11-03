<?php

namespace App\Http\Controllers\Candidate;

use App\DocumentVerification;
use App\Models\CandidateNotification;
use App\ProfileDocument;
use App\Profile;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Requests\Candidate\UploadDocumentsRequest;
use App\Http\Requests\Candidate\UploadPhotoRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends CandidateController
{
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

    public function show(Request $request)
    {
        try {
            $user = $this->candidate($request);
            $profile = $this->candidateProfile($request);
            if ($profile) {
                $profile->calculateCompletionPercentage();
                $profile->refresh();
            }

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

    public function status(Request $request)
    {
        $user = $this->candidate($request);
        $profile = $user->profile_id
            ? Profile::query()->find($user->profile_id, ['id', 'profile_completion', 'groomorbride'])
            : null;
        $completion = (int) ($profile?->profile_completion ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'has_profile' => $profile !== null,
                'profile_completion' => $completion,
                'complete' => $profile !== null && $completion >= 100,
                'gender' => strtolower((string) $profile?->groomorbride),
            ],
        ]);
    }

    public function store(Request $request, CandidateProfileService $profileService)
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
            $profile = DB::transaction(function () use ($user, $data, $profileService) {
                $profile = $profileService->create($user, $data);

                $user->profile_id = $profile->id;
                if (! empty($data['email'])) {
                    $user->email = $data['email'];
                }
                $user->name = $data['full_name'];
                $user->save();

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

    public function update(UpdateProfileRequest $request, CandidateProfileService $profileService)
    {
        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($user, $profile, $validated, $profileService) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $profileService->update($locked, $validated);

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
        $oldPath = null;
        $committed = false;

        try {
            $newPath = $request->file('photo')->store('candidate_images', 'public');

            DB::transaction(function () use ($profile, $newPath, &$oldPath) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $photos = is_array($locked->photos) ? $locked->photos : [];
                $oldPath = $photos['profile_photo'] ?? $photos[0] ?? null;
                $photos['profile_photo'] = $newPath;
                $locked->photos = $photos;
                $locked->save();

                $this->refreshCompletion($locked);
            });
            $committed = true;
            $this->deleteStoredFile($oldPath);

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

            if ($newPath && ! $committed) {
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
        $oldPath = null;

        try {
            DB::transaction(function () use ($profile, &$oldPath) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $photos = is_array($locked->photos) ? $locked->photos : [];
                $oldPath = $photos['profile_photo'] ?? $photos[0] ?? null;
                unset($photos['profile_photo'], $photos[0]);
                $locked->photos = $photos ?: null;
                $locked->save();

                $this->refreshCompletion($locked);
            });
            $this->deleteStoredFile($oldPath);

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

    public function uploadBodyPhotos(Request $request)
    {
        $validated = $request->validate([
            'full_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'half_body_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $photoFields = ['full_body_photo', 'half_body_photo', 'selfie_photo'];
        if (! collect($photoFields)->contains(fn (string $field) => $request->hasFile($field))) {
            return response()->json([
                'success' => false,
                'message' => 'Choose at least one photo to upload.',
            ], 422);
        }

        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);
        $stored = [];
        $oldPaths = [];
        $committed = false;

        try {
            foreach ($photoFields as $field) {
                if ($request->hasFile($field)) {
                    $stored[$field] = $request->file($field)->store(
                        'candidate_images',
                        'public'
                    );
                }
            }

            DB::transaction(function () use ($profile, $stored, &$oldPaths) {
                $locked = Profile::lockForUpdate()->findOrFail($profile->id);
                $photos = is_array($locked->photos) ? $locked->photos : [];

                foreach ($stored as $field => $path) {
                    $oldPaths[] = $photos[$field] ?? null;
                    $photos[$field] = $path;
                }

                $locked->photos = $photos;
                $locked->save();
                $this->refreshCompletion($locked);
            });
            $committed = true;
            foreach ($oldPaths as $path) {
                $this->deleteStoredFile($path);
            }

            $freshProfile = Profile::with(['user', 'preference'])->findOrFail($profile->id);

            return response()->json([
                'success' => true,
                'message' => 'Photos updated successfully.',
                'data' => [
                    'profile' => $this->presentOwnProfile($freshProfile, $user),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            if (! $committed) {
                foreach ($stored as $path) {
                    Storage::disk('public')->delete($path);
                }
            }
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload the photos right now.',
            ], 500);
        }
    }

    public function uploadAdditionalPhotos(Request $request)
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:4'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);
        $stored = [];
        $committed = false;

        try {
            foreach ($request->file('photos', []) as $photo) {
                $stored[] = $photo->store('candidate_images', 'public');
            }

            DB::transaction(function () use ($profile, $stored) {
                $locked = Profile::lockForUpdate()->findOrFail($profile->id);
                $existing = $this->normalizePathList($locked->getRawOriginal('additional_photos'));

                if (count($existing) + count($stored) > 4) {
                    throw ValidationException::withMessages([
                        'photos' => ['You can keep up to four additional photos on your profile.'],
                    ]);
                }

                $locked->additional_photos = array_merge($existing, $stored);
                $locked->save();
                $this->refreshCompletion($locked);
            });
            $committed = true;

            $freshProfile = Profile::with(['user', 'preference'])->findOrFail($profile->id);

            return response()->json([
                'success' => true,
                'message' => 'Additional photos uploaded successfully.',
                'data' => [
                    'profile' => $this->presentOwnProfile($freshProfile, $user),
                ],
            ]);
        } catch (Throwable $e) {
            if (! $committed) {
                foreach ($stored as $path) {
                    Storage::disk('public')->delete($path);
                }
            }
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload additional photos right now.',
            ], 500);
        }
    }

    public function uploadDocuments(UploadDocumentsRequest $request)
    {
        $user = $this->candidate($request);
        $profile = $this->candidateProfile($request, true);
        $stored = [];
        $oldFiles = [];
        $committed = false;

        try {
            foreach (self::DOCUMENT_KEYS as $key) {
                if ($request->hasFile($key)) {
                    $stored[$key] = $request->file($key)->store(
                        'candidate_documents',
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

            DB::transaction(function () use ($profile, $stored, &$oldFiles) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $documents = ProfileDocument::firstOrNew(['profile_id' => $locked->id]);
                foreach ($stored as $key => $path) {
                    $column = self::DOCUMENT_COLUMN_MAP[$key] ?? null;
                    if ($column === null) {
                        continue;
                    }
                    $oldFiles[] = $documents->{$column};
                    $documents->{$column} = $path;
                }

                $documents->save();

                $verification = DocumentVerification::firstOrCreate(['profile_id' => $locked->id]);
                foreach ($stored as $key => $path) {
                    $verification->{$key.'_status'} = 'pending';
                    $verification->{$key.'_remarks'} = null;
                }
                $verification->save();

                $this->refreshCompletion($locked);
            });
            $committed = true;
            foreach ($oldFiles as $path) {
                $this->deleteStoredFile($path);
            }

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

            if (! $committed) {
                foreach ($stored as $path) {
                    Storage::disk('public')->delete($path);
                }
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

        $oldPath = null;
        try {
            DB::transaction(function () use ($profile, $key, &$oldPath) {
                $locked = Profile::lockForUpdate()->find($profile->id);
                $column = self::DOCUMENT_COLUMN_MAP[$key];
                $documents = ProfileDocument::firstOrNew(['profile_id' => $locked->id]);
                $oldPath = $documents->{$column};
                $documents->{$column} = null;
                $documents->save();

                $verification = DocumentVerification::firstOrCreate(['profile_id' => $locked->id]);
                $verification->{$key.'_status'} = 'pending';
                $verification->{$key.'_remarks'} = null;
                $verification->save();

                $this->refreshCompletion($locked);
            });
            $this->deleteStoredFile($oldPath);

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

        $path = preg_replace('#^storage/#', '', $path);
        if (str_starts_with($path, 'candidate/')) {
            $legacyPath = substr($path, strlen('candidate/'));
            $directory = str_starts_with($legacyPath, 'documents/')
                ? 'candidate_documents'
                : 'candidate_images';
            Storage::disk('public')->delete($directory.'/'.basename($legacyPath));

            return;
        }

        if (str_starts_with($path, 'candidate_images/') || str_starts_with($path, 'candidate_documents/')) {
            Storage::disk('public')->delete($path);

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
