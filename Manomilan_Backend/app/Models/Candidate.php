<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = [
    'name', 'address', 'candidateType', 'stdCode', 'stdNumber', 'mobileNumber', 'email', 'education',
    'jobType', 'workPlace', 'monthlyIncome', 'DOB', 'birthTime', 'birthPlace', 'height', 'feet',
    'inch', 'weight', 'kgGram', 'subCast', 'parentName', 'parentJobType', 'parentMonthlyIncome',
    'isGlasses', 'isHoroscope', 'isNonJain', 'nonJainReligion', 'whoNonJain', 'expectation',
    'extraName', 'extraCity', 'extraMobileNo', 'skinColor', 'bloodGroup', 'motherTongue', 'otherTongue',
    'bortherMarriedNo', 'bortherNotMarriedNo', 'sisterMarriedNo', 'sisterNotMarriedNo',
    'companyName', 'companyDesignationAndNumber', 'hobbies', 'isAcknowledge'
];
}
