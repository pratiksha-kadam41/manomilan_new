<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name_snapshot',
        'action',
        'module_id',
        'module_name_snapshot',
        'entity_type',
        'entity_id',
        'candidate_id',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function module()
    {
        return $this->belongsTo(\App\Module::class);
    }
}
