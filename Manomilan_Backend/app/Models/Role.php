<?php

namespace App;

use App\Models\ModulePermission;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['name', 'user_type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class);
    }

    public function isProtectedSystemRole(): bool
    {
        return preg_replace('/[^a-z]/', '', strtolower((string) $this->name)) === 'superadmin'
            || (string) $this->user_type === '00';
    }

    public function modulePermissions()
    {
        return $this->hasMany(ModulePermission::class, 'role_id');
    }
}
