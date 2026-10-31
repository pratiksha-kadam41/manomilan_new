<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\AuditLogger;

class EnforceApiModulePermissions
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $path = preg_replace('#^api/#', '', $request->path());
        $path = trim((string) $path, '/');
        $method = strtoupper($request->method());

        if (in_array($path, ['user', 'logout', 'change-password', 'system/my-permissions'], true)) {
            return $next($request);
        }

        if (Str::startsWith($path, 'candidate/') && $user->portal() !== 'candidate') {
            return $this->deny($request, 'Forbidden.');
        }

        if ($user->portal() === 'candidate' && $this->isCandidateSharedRoute($method, $path)) {
            return $user->hasModulePermission('profile_management', 'view')
                ? $next($request)
                : $this->deny($request, 'Forbidden.', 'profile_management');
        }

        $modules = DB::table('modules')
            ->where('status', true)
            ->whereNotNull('api_prefixes')
            ->get(['id', 'module_key', 'api_prefixes']);
        $matched = null;
        $matchedLength = -1;

        foreach ($modules as $module) {
            $prefixes = json_decode((string) $module->api_prefixes, true);
            if (! is_array($prefixes)) {
                continue;
            }

            foreach ($prefixes as $prefix) {
                $pattern = trim(preg_replace('/\{[^}]+\}/', '*', trim((string) $prefix, '/')), '/');
                if ($pattern !== '' && Str::is($pattern, $path) && strlen($pattern) > $matchedLength) {
                    $matched = $module->module_key;
                    $matchedLength = strlen($pattern);
                }
            }
        }

        if (! $matched) {
            return $this->deny($request, 'Forbidden. No access rule is configured for this endpoint.');
        }

        if ($user->portal() === 'candidate' && ! in_array($matched, [
            'user_side_profile',
            'matchmaking_suggestions',
        ], true)) {
            return $this->deny($request, 'Forbidden.', $matched);
        }

        $permission = $this->permissionFor($method, $path);
        if (! $user->hasModulePermission($matched, $permission)) {
            return $this->deny($request, 'Forbidden.', $matched);
        }

        return $next($request);
    }

    private function isCandidateSharedRoute(string $method, string $path): bool
    {
        if ($method === 'GET' && preg_match(
            '#^profiles(?:/\d+(?:/biodata-preview|/documents/[^/]+/file)?)?$#',
            $path
        )) {
            return true;
        }

        return false;
    }

    private function permissionFor(string $method, string $path): string
    {
        if (preg_match('/(^|\/)(export|biodata|receipt)(\/|$)/i', $path)) {
            return 'export';
        }
        if (preg_match('/(^|\/)(verify|approve|payment-status)(\/|$)/i', $path)) {
            return 'approve';
        }
        if (in_array($method, ['POST'], true)) {
            return preg_match('/(^|\/)(status|deactivate|read|respond)(\/|$)/i', $path) ? 'edit' : 'create';
        }
        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return preg_match('/(^|\/)(status|verify|payment)(\/|$)/i', $path) ? 'approve' : 'edit';
        }
        if ($method === 'DELETE') {
            return 'delete';
        }

        return 'view';
    }

    private function deny(Request $request, string $message, ?string $moduleKey = null)
    {
        $route = $request->route();
        $parameters = $route?->parameters() ?? [];
        $entityId = $parameters['id'] ?? $parameters['profileId'] ?? null;
        $this->auditLogger->log(
            $request->user(),
            'access.denied',
            $moduleKey,
            'API',
            is_scalar($entityId) ? $entityId : null,
            null,
            [],
            [],
            $request->method() . ' ' . ($route?->uri() ?? $request->path()),
            $request
        );

        return response()->json(['success' => false, 'message' => $message], 403);
    }
}
