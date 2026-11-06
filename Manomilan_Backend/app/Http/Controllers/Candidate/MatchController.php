<?php

namespace App\Http\Controllers\Candidate;

use App\Profile;
use App\ProfileVisitor;
use App\UserInterest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

class MatchController extends CandidateController
{
    public function index(Request $request)
    {
        try {
            $user = $this->candidate($request);
            $me = $this->candidateProfile($request, true);
            $preference = $me->preference;

            $filters = $request->validate([
                'q' => ['nullable', 'string', 'max:100'],
                'age_min' => ['nullable', 'integer', 'min:18', 'max:100'],
                'age_max' => ['nullable', 'integer', 'min:18', 'max:100'],
                'religion' => ['nullable', 'string', 'max:255'],
                'caste' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:255'],
                'education' => ['nullable', 'string', 'max:255'],
                'profession' => ['nullable', 'string', 'max:255'],
                'marital_status' => ['nullable', 'string', 'max:50'],
                'sort' => ['nullable', 'in:best_match,newest,age_asc,age_desc'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:6', 'max:48'],
                'feed' => ['nullable', 'boolean'],
                'include_filters' => ['nullable', 'boolean'],
                'shuffle_seed' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
                'exclude_ids' => ['nullable', 'string', 'max:12000', function ($attribute, $value, $fail) {
                    if ($value !== null && collect(explode(',', $value))->contains(
                        fn ($id) => ! ctype_digit($id)
                    )) {
                        $fail('The excluded profile IDs must be comma-separated integers.');
                    }
                }],
            ]);

            $opposite = $this->profileGender($me) === 'groom' ? 'bride' : 'groom';
            $feed = (bool) ($filters['feed'] ?? false);
            $excludeIds = collect(explode(',', (string) ($filters['exclude_ids'] ?? '')))
                ->filter(fn ($id) => ctype_digit($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $query = Profile::query()
                ->with(['educations', 'employments', 'religionDetails', 'familyDetails'])
                ->where('groomorbride', ucfirst($opposite))
                ->where('status', 'active')
                ->where('id', '!=', $me->id)
                ->whereNotNull('full_name');

            $this->applyFilters($query, $filters);
            $sort = $filters['sort'] ?? 'best_match';
            $perPage = (int) ($filters['per_page'] ?? 12);
            $page = (int) ($filters['page'] ?? 1);
            $shuffleSeed = isset($filters['shuffle_seed']) ? (int) $filters['shuffle_seed'] : null;
            $this->applyRecommendationOrdering($query, $sort, $preference, $shuffleSeed);

            $total = (clone $query)->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            if ($feed) {
                if ($excludeIds->isNotEmpty()) {
                    $query->whereNotIn('id', $excludeIds);
                }
                $profiles = $query->limit($perPage + 1)->get();
                $hasMore = $profiles->count() > $perPage;
                $profiles = $profiles->take($perPage)->values();
                $page = min($page, $lastPage);
            } else {
                $page = min($page, $lastPage);
                $profiles = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();
                $hasMore = $page < $lastPage;
            }

            $profileIds = $profiles->pluck('id');

            $interests = UserInterest::where('user_id', $user->id)
                ->whereIn('profile_id', $profileIds)
                ->get()
                ->groupBy('profile_id');

            $matches = $profiles->map(function (Profile $profile) use ($me, $preference, $interests) {
                $match = $this->calculateMatch($me, $profile, $preference);
                $interestRow = $interests->get($profile->id, collect())->first();
                return array_merge($this->presentPublicProfile($profile), [
                    'match_percentage' => $match['score'],
                    'match_reasons' => $match['reasons'],
                    'interest_id' => $interestRow?->id,
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $matches->values(),
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => max(1, (int) ceil($total / $perPage)),
                        'per_page' => $perPage,
                        'total' => $total,
                        'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                        'to' => $total ? min($total, (($page - 1) * $perPage) + $matches->count()) : 0,
                        'has_more' => $hasMore,
                        'next_page' => $hasMore ? $page + 1 : null,
                    ],
                    'filters' => (bool) ($filters['include_filters'] ?? true)
                        ? $this->filterOptions($opposite)
                        : [],
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load matches right now.',
            ], 500);
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $user = $this->candidate($request);
            $me = $this->candidateProfile($request, true);

            $profile = Profile::with(['educations', 'employments', 'religionDetails', 'familyDetails'])
                ->where('status', 'active')
                ->find($id);

            if (! $profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'This profile is no longer available.',
                ], 404);
            }

            $match = $this->calculateMatch($me, $profile, $me->preference);

            $interest = UserInterest::where('user_id', $user->id)
                ->where('profile_id', $profile->id)
                ->first();

            if ($profile->id !== $me->id) {
                ProfileVisitor::firstOrCreate([
                    'profile_id' => $profile->id,
                    'visitor_id' => $user->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'profile' => array_merge($this->presentPublicProfile($profile), [
                        'match_percentage' => $match['score'],
                        'match_reasons' => $match['reasons'],
                        'interest_id' => $interest?->id,
                    ]),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load this profile right now.',
            ], 500);
        }
    }

    private function applyRecommendationOrdering(Builder $query, string $sort, $preference, ?int $shuffleSeed): void
    {
        if ($sort === 'newest') {
            $query->orderByRaw('DATE(profiles.created_at) DESC');
            if ($shuffleSeed !== null) {
                $query->orderByRaw("CRC32(CONCAT(?, ':', profiles.id)) ASC", [$shuffleSeed]);
            }
            $query->orderByDesc('profiles.id');
        } elseif ($sort === 'age_asc') {
            $query->orderBy('profiles.birth_date', 'desc');
        } elseif ($sort === 'age_desc') {
            $query->orderBy('profiles.birth_date', 'asc');
        } else {
            $query->orderByDesc('profiles.profile_completion');
            if ($preference) {
                $ageFrom = (int) ($preference->age_from ?? 0);
                $ageTo = (int) ($preference->age_to ?? 0);
                if ($ageFrom > 0 && $ageTo > 0) {
                    $youngestBirthDate = Carbon::now()->subYears($ageTo + 1)->addDay()->toDateString();
                    $oldestBirthDate = Carbon::now()->subYears($ageFrom)->toDateString();
                    $query->orderByRaw(
                        'CASE WHEN profiles.birth_date BETWEEN ? AND ? THEN 1 ELSE 0 END DESC',
                        [$youngestBirthDate, $oldestBirthDate]
                    );
                }

                if (filled($preference->preferred_religion)) {
                    $query->orderByRaw(
                        'CASE WHEN EXISTS (SELECT 1 FROM profile_religion_details AS recommendation_religion WHERE recommendation_religion.profile_id = profiles.id AND LOWER(recommendation_religion.religion) = LOWER(?)) THEN 1 ELSE 0 END DESC',
                        [$preference->preferred_religion]
                    );
                }

                if (filled($preference->preferred_caste)) {
                    $query->orderByRaw(
                        'CASE WHEN EXISTS (SELECT 1 FROM profile_religion_details AS recommendation_caste WHERE recommendation_caste.profile_id = profiles.id AND LOWER(recommendation_caste.caste) = LOWER(?)) THEN 1 ELSE 0 END DESC',
                        [$preference->preferred_caste]
                    );
                }

                if (filled($preference->preferred_education)) {
                    $education = '%'.trim($preference->preferred_education).'%';
                    $query->orderByRaw(
                        'CASE WHEN EXISTS (SELECT 1 FROM profile_education AS recommendation_education WHERE recommendation_education.profile_id = profiles.id AND (recommendation_education.education_level LIKE ? OR recommendation_education.degree_name LIKE ?)) THEN 1 ELSE 0 END DESC',
                        [$education, $education]
                    );
                }

                if (filled($preference->preferred_working_field)) {
                    $workingField = '%'.trim($preference->preferred_working_field).'%';
                    $query->orderByRaw(
                        'CASE WHEN EXISTS (SELECT 1 FROM profile_employment AS recommendation_employment WHERE recommendation_employment.profile_id = profiles.id AND (recommendation_employment.working_field LIKE ? OR recommendation_employment.profession LIKE ?)) THEN 1 ELSE 0 END DESC',
                        [$workingField, $workingField]
                    );
                }

                if (filled($preference->preferred_city)) {
                    $city = '%'.trim($preference->preferred_city).'%';
                    $query->orderByRaw(
                        'CASE WHEN profiles.birth_place LIKE ? OR EXISTS (SELECT 1 FROM profile_employment AS recommendation_city WHERE recommendation_city.profile_id = profiles.id AND recommendation_city.employment_city LIKE ?) THEN 1 ELSE 0 END DESC',
                        [$city, $city]
                    );
                }
            }

            $query->orderByRaw(
                "CASE WHEN JSON_LENGTH(profiles.photos) > 0 THEN 1 ELSE 0 END DESC"
            );
            if ($shuffleSeed !== null) {
                $query->orderByRaw("CRC32(CONCAT(?, ':', profiles.id)) ASC", [$shuffleSeed]);
            }
            $query->orderByRaw('DATE(profiles.updated_at) DESC')
                ->orderByRaw("CASE WHEN profiles.verification_status = 'Completed' THEN 1 ELSE 0 END DESC");
            $query->orderByDesc('profiles.updated_at');
        }

        $query->orderByDesc('profiles.id');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $term = '%'.trim($filters['q']).'%';
            $query->where(function (Builder $inner) use ($term) {
                foreach (['full_name', 'reg_id', 'phone', 'email', 'birth_place', 'about_me'] as $column) {
                    $inner->orWhere($column, 'like', $term);
                }
            });
        }

        if (! empty($filters['age_min'])) {
            $query->whereDate('birth_date', '<=', Carbon::now()->subYears((int) $filters['age_min'])->toDateString());
        }

        if (! empty($filters['age_max'])) {
            $query->whereDate('birth_date', '>=', Carbon::now()->subYears((int) $filters['age_max'] + 1)->addDay()->toDateString());
        }

        foreach (['religion', 'caste'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->whereHas('religionDetails', fn (Builder $details) => $details
                    ->where($filter, $filters[$filter]));
            }
        }

        if (! empty($filters['marital_status'])) {
            $query->where('marital_status', $filters['marital_status']);
        }

        if (! empty($filters['education'])) {
            $education = '%'.$filters['education'].'%';
            $query->whereHas('educations', fn (Builder $details) => $details
                ->where('education_level', 'like', $education)
                ->orWhere('degree_name', 'like', $education));
        }

        if (! empty($filters['profession'])) {
            $profession = '%'.trim($filters['profession']).'%';
            $query->whereHas('employments', fn (Builder $details) => $details
                ->where('profession', 'like', $profession)
                ->orWhere('working_field', 'like', $profession));
        }

        if (! empty($filters['city'])) {
            $city = '%'.trim($filters['city']).'%';
            $query->where(function (Builder $inner) use ($city) {
                $inner->where('birth_place', 'like', $city)
                    ->orWhere('addresses->current->city', 'like', $city)
                    ->orWhereHas('employments', fn (Builder $details) => $details
                        ->where('employment_city', 'like', $city));
            });
        }
    }

    private function filterOptions(string $opposite): array
    {
        $base = fn () => Profile::query()
            ->where('groomorbride', ucfirst($opposite))
            ->where('status', 'active');

        return [
            'religions' => \App\ProfileReligionDetail::query()
                ->whereHas('profile', fn (Builder $query) => $query
                    ->where('groomorbride', ucfirst($opposite))
                    ->where('status', 'active'))
                ->whereNotNull('religion')->where('religion', '!=', '')
                ->distinct()->orderBy('religion')->limit(120)->pluck('religion')->values(),
            'castes' => \App\ProfileReligionDetail::query()
                ->whereHas('profile', fn (Builder $query) => $query
                    ->where('groomorbride', ucfirst($opposite))
                    ->where('status', 'active'))
                ->whereNotNull('caste')->where('caste', '!=', '')
                ->distinct()->orderBy('caste')->limit(120)->pluck('caste')->values(),
            'cities' => $base()
                ->whereNotNull('birth_place')
                ->where('birth_place', '!=', '')
                ->distinct()
                ->orderBy('birth_place')
                ->limit(120)
                ->pluck('birth_place')
                ->values(),
            'educations' => \App\ProfileEducation::query()
                ->whereHas('profile', fn (Builder $query) => $query
                    ->where('groomorbride', ucfirst($opposite))
                    ->where('status', 'active'))
                ->whereNotNull('education_level')->where('education_level', '!=', '')
                ->distinct()->orderBy('education_level')->limit(120)->pluck('education_level')->values(),
            'marital_statuses' => $base()
                ->whereNotNull('marital_status')->where('marital_status', '!=', '')
                ->distinct()->orderBy('marital_status')->limit(120)->pluck('marital_status')->values(),
        ];
    }
}
