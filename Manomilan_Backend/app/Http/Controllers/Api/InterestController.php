<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Profile;
use App\UserInterest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class InterestController extends Controller
{
    public function sent(Request $request)
    {
        $user = $request->user();

        $interests = UserInterest::with(['profile.user'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        $interests->getCollection()->transform(function (UserInterest $interest) {
            return [
                'id' => $interest->id,
                'status' => $interest->status,
                'created_at' => optional($interest->created_at)->toIso8601String(),
                'profile' => $this->presentProfile($interest->profile),
            ];
        });

        return response()->json(['success' => true, 'data' => $interests]);
    }

    public function received(Request $request)
    {
        $user = $request->user();
        if (! $user->profile_id) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $interests = UserInterest::with(['user.profile'])
            ->where('profile_id', $user->profile_id)
            ->latest()
            ->paginate((int) $request->input('per_page', 20));
        $interests->getCollection()->transform(fn (UserInterest $interest) => [
            'id' => $interest->id,
            'status' => $interest->status,
            'created_at' => optional($interest->created_at)->toIso8601String(),
            'profile' => $this->presentProfile($interest->user?->profile),
        ]);
        return response()->json(['success' => true, 'data' => $interests]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'to_profile_id' => ['required', 'integer', 'exists:profiles,id'],
        ]);

        $user = $request->user();

        if (! $user->profile_id) {
            return response()->json([
                'success' => false,
                'message' => 'Complete your profile before sending an interest.',
            ], 422);
        }

        if ((int) $user->profile_id === (int) $data['to_profile_id']) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot send an interest to your own profile.',
            ], 422);
        }

        try {
            $interest = DB::transaction(function () use ($user, $data) {
                return UserInterest::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'profile_id' => $data['to_profile_id'],
                    ],
                    ['status' => 'pending']
                );
            });

            return response()->json([
                'success' => true,
                'message' => $interest->wasRecentlyCreated ? 'Interest sent successfully.' : 'Interest already sent.',
                'data' => $interest,
            ], $interest->wasRecentlyCreated ? 201 : 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send the interest right now.',
            ], 500);
        }
    }

    public function respond(Request $request, $id)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'rejected'])]]);
        $interest = UserInterest::findOrFail($id);
        $user = $request->user();
        if (! $user->isAdmin() && (int) $interest->profile_id !== (int) $user->profile_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        $interest->status = $data['status'];
        $interest->save();
        return response()->json(['success' => true, 'data' => $interest]);
    }

    private function presentProfile(?Profile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'full_name' => $profile->fullName,
            'photo_url' => $profile->profilePhoto ? url(ltrim($profile->profilePhoto, '/')) : '',
            'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
            'city' => $profile->location ?: $profile->professionalCity ?: $profile->cityOfEducation,
            'education' => $profile->education,
            'profession' => $profile->profession,
            'gender' => $profile->groomOrBride,
        ];
    }

    private function emptyPaginator(): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 20,
            'total' => 0,
            'from' => null,
            'to' => null,
        ];
    }
}
