<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $userId = $token ? ApiToken::userId($token) : null;
        $user = $userId ? User::find($userId) : null;

        if (! $user || in_array((string) $user->status, ['inactive', '0'], true)) {
            throw new AuthenticationException();
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        if (! $user->is_password_changed) {
            $route = $request->route();
            $allowedRoutes = ['api/change-password', 'api/logout'];

            if (! $route || ! in_array($route->uri(), $allowedRoutes, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Change your password before continuing.',
                    'password_change_required' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
