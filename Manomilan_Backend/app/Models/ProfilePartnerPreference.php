<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilePartnerPreference extends Model
{
    protected $fillable = [
        'profile_id',
        'age_from',
        'age_to',
        'height_from',
        'height_to',
        'preferred_education',
        'preferred_working_field',
        'preferred_caste',
        'preferred_location',
        'other_expectations',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }
}
