<?php

namespace Tests\Unit;

use App\Http\Middleware\EnforceApiModulePermissions;
use App\Models\User;
use App\Support\AuditLogger;
use App\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnforceApiModulePermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('user_type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('module_key');
            $table->boolean('status')->default(true);
            $table->text('api_prefixes')->nullable();
            $table->text('frontend_routes')->nullable();
            $table->timestamps();
        });
        Schema::create('module_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('module_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
        });
    }

    public function test_profile_list_uses_the_granted_module_for_a_shared_api_prefix(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Mediator',
            'user_type' => '21',
            'is_active' => true,
        ]);
        $candidateModuleId = DB::table('modules')->insertGetId([
            'module_key' => 'profile_management',
            'status' => true,
            'api_prefixes' => json_encode(['/profiles']),
            'frontend_routes' => json_encode(['/candidate/matches/{id}']),
        ]);
        $adminModuleId = DB::table('modules')->insertGetId([
            'module_key' => 'admin_side_profile',
            'status' => true,
            'api_prefixes' => json_encode(['/profiles']),
            'frontend_routes' => json_encode(['/admin/profiles']),
        ]);
        DB::table('module_permissions')->insert([
            [
                'role_id' => $roleId,
                'module_id' => $candidateModuleId,
                'can_view' => false,
                'can_manage' => false,
            ],
            [
                'role_id' => $roleId,
                'module_id' => $adminModuleId,
                'can_view' => true,
                'can_manage' => false,
            ],
        ]);

        $user = new User(['user_type' => '21', 'role_id' => $roleId]);
        $user->setRelation('role', new Role([
            'name' => 'Mediator',
            'user_type' => '21',
            'is_active' => true,
        ]));
        $user->role->setAttribute('id', $roleId);

        $request = Request::create('/api/profiles/allProfiles', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new EnforceApiModulePermissions(new AuditLogger()))->handle(
            $request,
            fn () => response()->json(['success' => true])
        );

        $this->assertSame(200, $response->getStatusCode());
    }
}
