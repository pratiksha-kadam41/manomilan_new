<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MelavaCampaign extends Model
{
    protected $table = 'melava_campaigns';

    public function profileRegistrations(): HasMany
    {
        return $this->hasMany(ProfileMelavaRegistration::class, 'melava_campaign_id', 'id');
    }
}
