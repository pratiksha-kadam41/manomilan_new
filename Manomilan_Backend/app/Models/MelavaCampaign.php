<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MelavaCampaign extends Model
{
    protected $table = 'melava_campaigns';

    protected $fillable = [
        'melava_code',
        'title',
        'description',
        'start_date',
        'end_date',
        'currency',
        'registration_fee',
        'booklet_available',
        'booklet_fee',
        'qr_image_path',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'booklet_available' => 'boolean',
        'registration_fee' => 'decimal:2',
        'booklet_fee' => 'decimal:2',
    ];

    public function profileRegistrations(): HasMany
    {
        return $this->hasMany(ProfileMelavaRegistration::class, 'melava_campaign_id', 'id');
    }
}
