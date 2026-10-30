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
        return (int) $this->role_id === 1 || (string) $this->user_type === '00';
    }
}
