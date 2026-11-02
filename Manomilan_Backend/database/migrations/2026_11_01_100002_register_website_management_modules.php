<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULES = [
        'website_overview' => [
            'module_name' => 'Website Overview',
            'description' => 'Overview of website availability and public feature settings.',
            'route' => '/admin/website',
            'frontend_routes' => ['/admin/website'],
            'api_prefixes' => ['/website-management/overview'],
            'icon' => 'globe',
            'sort_order' => 100,
        ],
        'upcoming_events_settings' => [
            'module_name' => 'Upcoming Events Settings',
            'description' => 'Control the public Upcoming Events floating button.',
            'route' => '/admin/website/upcoming-events',
            'frontend_routes' => ['/admin/website/upcoming-events'],
            'api_prefixes' => ['/website-management/upcoming-events'],
            'icon' => 'calendar-plus',
            'sort_order' => 101,
        ],
        'downtime_management' => [
            'module_name' => 'Downtime Management',
            'description' => 'Manage scheduled downtime notifications on the public website.',
            'route' => '/admin/website/downtime',
            'frontend_routes' => ['/admin/website/downtime'],
            'api_prefixes' => ['/website-management/downtime'],
            'icon' => 'clock-alert',
            'sort_order' => 102,
        ],
        'full_maintenance_mode' => [
            'module_name' => 'Full Maintenance Mode',
            'description' => 'Enable a maintenance page that blocks public website access.',
            'route' => '/admin/website/maintenance',
            'frontend_routes' => ['/admin/website/maintenance'],
            'api_prefixes' => ['/website-management/maintenance'],
            'icon' => 'shield-alert',
            'sort_order' => 103,
        ],
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('modules')) {
            return;
        }

        $applicationId = DB::table('applications')->orderBy('id')->value('id');
        if (! $applicationId) {
            return;
        }

        $now = now();
        foreach (self::MODULES as $key => $module) {
            DB::table('modules')->updateOrInsert(
                ['module_key' => $key],
                [
                    'application_id' => $applicationId,
                    'module_name' => $module['module_name'],
                    'description' => $module['description'],
                    'status' => true,
                    'route' => $module['route'],
                    'frontend_routes' => json_encode($module['frontend_routes']),
                    'api_prefixes' => json_encode($module['api_prefixes']),
                    'icon' => $module['icon'],
                    'sort_order' => $module['sort_order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        if (! DB::getSchemaBuilder()->hasTable('module_permissions')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('user_type', '01')->value('id');
        if (! $adminRoleId) {
            return;
        }

        $moduleIds = DB::table('modules')->whereIn('module_key', array_keys(self::MODULES))->pluck('id');
        foreach ($moduleIds as $moduleId) {
            DB::table('module_permissions')->updateOrInsert(
                ['role_id' => $adminRoleId, 'module_id' => $moduleId],
                [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => false,
                    'can_import' => false,
                    'can_export' => false,
                    'can_approve' => false,
                    'can_manage' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('modules')) {
            return;
        }

        $moduleIds = DB::table('modules')->whereIn('module_key', array_keys(self::MODULES))->pluck('id');
        if (DB::getSchemaBuilder()->hasTable('module_permissions')) {
            DB::table('module_permissions')->whereIn('module_id', $moduleIds)->delete();
        }
        DB::table('modules')->whereIn('id', $moduleIds)->delete();
    }
};
