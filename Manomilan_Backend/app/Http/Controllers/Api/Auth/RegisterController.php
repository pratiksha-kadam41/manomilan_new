<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => preg_replace('/\D+/', '', (string) $request->input('phone', $request->input('username'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'digits:10', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        try {
            $user = DB::transaction(function () use ($data) {
                return User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'username' => $data['phone'],
                    'user_type' => '21',
                    'role_id' => 3,
                    'password' => $data['password'],
                    'is_password_changed' => 1,
                    'status' => 'active',
                ]);
            });

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'token' => $token,
                'user' => $user,
            ], 201);
        } catch (QueryException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'An account with this email or mobile number already exists.',
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again later.',
            ], 500);
        }
    }
}
