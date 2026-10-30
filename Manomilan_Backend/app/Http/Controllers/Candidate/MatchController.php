<?php

namespace App\Http\Controllers\Candidate;

use App\Profile;
use App\ProfileVisitor;
use App\UserInterest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                'marital_status' => ['nullable', 'string', 'max:50'],
                'sort' => ['nullable', 'in:best_match,newest,age_asc,age_desc'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:6', 'max:48'],
            ]);

            $opposite = $me->groomOrBride === 'groom' ? 'bride' : 'groom';

            $query = Profile::query()
                ->where('groomOrBride', $opposite)
                ->where('active', 'active')
                ->where('id', '!=', $me->id)
                ->whereNotNull('fullName');

            $this->applyFilters($query, $filters);

            $sort = $filters['sort'] ?? 'best_match';
            $perPage = (int) ($filters['per_page'] ?? 12);
            $page = (int) ($filters['page'] ?? 1);

            if ($sort === 'newest') {
                $query->orderByDesc('id');
            } elseif ($sort === 'age_asc') {
                $query->orderBy('birthDate', 'desc');
            } elseif ($sort === 'age_desc') {
                $query->orderBy('birthDate', 'asc');
            }

            if ($sort !== 'best_match') {
                $total = (clone $query)->count();
                $lastPage = max(1, (int) ceil($total / $perPage));
                $page = min($page, $lastPage);
                $profiles = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();
            } else {
                $profiles = $query->limit(500)->get();
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
                    'interest_id' => $interestRow?->id,
                ]);
            });

            if ($sort === 'best_match') {
                $matches = $matches->sortByDesc('match_percentage')->values();
                $total = $matches->count();
                $lastPage = max(1, (int) ceil($total / $perPage));
                $page = min($page, $lastPage);
                $items = $matches->forPage($page, $perPage)->values();
            } else {
                $matches = $matches->values();
                $items = $matches;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $items,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $total,
                        'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                        'to' => $total ? min($total, $page * $perPage) : 0,
                    ],
                    'filters' => $this->filterOptions($opposite),
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

            $profile = Profile::where('active', 'active')->find($id);

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

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $term = '%'.trim($filters['q']).'%';
            $query->where(function (Builder $inner) use ($term) {
                foreach (['fullName', 'profile_id', 'registration_number', 'profession', 'organisationWorkingIn', 'professionalCity', 'location'] as $column) {
                    $inner->orWhere($column, 'like', $term);
                }
            });
        }

        if (! empty($filters['age_min'])) {
            $query->whereDate('birthDate', '<=', Carbon::now()->subYears((int) $filters['age_min'])->toDateString());
        }

        if (! empty($filters['age_max'])) {
            $query->whereDate('birthDate', '>=', Carbon::now()->subYears((int) $filters['age_max'] + 1)->addDay()->toDateString());
        }

        foreach (['religion', 'caste', 'marital_status'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (! empty($filters['education'])) {
            $query->where('education', 'like', '%'.$filters['education'].'%');
        }

        if (! empty($filters['city'])) {
            $city = '%'.trim($filters['city']).'%';
            $query->where(function (Builder $inner) use ($city) {
                $inner->where('location', 'like', $city)
                    ->orWhere('professionalCity', 'like', $city)
                    ->orWhere('cityOfEducation', 'like', $city);
            });
        }
    }

    private function filterOptions(string $opposite): array
    {
        $base = fn () => Profile::query()
            ->where('groomOrBride', $opposite)
            ->where('active', 'active');

        $values = function (string $column) use ($base) {
            return $base()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->limit(120)
                ->pluck($column)
                ->values();
        };

        return [
            'religions' => $values('religion'),
            'castes' => $values('caste'),
            'cities' => $base()
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->distinct()
                ->orderBy('location')
                ->limit(120)
                ->pluck('location')
                ->values(),
            'educations' => $values('education'),
            'marital_statuses' => $values('maritalStatus'),
        ];
    }
}
