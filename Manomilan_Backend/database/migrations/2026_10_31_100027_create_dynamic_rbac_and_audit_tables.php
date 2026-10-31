<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view' => 'can_view',
        'create' => 'can_create',
        'edit' => 'can_edit',
        'delete' => 'can_delete',
        'import' => 'can_import',
        'export' => 'can_export',
        'approve' => 'can_approve',
        'manage' => 'can_manage',
    ];

    public function up(): void
    {
        foreach (['roles', 'modules', 'users', 'applications'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("The {$table} table must exist before dynamic RBAC can be installed.");
            }
        }

        if (! Schema::hasColumn('modules', 'module_key')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->string('module_key', 120)->nullable();
            });
        }
        if (! Schema::hasColumn('modules', 'route')) {
            Schema::table('modules', fn (Blueprint $table) => $table->string('route', 255)->nullable());
        }
        if (! Schema::hasColumn('modules', 'frontend_routes')) {
            Schema::table('modules', fn (Blueprint $table) => $table->text('frontend_routes')->nullable());
        }
        if (! Schema::hasColumn('modules', 'icon')) {
            Schema::table('modules', fn (Blueprint $table) => $table->string('icon', 100)->nullable());
        }
        if (! Schema::hasColumn('modules', 'sort_order')) {
            Schema::table('modules', fn (Blueprint $table) => $table->unsignedInteger('sort_order')->default(0));
        }
        if (! Schema::hasColumn('modules', 'api_prefixes')) {
            Schema::table('modules', fn (Blueprint $table) => $table->text('api_prefixes')->nullable());
        }

        if (! Schema::hasColumn('roles', 'is_active')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        $this->populateModuleKeys();

        if (! Schema::hasIndex('modules', 'modules_module_key_unique')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->unique('module_key');
            });
        }

        $this->ensureModulePermissionsTable();

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name_snapshot')->nullable();
                $table->string('action', 100);
                $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
                $table->string('module_name_snapshot')->nullable();
                $table->string('entity_type', 120)->nullable();
                $table->string('entity_id', 120)->nullable();
                $table->string('candidate_id', 120)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('request_method', 12)->nullable();
                $table->text('request_url')->nullable();
                $table->timestamps();
                $table->index(['created_at', 'action']);
                $table->index(['user_id', 'created_at']);
                $table->index(['candidate_id', 'created_at']);
            });
        }

        $this->seedRoleModulePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('module_permissions');
        DB::table('modules')
            ->where('module_key', 'admin_dashboard')
            ->where('module_name', 'Admin Dashboard')
            ->delete();

        if (Schema::hasColumn('modules', 'module_key')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->dropUnique(['module_key']);
                $table->dropColumn(['module_key', 'route', 'frontend_routes', 'icon', 'sort_order', 'api_prefixes']);
            });
        }

        if (Schema::hasColumn('roles', 'is_active')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    private function ensureModulePermissionsTable(): void
    {
        if (! Schema::hasTable('module_permissions')) {
            $this->createModulePermissionsTable();

            return;
        }

        if (Schema::hasColumn('module_permissions', 'role_id')
            && Schema::hasColumn('module_permissions', 'can_view')) {
            return;
        }

        if (! Schema::hasColumn('module_permissions', 'permission_key')
            || ! Schema::hasColumn('module_permissions', 'module_id')) {
            throw new RuntimeException('The existing module_permissions table has an unsupported schema.');
        }

        $legacyGrants = collect();
        if (Schema::hasTable('role_module_permissions')) {
            $legacyGrants = DB::table('role_module_permissions')
                ->join('module_permissions', 'module_permissions.id', '=', 'role_module_permissions.module_permission_id')
                ->get([
                    'role_module_permissions.role_id',
                    'module_permissions.module_id',
                    'module_permissions.permission_key',
                ]);
            Schema::drop('role_module_permissions');
        }

        Schema::drop('module_permissions');
        $this->createModulePermissionsTable();

        $grantsByRoleAndModule = [];
        foreach ($legacyGrants as $grant) {
            $grantsByRoleAndModule[$grant->role_id][$grant->module_id][$grant->permission_key] = true;
        }

        $now = now();
        $rows = [];
        foreach (DB::table('roles')->get(['id']) as $role) {
            foreach (DB::table('modules')->get(['id']) as $module) {
                $row = [
                    'role_id' => $role->id,
                    'module_id' => $module->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach (self::PERMISSIONS as $action => $column) {
                    $row[$column] = isset($grantsByRoleAndModule[$role->id][$module->id][$action]);
                }
                $rows[] = $row;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('module_permissions')->insert($chunk);
        }
    }

    private function createModulePermissionsTable(): void
    {
        Schema::create('module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            foreach (self::PERMISSIONS as $column) {
                $table->boolean($column)->default(false);
            }
            $table->timestamps();
            $table->unique(['role_id', 'module_id']);
        });
    }

    private function populateModuleKeys(): void
    {
        $modules = DB::table('modules')->orderBy('id')->get(['id', 'module_name']);
        $usedKeys = [];

        DB::table('modules')->update(['module_key' => null]);

        foreach ($modules as $module) {
            $base = Str::limit(Str::slug($module->module_name, '_'), 110, '');
            $base = $base !== '' ? $base : 'module';
            $key = $base;
            $suffix = 1;
            while (isset($usedKeys[$key])) {
                $key = Str::limit($base, 105, '') . '_' . $module->id . ($suffix === 1 ? '' : '_'.$suffix);
                $suffix++;
            }
            $usedKeys[$key] = true;

            DB::table('modules')->where('id', $module->id)->update([
                'module_key' => $key,
            ]);
        }

        $prefixesByKey = [
            'admin_side_profile' => ['/profiles'],
            'verified_and_unverified' => [
                '/profiles/{id}/verification',
                '/profiles/{id}/verify',
                '/profiles/deactivate/{id}',
            ],
            'melava_registration' => ['/melava-registrations', '/melava-campaigns'],
            'website_registration' => ['/enquiries', '/workshop-registrations'],
            'authentication_security' => ['/admin/users'],
            'system_configuration' => ['/system'],
            'reports_data_export' => ['/reports'],
            'payments_verification' => ['/payments', '/profiles/{id}/payment-screenshots', '/profiles/{id}/payment-screenshot', '/profiles/{id}/receipt'],
            'software_ticket_module' => ['/tickets'],
            'user_side_profile' => ['/candidate/*', '/home-data', '/my-profile', '/preferences'],
            'matchmaking_suggestions' => [
                '/candidate/matches',
                '/candidate/matches/*',
                '/candidate/interests',
                '/candidate/interests/*',
            ],
        ];

        foreach ($prefixesByKey as $key => $prefixes) {
            DB::table('modules')
                ->where('module_key', $key)
                ->update(['api_prefixes' => json_encode($prefixes)]);
        }

        $menuByKey = [
            'admin_side_profile' => ['/admin/profiles', ['/admin/profiles', '/admin/nri-profiles'], 'users'],
            'verified_and_unverified' => ['/admin/verified-profiles', ['/admin/verified-profiles', '/admin/unverified-profiles'], 'badge-check'],
            'melava_registration' => ['/admin/melava', ['/admin/melava', '/admin/melava-campaigns'], 'calendar-heart'],
            'authentication_security' => ['/admin/reset-passwords', ['/admin/reset-passwords'], 'key-round'],
            'system_configuration' => ['/admin/system-management', ['/admin/system-management'], 'settings'],
            'reports_data_export' => ['/admin/report', ['/admin/report'], 'file-text'],
        ];

        foreach ($menuByKey as $key => [$route, $routes, $icon]) {
            DB::table('modules')
                ->where('module_key', $key)
                ->update([
                    'route' => $route,
                    'frontend_routes' => json_encode($routes),
                    'icon' => $icon,
                ]);
        }

        $candidateModules = [
            'user_side_profile' => [
                '/candidate',
                ['/candidate', '/candidate/profile', '/candidate/notifications', '/candidate/change-password'],
                ['/candidate/*', '/home-data', '/my-profile', '/preferences'],
            ],
            'matchmaking_suggestions' => [
                '/candidate/matches',
                ['/candidate/matches', '/candidate/matches/{id}', '/candidate/interests-sent', '/candidate/interests-received'],
                ['/candidate/matches', '/candidate/matches/*', '/candidate/interests', '/candidate/interests/*'],
            ],
            'profile_management' => [
                '/candidate/matches',
                ['/candidate/matches/{id}'],
                ['/profiles'],
            ],
        ];
        foreach ($candidateModules as $key => [$route, $routes, $prefixes]) {
            DB::table('modules')
                ->where('module_key', $key)
                ->update([
                    'route' => $route,
                    'frontend_routes' => json_encode($routes),
                    'api_prefixes' => json_encode($prefixes),
                ]);
        }

        $applicationId = DB::table('applications')->where('app_code', 'Manomilan')->value('id');
        if (! $applicationId) {
            $applicationId = DB::table('applications')->insertGetId([
                'app_code' => 'Manomilan',
                'app_name' => 'ManoMilan',
                'description' => 'ManoMilan application',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($applicationId && ! DB::table('modules')->where('module_key', 'admin_dashboard')->exists()) {
            DB::table('modules')->insert([
                'application_id' => $applicationId,
                'module_name' => 'Admin Dashboard',
                'module_key' => 'admin_dashboard',
                'description' => 'Administrative overview and dashboard reports.',
                'status' => true,
                'route' => '/admin',
                'frontend_routes' => json_encode(['/admin']),
                'icon' => 'layout-dashboard',
                'sort_order' => 0,
                'api_prefixes' => json_encode(['/dashboard']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedRoleModulePermissions(): void
    {
        $candidatePermissions = [
            'user_side_profile' => ['view', 'create', 'edit', 'delete'],
            'matchmaking_suggestions' => ['view', 'create', 'edit', 'approve'],
            'profile_management' => ['view'],
        ];
        $modules = DB::table('modules')->get(['id', 'module_key']);
        $roles = DB::table('roles')->get(['id', 'name', 'user_type']);
        $now = now();
        $rows = [];

        foreach ($roles as $role) {
            $normalizedName = preg_replace('/[^a-z]/', '', strtolower((string) $role->name));
            $isSuperAdmin = $normalizedName === 'superadmin' || (string) $role->user_type === '00';
            $isCandidate = strtolower((string) $role->name) === 'candidate' || (string) $role->user_type === '21';

            foreach ($modules as $module) {
                $grantedActions = $isSuperAdmin
                    ? array_keys(self::PERMISSIONS)
                    : ($isCandidate ? ($candidatePermissions[$module->module_key] ?? []) : []);
                $row = [
                    'role_id' => $role->id,
                    'module_id' => $module->id,
                ];
                foreach (self::PERMISSIONS as $action => $column) {
                    $row[$column] = in_array($action, $grantedActions, true);
                }
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $rows[] = $row;
            }
        }

        $updateColumns = [...array_values(self::PERMISSIONS), 'updated_at'];
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('module_permissions')->upsert($chunk, ['role_id', 'module_id'], $updateColumns);
        }
    }
};
