<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileMelavaRegistration extends Model
{
    protected $fillable = [
        'profile_id',
        'melava_campaign_id',
        'is_booklet_req',
        'payment_screenshot',
        'payment_verified',
    ];

    protected $casts = [
        'is_booklet_req' => 'boolean',
        'payment_verified' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MelavaCampaign::class, 'melava_campaign_id', 'id');
    }
}
