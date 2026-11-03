<?php

namespace App\Http\Controllers\Candidate;

use App\DocumentVerification;
use App\Http\Controllers\Controller;
use App\ProfileMelavaRegistration;
use App\Models\CandidateNotification;
use App\Models\User;
use App\Preference;
use App\Profile;
use App\ProfilePartnerPreference;
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
            'birth_date' => $user->birth_date,
            'gender' => $user->gender,
            'role_id' => $user->role_id,
            'user_type' => $user->user_type,
            'status' => $user->status,
        ];
    }

    protected function presentOwnProfile(Profile $profile, User $user): array
    {
        $profile->loadMissing(['familyDetails', 'educations', 'employments', 'religionDetails', 'profileDocuments']);
        $profile->calculateCompletionPercentage();
        $profile->refresh();
        $verification = DocumentVerification::where('profile_id', $profile->id)->first();
        $preference = $user->preference ?: $profile->partnerPreferences;

        $photos = is_array($profile->photos) ? $profile->photos : (is_string($profile->photos) && $profile->photos ? json_decode($profile->photos, true) : []);
        if (! is_array($photos)) {
            $photos = [];
        }

        $getPhoto = function ($key) use ($photos, $profile) {
            return $photos[$key] ?? $profile->{$key} ?? $profile->getAttribute($key) ?? null;
        };

        $docsModel = $profile->profileDocuments;
        $docMap = [
            'identificationProof' => $docsModel?->aadhaar_card ?: $docsModel?->pan_card ?: $docsModel?->passport,
            'birthDateProof' => $docsModel?->birth_certificate,
            'addressProof' => $docsModel?->address_proof,
            'educationProof' => $docsModel?->education_certificate,
            'professionProof' => $docsModel?->profession_proof,
            'incomeProof' => $docsModel?->income_proof,
            'casteCertificate' => $docsModel?->caste_certificate,
            'medicalFitnessCertificate' => $docsModel?->medical_fitness_certificate,
        ];

        $documents = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            $documents[$key] = [
                'key' => $key,
                'file_url' => $this->fileUrl($docMap[$key] ?? ($profile->{$key} ?? null), 'document'),
                'status' => $this->cleanDocumentStatus($verification?->{$key.'_status'}),
                'remarks' => (string) ($verification?->{$key.'_remarks'} ?? ''),
            ];
        }

        $additional = $photos['additional_photos'] ?? $this->normalizePathList($profile->getRawOriginal('additional_photos'));
        if (! is_array($additional)) {
            $additional = $this->normalizePathList($additional);
        }

        return [
            'id' => $profile->id,
            'registration_number' => $profile->reg_id ?: $profile->registration_number ?: $profile->profile_id,
            'profile_id' => $profile->reg_id ?: $profile->profile_id,
            'serial_no' => $profile->serial_no,
            'registration_type' => $profile->registration_type,
            'full_name' => $profile->full_name ?: $profile->fullName,
            'gender' => $profile->groomorbride ?: $profile->groomOrBride,
            'birth_date' => $profile->birth_date ?: $profile->birthDate,
            'age' => ($profile->birth_date ?: $profile->birthDate) ? Carbon::parse($profile->birth_date ?: $profile->birthDate)->age : null,
            'photo_url' => $this->fileUrl($getPhoto('profile_photo')),
            'full_body_photo_url' => $this->fileUrl($getPhoto('full_body_photo')),
            'half_body_photo_url' => $this->fileUrl($getPhoto('half_body_photo')),
            'selfie_photo_url' => $this->fileUrl($getPhoto('selfie_photo')),
            'additional_photo_urls' => collect($additional)
                ->map(fn ($path) => $this->fileUrl($path))
                ->filter()
                ->values()
                ->all(),
            'email' => $profile->email ?: $user->email,
            'mobile' => $user->username ?: $profile->phone,
            'city' => $this->profileCity($profile),
            'verification_status' => $profile->verification_status ?: $profile->profileVerificationStatus,
            'profile_completion' => (int) $profile->profile_completion,
            'created_at' => optional($profile->created_at)->toIso8601String(),
            'details' => $this->profileDetails($profile),
            'has_education' => filled($profile->educations?->first()?->degree_name) || filled($profile->employments?->first()?->profession),
            'has_family' => filled($profile->familyDetails?->father_name),
            'has_photos' => filled($getPhoto('profile_photo')),
            'has_preferences' => (bool) $preference,
            'has_documents' => collect($docMap)->contains(fn ($val) => filled($val)),
            'documents' => $documents,
            'preference' => $this->presentPreference($preference),
        ];
    }

    protected function presentPublicProfile(Profile $profile): array
    {
        $additionalPhotos = $this->normalizePathList($profile->additional_photos);
        $candidatePhotos = [];
        if (
            Schema::hasTable('profile_melava_registrations')
            && Schema::hasColumn('profile_melava_registrations', 'candidate_photos')
        ) {
            $melavaRegistration = ProfileMelavaRegistration::where('profile_id', $profile->id)->latest('id')->first();
            $candidatePhotos = $this->normalizePathList($melavaRegistration?->getRawOriginal('candidate_photos'));
        }

        $details = $this->profileDetails($profile);
        $profilePhotos = is_array($profile->photos) ? $profile->photos : [];
        $education = $profile->educations?->sortByDesc('id')->first();
        $employment = $profile->employments?->sortByDesc('id')->first();
        $religion = $profile->religionDetails;

        return array_merge([
            'id' => $profile->id,
            'registration_number' => $profile->registration_number ?: $profile->reg_id,
            'profile_id' => $profile->profile_id ?: $profile->reg_id,
            'full_name' => $profile->full_name,
            'first_name' => strtok((string) $profile->full_name, ' ') ?: $profile->full_name,
            'photo_url' => $this->fileUrl($profilePhotos['profile_photo'] ?? $profilePhotos[0] ?? null),
            'gender' => $this->profileGender($profile),
            'age' => $profile->birth_date ? Carbon::parse($profile->birth_date)->age : null,
            'birth_date' => $profile->birth_date,
            'height' => $profile->height_cm,
            'city' => $this->profileCity($profile),
            'religion' => $religion?->religion,
            'caste' => $religion?->caste,
            'marital_status' => $profile->marital_status,
            'mother_tongue' => $profile->mother_tongue,
            'education' => $education?->education_level ?: $education?->degree_name,
            'degree' => $education?->degree_name,
            'college' => $education?->university_name ?: $education?->college_name,
            'profession' => $employment?->profession,
            'field_of_working' => $employment?->working_field,
            'organisation' => $employment?->organisation_name,
            'designation' => $employment?->designation,
            'annual_income' => $employment?->annual_income ?: $profile->annual_income,
            'about' => $profile->about_me,
            'family' => [
                'father_name' => $profile->familyDetails?->father_name,
                'mother_name' => $profile->familyDetails?->mother_name,
                'brothers' => $profile->familyDetails?->brothers_count,
                'sisters' => $profile->familyDetails?->sisters_count,
                'family_values' => $profile->familyDetails?->family_values,
                'financial_status' => $profile->familyDetails?->family_financial_status,
            ],
            'verification_status' => $profile->verification_status,
            'verified' => $profile->verification_status === 'Completed',
            'profile_completion' => (int) $profile->profile_completion,
            'full_body_photo_url' => $this->fileUrl($profilePhotos['full_body_photo'] ?? null),
            'half_body_photo_url' => $this->fileUrl($profilePhotos['half_body_photo'] ?? null),
            'selfie_photo_url' => $this->fileUrl($profilePhotos['selfie_photo'] ?? null),
            'additional_photo_urls' => collect($additionalPhotos)->filter()->map(fn ($path) => $this->fileUrl($path))->values()->all(),
            'candidate_photo_urls' => collect($candidatePhotos)->filter()->map(fn ($path) => $this->fileUrl($path))->values()->all(),
            'details' => $details,
        ], $details);
    }

    protected function presentPreference(Preference|ProfilePartnerPreference|null $preference): ?array
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
            'preferred_working_field' => $preference->preferred_working_field,
            'working_partner' => $preference->working_partner,
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
        $profile->loadMissing(['familyDetails', 'educations', 'employments', 'religionDetails']);
        $addresses = is_array($profile->addresses) ? $profile->addresses : [];
        $metadata = is_array($addresses['metadata'] ?? null) ? $addresses['metadata'] : [];
        $current = is_array($addresses['current'] ?? null) ? $addresses['current'] : [];
        $permanent = is_array($addresses['permanent'] ?? null) ? $addresses['permanent'] : [];
        $reference = is_array($addresses['reference'] ?? null) ? $addresses['reference'] : [];
        $religion = $profile->religionDetails;
        $family = $profile->familyDetails;
        $education = $profile->educations?->sortBy('id')->values() ?? collect();
        $employment = $profile->employments?->sortByDesc('id')->first();

        return [
            'religion' => $religion?->religion,
            'caste' => $religion?->caste,
            'sub_caste' => $religion?->sub_caste,
            'marital_status' => $metadata['marital_status'] ?? $profile->marital_status,
            'height' => $profile->height_cm,
            'weight' => $profile->weight_kg,
            'having_specs_lenses' => $metadata['having_specs_lenses']
                ?? ($profile->has_spectacles ? 'Specs' : 'None'),
            'blood_group' => $profile->blood_group,
            'mother_tongue' => $profile->mother_tongue,
            'language' => is_array($profile->languages) ? implode(', ', $profile->languages) : $profile->languages,
            'special_cases' => $profile->special_cases,
            'source_of_information' => $metadata['source_of_information'] ?? $profile->source_of_information,
            'reference_name' => $reference['name'] ?? null,
            'reference_relation' => $reference['relation'] ?? null,
            'reference_contact' => $reference['contact'] ?? null,
            'reference_address' => $reference['address'] ?? null,
            'reference_email' => $reference['email'] ?? null,
            'about' => $profile->about_me,
            'address' => $current['address'] ?? null,
            'permanent_address' => $permanent['address'] ?? null,
            'country' => $current['country'] ?? null,
            'state' => $current['state'] ?? null,
            'district' => $current['district'] ?? null,
            'city' => $current['city'] ?? null,
            'taluka' => $current['taluka'] ?? null,
            'village' => $current['village'] ?? null,
            'pincode' => $current['pincode'] ?? null,
            'education' => $education->get(0)?->education_level ?: $education->get(0)?->degree_name,
            'degree' => $education->get(0)?->degree_name,
            'degree_2' => $education->get(1)?->degree_name,
            'specialisation' => $education->get(0)?->specialization,
            'specialisation_2' => $education->get(1)?->specialization,
            'specialisations' => $education
                ->pluck('specialization')
                ->filter(fn ($value) => filled($value))
                ->values()
                ->all(),
            'college' => $education->get(0)?->university_name ?: $education->get(0)?->college_name,
            'additional_education' => $education->get(0)?->additional_qualification,
            'additional_education_2' => $education->get(1)?->additional_qualification,
            'additional_qualifications' => $education
                ->pluck('additional_qualification')
                ->filter(fn ($value) => filled($value))
                ->values()
                ->all(),
            'city_of_education' => $education->get(0)?->education_city,
            'profession' => $employment?->profession,
            'field_of_working' => $employment?->working_field,
            'organisation' => $employment?->organisation_name,
            'professional_city' => $employment?->employment_city,
            'designation' => $employment?->designation,
            'length_of_employment' => $employment?->employment_duration,
            'monthly_income' => $employment?->monthly_income ?? $profile->monthly_income,
            'annual_income' => $employment?->annual_income ?? $profile->annual_income,
            'has_medical_history' => $profile->has_medical_history ? 'Yes' : 'No',
            'personal_medical_history' => $profile->personal_medical_history,
            'current_medical_problems' => $profile->current_medical_problems,
            'medications' => $profile->medications,
            'father_name' => $family?->father_name,
            'father_alive' => $family?->father_status === 'Alive' ? 'Yes' : ($family?->father_status === 'Deceased' ? 'No' : null),
            'father_occupation' => $family?->father_occupation,
            'father_contact' => $family?->father_contact,
            'father_native_place' => $family?->father_native_place,
            'mother_name' => $family?->mother_name,
            'mother_alive' => $family?->mother_status === 'Alive' ? 'Yes' : ($family?->mother_status === 'Deceased' ? 'No' : null),
            'mother_occupation' => $family?->mother_occupation,
            'mother_contact' => $family?->mother_contact,
            'mother_native_place' => $family?->mother_native_place,
            'brothers' => $family?->brothers_count,
            'brothers_married' => $family?->married_brothers_count,
            'sisters' => $family?->sisters_count,
            'sisters_married' => $family?->married_sisters_count,
            'parents_intercaste_marriage' => $family?->parents_intercaste_marriage ? 'Yes' : 'No',
            'parents_different_caste' => $family?->parents_different_caste_details,
            'family_values' => $family?->family_values,
            'family_financial_status' => $family?->family_financial_status,
            'family_annual_income' => $family?->family_annual_income,
            'profile_creator_name' => $profile->profile_creator_name ?: ($metadata['profile_creator_name'] ?? null),
            'profile_creator_relation' => $profile->creator_relation ?: ($metadata['profile_creator_relation'] ?? null),
            'place_of_birth' => $profile->birth_place,
            'time_of_birth' => $profile->birth_time,
            'birth_time_ampm' => $metadata['birth_time_ampm'] ?? null,
            'horoscope_caste' => $metadata['horoscope_caste'] ?? null,
            'horoscope_sub_caste' => $metadata['horoscope_sub_caste'] ?? null,
            'horoscope_dob' => $metadata['horoscope_dob'] ?? null,
        ];
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

        $addresses = is_array($profile->addresses) ? $profile->addresses : [];

        return $profile->professionalCity
            ?: $profile->cityOfEducation
            ?: ($addresses['current']['city'] ?? $addresses['city'] ?? null);
    }

    protected function profileGender(Profile $profile): string
    {
        return strtolower(trim((string) ($profile->groomorbride ?: $profile->groomOrBride)));
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
