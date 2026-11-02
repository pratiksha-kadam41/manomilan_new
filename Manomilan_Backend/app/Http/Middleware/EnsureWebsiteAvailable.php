<?php

namespace App\Http\Middleware;

use App\Support\WebsiteSettingsService;
use Closure;
use Illuminate\Http\Request;

class EnsureWebsiteAvailable
{
    public function __construct(private readonly WebsiteSettingsService $settings)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $this->settings->maintenanceActive()) {
            return $next($request);
        }

        $maintenance = $this->settings->maintenancePayload($this->settings->maintenance());
        $response = response()->json([
            'success' => false,
            'message' => $maintenance['title'] ?: 'The website is under maintenance.',
            'maintenance' => $maintenance,
        ], 503);

        if ($maintenance['seconds_remaining']) {
            $response->header('Retry-After', (string) $maintenance['seconds_remaining']);
        }

        return $response;
    }
}
