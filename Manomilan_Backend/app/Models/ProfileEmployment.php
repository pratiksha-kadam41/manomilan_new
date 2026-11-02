<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileEmployment extends Model
{
    protected $table = 'profile_employment';

    protected $fillable = [
        'profile_id',
        'employment_type',
        'profession',
        'working_field',
        'organisation_name',
        'designation',
        'employment_city',
        'employment_duration',
        'monthly_income',
        'annual_income',
        'income_currency',
        'original_monthly_income',
        'original_annual_income',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'annual_income' => 'decimal:2',
        'original_monthly_income' => 'decimal:2',
        'original_annual_income' => 'decimal:2',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }
}
