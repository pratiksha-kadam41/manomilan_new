<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class NriProfileScope
{
    private const CITY_KEYWORDS = [
        'USA', 'US', 'UK', 'United Kingdom', 'England', 'Scotland', 'Australia',
        'Canada', 'Dubai', 'UAE', 'Singapore', 'Germany', 'France', 'Denmark',
        'Sweden', 'New Zealand', 'Malaysia', 'Netherlands', 'Japan', 'Virginia',
        'Melbourne', 'Copenhagen', 'London', 'Chicago', 'Texas', 'Seattle',
        'Amsterdam', 'Winnipeg', 'NJ', 'California', 'Dallas', 'Austin',
        'Arizona', 'Hartford', 'Tokyo', 'Kuala Lumpur', 'Glasgow',
    ];

    public static function apply(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            foreach (self::CITY_KEYWORDS as $keyword) {
                if (strlen($keyword) <= 2) {
                    $query->orWhereRaw(
                        'profiles.professionalCity REGEXP ?',
                        ['\\b' . $keyword . '\\b'],
                    );
                } else {
                    $query->orWhere('profiles.professionalCity', 'LIKE', '%' . $keyword . '%');
                }
            }
        });
    }
}
