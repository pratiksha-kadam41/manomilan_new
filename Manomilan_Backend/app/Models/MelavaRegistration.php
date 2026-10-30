<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MelavaRegistration extends Model
{
    protected $table = 'melava_registrations';

    protected $fillable = [
        'fullName',
        'groomOrBride',
        'birthDate',
        'username',
        'source_of_information',
        'maritalStatus',
        'caste',
        'subCaste',
        'education',
        'profession',
        'city',
        'address',
        'height',
        'parents_contact',
        'fathersName',
        'mothersName',
        'noOfBrothers',
        'noOfSisters',
        'age_from',
        'age_to',
        'height_from',
        'height_to',
        'preferred_education',
        'preferred_working_field',
        'preferred_caste',
        'preferred_location',
        'other_expectations',
        'user_id',
        'profile_id',
        'is_existing_website_user',
        'melava_campaign_id',
        'melava_number',
        'melava_title',
        'registration_fee',
        'booklet_fee',
        'total_amount',
        'melava_batch',
        'melava_registered_at',
        'updated_by',
        'biodata',
        'birth_verification_doc',
        'candidate_photos',
        'link_token',
        'payment_screenshot',
        'payment_verified',
        'is_booklet_req',
        'booklet_payment_screenshot',
        'booklet_payment_verified',
    ];

    protected $casts = [
        'candidate_photos' => 'array',
        'payment_verified' => 'boolean',
        'booklet_payment_verified' => 'boolean',
        'is_booklet_req' => 'boolean',
    ];

    public function profile()
    {
        return $this->belongsTo(\App\Profile::class, 'profile_id', 'id');
    }
}