<?php

namespace App\Services;

use App\Profile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class CandidateProfileService
{
    public function create(User $user, array $data): Profile
    {
        $profileData = [
            'groomorbride' => ucfirst($data['gender']),
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? $user->email,
            'phone' => $user->username,
            'birth_date' => $data['birth_date'],
            'created_by' => $user->id,
            'verification_status' => 'Pending',
            'status' => 'active',
        ];

        if (Schema::hasColumn('profiles', 'registration_type')) {
            $profileData['registration_type'] = 'quick';
        }

        return Profile::create($profileData);
    }

    public function update(Profile $profile, array $data): Profile
    {
        $core = Arr::only($data, ['full_name', 'birth_date', 'email']);
        if (array_key_exists('gender', $data)) {
            $core['groomorbride'] = ucfirst((string) $data['gender']);
        }
        if (array_key_exists('mobile', $data) && filled($data['mobile'])) {
            $core['phone'] = preg_replace('/\D+/', '', (string) $data['mobile']);
        }

        $profile->fill($core);

        $addresses = is_array($profile->addresses) ? $profile->addresses : [];
        $metadata = is_array($addresses['metadata'] ?? null) ? $addresses['metadata'] : [];
        $current = is_array($addresses['current'] ?? null) ? $addresses['current'] : [];
        $permanent = is_array($addresses['permanent'] ?? null) ? $addresses['permanent'] : [];
        $reference = is_array($addresses['reference'] ?? null) ? $addresses['reference'] : [];

        $addressMap = [
            'address' => ['current', 'address'],
            'permanent_address' => ['permanent', 'address'],
        ];
        foreach ($addressMap as $input => [$section, $key]) {
            if (! array_key_exists($input, $data)) {
                continue;
            }
            if ($section === 'current') {
                $current[$key] = $data[$input];
            } else {
                $permanent[$key] = $data[$input];
            }
        }

        $referenceMap = [
            'reference_name' => 'name',
            'reference_relation' => 'relation',
            'reference_contact' => 'contact',
            'reference_address' => 'address',
            'reference_email' => 'email',
        ];
        foreach ($referenceMap as $input => $key) {
            if (array_key_exists($input, $data)) {
                $reference[$key] = $data[$input];
            }
        }

        $metadataMap = [
            'source_of_information' => 'source_of_information',
            'marital_status' => 'marital_status',
            'has_medical_history' => 'has_medical_history',
            'profile_creator_name' => 'profile_creator_name',
            'profile_creator_relation' => 'profile_creator_relation',
            'birth_time_ampm' => 'birth_time_ampm',
            'having_specs_lenses' => 'having_specs_lenses',
            'horoscope_caste' => 'horoscope_caste',
            'horoscope_sub_caste' => 'horoscope_sub_caste',
            'horoscope_dob' => 'horoscope_dob',
        ];
        foreach ($metadataMap as $input => $key) {
            if (array_key_exists($input, $data)) {
                $metadata[$key] = $data[$input];
            }
        }

        if (array_key_exists('country', $data)) $current['country'] = $data['country'];
        if (array_key_exists('state', $data)) $current['state'] = $data['state'];
        if (array_key_exists('district', $data)) $current['district'] = $data['district'];
        if (array_key_exists('city', $data)) $current['city'] = $data['city'];
        if (array_key_exists('taluka', $data)) $current['taluka'] = $data['taluka'];
        if (array_key_exists('village', $data)) $current['village'] = $data['village'];
        if (array_key_exists('pincode', $data)) $current['pincode'] = $data['pincode'];

        $profile->addresses = [
            'current' => $current,
            'permanent' => $permanent,
            'reference' => $reference,
            'metadata' => $metadata,
        ];

        $profile->birth_place = $data['place_of_birth'] ?? $profile->birth_place;
        $profile->birth_time = $data['time_of_birth'] ?? $profile->birth_time;
        $profile->blood_group = $data['blood_group'] ?? $profile->blood_group;
        $profile->mother_tongue = $data['mother_tongue'] ?? $profile->mother_tongue;
        $profile->special_cases = $data['special_cases'] ?? $profile->special_cases;
        $profile->about_me = $data['about'] ?? $profile->about_me;
        $profile->profile_creator_name = $data['profile_creator_name'] ?? $profile->profile_creator_name;
        $profile->creator_relation = $data['profile_creator_relation'] ?? $profile->creator_relation;
        if (array_key_exists('has_medical_history', $data)) {
            $profile->has_medical_history = $data['has_medical_history'] === 'Yes';
        }
        $profile->has_spectacles = array_key_exists('having_specs_lenses', $data)
            ? ! in_array(strtolower((string) $data['having_specs_lenses']), ['', 'none', 'no'], true)
            : $profile->has_spectacles;
        if (array_key_exists('language', $data)) {
            $profile->languages = collect(explode(',', (string) $data['language']))
                ->map(fn (string $item) => trim($item))
                ->filter()
                ->values()
                ->all();
        }
        if (array_key_exists('personal_medical_history', $data)) $profile->personal_medical_history = $data['personal_medical_history'];
        if (array_key_exists('current_medical_problems', $data)) $profile->current_medical_problems = $data['current_medical_problems'];
        if (array_key_exists('medications', $data)) $profile->medications = $data['medications'];
        if (array_key_exists('height', $data)) $profile->height_cm = $this->heightInCentimetres($data['height']);
        if (array_key_exists('weight', $data)) $profile->weight_kg = $this->number($data['weight']);
        if (array_key_exists('monthly_income', $data)) $profile->monthly_income = $this->number($data['monthly_income']);
        if (array_key_exists('annual_income', $data)) $profile->annual_income = $this->number($data['annual_income']);

        $profile->save();

        $religion = $profile->religionDetails()->firstOrNew();
        $this->fillIfPresent($religion, $data, [
            'religion' => 'religion',
            'caste' => 'caste',
            'sub_caste' => 'sub_caste',
            'horoscope_caste' => 'horoscope',
            'horoscope_sub_caste' => 'sub_caste',
        ]);
        $religion->profile_id = $profile->id;
        $religion->save();

        $family = $profile->familyDetails()->firstOrNew();
        $this->fillIfPresent($family, $data, [
            'father_name' => 'father_name',
            'father_occupation' => 'father_occupation',
            'father_contact' => 'father_contact',
            'father_native_place' => 'father_native_place',
            'mother_name' => 'mother_name',
            'mother_occupation' => 'mother_occupation',
            'mother_contact' => 'mother_contact',
            'mother_native_place' => 'mother_native_place',
            'brothers' => 'brothers_count',
            'brothers_married' => 'married_brothers_count',
            'sisters' => 'sisters_count',
            'sisters_married' => 'married_sisters_count',
            'parents_different_caste' => 'parents_different_caste_details',
            'family_values' => 'family_values',
            'family_financial_status' => 'family_financial_status',
            'family_annual_income' => 'family_annual_income',
        ]);
        if (array_key_exists('father_alive', $data)) $family->father_status = $data['father_alive'] === 'Yes' ? 'Alive' : 'Deceased';
        if (array_key_exists('mother_alive', $data)) $family->mother_status = $data['mother_alive'] === 'Yes' ? 'Alive' : 'Deceased';
        if (array_key_exists('parents_intercaste_marriage', $data)) $family->parents_intercaste_marriage = $data['parents_intercaste_marriage'] === 'Yes';
        $family->profile_id = $profile->id;
        $family->save();

        $this->syncEducation($profile, $data);
        $this->syncEmployment($profile, $data);

        return $profile->fresh([
            'familyDetails', 'educations', 'employments', 'religionDetails',
            'profileDocuments', 'partnerPreferences', 'documentVerification',
        ]);
    }

    private function syncEducation(Profile $profile, array $data): void
    {
        $rows = $profile->educations()->orderBy('id')->get();
        $sets = [
            [
                'education_level' => $data['education'] ?? null,
                'degree_name' => $data['degree'] ?? null,
                'specialization' => $data['specialisation'] ?? null,
                'university_name' => $data['college'] ?? null,
                'additional_qualification' => $data['additional_education'] ?? null,
                'education_city' => $data['city_of_education'] ?? null,
            ],
            [
                'degree_name' => $data['degree_2'] ?? null,
                'specialization' => $data['specialisation_2'] ?? null,
                'additional_qualification' => $data['additional_education_2'] ?? null,
            ],
        ];

        foreach ($sets as $index => $values) {
            if (! collect($values)->contains(fn ($value) => filled($value))) continue;
            $row = $rows->get($index) ?: $profile->educations()->make();
            $row->fill(array_filter($values, fn ($value) => $value !== null));
            $row->profile_id = $profile->id;
            $row->save();
        }
    }

    private function syncEmployment(Profile $profile, array $data): void
    {
        $values = [
            'profession' => $data['profession'] ?? null,
            'working_field' => $data['field_of_working'] ?? null,
            'organisation_name' => $data['organisation'] ?? null,
            'designation' => $data['designation'] ?? null,
            'employment_city' => $data['professional_city'] ?? null,
            'employment_duration' => $data['length_of_employment'] ?? null,
            'monthly_income' => $this->number($data['monthly_income'] ?? null),
            'annual_income' => $this->number($data['annual_income'] ?? null),
            'income_currency' => $data['income_currency'] ?? 'INR',
        ];
        if (! collect($values)->except('income_currency')->contains(fn ($value) => filled($value))) return;
        $employment = $profile->employments()->latest('id')->first() ?: $profile->employments()->make();
        $employment->fill(array_filter($values, fn ($value) => $value !== null));
        $employment->profile_id = $profile->id;
        $employment->save();
    }

    private function fillIfPresent(object $model, array $data, array $map): void
    {
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $model->{$column} = $this->numberIfNumeric($data[$input], $column);
            }
        }
    }

    private function numberIfNumeric($value, string $column)
    {
        return in_array($column, [
            'brothers_count', 'married_brothers_count', 'sisters_count', 'married_sisters_count',
            'family_annual_income',
        ], true) ? $this->number($value) : $value;
    }

    private function number($value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function heightInCentimetres($value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        $text = strtolower(trim((string) $value));
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:ft|feet|\')\s*(?:(\d+(?:\.\d+)?)\s*(?:in|inches|"))?/', $text, $match)) {
            return round(((float) $match[1] * 12 + (float) ($match[2] ?? 0)) * 2.54, 2);
        }
        $number = $this->number($text);
        return $number !== null && $number < 12 ? round($number * 2.54, 2) : $number;
    }
}
