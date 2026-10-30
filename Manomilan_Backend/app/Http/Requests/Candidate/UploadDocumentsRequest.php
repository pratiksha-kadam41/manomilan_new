<?php

namespace App\Http\Requests\Candidate;

use App\Http\Controllers\Candidate\CandidateController;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $rules = [];

        foreach (CandidateController::documentKeys() as $key) {
            $rules[$key] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasFile = false;

                foreach (CandidateController::documentKeys() as $key) {
                    if ($this->hasFile($key)) {
                        $hasFile = true;
                        break;
                    }
                }

                if (! $hasFile) {
                    $validator->errors()->add('documents', 'Choose at least one document to upload.');
                }
            },
        ];
    }
}
