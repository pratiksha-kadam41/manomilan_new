<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileEducation extends Model
{
    protected $table = 'profile_education';

    protected $fillable = [
        'profile_id',
        'education_level',
        'degree_name',
        'specialization',
        'university_name',
        'college_name',
        'passing_year',
        'percentage',
        'grade',
        'education_city',
        'additional_qualification',
        'education_certificate',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }
}
