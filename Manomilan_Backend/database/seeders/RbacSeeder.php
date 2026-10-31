<?php

namespace Database\Seeders;

use App\Application;
use App\Module;
use App\Models\ModulePermission;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbacSeeder extends Seeder
{
    private const PERMISSIONS = [
        'view',
        'create',
        'edit',
        'delete',
        'import',
        'export',
        'approve',
        'manage',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $roles = collect([
                ['name' => 'Super Admin', 'user_type' => '00'],
                ['name' => 'Admin', 'user_type' => '01'],
                ['name' => 'Candidate', 'user_type' => '21'],
            ])->mapWithKeys(function (array $attributes) {
                $role = Role::updateOrCreate(
                    ['user_type' => $attributes['user_type']],
                    $attributes + ['is_active' => true]
                );

                return [$attributes['user_type'] => $role];
            });

            $application = Application::updateOrCreate(
                ['app_code' => 'Manomilan'],
                [
                    'app_name' => 'ManoMilan',
                    'description' => 'ManoMilan application',
                    'status' => true,
                ]
            );

            foreach ($this->modules() as $sortOrder => $moduleData) {
                $module = Module::updateOrCreate(
                    ['module_key' => $moduleData['module_key']],
                    $moduleData + [
                        'application_id' => $application->id,
                        'status' => true,
                        'sort_order' => $sortOrder,
                    ]
                );

                foreach (self::PERMISSIONS as $permissionKey) {
                    ModulePermission::updateOrCreate(
                        ['module_id' => $module->id, 'permission_key' => $permissionKey],
                        []
                    );
                }
            }

            $permissionIds = ModulePermission::query()->pluck('id')->all();
            $roles['00']->modulePermissions()->syncWithoutDetaching($permissionIds);
            $roles['01']->modulePermissions()->syncWithoutDetaching(
                ModulePermission::query()->where('permission_key', '!=', 'manage')->pluck('id')->all()
            );

            $candidateGrants = [
                'user_side_profile' => ['view', 'create', 'edit', 'delete'],
                'matchmaking_suggestions' => ['view', 'create', 'edit', 'approve'],
                'profile_management' => ['view'],
                'payments_verification' => ['view', 'create'],
                'software_ticket_module' => ['view', 'create', 'edit'],
            ];
            $candidatePermissionIds = ModulePermission::query()
                ->whereIn('permission_key', collect($candidateGrants)->flatten()->unique())
                ->whereHas('module', fn ($query) => $query->whereIn('module_key', array_keys($candidateGrants)))
                ->pluck('id')
                ->all();
            $roles['21']->modulePermissions()->syncWithoutDetaching($candidatePermissionIds);
        });
    }

    private function modules(): array
    {
        return [
            [
                'module_key' => 'admin_dashboard',
                'module_name' => 'Admin Dashboard',
                'description' => 'Administrative overview and dashboard reports.',
                'route' => '/admin',
                'frontend_routes' => ['/admin'],
                'icon' => 'layout-dashboard',
                'api_prefixes' => ['/dashboard'],
            ],
            [
                'module_key' => 'admin_side_profile',
                'module_name' => 'Admin Side Profile',
                'description' => 'Profile management in the admin panel.',
                'route' => '/admin/profiles',
                'frontend_routes' => ['/admin/profiles', '/admin/nri-profiles'],
                'icon' => 'users',
                'api_prefixes' => ['/profiles'],
            ],
            [
                'module_key' => 'verified_and_unverified',
                'module_name' => 'Verified And Unverified',
                'description' => 'Review profile verification status.',
                'route' => '/admin/verified-profiles',
                'frontend_routes' => ['/admin/verified-profiles', '/admin/unverified-profiles'],
                'icon' => 'badge-check',
                'api_prefixes' => [
                    '/profiles/{id}/verification',
                    '/profiles/{id}/verify',
                    '/profiles/deactivate/{id}',
                ],
            ],
            [
                'module_key' => 'melava_registration',
                'module_name' => 'Melava Registration',
                'description' => 'Melava registrations and campaigns.',
                'route' => '/admin/melava',
                'frontend_routes' => ['/admin/melava', '/admin/melava-campaigns'],
                'icon' => 'calendar-heart',
                'api_prefixes' => ['/melava-registrations', '/melava-campaigns'],
            ],
            [
                'module_key' => 'website_registration',
                'module_name' => 'Website Registration',
                'description' => 'Website enquiries and workshop registrations.',
                'route' => null,
                'frontend_routes' => [],
                'icon' => null,
                'api_prefixes' => ['/enquiries', '/workshop-registrations'],
            ],
            [
                'module_key' => 'authentication_security',
                'module_name' => 'Authentication Security',
                'description' => 'Authentication and account security.',
                'route' => '/admin/reset-passwords',
                'frontend_routes' => ['/admin/reset-passwords'],
                'icon' => 'key-round',
                'api_prefixes' => ['/admin/users'],
            ],
            [
                'module_key' => 'system_configuration',
                'module_name' => 'System Configuration',
                'description' => 'Manage system settings and access control.',
                'route' => '/admin/system-management',
                'frontend_routes' => ['/admin/system-management'],
                'icon' => 'settings',
                'api_prefixes' => ['/system'],
            ],
            [
                'module_key' => 'reports_data_export',
                'module_name' => 'Reports Data Export',
                'description' => 'Reports and data exports.',
                'route' => '/admin/report',
                'frontend_routes' => ['/admin/report'],
                'icon' => 'file-text',
                'api_prefixes' => ['/reports'],
            ],
            [
                'module_key' => 'payments_verification',
                'module_name' => 'Payments Verification',
                'description' => 'Payment verification and receipts.',
                'route' => null,
                'frontend_routes' => [],
                'icon' => null,
                'api_prefixes' => [
                    '/payments',
                    '/profiles/{id}/payment-screenshots',
                    '/profiles/{id}/payment-screenshot',
                    '/profiles/{id}/receipt',
                ],
            ],
            [
                'module_key' => 'software_ticket_module',
                'module_name' => 'Software Ticket Module',
                'description' => 'Support tickets and ticket discussions.',
                'route' => null,
                'frontend_routes' => [],
                'icon' => null,
                'api_prefixes' => ['/tickets'],
            ],
            [
                'module_key' => 'matchmaking_suggestions',
                'module_name' => 'Matchmaking Suggestions',
                'description' => 'Candidate matches and interests.',
                'route' => null,
                'frontend_routes' => [],
                'icon' => null,
                'api_prefixes' => ['/candidate/matches', '/interests'],
            ],
            [
                'module_key' => 'user_side_profile',
                'module_name' => 'User Side Profile',
                'description' => 'Candidate profile and preferences.',
                'route' => null,
                'frontend_routes' => [],
                'icon' => null,
                'api_prefixes' => ['/candidate', '/home-data', '/my-profile', '/preferences'],
            ],
            [
                'module_key' => 'profile_management',
                'module_name' => 'Profile Management',
                'description' => 'Shared profile access for candidate accounts.',
                'route' => null,
                'frontend_routes' => [],
                'icon' => null,
                'api_prefixes' => [],
            ],
        ];
    }
}
