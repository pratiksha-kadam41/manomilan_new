<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = [
        'profilePhoto',
        'melavaNo',
        'name',
        'address',
        'city',
        'taluka',
        'dist',
        'contactPersonName1',
        'contactPersonMobile1',
        'contactPersonName2',
        'contactPersonMobile2',
        'contactPersonName3',
        'contactPersonMobile3',
        'email',
        'education',
        'gender',
        'DOB',
        'time',
        'place',
        'height',
        'potJat',
        'parentName',
        'job',
        'monthlyIncome',
        'expectation',
        'extraInformation',
        'location',
        'mobileNo',
        'status',
        'updated_by'
    ];
}
