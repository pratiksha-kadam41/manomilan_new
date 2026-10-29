<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'candidate_id',
        'place',
        'date',
        'time_slot',
        'spoke_to_matched_parents',
        'spoke_to_candidate',
        'other_candidate_id',
        'day',
        'spoke_to_matched_profile',
        'spoke_to_cand_parents',
        'remark',
    ];
}
