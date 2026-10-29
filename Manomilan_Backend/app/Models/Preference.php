<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'preferred_religion', // not updated in database
        'preferred_caste', // not updated in database
        'preferred_sub_caste',
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

        // 'preferred_body_type',
        'preferred_location',
        'preferred_country',
        'preferred_state',
        'preferred_city',
        'other_expectations',
    ];

    /**
     * Get the user that owns the preference.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}