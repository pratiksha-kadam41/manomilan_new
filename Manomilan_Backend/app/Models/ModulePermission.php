<?php

namespace App\Models;

use App\Module;
use App\Role;
use Illuminate\Database\Eloquent\Model;

class ModulePermission extends Model
{
    protected $table = 'module_permissions';

    public const ACTIONS = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'import' => 'can_import',
        'export' => 'can_export',
        'approve' => 'can_approve',
        'manage' => 'can_manage',
    ];

    protected $fillable = [
        'role_id',
        'module_id',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
        'can_import',
        'can_export',
        'can_approve',
        'can_manage',
    ];

    protected function casts(): array
    {
        return array_fill_keys(array_values(self::ACTIONS), 'boolean');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
