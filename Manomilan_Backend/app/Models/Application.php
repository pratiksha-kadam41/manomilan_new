<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'applications';

    protected $fillable = [
        'app_code',
        'app_name',
        'description',
        'current_version',
        'base_url',
        'api_key',
        'status',
    ];

    public function modules()
    {
        return $this->hasMany(Module::class, 'application_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'application_id');
    }
}