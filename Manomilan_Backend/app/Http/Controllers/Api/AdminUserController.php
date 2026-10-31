<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->isSuperAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Super administrator access is required.',
            ], 403);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $eligibleUsers = User::query()
            ->where(function ($query) {
                $query->whereNull('user_type')->orWhere('user_type', '!=', '00');
            })
            ->where(function ($query) {
                $query->whereNull('role_id')->orWhere('role_id', '!=', 1);
            });
        $stats = (clone $eligibleUsers)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'inactive' THEN 1 ELSE 0 END) as inactive
            ")
            ->first();

        $users = (clone $eligibleUsers)
            ->with(['profile:id,fullName,serial_no,registration_number'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($search) use ($term) {
                    $search->where('name', 'like', '%' . $term . '%')
                        ->orWhere('username', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%')
                        ->orWhereHas('profile', function ($profile) use ($term) {
                            $profile->where('fullName', 'like', '%' . $term . '%')
                                ->orWhere('serial_no', 'like', '%' . $term . '%')
                                ->orWhere('registration_number', 'like', '%' . $term . '%');
                        });
                });
            })
            ->latest('users.created_at')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'profile' => $user->profile ? [
                    'full_name' => $user->profile->fullName,
                    'serial_no' => $user->profile->serial_no,
                    'registration_number' => $user->profile->registration_number,
                ] : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => $users,
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'active' => (int) ($stats->active ?? 0),
                'inactive' => (int) ($stats->inactive ?? 0),
            ],
        ]);
    }

    public function resetPassword(Request $request, int $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Super administrator access is required.',
            ], 403);
        }

        $user = User::query()
            ->whereKey($id)
            ->where(function ($query) {
                $query->whereNull('user_type')->orWhere('user_type', '!=', '00');
            })
            ->where(function ($query) {
                $query->whereNull('role_id')->orWhere('role_id', '!=', 1);
            })
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate account not found.',
            ], 404);
        }

        $temporaryPassword = 'password';

        try {
            DB::transaction(function () use ($user, $temporaryPassword) {
                $user->password = $temporaryPassword;
                $user->is_password_changed = false;
                $user->save();
            });
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to reset this password right now.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'The default password was assigned. The account holder must change it after signing in.',
            'data' => [
                'user_id' => $user->id,
                'default_password' => $temporaryPassword,
            ],
        ]);
    }

    private function isSuperAdmin(Request $request): bool
    {
        return $request->user() && (string) $request->user()->user_type === '00';
    }
}
