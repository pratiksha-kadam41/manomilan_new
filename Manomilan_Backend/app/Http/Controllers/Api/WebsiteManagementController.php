<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use App\Support\WebsiteSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WebsiteManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly WebsiteSettingsService $settings,
    ) {
    }

    public function overview()
    {
        try {
            $data = $this->settings->publicPayload();

            return response()->json([
                'success' => true,
                'data' => [
                    'upcoming_events' => $data['upcoming_events'],
                    'downtime' => $data['downtime'],
                    'maintenance' => $data['maintenance'],
                    'summary' => [
                        'upcoming_events_enabled' => $data['upcoming_events']['enabled'],
                        'downtime_enabled' => $data['downtime']['enabled'],
                        'downtime_active' => $data['downtime']['active'],
                        'maintenance_enabled' => $data['maintenance']['enabled'],
                        'maintenance_active' => $data['maintenance']['active'],
                        'timezone' => WebsiteSettingsService::TIMEZONE,
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Unable to load website settings.'], 500);
        }
    }

    public function showUpcomingEvents()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->settings->upcomingEventsPayload($this->settings->settings()),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Unable to load Upcoming Events settings.'], 500);
        }
    }

    public function updateUpcomingEvents(Request $request)
    {
        $request->merge(['url' => $request->input('url') ?: null]);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'label' => ['required', 'string', 'max:80', 'not_regex:/^\s*$/'],
            'icon' => ['required', 'string', Rule::in(['calendar', 'sparkles', 'megaphone', 'bell', 'gift', 'star'])],
            'background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['required', 'string', Rule::in(['bottom-right', 'bottom-left'])],
            'url' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/[^\s]+|\/(?!\/)[^\s]*)$/i'],
            'open_new_tab' => ['required', 'boolean'],
        ]);

        try {
            $settings = $this->settings->settings();
            $columnMap = [
                'enabled' => 'upcoming_events_enabled',
                'label' => 'upcoming_events_label',
                'icon' => 'upcoming_events_icon',
                'background_color' => 'upcoming_events_bg_color',
                'text_color' => 'upcoming_events_text_color',
                'position' => 'upcoming_events_position',
                'url' => 'upcoming_events_url',
                'open_new_tab' => 'upcoming_events_open_new_tab',
            ];
            $attributes = [
                'upcoming_events_enabled' => $data['enabled'],
                'upcoming_events_label' => trim(strip_tags($data['label'])),
                'upcoming_events_icon' => $data['icon'],
                'upcoming_events_bg_color' => $data['background_color'],
                'upcoming_events_text_color' => $data['text_color'],
                'upcoming_events_position' => $data['position'],
                'upcoming_events_url' => $data['url'],
                'upcoming_events_open_new_tab' => $data['open_new_tab'],
            ];
            $old = $settings->only(array_values($columnMap));

            DB::transaction(function () use ($settings, $attributes) {
                $settings->fill($attributes)->save();
            });

            $this->settings->forget();
            $this->auditLogger->log(
                $request->user(),
                'website.upcoming_events.updated',
                'upcoming_events_settings',
                'WebsiteSetting',
                $settings->id,
                null,
                $old,
                $settings->fresh()->only(array_values($columnMap))
            );

            return response()->json([
                'success' => true,
                'message' => 'Upcoming Events settings saved.',
                'data' => $this->settings->upcomingEventsPayload($settings->fresh()),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Unable to save Upcoming Events settings.'], 500);
        }
    }

    public function showDowntime()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->settings->downtimePayload($this->settings->downtime()),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Unable to load downtime settings.'], 500);
        }
    }

    public function updateDowntime(Request $request)
    {
        $request->merge([
            'message' => $request->input('message') ?: null,
            'starts_at' => $request->input('starts_at') ?: null,
            'ends_at' => $request->input('ends_at') ?: null,
            'contact_email' => $request->input('contact_email') ?: null,
            'contact_phone' => $request->input('contact_phone') ?: null,
        ]);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:150', 'not_regex:/^\s*$/'],
            'message' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'show_countdown' => ['required', 'boolean'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'show_contact' => ['required', 'boolean'],
            'display_location' => ['required', 'string', Rule::in(['login', 'home', 'both'])],
        ]);

        try {
            $downtime = $this->settings->downtime();
            $old = $downtime->only(array_keys($data));

            DB::transaction(function () use ($downtime, $data) {
                $message = isset($data['message']) ? trim(strip_tags($data['message'])) : null;

                $downtime->fill([
                    ...$data,
                    'title' => trim(strip_tags($data['title'])),
                    'message' => $message === '' ? null : $message,
                ])->save();
            });

            $this->settings->forget();
            $this->auditLogger->log(
                $request->user(),
                'website.downtime.updated',
                'downtime_management',
                'WebsiteDowntimeSetting',
                $downtime->id,
                null,
                $old,
                $downtime->fresh()->only(array_keys($data))
            );

            return response()->json([
                'success' => true,
                'message' => 'Downtime settings saved.',
                'data' => $this->settings->downtimePayload($downtime->fresh()),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Unable to save downtime settings.'], 500);
        }
    }

    public function showMaintenance()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->settings->maintenancePayload($this->settings->maintenance()),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Unable to load maintenance settings.'], 500);
        }
    }

    public function updateMaintenance(Request $request)
    {
        $request->merge([
            'message' => $request->input('message') ?: null,
            'starts_at' => $request->input('starts_at') ?: null,
            'ends_at' => $request->input('ends_at') ?: null,
        ]);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:150', 'not_regex:/^\s*$/'],
            'message' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        try {
            $maintenance = $this->settings->maintenance();
            $old = $maintenance->only(array_keys($data));

            DB::transaction(function () use ($maintenance, $data) {
                $message = isset($data['message']) ? trim(strip_tags($data['message'])) : null;

                $maintenance->fill([
                    ...$data,
                    'title' => trim(strip_tags($data['title'])),
                    'message' => $message === '' ? null : $message,
                ])->save();
            });

            $this->settings->forget();
            $this->auditLogger->log(
                $request->user(),
                'website.maintenance.updated',
                'full_maintenance_mode',
                'WebsiteMaintenanceSetting',
                $maintenance->id,
                null,
                $old,
                $maintenance->fresh()->only(array_keys($data))
            );

            return response()->json([
                'success' => true,
                'message' => 'Maintenance settings saved.',
                'data' => $this->settings->maintenancePayload($maintenance->fresh()),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'Unable to save maintenance settings.'], 500);
        }
    }

    public function publicSettings()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->settings->publicPayload(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => true,
                'data' => [
                    'upcoming_events' => ['enabled' => false],
                    'downtime' => ['enabled' => false, 'active' => false],
                    'maintenance' => ['enabled' => false, 'active' => false],
                ],
            ]);
        }
    }
}
