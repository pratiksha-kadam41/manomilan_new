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
            ]);

            $opposite = $this->profileGender($me) === 'groom' ? 'bride' : 'groom';

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

            if ($sort === 'newest') {
                $query->orderByDesc('id');
            } elseif ($sort === 'age_asc') {
                $query->orderBy('birth_date', 'desc');
            } elseif ($sort === 'age_desc') {
                $query->orderBy('birth_date', 'asc');
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
