<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CandidateRequest extends Model
{
    protected $table = 'candidate_requests';

    protected $fillable = [
        'name',
        'birth_date',
        'birth_time',
        'height',
        'marital_status',
        'education',
        'address',
        'subcast',
        'image',
        'occupation',
        'annual_income',
        'married_brothers',
        'unmarried_brothers',
        'married_sisters',
        'unmarried_sisters',
        'expectations',
        'terms_accepted',
        'status',
        'updated_by'
    ];
}