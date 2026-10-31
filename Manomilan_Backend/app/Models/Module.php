<?php

namespace App;

use App\Models\ModulePermission;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = [
        'application_id',
        'module_name',
        'module_key',
        'description',
        'status',
        'route',
        'frontend_routes',
        'icon',
        'sort_order',
        'api_prefixes',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort_order' => 'integer',
            'api_prefixes' => 'array',
            'frontend_routes' => 'array',
        ];
    }

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'module_id');
    }

    public function permissions()
    {
        return $this->hasMany(ModulePermission::class, 'module_id');
    }
}