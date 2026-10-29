<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'photo_upload',
        'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
