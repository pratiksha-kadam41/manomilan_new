<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditApiActivity
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $isExport = $request->isMethod('GET') && preg_match('/(^|\/)(export|audit-logs\/export)(\/|$)/i', $request->path());
        if ($response->getStatusCode() >= 400
            || (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $isExport)) {
            return $response;
        }

        $route = $request->route();
        $parameters = $route?->parameters() ?? [];
        $entityId = $parameters['id'] ?? $parameters['profileId'] ?? $parameters['paymentId'] ?? null;
        $path = preg_replace('#^api/#', '', $request->path());
        $action = match ($request->path()) {
            'api/logout' => 'auth.logout',
            default => match ($path) {
                'login' => 'auth.login',
                default => match ($request->method()) {
                    'POST' => 'api.create',
                    'PUT', 'PATCH' => 'api.update',
                    'DELETE' => 'api.delete',
                    default => $isExport ? 'api.export' : 'api.change',
                },
            },
        };
        $controller = $route?->getActionName() ?? 'API';

        $this->auditLogger->log(
            $request->user(),
            $action,
            null,
            $controller,
            is_scalar($entityId) ? $entityId : null,
            null,
            [],
            [],
            $request->method() . ' ' . ($route?->uri() ?? $request->path()),
            $request
        );

        return $response;
    }
}
