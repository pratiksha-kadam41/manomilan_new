<?php

namespace Database\Seeders;

use App\Application;
use App\Module;
use App\Models\ModulePermission;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebsiteManagementModuleSeeder extends Seeder
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

    public function run(): void
    {
        $application = Application::query()->orderBy('id')->first();
        if (! $application) {
            return;
        }

        foreach (self::MODULES as $key => $module) {
            Module::query()->updateOrCreate(
                ['module_key' => $key],
                [
                    'application_id' => $application->id,
                    'module_name' => $module['module_name'],
                    'description' => $module['description'],
                    'status' => true,
                    'route' => $module['route'],
                    'frontend_routes' => $module['frontend_routes'],
                    'api_prefixes' => $module['api_prefixes'],
                    'icon' => $module['icon'],
                    'sort_order' => $module['sort_order'],
                ]
            );
        }

        $adminRole = Role::query()->where('user_type', '01')->first();
        if (! $adminRole) {
            return;
        }

        $grants = array_fill_keys(array_values(ModulePermission::ACTIONS), true);
        $grants[ModulePermission::ACTIONS['manage']] = false;
        $grants[ModulePermission::ACTIONS['delete']] = false;
        $grants[ModulePermission::ACTIONS['import']] = false;
        $grants[ModulePermission::ACTIONS['export']] = false;
        $grants[ModulePermission::ACTIONS['approve']] = false;

        DB::transaction(function () use ($adminRole, $grants) {
            foreach (Module::query()->whereIn('module_key', array_keys(self::MODULES))->pluck('id') as $moduleId) {
                ModulePermission::query()->updateOrCreate(
                    ['role_id' => $adminRole->id, 'module_id' => $moduleId],
                    $grants
                );
            }
        });
    }
}
