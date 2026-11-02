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
                $matchCity = static function (Builder $locationQuery, string $column) use ($keyword): void {
                    if (strlen($keyword) <= 2) {
                        $locationQuery->whereRaw('LOWER(TRIM('.$column.')) = ?', [strtolower($keyword)]);
                    } else {
                        $locationQuery->where($column, 'LIKE', '%'.$keyword.'%');
                    }
                };

                $query->orWhereHas('employments', fn (Builder $employment) => $matchCity($employment, 'employment_city'))
                    ->orWhereHas('educations', fn (Builder $education) => $matchCity($education, 'education_city'));
            }
        });
    }
}
