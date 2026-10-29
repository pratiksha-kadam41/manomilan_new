<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MelavaPendingRegistration extends Model
{
    protected $table = 'melava_pending_registrations';

    protected $fillable = [
        'token',
        'mobile',
        'is_existing_website_user',
        'user_id',
        'profile_id',
        'registration_data',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'is_existing_website_user' => 'boolean',
        'expires_at' => 'datetime',
    ];
}