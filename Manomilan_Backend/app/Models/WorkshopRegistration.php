<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'mobile_number',
        'whatsapp_number',
        'date_of_birth',
        'education',
        'city',
        'district',
        'marital_status',
        'parent_name',
        'parent_contact_number',
        'payment_screenshot',
        'transaction_id',
        'consent',
        'status',
        'remarks',
        'workshop_event_id',
        'registration_fee',
        'registration_currency',
    ];

    public function workshopEvent(): BelongsTo
    {
        return $this->belongsTo(WorkshopEvent::class);
    }
}
