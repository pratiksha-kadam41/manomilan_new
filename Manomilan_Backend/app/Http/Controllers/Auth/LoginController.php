<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Auth;
use App\User;
use Hash;
use App\Profile;
class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest')->except('login');
    }
    
    public function showLoginForm(Request $request)
    {
        return view('admin.login'); 
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('username', 'password');
        $remember = $request->filled('remember');

     if (Auth::attempt($credentials, $remember)) {

    $user = Auth::user();

    // CHECK USER STATUS
    if ($user->status != 'active') {

    Auth::logout();

    return back()->withErrors([
        'username' => 'Your account has been deactivated by admin.'
    ]);
}

    // Regenerate session to prevent session fixation
    $request->session()->regenerate();

            // Check if user is Melava 3 candidate to bypass mandatory screens
            $isMelava3 = false;
            $profile = null;
            
            if ($user->profile_id) {
                $profile = Profile::find($user->profile_id);
            } else {
                // Try to find profile by phone number (username) if not linked
                $profile = Profile::where('phone', $user->username)->first();
                if ($profile) {
                    $user->profile_id = $profile->id;
                    $user->save();
                }
            }

            if ($profile && $profile->isMelava3Candidate()) {
                $isMelava3 = true;
            }
            
            if ($user->is_password_changed == false && !$isMelava3) {
                $request->session()->flash('force_password_change_popup', true);
            }

            if ($user->user_type == '21') 
            {
                if($user->profile_id == null && !$isMelava3)
                {
                    // Redirect to profile registration page instead of returning a view
                    return redirect()->route('profileRegistration');
                } else {
                    $profile = $profile ?? Profile::where('id', $user->profile_id)->first();
                    if ($profile) {
                        $request->session()->put('profileVerficationStatus', $profile->profileVerficationStatus);
                    }
                    return redirect()->route('home');
                }
            }
            else {
                return redirect()->intended('/home');
            }
        }

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ])->withInput($request->only('username', 'remember'));
    }

    public function logout(Request $request)
    {
        // Log the user out
        Auth::logout();

        // Clear all session data
        $request->session()->flush();

        // Optional: Invalidate the session (recommended)
        $request->session()->invalidate();

        // Optional: Regenerate CSRF token
        $request->session()->regenerateToken();

        // Redirect to login or homepage
        return redirect('/login');
    }

    public function registration()
    {
        return view('admin.signup');
    }
    
    public function signUp(Request $request)
    {
        // Trim inputs to prevent false positive uniqueness errors due to whitespace
        $request->merge([
            'email' => trim($request->email),
            'phone' => trim($request->phone),
        ]);

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|digits:10|unique:users,username',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->phone,
            'user_type' => '21',
            'role_id' => '3',
            'password' => Hash::make($request->password),
            'is_password_changed' => 1,
        ]);

        return redirect()->intended('/home');
    }

    protected function loggedOut(Request $request)
    {
        return redirect('/login'); // or wherever you want
    }
}
