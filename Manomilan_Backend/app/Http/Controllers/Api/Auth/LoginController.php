<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($credentials['username']);

        try {
            $user = User::where('username', $login)
                ->orWhere('email', $login)
                ->first();

            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid username or password.',
                ], 401);
            }

            if ((string) $user->status === 'inactive' || (string) $user->status === '0') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact the administrator.',
                ], 403);
            }

            $user->tokens()->where('name', 'api-token')->delete();
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user->load('profile'),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to sign in right now. Please try again.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return response()->json(['success' => true, 'message' => 'Logged out successfully']);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to log out right now.',
            ], 500);
        }
    }
}
