<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PreferenceController extends Controller
{
    private const FIELDS = [
        'preferred_religion', 'preferred_caste', 'preferred_sub_caste',
        'age_from', 'age_to', 'height_from', 'height_to',
        'preferred_education', 'working_partner', 'preferred_working_field',
        'expected_income', 'family_type', 'financial_background', 'family_values',
        'preferred_location', 'preferred_country', 'preferred_state',
        'preferred_city', 'other_expectations',
    ];

    public function show(Request $request)
    {
        $preference = Preference::where('user_id', $request->user()->id)->first();

        return response()->json(['success' => true, 'data' => $preference]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'age_from' => ['nullable', 'integer', 'min:18', 'max:100'],
            'age_to' => ['nullable', 'integer', 'min:18', 'max:100', 'gte:age_from'],
            'preferred_religion' => ['nullable', 'string', 'max:255'],
            'preferred_caste' => ['nullable', 'string', 'max:255'],
            'preferred_sub_caste' => ['nullable', 'string', 'max:255'],
            'preferred_education' => ['nullable', 'string', 'max:255'],
            'preferred_working_field' => ['nullable', 'string', 'max:255'],
            'preferred_location' => ['nullable', 'string', 'max:255'],
            'preferred_country' => ['nullable', 'string', 'max:255'],
            'preferred_state' => ['nullable', 'string', 'max:255'],
            'preferred_city' => ['nullable', 'string', 'max:255'],
            'other_expectations' => ['nullable', 'string', 'max:5000'],
        ]);

        $payload = $request->only(self::FIELDS);

        try {
            $preference = DB::transaction(function () use ($request, $payload) {
                return Preference::updateOrCreate(
                    ['user_id' => $request->user()->id],
                    $payload
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Preferences saved successfully.',
                'data' => $preference,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save preferences right now.',
            ], 500);
        }
    }
}
