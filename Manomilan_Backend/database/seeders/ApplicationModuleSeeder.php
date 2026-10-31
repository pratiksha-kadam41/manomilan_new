<?php

namespace Database\Seeders;

use App\Application;
use App\Module;
use Illuminate\Database\Seeder;

class ApplicationModuleSeeder extends Seeder
{
    private const MODULES = [
        'Profile Management' => [
            'key' => 'profile_management',
            'description' => 'Profile Management module for Aaryans Manomilan',
            'route' => '/candidate/matches',
            'frontend_routes' => ['/candidate/matches/{id}'],
            'api_prefixes' => ['/profiles'],
        ],
        'user side profile' => [
            'key' => 'user_side_profile',
            'description' => 'user side profile module for Aaryans Manomilan',
            'route' => '/candidate',
            'frontend_routes' => ['/candidate', '/candidate/profile', '/candidate/notifications', '/candidate/change-password'],
            'api_prefixes' => ['/candidate/*', '/home-data', '/my-profile', '/preferences'],
        ],
        'admin side profile' => [
            'key' => 'admin_side_profile',
            'description' => 'admin side profile module for Aaryans Manomilan',
            'route' => '/admin/profiles',
            'frontend_routes' => ['/admin/profiles', '/admin/nri-profiles'],
            'api_prefixes' => ['/profiles'],
            'icon' => 'users',
        ],
        'Matchmaking & Suggestions' => [
            'key' => 'matchmaking_suggestions',
            'description' => 'Matchmaking & Suggestions module for Aaryans Manomilan',
            'route' => '/candidate/matches',
            'frontend_routes' => ['/candidate/matches', '/candidate/matches/{id}', '/candidate/interests-sent', '/candidate/interests-received'],
            'api_prefixes' => ['/candidate/matches', '/candidate/matches/*', '/candidate/interests', '/candidate/interests/*'],
        ],
        'Meetings & Schedulers' => [
            'key' => 'meetings_schedulers',
            'description' => 'Meetings & Schedulers module for Aaryans Manomilan',
            'api_prefixes' => ['/meetings'],
        ],
        'verified and unverified' => [
            'key' => 'verified_and_unverified',
            'description' => 'verified and unverified module for Aaryans Manomilan',
            'route' => '/admin/verified-profiles',
            'frontend_routes' => ['/admin/verified-profiles', '/admin/unverified-profiles'],
            'api_prefixes' => ['/profiles/{id}/verification', '/profiles/{id}/verify', '/profiles/deactivate/{id}'],
            'icon' => 'badge-check',
        ],
        'Melava Registration' => [
            'key' => 'melava_registration',
            'description' => 'Melava Registration module for Aaryans Manomilan',
            'route' => '/admin/melava',
            'frontend_routes' => ['/admin/melava', '/admin/melava-campaigns'],
            'api_prefixes' => ['/melava-registrations', '/melava-campaigns'],
            'icon' => 'calendar-heart',
        ],
        'Website Registration' => [
            'key' => 'website_registration',
            'description' => 'Website Registration module for Aaryans Manomilan',
            'api_prefixes' => ['/enquiries', '/workshop-registrations'],
        ],
        'Authentication & Security' => [
            'key' => 'authentication_security',
            'description' => 'Authentication & Security module for Aaryans Manomilan',
            'route' => '/admin/reset-passwords',
            'frontend_routes' => ['/admin/reset-passwords'],
            'api_prefixes' => ['/admin/users'],
            'icon' => 'key-round',
        ],
        'System Configuration' => [
            'key' => 'system_configuration',
            'description' => 'System Configuration module for Aaryans Manomilan',
            'route' => '/admin/system-management',
            'frontend_routes' => ['/admin/system-management'],
            'api_prefixes' => ['/system'],
            'icon' => 'settings',
        ],
        'Reports & Data Export' => [
            'key' => 'reports_data_export',
            'description' => 'Reports & Data Export module for Aaryans Manomilan',
            'route' => '/admin/report',
            'frontend_routes' => ['/admin/report'],
            'api_prefixes' => ['/reports'],
            'icon' => 'file-text',
        ],
        'Payments & Verification' => [
            'key' => 'payments_verification',
            'description' => 'Payments & Verification module for Aaryans Manomilan',
            'api_prefixes' => ['/payments', '/profiles/{id}/payment-screenshots', '/profiles/{id}/payment-screenshot', '/profiles/{id}/receipt'],
        ],
        'Software Ticket Module' => [
            'key' => 'software_ticket_module',
            'description' => 'Software Ticket Module module for Aaryans Manomilan',
            'api_prefixes' => ['/tickets'],
        ],
        'Admin Dashboard' => [
            'key' => 'admin_dashboard',
            'description' => 'Administrative overview and dashboard reports.',
            'route' => '/admin',
            'frontend_routes' => ['/admin'],
            'api_prefixes' => ['/dashboard'],
            'icon' => 'layout-dashboard',
        ],
    ];

    public function run(): void
    {
        $application = Application::query()->orderBy('id')->first();

        if (! $application) {
            $application = Application::query()->create([
                'app_code' => 'Manomilan',
                'app_name' => 'Aaryans Manomilan Matrimonial',
                'description' => 'Aaryans Manomilan Matrimony and Matchmaking Application System',
                'current_version' => '1.0.0',
                'base_url' => config('app.url'),
                'status' => true,
            ]);
        }

        foreach (self::MODULES as $moduleName => $configuration) {
            $module = Module::query()->firstOrCreate(
                [
                    'application_id' => $application->id,
                    'module_name' => $moduleName,
                ],
                [
                    'module_key' => $configuration['key'],
                    'description' => $configuration['description'],
                    'status' => true,
                    'route' => $configuration['route'] ?? null,
                    'frontend_routes' => $configuration['frontend_routes'] ?? null,
                    'api_prefixes' => $configuration['api_prefixes'] ?? null,
                    'icon' => $configuration['icon'] ?? null,
                ]
            );

            if (! $module->module_key) {
                $module->module_key = $configuration['key'];
                $module->save();
            }
        }
    }
}
