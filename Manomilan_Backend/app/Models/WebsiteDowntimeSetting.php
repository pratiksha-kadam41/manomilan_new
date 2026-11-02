<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteDowntimeSetting extends Model
{
    protected $table = 'website_downtime_settings';

    protected $fillable = [
        'enabled',
        'title',
        'message',
        'starts_at',
        'ends_at',
        'show_countdown',
        'contact_email',
        'contact_phone',
        'show_contact',
        'display_location',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'show_countdown' => 'boolean',
            'show_contact' => 'boolean',
        ];
    }
}
