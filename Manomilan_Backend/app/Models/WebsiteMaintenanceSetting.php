<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteMaintenanceSetting extends Model
{
    protected $table = 'website_maintenance_settings';

    protected $fillable = [
        'enabled',
        'title',
        'message',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
