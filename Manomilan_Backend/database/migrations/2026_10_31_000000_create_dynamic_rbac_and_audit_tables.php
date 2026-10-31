<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
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

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('user_type')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('modules', 'module_key')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->string('module_key', 120)->nullable();
                $table->string('route', 255)->nullable();
                $table->text('frontend_routes')->nullable();
                $table->string('icon', 100)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('api_prefixes')->nullable();
            });
        }

        if (! Schema::hasColumn('roles', 'is_active')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        $this->populateModuleKeys();

        Schema::table('modules', function (Blueprint $table) {
            $table->unique('module_key');
        });

        Schema::create('module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('permission_key', 40);
            $table->timestamps();
            $table->unique(['module_id', 'permission_key']);
        });

        Schema::create('role_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_permission_id')->constrained('module_permissions')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'module_permission_id'], 'role_module_permission_unique');
        });

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

        $this->seedPermissionDefinitions();
        $this->grantInitialPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('role_module_permissions');
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

    private function populateModuleKeys(): void
    {
        $modules = DB::table('modules')->orderBy('id')->get(['id', 'module_name']);
        $usedKeys = [];

        foreach ($modules as $module) {
            $base = Str::limit(Str::slug($module->module_name, '_'), 110, '');
            $base = $base !== '' ? $base : 'module';
            $key = $base;
            if (isset($usedKeys[$key])) {
                $key = Str::limit($base, 105, '') . '_' . $module->id;
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
            'matchmaking_suggestions' => ['/candidate/matches', '/interests'],
            'user_side_profile' => ['/candidate', '/home-data', '/my-profile', '/preferences'],
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

        $applicationId = DB::table('applications')->orderBy('id')->value('id');
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

    private function seedPermissionDefinitions(): void
    {
        foreach (DB::table('modules')->pluck('id') as $moduleId) {
            foreach (self::PERMISSIONS as $permission) {
                DB::table('module_permissions')->insert([
                    'module_id' => $moduleId,
                    'permission_key' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function grantInitialPermissions(): void
    {
        $superAdminIds = DB::table('roles')
            ->whereRaw("REPLACE(LOWER(name), ' ', '') = ?", ['superadmin'])
            ->pluck('id');

        foreach ($superAdminIds as $roleId) {
            $this->grantAllPermissions((int) $roleId);
        }

        $candidateRoleIds = DB::table('roles')
            ->whereRaw('LOWER(name) = ?', ['candidate'])
            ->orWhere('user_type', '21')
            ->pluck('id');

        foreach ($candidateRoleIds as $roleId) {
            foreach ([
                'user_side_profile' => ['view', 'create', 'edit', 'delete'],
                'matchmaking_suggestions' => ['view', 'create', 'edit', 'approve'],
                'profile_management' => ['view'],
                'payments_verification' => ['view', 'create'],
                'software_ticket_module' => ['view', 'create', 'edit'],
            ] as $moduleKey => $permissions) {
                DB::table('role_module_permissions')->insertUsing(
                    ['role_id', 'module_permission_id', 'created_at', 'updated_at'],
                    DB::table('module_permissions')
                        ->join('modules', 'modules.id', '=', 'module_permissions.module_id')
                        ->where('modules.module_key', $moduleKey)
                        ->whereIn('module_permissions.permission_key', $permissions)
                        ->selectRaw('? as role_id, module_permissions.id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP', [$roleId])
                );
            }
        }
    }

    private function grantAllPermissions(int $roleId): void
    {
        DB::table('role_module_permissions')->insertUsing(
            ['role_id', 'module_permission_id', 'created_at', 'updated_at'],
            DB::table('module_permissions')
                ->selectRaw('? as role_id, id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP', [$roleId])
        );
    }
};
