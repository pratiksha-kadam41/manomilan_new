<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = [
        'application_id',
        'module_name',
        'description',
        'status',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'module_id');
    }
}