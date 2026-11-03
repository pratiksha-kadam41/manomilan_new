<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopEvent extends Model
{
    protected $fillable = [
        'workshop_code',
        'title',
        'description',
        'start_date',
        'end_date',
        'currency',
        'registration_fee',
        'qr_image_path',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'registration_fee' => 'decimal:2',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(WorkshopRegistration::class);
    }
}
