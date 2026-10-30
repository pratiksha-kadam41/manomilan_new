<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\PaymentScreenshot;
use App\MelavaRegistration;
use App\Http\Controllers\Controller;

class Profile extends Model
{

    protected static function boot()
    {
        parent::boot();

        // Generate registration_number and profile_id
        static::creating(function ($profile) {

            // Priority: Model Attribute > Session > Default
            $type = $profile->registration_type ?: session('registration_type', 'quick');

            if ($type === 'melava') {

                $prefix = 'MM3-';

                // Find the highest existing Melava registration number
                $lastProfile = self::where('registration_number', 'like', $prefix . '%')
                    ->orderByRaw(
                        'CAST(SUBSTRING(registration_number, ' .
                        (strlen($prefix) + 1) .
                        ') AS UNSIGNED) DESC'
                    )
                    ->first();

                $nextNumber = 1;

                if (
                    $lastProfile &&
                    preg_match('/^' . preg_quote($prefix, '/') . '(?:B|G)?(\d+)$/', $lastProfile->registration_number, $matches)
                ) {
                    $nextNumber = (int) $matches[1] + 1;
                }

                $profile->registration_number = $prefix .
                    str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

                $profile->profile_id = $profile->registration_number;

            } else {

                // Quick registration number
                $prefix = 'AMM2026';

                // Find the highest existing quick registration number
                $lastProfile = self::where('registration_number', 'like', $prefix . '%')
                    ->orderByRaw(
                        'CAST(SUBSTRING(registration_number, ' .
                        (strlen($prefix) + 1) .
                        ') AS UNSIGNED) DESC'
                    )
                    ->first();

                $nextNumber = 1;

                if (
                    $lastProfile &&
                    preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastProfile->registration_number, $matches)
                ) {
                    $nextNumber = (int) $matches[1] + 1;
                }

                $profile->registration_number = $prefix .
                    str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

                $profile->profile_id = $profile->registration_number;
            }
        });


        // Generate serial_no for the current Melava batch
        static::saving(function ($profile) {

            // Only generate a serial number for a Melava registration
            // belonging to the current batch, if serial_no is empty.
            //
            // "existing" covers candidates who were already registered on
            // the website (e.g. Melava 2) and have now registered for the
            // current batch. Their controllers clear the stale serial so it
            // is regenerated here with the correct age-group-wise prefix.
            if (
                !in_array($profile->registration_type, ['melava', 'existing'], true) ||
                $profile->melava_batch !== Controller::CURRENT_MELAVA_BATCH ||
                !empty($profile->serial_no)
            ) {
                return;
            }

            // Ensure birth date is available and valid
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
        return strpos($this->profile_id, 'MM3-') === 0 && $this->melava_batch === 'october_2026';
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
        'profile_id',
        'serial_no',
        'registration_number',
        'registration_type',
        'melava_batch',
        'email',
        'phone',
        'source_of_information',
        // Basic Details
        'groomOrBride',
        'fullName',
        'profilePhoto',
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
        // Hobbies and Extra Curriculars
        'hobbies',
        'extraCurricularActivities',
        'achievements',
        'sportsAndFitnessLikings',
        'cookingSkills',
        // Habits and Lifestyle
        'diet',
        'smoking',
        'smokingFrequency',
        'drinking',
        'drinkingFrequency',
        'partying',
        'activeOnSocialSites',
        'instagram', // not updated in database
        'facebook', // not updated in database
        // Medical Details
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
        'rashi',
        'nakshatra',
        'charan',
        'gan',
        'gotra',
        'nadi',
        'matchPatrika',
        'kundali',
        // Investment Details
        'homeStatus',
        'ownedRealEstate',
        'hasVehicle',
        'vehicleType',
        'otherInvestments',
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
        // Meeting Schedule Details
        'branch',
        'location',
        'meeting_date',
        'day',
        'meeting_time',
        'remarks',
        'spoke_to',
        // Non-fillable by default (status columns) — explicitly added for Melava flow
        'profileVerificationStatus',
        'profile_completion',
        'active',
    ];

    protected $attributes = [
        'profileVerificationStatus' => 'Pending',
        'active' => 'active',
        'profile_completion' => 0,
    ];

    /**
     * The attributes that should be cast to native types.
     * ✅ ADD THIS ENTIRE BLOCK
     * @var array
     */
    protected $casts = [
        'additional_photos' => 'array',
    ];

    public function syncDocumentVerificationStatus(): bool
    {
        $verification = DocumentVerification::where('profile_id', $this->id)->first();
        $normalizedStatus = static function ($status) {
            return strtolower(trim((string) $status));
        };

        $completed = $verification
            && $normalizedStatus($verification->identificationProof_status) === 'verified'
            && $normalizedStatus($verification->birthDateProof_status) === 'verified';

        $status = $completed ? 'Completed' : 'Pending';
        if ($this->profileVerificationStatus !== $status) {
            $this->profileVerificationStatus = $status;
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
     * social handles, ...) are only counted when they apply to the candidate,
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
            // Hobbies and Extra Curriculars
            'hobbies',
            'extraCurricularActivities',
            'achievements',
            'sportsAndFitnessLikings',
            'cookingSkills',
            'activeOnSocialSites',
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
        if ($this->activeOnSocialSites === 'Yes') {
            $conditionalFields[] = 'instagram';
            $conditionalFields[] = 'facebook';
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

        $this->profile_completion = $percentage;
        $this->save();

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
            'hobbies',
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

    public function melavaRegistrations()
    {
        return $this->hasMany(MelavaRegistration::class, 'profile_id');
    }
}