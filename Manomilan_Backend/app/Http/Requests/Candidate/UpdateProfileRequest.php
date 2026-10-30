<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $text = [];

        foreach ($this->except(['email', 'groomOrBride', 'birthDate']) as $key => $value) {
            if (is_string($value)) {
                $text[$key] = trim($value);
            }
        }

        $this->merge($text);
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', Rule::in(['groom', 'bride'])],
            'birth_date' => ['sometimes', 'required', 'date', 'before:today'],
            'email' => [
                'sometimes', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'religion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caste' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sub_caste' => ['sometimes', 'nullable', 'string', 'max:255'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'height' => ['sometimes', 'nullable', 'string', 'max:50'],
            'weight' => ['sometimes', 'nullable', 'string', 'max:50'],
            'having_specs_lenses' => ['sometimes', 'nullable', 'string', 'max:10'],
            'blood_group' => ['sometimes', 'nullable', 'string', 'max:10'],
            'mother_tongue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'language' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'permanent_address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'source_of_information' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education' => ['sometimes', 'nullable', 'string', 'max:255'],
            'degree' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'degree_2' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'specialisation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'college' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'additional_education' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'city_of_education' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'field_of_working' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'organisation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'professional_city' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'length_of_employment' => ['sometimes', 'nullable', 'string', 'max:255'],
            'monthly_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'annual_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hobbies' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'extra_curricular' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'achievements' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sports' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'cooking_skills' => ['sometimes', 'nullable', 'string', 'max:255'],
            'diet' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smoking' => ['sometimes', 'nullable', 'string', 'max:10'],
            'drinking' => ['sometimes', 'nullable', 'string', 'max:10'],
            'partying' => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_alive' => ['sometimes', 'nullable', 'string', 'max:10'],
            'father_occupation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'father_contact' => ['sometimes', 'nullable', 'string', 'max:30'],
            'mother_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mother_alive' => ['sometimes', 'nullable', 'string', 'max:10'],
            'mother_occupation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'mother_contact' => ['sometimes', 'nullable', 'string', 'max:30'],
            'brothers' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'brothers_married' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'sisters' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'sisters_married' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'family_values' => ['sometimes', 'nullable', 'string', 'max:255'],
            'family_financial_status' => ['sometimes', 'nullable', 'string', 'max:255'],
            'family_annual_income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'place_of_birth' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'time_of_birth' => ['sometimes', 'nullable', 'date_format:H:i'],
            'birth_time_ampm' => ['sometimes', 'nullable', Rule::in(['AM', 'PM'])],
            'rashi' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nakshatra' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gotra' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nadi' => ['sometimes', 'nullable', 'string', 'max:255'],
            'home_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'has_vehicle' => ['sometimes', 'nullable', 'string', 'max:10'],
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
