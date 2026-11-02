<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileDocument extends Model
{
    protected $fillable = [
        'profile_id',
        'aadhaar_card',
        'pan_card',
        'passport',
        'birth_certificate',
        'address_proof',
        'education_certificate',
        'profession_proof',
        'income_proof',
        'caste_certificate',
        'medical_fitness_certificate',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }
}
