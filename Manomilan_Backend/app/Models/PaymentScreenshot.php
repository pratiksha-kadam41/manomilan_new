<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentScreenshot extends Model
{
    protected $fillable = [
        'profile_id',
        'screenshot_path',
        'payment_verified',
    ];

    protected $casts = [
        'payment_verified' => 'boolean',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
