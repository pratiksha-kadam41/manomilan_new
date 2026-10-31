<?php

namespace App\Models;

use App\Module;
use App\Role;
use Illuminate\Database\Eloquent\Model;

class ModulePermission extends Model
{
    protected $table = 'module_permissions';

    protected $fillable = ['module_id', 'permission_key'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_module_permissions',
            'module_permission_id',
            'role_id'
        )->withTimestamps();
    }
}
