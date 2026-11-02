<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileFamilyDetail extends Model
{
    protected $fillable = [
        'profile_id',
        'father_name',
        'father_status',
        'father_native_place',
        'father_occupation',
        'father_contact',
        'mother_name',
        'mother_status',
        'mother_native_place',
        'mother_occupation',
        'mother_contact',
        'brothers_count',
        'married_brothers_count',
        'sisters_count',
        'married_sisters_count',
        'parents_intercaste_marriage',
        'parents_different_caste_details',
        'family_values',
        'family_financial_status',
        'family_annual_income',
    ];

    protected $casts = [
        'parents_intercaste_marriage' => 'boolean',
        'family_annual_income' => 'decimal:2',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }
}
