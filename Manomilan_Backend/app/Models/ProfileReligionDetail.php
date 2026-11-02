<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileReligionDetail extends Model
{
    protected $fillable = [
        'profile_id',
        'religion',
        'caste',
        'sub_caste',
        'community',
        'horoscope',
        'horoscope_match_required',
        'manglik_status',
        'rashi',
        'nakshatra',
        'charan',
        'nadi',
        'gan',
        'gotra',
    ];

    protected $casts = [
        'horoscope_match_required' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }
}
