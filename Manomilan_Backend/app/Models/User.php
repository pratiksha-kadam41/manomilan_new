<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'status',
        'user_type',
        'role_id',
        'profile_id',
        'is_password_changed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_password_changed' => 'boolean',
        ];
    }

    public function profile()
    {
        return $this->belongsTo(\App\Profile::class, 'profile_id');
    }

    public function role()
    {
        return $this->belongsTo(\App\Role::class, 'role_id');
    }

    public function hasModulePermission(string $moduleKey, string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $actionColumn = ModulePermission::ACTIONS[$permission] ?? null;
        if (! $actionColumn) {
            return false;
        }

        return ModulePermission::query()
            ->where('role_id', $this->role_id)
            ->whereHas('role', fn ($query) => $query->where('is_active', true))
            ->whereHas('module', fn ($query) => $query
                ->where('module_key', $moduleKey)
                ->where('status', true))
            ->where(fn ($query) => $query
                ->where($actionColumn, true)
                ->orWhere('can_manage', true))
            ->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->is_active === true
            && preg_replace('/[^a-z]/', '', strtolower((string) $this->role?->name)) === 'superadmin'
            && (string) $this->role?->user_type === '00';
    }

    public function portal(): string
    {
        return (string) ($this->role?->user_type ?? $this->user_type) === '21'
            ? 'candidate'
            : 'admin';
    }

    public function preference()
    {
        return $this->hasOne(\App\Preference::class, 'user_id');
    }

    public function interests()
    {
        return $this->hasMany(\App\UserInterest::class, 'user_id');
    }

    public function receivedInterests()
    {
        return $this->hasMany(\App\UserInterest::class, 'profile_id');
    }

    public function isAdmin(): bool
    {
        return $this->portal() === 'admin';
    }
}
