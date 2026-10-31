<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;

class RequireSuperAdmin
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->isSuperAdmin()) {
            $this->auditLogger->log(
                $request->user(),
                'access.denied',
                'system_configuration',
                'API',
                null,
                null,
                [],
                [],
                $request->method() . ' ' . ($request->route()?->uri() ?? $request->path()),
                $request
            );
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
