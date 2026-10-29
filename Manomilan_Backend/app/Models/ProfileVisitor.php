<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProfileVisitor extends Model
{
    protected $table = 'profile_visitors';

    protected $fillable = [
        'profile_id',
        'visitor_id',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function visitor()
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }
}
