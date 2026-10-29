<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    public function resetPasswordList(Request $request)
    {
        // Only Super Admin can access this
        if (Auth::user()->user_type !== '00') {
            return redirect()->route('home')->with('error', 'Unauthorized access.');
        }

        $q = $request->query('q');

        $users = User::with('profile')
            ->when($q, function ($query) use ($q) {
                $query->where(function($subQuery) use ($q) {
                    $subQuery->where('name', 'like', "%{$q}%")
                             ->orWhere('username', 'like', "%{$q}%")
                             ->orWhere('email', 'like', "%{$q}%")
                             ->orWhereHas('profile', function($profileQuery) use ($q) {
                                 $profileQuery->where('profile_id', 'like', "%{$q}%")
                                              ->orWhere('serial_no', 'like', "%{$q}%")
                                              ->orWhere('serial_no', strtoupper(trim($q)));
                             });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->appends(['q' => $q]);

        return view('admin.users.reset_passwords', compact('users', 'q'));
    }

    public function resetPasswordAction(Request $request, $id)
    {
        if (Auth::user()->user_type !== '00') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $user = User::findOrFail($id);
        
        if ($user->user_type === '00') {
            return redirect()->back()->with('error', 'Cannot reset password for Super Admin.');
        }

        // Reset to default password
        $user->password = Hash::make('manomilana'); // Default password
        $user->is_password_changed = 0; 
        $user->save();

        return redirect()->back()->with('success', 'Password for ' . $user->name . ' has been reset to "manomilana". They will be asked to change it upon next login.');
    }
}
