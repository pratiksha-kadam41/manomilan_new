<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'user_type'];

    public function users()
    {
        return $this->hasMany(\App\Models\User::class);
    }
}
