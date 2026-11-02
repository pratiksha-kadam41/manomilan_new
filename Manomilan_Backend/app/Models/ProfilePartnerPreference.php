<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilePartnerPreference extends Model
{
    protected $fillable = [
        'profile_id',
        'preferred_religion',
        'age_from',
        'age_to',
        'height_from',
        'height_to',
        'preferred_education',
        'preferred_working_field',
        'working_partner',
        'expected_income',
        'family_type',
        'financial_background',
        'family_values',
        'preferred_caste',
        'preferred_sub_caste',
        'preferred_location',
        'preferred_country',
        'preferred_state',
        'preferred_city',
        'other_expectations',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }
}
