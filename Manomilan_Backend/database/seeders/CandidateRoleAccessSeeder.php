<?php

namespace Database\Seeders;

use App\Models\ModulePermission;
use App\Models\User;
use App\Module;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CandidateRoleAccessSeeder extends Seeder
{
    private const MODULE_CONFIGURATION = [
        'user_side_profile' => [
            'route' => '/candidate',
            'frontend_routes' => [
                '/candidate',
                '/candidate/profile',
                '/candidate/notifications',
                '/candidate/change-password',
            ],
            'api_prefixes' => ['/candidate/*', '/home-data', '/my-profile', '/preferences'],
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'matchmaking_suggestions' => [
            'route' => '/candidate/matches',
            'frontend_routes' => [
                '/candidate/matches',
                '/candidate/matches/{id}',
                '/candidate/interests-sent',
                '/candidate/interests-received',
            ],
            'api_prefixes' => [
                '/candidate/matches',
                '/candidate/matches/*',
                '/candidate/interests',
                '/candidate/interests/*',
            ],
            'actions' => ['view', 'create', 'edit', 'approve'],
        ],
        'profile_management' => [
            'route' => '/candidate/matches',
            'frontend_routes' => ['/candidate/matches/{id}'],
            'api_prefixes' => ['/profiles'],
            'actions' => ['view'],
        ],
        'payments_verification' => [
            'route' => null,
            'frontend_routes' => [],
            'api_prefixes' => [
                '/payments',
                '/profiles/{id}/payment-screenshots',
                '/profiles/{id}/payment-screenshot',
                '/profiles/{id}/receipt',
            ],
            'actions' => ['view', 'create'],
        ],
        'software_ticket_module' => [
            'route' => null,
            'frontend_routes' => [],
            'api_prefixes' => ['/tickets'],
            'actions' => ['view', 'create', 'edit'],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()->where('user_type', '21')->first();
            if (! $role) {
                if (Role::query()->whereRaw('LOWER(name) = ?', ['candidate'])->exists()) {
                    throw new RuntimeException('A Candidate role exists without user_type 21; refusing to change its portal identity.');
                }

                $role = Role::query()->create([
                    'name' => 'Candidate',
                    'user_type' => '21',
                    'is_active' => true,
                ]);
            } elseif (! $role->is_active) {
                $role->is_active = true;
                $role->save();
            }

            User::query()
                ->where('user_type', '21')
                ->whereNull('role_id')
                ->update(['role_id' => $role->id]);

            $modules = Module::query()
                ->whereIn('module_key', array_keys(self::MODULE_CONFIGURATION))
                ->get()
                ->keyBy('module_key');
            $missingModules = array_diff(array_keys(self::MODULE_CONFIGURATION), $modules->keys()->all());
            if ($missingModules !== []) {
                throw new RuntimeException('Candidate portal modules are missing: '.implode(', ', $missingModules));
            }

            foreach (self::MODULE_CONFIGURATION as $moduleKey => $configuration) {
                $module = $modules->get($moduleKey);
                $module->route = $configuration['route'];
                $module->frontend_routes = $configuration['frontend_routes'];
                $module->api_prefixes = $configuration['api_prefixes'];
                $module->save();
            }

            $actionColumns = array_fill_keys(array_values(ModulePermission::ACTIONS), false);
            $allowedModuleIds = [];
            foreach (Module::query()->get(['id', 'module_key']) as $module) {
                $actions = $actionColumns;
                if (isset(self::MODULE_CONFIGURATION[$module->module_key])) {
                    $allowedModuleIds[] = $module->id;
                    foreach (self::MODULE_CONFIGURATION[$module->module_key]['actions'] as $action) {
                        $actions[ModulePermission::ACTIONS[$action]] = true;
                    }
                }

                ModulePermission::query()->updateOrCreate(
                    ['role_id' => $role->id, 'module_id' => $module->id],
                    $actions
                );
            }

            $role->modulePermissions()
                ->whereNotIn('module_id', $allowedModuleIds)
                ->delete();
        });
    }
}
