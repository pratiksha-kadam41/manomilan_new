<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    protected $table = 'website_settings';

    protected $fillable = [
        'upcoming_events_enabled',
        'upcoming_events_label',
        'upcoming_events_icon',
        'upcoming_events_bg_color',
        'upcoming_events_text_color',
        'upcoming_events_position',
        'upcoming_events_url',
        'upcoming_events_open_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'upcoming_events_enabled' => 'boolean',
            'upcoming_events_open_new_tab' => 'boolean',
        ];
    }
}
