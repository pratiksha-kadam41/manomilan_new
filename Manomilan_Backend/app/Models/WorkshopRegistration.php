<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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

    'remarks'

];
}
