<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Requests\Candidate\RespondInterestRequest;
use App\Http\Requests\Candidate\SendInterestRequest;
use App\Profile;
use App\UserInterest;
use Illuminate\Http\Request;
use Throwable;

class InterestController extends CandidateController
{
    public function received(Request $request)
    {
        try {
            $user = $this->candidate($request);
            $me = $this->candidateProfile($request, true);
            $filters = $request->validate([
                'status' => ['nullable', 'in:pending,accepted,rejected,declined'],
                'q' => ['nullable', 'string', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $query = UserInterest::with(['user.profile', 'profile'])
                ->where('profile_id', $me->id);
            $this->applyStatus($query, $filters['status'] ?? null);

            if (! empty($filters['q'])) {
                $term = '%'.trim($filters['q']).'%';
                $query->whereHas('user.profile', function ($inner) use ($term) {
                    $inner->where('fullName', 'like', $term)
                        ->orWhere('profile_id', 'like', $term)
                        ->orWhere('profession', 'like', $term);
                });
            }

            $paginator = $query->latest()->paginate((int) ($filters['per_page'] ?? 10));
            $paginator->setCollection(
                $paginator->getCollection()
                    ->map(fn (UserInterest $interest) => $this->presentInterest($interest, 'received', $me, $me->preference))
                    ->filter(fn (array $item) => $item['profile'] !== null)
                    ->values()
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'interests' => $paginator->items(),
                    'pagination' => $this->pagination($paginator),
                    'counts' => $this->counts($me),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);
            return response()->json(['success' => false, 'message' => 'Unable to load received interests right now.'], 500);
        }
    }

    public function sent(Request $request)
    {
        try {
            $user = $this->candidate($request);
            $me = $this->candidateProfile($request, true);
            $filters = $request->validate([
                'q' => ['nullable', 'string', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $query = UserInterest::with('profile')
                ->where('user_id', $user->id);

            if (! empty($filters['q'])) {
                $term = '%'.trim($filters['q']).'%';
                $query->whereHas('profile', function ($inner) use ($term) {
                    $inner->where('fullName', 'like', $term)
                        ->orWhere('profile_id', 'like', $term)
                        ->orWhere('profession', 'like', $term);
                });
            }

            $paginator = $query->latest()->paginate((int) ($filters['per_page'] ?? 10));
            $paginator->setCollection(
                $paginator->getCollection()
                    ->map(fn (UserInterest $interest) => $this->presentInterest($interest, 'sent', $me, $me->preference))
                    ->filter(fn (array $item) => $item['profile'] !== null)
                    ->values()
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'interests' => $paginator->items(),
                    'pagination' => $this->pagination($paginator),
                            'counts' => $this->counts($me),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load suggested profiles right now.',
            ], 500);
        }
    }

    public function store(SendInterestRequest $request)
    {
        try {
            $user = $this->candidate($request);
            $me = $this->candidateProfile($request, true);
            $profile = Profile::where('active', 'active')->find($request->validated('to_profile_id'));

            if (! $profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'This profile is no longer available.',
                ], 404);
            }

            if ($profile->id === $me->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot send an interest to your own profile.',
                ], 422);
            }

            if ($profile->groomOrBride === $me->groomOrBride) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interests can only be sent to matching candidate profiles.',
                ], 422);
            }

            $existing = UserInterest::where('user_id', $user->id)
                ->where('profile_id', $profile->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This profile has already been sent.',
                ], 409);
            }

            $interest = UserInterest::create([
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'status' => 'pending',
            ]);

            $interest->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Profile suggested successfully.',
                'data' => [
                    'interest' => $this->presentInterest($interest, 'sent', $me, $me->preference),
                ],
            ], 201);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to suggest this profile right now.',
            ], 500);
        }
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem() ?: 0,
            'to' => $paginator->lastItem() ?: 0,
        ];
    }

    public function respond(RespondInterestRequest $request, int $id)
    {
        try {
            $me = $this->candidateProfile($request, true);
            $interest = UserInterest::with(['user.profile', 'profile'])
                ->where('id', $id)
                ->where('profile_id', $me->id)
                ->first();

            if (! $interest) {
                return response()->json(['success' => false, 'message' => 'Interest not found.'], 404);
            }

            $status = $request->validated('status') === 'declined' ? 'rejected' : $request->validated('status');
            $interest->status = $status;
            $interest->save();

            return response()->json([
                'success' => true,
                'message' => $status === 'accepted' ? 'Interest accepted.' : 'Interest declined.',
                'data' => ['interest' => $interest->fresh()],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);
            return response()->json(['success' => false, 'message' => 'Unable to update the interest right now.'], 500);
        }
    }

    private function applyStatus($query, ?string $status): void
    {
        if (! $status) {
            return;
        }
        $status = $status === 'declined' ? 'rejected' : $status;
        $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
    }

    private function counts(Profile $me): array
    {
        $received = UserInterest::where('profile_id', $me->id);
        $sent = UserInterest::where('user_id', $me->user_id);

        return [
            'received' => (clone $received)->count(),
            'received_pending' => (clone $received)->whereRaw('LOWER(status) = ?', ['pending'])->count(),
            'received_accepted' => (clone $received)->whereRaw('LOWER(status) = ?', ['accepted'])->count(),
            'received_declined' => (clone $received)->whereRaw('LOWER(status) = ?', ['rejected'])->count(),
            'sent' => (clone $sent)->count(),
            'sent_pending' => (clone $sent)->whereRaw('LOWER(status) = ?', ['pending'])->count(),
            'sent_accepted' => (clone $sent)->whereRaw('LOWER(status) = ?', ['accepted'])->count(),
            'sent_declined' => (clone $sent)->whereRaw('LOWER(status) = ?', ['rejected'])->count(),
        ];
    }
}
