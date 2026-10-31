<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ChangePasswordController extends Controller
{
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $rules = [
            'new_password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ];

        if ($user->is_password_changed) {
            $rules['current_password'] = ['required', 'string'];
        } else {
            $rules['current_password'] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        if ($user->is_password_changed && ! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($user, $data) {
                $user->password = $data['new_password'];
                $user->is_password_changed = 1;
                $user->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to change the password right now.',
            ], 500);
        }
    }
}
