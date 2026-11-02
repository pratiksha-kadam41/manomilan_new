<?php

namespace App\Support;

use App\Models\WebsiteDowntimeSetting;
use App\Models\WebsiteMaintenanceSetting;
use App\Models\WebsiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WebsiteSettingsService
{
    public const TIMEZONE = 'Asia/Kolkata';
    public const CACHE_KEY = 'website.public.settings';
    public const UPCOMING_CACHE_KEY = 'website.upcoming.settings';

    public function settings(): WebsiteSetting
    {
        return WebsiteSetting::query()->firstOrCreate([]);
    }

    public function downtime(): WebsiteDowntimeSetting
    {
        return WebsiteDowntimeSetting::query()->firstOrCreate([]);
    }

    public function maintenance(): WebsiteMaintenanceSetting
    {
        return WebsiteMaintenanceSetting::query()->firstOrCreate([]);
    }

    public function publicPayload(): array
    {
        $upcoming = Cache::remember(self::UPCOMING_CACHE_KEY, now()->addMinute(), function () {
            return $this->upcomingEventsPayload($this->settings());
        });

        return [
            'upcoming_events' => $upcoming,
            'downtime' => $this->downtimePayload($this->downtime()),
            'maintenance' => $this->maintenancePayload($this->maintenance()),
        ];
    }

    public function maintenanceActive(): bool
    {
        try {
            return $this->maintenancePayload($this->maintenance())['active'];
        } catch (Throwable) {
            return false;
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::UPCOMING_CACHE_KEY);
    }

    public function upcomingEventsPayload(WebsiteSetting $settings): array
    {
        return [
            'enabled' => (bool) $settings->upcoming_events_enabled,
            'label' => $settings->upcoming_events_label ?: 'Upcoming Events',
            'icon' => $settings->upcoming_events_icon ?: 'calendar',
            'background_color' => $settings->upcoming_events_bg_color ?: '#c4144f',
            'text_color' => $settings->upcoming_events_text_color ?: '#ffffff',
            'position' => in_array($settings->upcoming_events_position, ['bottom-left', 'bottom-right'], true)
                ? $settings->upcoming_events_position
                : 'bottom-right',
            'url' => $settings->upcoming_events_url,
            'open_new_tab' => (bool) $settings->upcoming_events_open_new_tab,
        ];
    }

    public function downtimePayload(WebsiteDowntimeSetting $downtime): array
    {
        $window = $this->windowState($downtime->starts_at, $downtime->ends_at);

        return [
            'enabled' => (bool) $downtime->enabled,
            'active' => (bool) $downtime->enabled && $window['active'],
            'title' => $downtime->title,
            'message' => $downtime->message,
            'starts_at' => $this->iso($downtime->starts_at),
            'ends_at' => $this->iso($downtime->ends_at),
            'starts_at_input' => $this->localInput($downtime->starts_at),
            'ends_at_input' => $this->localInput($downtime->ends_at),
            'show_countdown' => (bool) $downtime->show_countdown,
            'contact_email' => $downtime->contact_email,
            'contact_phone' => $downtime->contact_phone,
            'show_contact' => (bool) $downtime->show_contact,
            'display_location' => in_array($downtime->display_location, ['login', 'home', 'both'], true)
                ? $downtime->display_location
                : 'both',
            'server_now' => now(self::TIMEZONE)->toIso8601String(),
            'seconds_remaining' => $window['seconds_remaining'],
        ];
    }

    public function maintenancePayload(WebsiteMaintenanceSetting $maintenance): array
    {
        $window = $this->windowState($maintenance->starts_at, $maintenance->ends_at);

        return [
            'enabled' => (bool) $maintenance->enabled,
            'active' => (bool) $maintenance->enabled && $window['active'],
            'title' => $maintenance->title,
            'message' => $maintenance->message,
            'starts_at' => $this->iso($maintenance->starts_at),
            'ends_at' => $this->iso($maintenance->ends_at),
            'starts_at_input' => $this->localInput($maintenance->starts_at),
            'ends_at_input' => $this->localInput($maintenance->ends_at),
            'server_now' => now(self::TIMEZONE)->toIso8601String(),
            'seconds_remaining' => $window['seconds_remaining'],
        ];
    }

    public function windowState(?string $startsAt, ?string $endsAt): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $start = $this->parse($startsAt);
        $end = $this->parse($endsAt);

        $active = (! $start || $now->greaterThanOrEqualTo($start))
            && (! $end || $now->lessThanOrEqualTo($end));

        return [
            'active' => $active,
            'seconds_remaining' => $end ? (int) max(0, $now->diffInSeconds($end, false)) : null,
        ];
    }

    private function parse(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, self::TIMEZONE);
        } catch (Throwable) {
            return null;
        }
    }

    private function iso(?string $value): ?string
    {
        return $this->parse($value)?->toIso8601String();
    }

    private function localInput(?string $value): ?string
    {
        return $this->parse($value)?->format('Y-m-d\TH:i');
    }
}
