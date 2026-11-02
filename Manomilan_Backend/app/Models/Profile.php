<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\PaymentScreenshot;
use App\Http\Controllers\Controller;

class Profile extends Model
{

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (self $profile): void {
            $profileId = $profile->getKey();

            foreach ([
                'profile_family_details',
                'profile_education',
                'profile_employment',
                'profile_religion_details',
                'profile_documents',
                'profile_partner_preferences',
                'profile_melava_registrations',
                'payment_screenshots',
                'user_interests',
                'profile_visitors',
                'document_verifications',
                'melava_pending_registrations',
            ] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'profile_id')) {
                    DB::table($table)->where('profile_id', $profileId)->delete();
                }
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'profile_id')) {
                DB::table('users')->where('profile_id', $profileId)->update(['profile_id' => null]);
            }
        });

        static::creating(function (self $profile): void {
            if (Schema::hasColumn($profile->getTable(), 'reg_id')) {
                if (! $profile->reg_id) {
                    $prefix = 'MM3-';
                    $lastId = self::query()
                        ->where('reg_id', 'like', $prefix.'%')
                        ->orderByDesc('id')
                        ->value('reg_id');
                    $nextNumber = $lastId && preg_match('/^MM3-(\d+)$/', $lastId, $matches)
                        ? (int) $matches[1] + 1
                        : 1;
                    $profile->reg_id = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
                }

                return;
            }

            if (! Schema::hasColumn($profile->getTable(), 'registration_number')) {
                return;
            }

            $type = $profile->registration_type ?: session('registration_type', 'quick');
            $prefix = $type === 'melava' ? 'MM3-' : 'AMM2026';
            $lastProfile = self::query()
                ->where('registration_number', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->first();
            $nextNumber = 1;

            if (
                $lastProfile &&
                preg_match('/^'.preg_quote($prefix, '/').'(?:B|G)?(\d+)$/', (string) $lastProfile->registration_number, $matches)
            ) {
                $nextNumber = (int) $matches[1] + 1;
            }

            $profile->registration_number = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            if (Schema::hasColumn($profile->getTable(), 'profile_id')) {
                $profile->profile_id = $profile->registration_number;
            }
        });

        static::saving(function (self $profile): void {
            if (! Schema::hasColumn($profile->getTable(), 'serial_no')) {
                return;
            }

            if (
                ! in_array($profile->registration_type, ['melava', 'existing'], true)
                || $profile->melava_batch !== Controller::CURRENT_MELAVA_BATCH
                || ! empty($profile->serial_no)
            ) {
                return;
            }

            if (empty($profile->birthDate)) {
                return;
            }

            try {
                $birthYear = \Carbon\Carbon::parse($profile->birthDate)->year;
            } catch (\Exception $e) {
                return;
            }

            // Normalize marital status for case-insensitive matching
            $maritalStatus = strtolower(trim($profile->maritalStatus ?? ''));

            // Special marital statuses receive the E3 prefix
            $specialStatuses = [
                'widow',
                'widower',
                'divorcee',
                'divorced',
                'widowed',
                'separated',
            ];

            // Determine serial prefix
            if (in_array($maritalStatus, $specialStatuses, true)) {
                $serialPrefix = 'E3';
            } elseif ($birthYear >= 1998) {
                $serialPrefix = 'A3';
            } elseif ($birthYear >= 1992 && $birthYear <= 1997) {
                $serialPrefix = 'B3';
            } elseif ($birthYear >= 1984 && $birthYear <= 1991) {
                $serialPrefix = 'C3';
            } else {
                $serialPrefix = 'D3';
            }

            // Find the highest existing serial number for this prefix
            // within the current Melava batch.
            $lastSerialProfile = self::where(
                'melava_batch',
                Controller::CURRENT_MELAVA_BATCH
            )
                ->where('serial_no', 'LIKE', $serialPrefix . ' %')
                ->orderByRaw(
                    'CAST(SUBSTRING(serial_no, ' .
                    (strlen($serialPrefix) + 2) .
                    ') AS UNSIGNED) DESC'
                )
                ->first();

            $nextSerialNumber = 1;

            if (
                $lastSerialProfile &&
                preg_match(
                    '/^' . preg_quote($serialPrefix, '/') . ' (\d+)$/',
                    $lastSerialProfile->serial_no,
                    $matches
                )
            ) {
                $nextSerialNumber = (int) $matches[1] + 1;
            }

            // Final format: A3 001, B3 001, C3 001, D3 001, E3 001
            $profile->serial_no = $serialPrefix . ' ' .
                str_pad($nextSerialNumber, 3, '0', STR_PAD_LEFT);
        });
    }



    public function isMelava3Candidate()
    {
        $registrationId = $this->reg_id ?: $this->profile_id;

        return is_string($registrationId)
            && str_starts_with($registrationId, 'MM3-')
            && $this->melava_batch === 'october_2026';
    }

    /**
     * Whether the given serial number is already a valid serial for the
     * current Melava batch (new format, e.g. "A3 001"). Old-format Melava 2
     * serials (e.g. "A101") or serials from another batch return false.
     */
    public static function hasCurrentMelavaSerial($serialNo, $melavaBatch)
    {
        return $melavaBatch === Controller::CURRENT_MELAVA_BATCH
            && !empty($serialNo)
            && preg_match('/^[A-E]3 \d{3}$/', $serialNo) === 1;
    }

    protected $fillable = [
        'reg_id',
        'groomorbride',
        'full_name',
        'birth_date',
        'height_cm',
        'weight_kg',
        'has_spectacles',
        'blood_group',
        'mother_tongue',
        'languages',
        'special_cases',
        'about_me',
        'photos',
        'monthly_income',
        'annual_income',
        'personal_medical_history',
        'current_medical_problems',
        'medications',
        'birth_place',
        'birth_time',
        'created_by',
        'creator_relation',
        'profile_creator_name',
        'addresses',
        'verification_status',
        'status',
        'profile_id',
        'serial_no',
        'registration_number',
        'registration_type',
        'melava_batch',
        'email',
        'phone',
        'source_of_information',
        'marital_status',
        'has_medical_history',
        // Basic Details
        'groomOrBride',
        'fullName',
        'profilePhoto',
        'full_body_photo',
        'half_body_photo',
        'selfie_photo',
        'caste',
        'subCaste', // not updated in database
        'maritalStatus',
        'birthDate',
        'height', // not updated in database
        'weight',
        'havingSpecsLenses',
        'bloodGroup',
        'motherTongue',
        'language',
        'specialCases',
        'knowAboutMyself',
        'religion',
        'address',
        'permanent_address',
        'country',
        'state',
        'district',
        'taluka',
        'village',
        'pincode',
        // Educational Details
        'education',
        'degreeDiplomaName1',
        'degreeDiplomaName2',
        'specialisation1',
        'specialisation2',
        'universityBoardCollege',
        'additionalEducation1',
        'additionalEducation2',
        'cityOfEducation',
        // Professional Details
        'profession',
        'fieldOfWorking',
        'organisationWorkingIn',
        'professionalCity',
        'designation',
        'lengthOfEmployment',
        'monthlyIncome',
        'annualIncome',
        'income_currency',
        'original_monthly_income',
        'original_annual_income',
        // Habits and Lifestyle
        // Medical Details
        'hasMedicalHistory',
        'personalMedicalHistory',
        'currentMedicalProblems',
        'medications',
        // Horoscope Details
        'horoscopeCaste',
        'horoscopeSubCaste',
        'horoscopeDob',
        'placeOfBirth',
        'timeOfBirth',
        'birthTime_ampm',
        // Investment Details
        // Family Details
        'fathersName',
        'fatherAlive',
        'mothersName',
        'motherAlive',
        'fathersNativePlace',
        'fathersOccupation',
        'fatherContactNumber',
        'mothersNativePlace',
        'mothersOccupation',
        'motherContactNumber',
        'noOfBrothers',
        'brothersMarried',
        'noOfSisters',
        'sistersMarried',
        'parentsIntercasteMarriage',
        'parentsDifferentCaste',
        'familyValues',
        'familyFinancialStatus',
        'familyAnnualIncome',
        // Profile Creator & Reference
        'profileCreatorName',
        'profileCreatorRelation',
        'referenceName',
        'referenceRelation',
        'referenceAddress',
        'referenceContactNumber',
        'referenceEmail',
        // Document Details
        'identificationProof',
        'birthDateProof',
        'addressProof',
        'educationProof',
        'professionProof',
        'incomeProof',
        'casteCertificate',
        'medicalFitnessCertificate',
        'additional_photos',
        // Non-fillable by default (status columns) — explicitly added for Melava flow
        'profileVerificationStatus',
        'profile_completion',
        'active',
    ];

    protected $attributes = [];

    /**
     * The attributes that should be cast to native types.
     * ✅ ADD THIS ENTIRE BLOCK
     * @var array
     */
    protected $casts = [
        'additional_photos' => 'array',
        'languages' => 'array',
        'photos' => 'array',
        'addresses' => 'array',
        'has_spectacles' => 'boolean',
        'height_cm' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'monthly_income' => 'decimal:2',
        'annual_income' => 'decimal:2',
    ];

    public const VERIFICATION_DOCUMENT_KEYS = [
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

    public function documentVerification()
    {
        return $this->hasOne(DocumentVerification::class, 'profile_id');
    }

    private function isVerifiedDocumentStatus($status): bool
    {
        return in_array(strtolower(trim((string) $status)), [
            'verified', 'approved', 'approve', 'complete', 'completed',
        ], true);
    }

    public function hasVerifiedPayment(): bool
    {
        if ($this->hasAttribute('has_verified_payment_screenshot') || $this->hasAttribute('has_verified_melava_payment')) {
            return (bool) $this->getAttribute('has_verified_payment_screenshot')
                || (bool) $this->getAttribute('has_verified_melava_payment');
        }

        $paymentsLoaded = $this->relationLoaded('paymentScreenshots');
        $profileMelavaLoaded = $this->relationLoaded('profileMelavaRegistrations');

        if ($paymentsLoaded && $profileMelavaLoaded) {
            return $this->paymentScreenshots->contains(fn ($payment) => (bool) $payment->payment_verified)
                || $this->profileMelavaRegistrations->contains(fn ($registration) => (bool) $registration->payment_verified);
        }

        return PaymentScreenshot::where('profile_id', $this->id)
                ->where('payment_verified', true)
                ->exists()
            || $this->profileMelavaRegistrations()
                ->where('payment_verified', true)
                ->exists();
    }

    public function verificationSummary(): array
    {
        $verification = $this->relationLoaded('documentVerification')
            ? $this->documentVerification
            : $this->documentVerification()->first();

        $uploaded = [];
        $verified = [];
        $documents = $this->relationLoaded('profileDocuments')
            ? $this->profileDocuments
            : $this->profileDocuments()->first();
        $documentColumnMap = [
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

        foreach (self::VERIFICATION_DOCUMENT_KEYS as $key) {
            $legacyPath = array_key_exists($key, $this->getAttributes())
                ? $this->getAttribute($key)
                : null;
            $normalizedPath = collect($documentColumnMap[$key] ?? [])
                ->map(fn (string $column) => $documents?->{$column})
                ->first(fn ($path) => filled($path));

            if (filled($legacyPath) || filled($normalizedPath)) {
                $uploaded[] = $key;

                if ($this->isVerifiedDocumentStatus($verification?->{$key.'_status'})) {
                    $verified[] = $key;
                }
            }
        }

        $required = ['identificationProof', 'birthDateProof'];
        $missingRequired = array_values(array_diff($required, $uploaded));
        $unverifiedDocuments = array_values(array_diff($uploaded, $verified));
        $documentsAllVerified = empty($missingRequired) && empty($unverifiedDocuments);
        $paymentVerified = $this->hasVerifiedPayment();
        $fullyVerified = $documentsAllVerified && $paymentVerified;

        return [
            'status' => $fullyVerified ? 'Completed' : 'Pending',
            'display_status' => $fullyVerified ? 'Verified' : 'Pending',
            'is_verified' => $fullyVerified,
            'documents_total' => count($uploaded),
            'documents_verified' => count($verified),
            'documents_pending' => count($uploaded) - count($verified),
            'documents_all_verified' => $documentsAllVerified,
            'missing_required_documents' => $missingRequired,
            'unverified_documents' => $unverifiedDocuments,
            'payment_verified' => $paymentVerified,
        ];
    }

    public function registrationSummary(): array
    {
        $type = strtolower(trim((string) $this->registration_type));
        $hasMelavaRecord = $this->hasAttribute('melava_registration_exists')
            ? (bool) $this->getAttribute('melava_registration_exists')
            : (($this->relationLoaded('profileMelavaRegistrations')
                ? $this->profileMelavaRegistrations->isNotEmpty()
                : $this->profileMelavaRegistrations()->exists())
                );
        $hasMelava = $type === 'melava'
            || filled($this->melava_batch)
            || $hasMelavaRecord;
        $hasProfile = $type !== 'melava' || ! $hasMelava;

        if ($hasProfile && $hasMelava) {
            $label = 'Both';
        } elseif ($hasMelava) {
            $label = 'Melava Registration';
        } else {
            $label = 'Profile';
        }

        return [
            'label' => $label,
            'is_profile' => $hasProfile,
            'is_melava' => $hasMelava,
        ];
    }

    public function syncDocumentVerificationStatus(): bool
    {
        $this->unsetRelation('documentVerification');
        $this->unsetRelation('paymentScreenshots');
        $this->unsetRelation('profileMelavaRegistrations');

        $summary = $this->verificationSummary();
        $status = $summary['status'];

        if ($this->verification_status !== $status) {
            $this->verification_status = $status;
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * ✅ THIS FUNCTION IS UPDATED
     * Get the user that owns the profile.
     */
    public function user()
    {
        // Changed to hasOne as users table contains the profile_id foreign key
        return $this->hasOne(\App\Models\User::class, 'profile_id');
    }

    /**
     * ✅ ADD THIS FUNCTION
     * Get the preference associated with the user of this profile.
     */
    public function preference()
    {
        return $this->hasOneThrough(Preference::class, \App\Models\User::class, 'profile_id', 'user_id', 'id', 'id');
    }

    public function interestedUsers()
    {
        return $this->belongsToMany(\App\Models\User::class, 'user_interests', 'user_id', 'profile_id')
            ->withTimestamps();
    }

    // Define the relationship for sent requests
    public function sentRequests()
    {
        return $this->hasMany(UserInterest::class, 'user_id');
    }

    // Define the relationship for accepted requests
    public function acceptedRequests()
    {
        return $this->sentRequests()->where('status', 'accepted');
    }

    /**
     * Whether a stored value should be treated as filled.
     */
    protected function isFieldFilled($value)
    {
        return !empty($value) || $value === '0' || $value === 0;
    }

    /**
     * Calculate the profile completion percentage.
     *
     * The counted fields mirror the Melava registration form
     * (resources/views/admin/melava/public_create.blade.php) exactly, so a
     * profile that has every field of that form filled reaches 100%.
     *
     * Conditional fields (sub caste, parent contact details, medical details,
     * ...) are only counted when they apply to the candidate,
     * so answering "No" / "0" never penalises the score.
     */
    public function calculateCompletionPercentage()
    {
        $allFields = [
            // Basic Details
            'phone', // Mobile Number
            'fullName',
            'source_of_information',
            'profilePhoto',
            'full_body_photo',
            'half_body_photo',
            'selfie_photo',
            'groomOrBride',
            'religion',
            'caste',
            'maritalStatus',
            'birthDate',
            'height',
            'weight',
            'havingSpecsLenses',
            'bloodGroup',
            'motherTongue',
            'language',
            'specialCases',
            'knowAboutMyself',
            'address',
            'permanent_address',
            // Horoscope Details
            'timeOfBirth',
            'placeOfBirth',
            // Educational Details
            'education',
            'degreeDiplomaName1',
            'degreeDiplomaName2',
            'specialisation1',
            'specialisation2',
            'universityBoardCollege',
            'additionalEducation1',
            'additionalEducation2',
            'cityOfEducation',
            // Professional Details
            'profession',
            'fieldOfWorking',
            'organisationWorkingIn',
            'professionalCity',
            'designation',
            'lengthOfEmployment',
            'monthlyIncome',
            'annualIncome',
            // Medical Details
            'hasMedicalHistory',
            // Family Details
            'fathersName',
            'fatherAlive',
            'fathersNativePlace',
            'mothersName',
            'motherAlive',
            'mothersNativePlace',
            'noOfBrothers',
            'noOfSisters',
            'parentsIntercasteMarriage',
            'familyValues',
            'familyFinancialStatus',
            'familyAnnualIncome',
            'profileCreatorName',
            'profileCreatorRelation',
            // Reference Person's Details
            'referenceName',
            'referenceRelation',
            'referenceAddress',
            'referenceContactNumber',
            'referenceEmail',
            // Document Details
            'identificationProof',
            'birthDateProof',
            'addressProof',
            'educationProof',
            'professionProof',
            'incomeProof',
            'casteCertificate',
            'medicalFitnessCertificate',
        ];

        $filledCount = 0;
        $totalCount = 0;

        foreach ($allFields as $field) {
            $totalCount++;
            if ($this->isFieldFilled($this->$field)) {
                $filledCount++;
            }
        }

        // Conditional profile fields – only counted when they apply.
        $conditionalFields = [];

        if (in_array($this->caste, ['Shwetambar', 'Digambar'], true)) {
            $conditionalFields[] = 'subCaste';
        }
        if ($this->fatherAlive === 'Yes') {
            $conditionalFields[] = 'fathersOccupation';
            $conditionalFields[] = 'fatherContactNumber';
        }
        if ($this->motherAlive === 'Yes') {
            $conditionalFields[] = 'mothersOccupation';
            $conditionalFields[] = 'motherContactNumber';
        }
        if ($this->hasMedicalHistory === 'Yes') {
            $conditionalFields[] = 'personalMedicalHistory';
            $conditionalFields[] = 'currentMedicalProblems';
            $conditionalFields[] = 'medications';
        }
        if ($this->parentsIntercasteMarriage === 'Yes') {
            $conditionalFields[] = 'parentsDifferentCaste';
        }
        if ((int) $this->noOfBrothers > 0) {
            $conditionalFields[] = 'brothersMarried';
        }
        if ((int) $this->noOfSisters > 0) {
            $conditionalFields[] = 'sistersMarried';
        }

        foreach ($conditionalFields as $field) {
            $totalCount++;
            if ($this->isFieldFilled($this->$field)) {
                $filledCount++;
            }
        }

        // Partner Preferences
        $preference = optional($this->user)->preference;

        $prefFields = [
            'preferred_religion',
            'preferred_caste',
            'age_from',
            'age_to',
            'height_from',
            'height_to',
            'preferred_education',
            'working_partner',
            'preferred_working_field',
            'expected_income',
            'family_type',
            'financial_background',
            'family_values',
            'preferred_country',
            'preferred_state',
            'preferred_city',
            'other_expectations',
        ];

        if ($preference && in_array($preference->preferred_caste, ['Shwetambar', 'Digambar'], true)) {
            $prefFields[] = 'preferred_sub_caste';
        }

        foreach ($prefFields as $field) {
            $totalCount++;
            $value = $preference ? $preference->$field : null;
            if ($this->isFieldFilled($value)) {
                $filledCount++;
            }
        }

        $percentage = ($totalCount > 0) ? (int) round(($filledCount / $totalCount) * 100) : 0;

        // The candidate dashboard should show 100% once every field required
        // by the registration form is filled, regardless of optional details.
        if ($this->hasCompletedMandatoryFields()) {
            $percentage = 100;
        }

        if (Schema::hasColumn($this->getTable(), 'profile_completion')) {
            $this->profile_completion = $percentage;
            $this->save();
        }

        return $percentage;
    }

    /**
     * Check if the profile has all mandatory fields completed.
     *
     * The mandatory set mirrors the fields marked with a red asterisk (*) in
     * the Melava registration form (public_create.blade.php).
     */
    public function hasCompletedMandatoryFields()
    {
        foreach ($this->missingMandatoryFields() as $field) {
            return false;
        }
        return true;
    }

    public function missingMandatoryFields(): array
    {
        $mandatoryFields = [
            'phone', // Mobile Number
            'fullName',
            'source_of_information',
            'groomOrBride',
            'religion',
            'caste',
            'maritalStatus',
            'birthDate',
            'height',
            'weight',
            'bloodGroup',
            'motherTongue',
            'address',
            'permanent_address',
            'timeOfBirth',
            'placeOfBirth',
            'education',
            'degreeDiplomaName1',
            'profession',
            'fieldOfWorking',
            'professionalCity',
            'monthlyIncome',
            'fathersName',
            'fatherAlive',
            'mothersName',
            'motherAlive',
            'noOfBrothers',
            'noOfSisters',
            'referenceName',
            'referenceRelation',
            'referenceContactNumber',
        ];

        $missing = [];
        foreach ($mandatoryFields as $field) {
            if (!$this->isFieldFilled($this->$field)) {
                $missing[] = $field;
            }
        }

        // Profile photo and proofs are marked mandatory in the registration form.
        if (empty($this->profilePhoto)) {
            $missing[] = 'profilePhoto';
        }

        foreach (['full_body_photo', 'half_body_photo', 'selfie_photo'] as $photoField) {
            if (empty($this->{$photoField})) {
                $missing[] = $photoField;
            }
        }

        if (empty($this->identificationProof) || empty($this->birthDateProof)) {
            if (empty($this->identificationProof)) $missing[] = 'identificationProof';
            if (empty($this->birthDateProof)) $missing[] = 'birthDateProof';
        }

        if (in_array($this->caste, ['Shwetambar', 'Digambar'], true) && empty($this->subCaste)) {
            $missing[] = 'subCaste';
        }

        if ($this->fatherAlive === 'Yes') {
            if (empty($this->fathersOccupation) || empty($this->fatherContactNumber))
                if (empty($this->fathersOccupation)) $missing[] = 'fathersOccupation';
                if (empty($this->fatherContactNumber)) $missing[] = 'fatherContactNumber';
        }

        if ($this->motherAlive === 'Yes') {
            if (empty($this->mothersOccupation) || empty($this->motherContactNumber))
                if (empty($this->mothersOccupation)) $missing[] = 'mothersOccupation';
                if (empty($this->motherContactNumber)) $missing[] = 'motherContactNumber';
        }

        if ((int) $this->noOfBrothers > 0 && !$this->isFieldFilled($this->brothersMarried)) {
            $missing[] = 'brothersMarried';
        }

        if ((int) $this->noOfSisters > 0 && !$this->isFieldFilled($this->sistersMarried)) {
            $missing[] = 'sistersMarried';
        }

        return $missing;
    }
    /**
     * Get the payment screenshots associated with the profile
     */
    public function paymentScreenshots()
    {
        return $this->hasMany(PaymentScreenshot::class);
    }

    public function familyDetails(): HasOne
    {
        return $this->hasOne(ProfileFamilyDetail::class, 'profile_id', 'id');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(ProfileEducation::class, 'profile_id', 'id');
    }

    public function employments(): HasMany
    {
        return $this->hasMany(ProfileEmployment::class, 'profile_id', 'id');
    }

    public function religionDetails(): HasOne
    {
        return $this->hasOne(ProfileReligionDetail::class, 'profile_id', 'id');
    }

    public function profileDocuments(): HasOne
    {
        return $this->hasOne(ProfileDocument::class, 'profile_id', 'id');
    }

    public function partnerPreferences(): HasOne
    {
        return $this->hasOne(ProfilePartnerPreference::class, 'profile_id', 'id');
    }

    public function profileMelavaRegistrations(): HasMany
    {
        return $this->hasMany(ProfileMelavaRegistration::class, 'profile_id', 'id');
    }

    public function melavaRegistrations(): HasMany
    {
        return $this->profileMelavaRegistrations();
    }
}