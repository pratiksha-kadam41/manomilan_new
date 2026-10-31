<?php

namespace App\Http\Controllers\Candidate;

use App\DocumentVerification;
use App\Http\Controllers\Controller;
use App\MelavaRegistration;
use App\Models\CandidateNotification;
use App\Models\User;
use App\Preference;
use App\Profile;
use App\UserInterest;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class CandidateController extends Controller
{
    protected const DOCUMENT_KEYS = [
        'identificationProof',
        'birthDateProof',
        'addressProof',
        'educationProof',
        'professionProof',
        'incomeProof',
        'casteCertificate',
        'medicalFitnessCertificate',
    ];

    public static function documentKeys(): array
    {
        return self::DOCUMENT_KEYS;
    }

    protected function candidate(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Please sign in to continue.');
        }

        if ($user->isAdmin()) {
            throw new AuthorizationException('Candidate access is required.');
        }

        return $user;
    }

    protected function candidateProfile(Request $request, bool $required = false): ?Profile
    {
        $user = $this->candidate($request);

        if (! $user->profile_id) {
            if ($required) {
                throw ValidationException::withMessages([
                    'profile' => ['Complete your candidate profile before continuing.'],
                ]);
            }

            return null;
        }

        $profile = Profile::with(['user', 'preference'])->find($user->profile_id);

        if (! $profile && $required) {
            throw ValidationException::withMessages([
                'profile' => ['Your candidate profile could not be found. Please contact support.'],
            ]);
        }

        return $profile;
    }

    protected function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'mobile' => $user->username,
            'role_id' => $user->role_id,
            'user_type' => $user->user_type,
            'status' => $user->status,
        ];
    }

    protected function presentOwnProfile(Profile $profile, User $user): array
    {
        $profile->calculateCompletionPercentage();
        $profile->refresh();
        $verification = DocumentVerification::where('profile_id', $profile->id)->first();
        $preference = $user->preference;

        $documents = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            $documents[$key] = [
                'key' => $key,
                'file_url' => $this->fileUrl($profile->{$key} ?? null, 'document'),
                'status' => $this->cleanDocumentStatus($verification?->{$key.'_status'}),
                'remarks' => (string) ($verification?->{$key.'_remarks'} ?? ''),
            ];
        }

        return [
            'id' => $profile->id,
            'registration_number' => $profile->registration_number ?: $profile->profile_id,
            'profile_id' => $profile->profile_id,
            'serial_no' => $profile->serial_no,
            'registration_type' => $profile->registration_type,
            'full_name' => $profile->fullName,
            'gender' => $profile->groomOrBride,
            'birth_date' => $profile->birthDate,
            'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
            'photo_url' => $this->fileUrl($profile->profilePhoto),
            'full_body_photo_url' => $this->fileUrl($profile->full_body_photo),
            'half_body_photo_url' => $this->fileUrl($profile->half_body_photo),
            'selfie_photo_url' => $this->fileUrl($profile->selfie_photo),
            'email' => $profile->email ?: $user->email,
            'mobile' => $user->username ?: $profile->phone,
            'city' => $this->profileCity($profile),
            'verification_status' => $profile->profileVerificationStatus,
            'profile_completion' => (int) $profile->profile_completion,
            'created_at' => optional($profile->created_at)->toIso8601String(),
            'details' => $this->profileDetails($profile),
            'has_education' => filled($profile->education) && filled($profile->profession),
            'has_family' => filled($profile->fathersName) && filled($profile->mothersName),
            'has_photos' => filled($profile->profilePhoto),
            'has_preferences' => (bool) $preference,
            'has_documents' => collect(self::DOCUMENT_KEYS)
                ->contains(fn (string $key) => filled($profile->{$key} ?? null)),
            'documents' => $documents,
            'preference' => $this->presentPreference($preference),
        ];
    }

    protected function presentPublicProfile(Profile $profile): array
    {
        $additionalPhotos = $this->normalizePathList($profile->additional_photos);
        $melavaRegistration = MelavaRegistration::where('profile_id', $profile->id)->latest('id')->first();
        $candidatePhotos = $this->normalizePathList($melavaRegistration?->getRawOriginal('candidate_photos'));

        $details = $this->profileDetails($profile);

        return array_merge([
            'id' => $profile->id,
            'registration_number' => $profile->registration_number ?: $profile->profile_id,
            'profile_id' => $profile->profile_id,
            'full_name' => $profile->fullName,
            'first_name' => strtok((string) $profile->fullName, ' ') ?: $profile->fullName,
            'photo_url' => $this->fileUrl($profile->profilePhoto),
            'gender' => $profile->groomOrBride,
            'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
            'birth_date' => $profile->birthDate,
            'height' => $profile->height,
            'city' => $this->profileCity($profile),
            'religion' => $profile->religion,
            'caste' => $profile->caste,
            'marital_status' => $profile->maritalStatus,
            'mother_tongue' => $profile->motherTongue,
            'education' => $profile->education,
            'degree' => $profile->degreeDiplomaName1,
            'college' => $profile->universityBoardCollege,
            'profession' => $profile->profession,
            'field_of_working' => $profile->fieldOfWorking,
            'organisation' => $profile->organisationWorkingIn,
            'designation' => $profile->designation,
            'annual_income' => $profile->annualIncome,
            'diet' => $profile->diet,
            'smoking' => $profile->smoking,
            'drinking' => $profile->drinking,
            'hobbies' => $profile->hobbies,
            'about' => $profile->knowAboutMyself,
            'family' => [
                'father_name' => $profile->fathersName,
                'mother_name' => $profile->mothersName,
                'brothers' => $profile->noOfBrothers,
                'sisters' => $profile->noOfSisters,
                'family_values' => $profile->familyValues,
                'financial_status' => $profile->familyFinancialStatus,
            ],
            'verification_status' => $profile->profileVerificationStatus,
            'verified' => $profile->profileVerificationStatus === 'Completed',
            'profile_completion' => (int) $profile->profile_completion,
            'full_body_photo_url' => $this->fileUrl($profile->full_body_photo),
            'half_body_photo_url' => $this->fileUrl($profile->half_body_photo),
            'selfie_photo_url' => $this->fileUrl($profile->selfie_photo),
            'additional_photo_urls' => collect($additionalPhotos)->filter()->map(fn ($path) => $this->fileUrl($path))->values()->all(),
            'candidate_photo_urls' => collect($candidatePhotos)->filter()->map(fn ($path) => $this->fileUrl($path))->values()->all(),
            'details' => $details,
        ], $details);
    }

    protected function presentPreference(?Preference $preference): ?array
    {
        if (! $preference) {
            return null;
        }

        return [
            'preferred_religion' => $preference->preferred_religion,
            'preferred_caste' => $preference->preferred_caste,
            'preferred_sub_caste' => $preference->preferred_sub_caste,
            'age_from' => $preference->age_from,
            'age_to' => $preference->age_to,
            'height_from' => $preference->height_from,
            'height_to' => $preference->height_to,
            'preferred_education' => $preference->preferred_education,
            'working_partner' => $preference->working_partner,
            'preferred_working_field' => $preference->preferred_working_field,
            'expected_income' => $preference->expected_income,
            'family_type' => $preference->family_type,
            'financial_background' => $preference->financial_background,
            'family_values' => $preference->family_values,
            'preferred_location' => $preference->preferred_location,
            'preferred_country' => $preference->preferred_country,
            'preferred_state' => $preference->preferred_state,
            'preferred_city' => $preference->preferred_city,
            'other_expectations' => $preference->other_expectations,
        ];
    }

    protected function profileDetails(Profile $profile): array
    {
        $fields = [
            'religion' => 'religion',
            'caste' => 'caste',
            'sub_caste' => 'subCaste',
            'marital_status' => 'maritalStatus',
            'height' => 'height',
            'weight' => 'weight',
            'blood_group' => 'bloodGroup',
            'mother_tongue' => 'motherTongue',
            'language' => 'language',
            'about' => 'knowAboutMyself',
            'address' => 'address',
            'permanent_address' => 'permanent_address',
            'education' => 'education',
            'degree' => 'degreeDiplomaName1',
            'degree_2' => 'degreeDiplomaName2',
            'specialisation' => 'specialisation1',
            'college' => 'universityBoardCollege',
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

        $columns = Schema::getColumnListing((new Profile)->getTable());
        $details = [];

        foreach ($fields as $key => $column) {
            $details[$key] = in_array($column, $columns, true) ? $profile->{$column} : null;
        }

        return $details;
    }

    protected function presentInterest(UserInterest $interest, string $direction, Profile $me, ?Preference $preference): array
    {
        $otherProfile = $direction === 'received'
            ? $interest->user?->profile
            : $interest->profile;

        $payload = [
            'id' => $interest->id,
            'status' => strtolower((string) ($interest->status ?: 'pending')) === 'rejected'
                ? 'declined'
                : strtolower((string) ($interest->status ?: 'pending')),
            'direction' => $direction,
            'created_at' => optional($interest->created_at)->toIso8601String(),
            'updated_at' => optional($interest->updated_at)->toIso8601String(),
            'profile' => $otherProfile ? $this->presentPublicProfile($otherProfile) : null,
        ];

        if ($otherProfile) {
            $match = $this->calculateMatch($me, $otherProfile, $preference);
            $payload['match_percentage'] = $match['score'];
            $payload['match_reasons'] = $match['reasons'];
        } else {
            $payload['match_percentage'] = 0;
            $payload['match_reasons'] = [];
        }

        return $payload;
    }

    protected function calculateMatch(Profile $me, Profile $candidate, ?Preference $preference): array
    {
        $score = 45.0;
        $reasons = [];

        if ($this->sameText($me->religion, $candidate->religion)) {
            $score += 10;
            $reasons[] = 'Same religion';
        }

        if ($this->sameText($me->caste, $candidate->caste)) {
            $score += 8;
            $reasons[] = 'Same community';
        }

        if ($this->sameText($me->motherTongue, $candidate->motherTongue)) {
            $score += 4;
            $reasons[] = 'Same mother tongue';
        }

        $candidateAge = $candidate->birthDate ? Carbon::parse($candidate->birthDate)->age : null;

        if ($preference) {
            $ageFrom = (int) ($preference->age_from ?: 0);
            $ageTo = (int) ($preference->age_to ?: 0);

            if ($candidateAge !== null && $ageFrom > 0 && $ageTo > 0) {
                if ($candidateAge >= $ageFrom && $candidateAge <= $ageTo) {
                    $score += 15;
                    $reasons[] = 'Within your age range';
                } else {
                    $distance = $candidateAge < $ageFrom ? $ageFrom - $candidateAge : $candidateAge - $ageTo;
                    $score += max(0, 12 - ($distance * 2));
                    if ($distance <= 2) {
                        $reasons[] = 'Close to your age range';
                    }
                }
            } else {
                $score += 8;
            }

            if ($this->matchesPreference($preference->preferred_religion, $candidate->religion)) {
                $score += 8;
                $reasons[] = 'Preferred religion';
            }
            if ($this->matchesPreference($preference->preferred_caste, $candidate->caste)) {
                $score += 6;
                $reasons[] = 'Preferred community';
            }
            if ($this->matchesPreference($preference->preferred_city, $this->profileCity($candidate), $candidate->professionalCity)) {
                $score += 6;
                $reasons[] = 'Preferred city';
            }
            if ($this->matchesPreference($preference->preferred_education, $candidate->education)) {
                $score += 5;
                $reasons[] = 'Education preference';
            }
            if ($this->matchesPreference($preference->preferred_working_field, $candidate->fieldOfWorking, $candidate->profession)) {
                $score += 5;
                $reasons[] = 'Profession preference';
            }
        } else {
            if ($this->sameText($this->profileCity($me), $this->profileCity($candidate))) {
                $score += 8;
                $reasons[] = 'Same city';
            }
            if ($this->sameText($me->education, $candidate->education)) {
                $score += 6;
                $reasons[] = 'Similar education';
            }
            if ($this->similarText($me->profession, $candidate->profession)) {
                $score += 6;
                $reasons[] = 'Similar profession';
            }
        }

        if ($candidate->profileVerificationStatus === 'Completed') {
            $score += 5;
            $reasons[] = 'Verified profile';
        }

        if (filled($candidate->profilePhoto)) {
            $score += 3;
        }

        return [
            'score' => (int) max(35, min(99, round($score))),
            'reasons' => array_values(array_slice(array_unique($reasons), 0, 4)),
        ];
    }

    protected function cleanDocumentStatus(?string $status): string
    {
        $status = strtolower((string) $status);

        return in_array($status, ['pending', 'verified', 'rejected', 'not_eligible'], true)
            ? $status
            : 'pending';
    }

    protected function profileCity(?Profile $profile): ?string
    {
        if (! $profile) {
            return null;
        }

        return $profile->location ?: $profile->professionalCity ?: $profile->cityOfEducation;
    }

    protected function fileUrl(?string $path, string $mediaType = 'image'): string
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
            $path = substr($path, strlen('storage/'));
        }

        if (str_starts_with($path, 'candidate/')) {
            $legacyPath = substr($path, strlen('candidate/'));
            $mediaType = str_starts_with($legacyPath, 'documents/') ? 'document' : $mediaType;
            $path = ($mediaType === 'document' ? 'candidate_documents' : 'candidate_images')
                .'/'.basename($legacyPath);
        } elseif (! str_starts_with($path, 'candidate_images/')
            && ! str_starts_with($path, 'candidate_documents/')) {
            return url($path);
        }

        return url('storage/'.$path);
    }

    protected function normalizePathList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn ($item) => is_string($item) ? trim($item) : null,
                $value
            )));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (is_array($decoded)) {
            return array_values(array_filter(array_map(
                fn ($item) => is_string($item) ? trim($item) : null,
                $decoded
            )));
        }

        return [trim($value)];
    }

    protected function rethrowClientError(Throwable $e): void
    {
        if ($e instanceof ValidationException || $e instanceof AuthorizationException || $e instanceof HttpExceptionInterface) {
            throw $e;
        }
    }

    protected function notifyUser(?User $user, string $type, string $title, string $message, array $data = []): void
    {
        if (! $user) {
            return;
        }

        try {
            CandidateNotification::pushTo($user, $type, $title, $message, $data);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function matchesPreference($preference, ...$values): bool
    {
        if (! is_string($preference) || trim($preference) === '') {
            return false;
        }

        foreach ($values as $value) {
            if ($this->similarText($preference, $value)) {
                return true;
            }
        }

        return false;
    }

    protected function sameText($left, $right): bool
    {
        if (! filled($left) || ! filled($right)) {
            return false;
        }

        return mb_strtolower(trim((string) $left)) === mb_strtolower(trim((string) $right));
    }

    protected function similarText($left, $right): bool
    {
        if (! filled($left) || ! filled($right)) {
            return false;
        }

        $left = mb_strtolower(trim((string) $left));
        $right = mb_strtolower(trim((string) $right));

        return $left === $right || str_contains($left, $right) || str_contains($right, $left);
    }
}
