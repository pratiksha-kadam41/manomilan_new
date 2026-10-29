<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuccessStory extends Model
{
    use SoftDeletes;

    protected $table = 'success_stories';

    protected $fillable = [
        'groom_name',
        'bride_name',
        'marriage_date',
        'city',
        'state',
        'groom_photo',
        'bride_photo',
        'couple_photo',
        'wedding_photo',
        'story_title',
        'short_description',
        'full_story',
        'family_message',
        'rating',
        'is_featured',
        'display_order',
        'status',
        'created_by'
    ];

    protected $casts = [
        'marriage_date' => 'date',
        'is_featured' => 'boolean',
    ];
}