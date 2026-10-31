<?php

namespace Database\Seeders;

use App\Models\ModulePermission;
use App\Models\User;
use App\Module;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPER_ADMIN_PASSWORD');
        if (! $password) {
            if (app()->isProduction()) {
                throw new RuntimeException('Set SUPER_ADMIN_PASSWORD before seeding the production Super Admin.');
            }

            $password = 'password';
        }

        DB::transaction(function () use ($password) {
            $role = Role::query()->find(1);
            if ($role) {
                $normalizedName = preg_replace('/[^a-z]/', '', strtolower((string) $role->name));
                if ($normalizedName !== 'superadmin' || (string) $role->user_type !== '00') {
                    throw new RuntimeException('Role ID 1 is not the Super Admin role; refusing to change another role.');
                }

                if (! $role->is_active) {
                    $role->is_active = true;
                    $role->save();
                }
            } else {
                if (Role::query()->where('user_type', '00')->orWhere('name', 'superadmin')->exists()) {
                    throw new RuntimeException('A Super Admin role already exists with an ID other than 1.');
                }

                DB::table('roles')->insert([
                    'id' => 1,
                    'name' => 'superadmin',
                    'user_type' => '00',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $role = Role::query()->findOrFail(1);
            }

            $email = env('SUPER_ADMIN_EMAIL', 'super@manomilan.local');
            $existingUser = User::query()->where('username', 'super')->first();
            if ($existingUser) {
                if ((int) $existingUser->role_id !== 1) {
                    throw new RuntimeException('Username "super" already belongs to a user outside role ID 1.');
                }
            } else {
                if (User::query()->where('email', $email)->exists()) {
                    throw new RuntimeException('SUPER_ADMIN_EMAIL is already assigned to another user.');
                }

                User::create([
                    'name' => 'Super Admin',
                    'email' => $email,
                    'username' => 'super',
                    'password' => $password,
                    'status' => 'active',
                    'is_password_changed' => false,
                    'user_type' => '00',
                    'role_id' => 1,
                ]);
            }

            $allActions = array_fill_keys(array_values(ModulePermission::ACTIONS), true);
            foreach (Module::query()->pluck('id') as $moduleId) {
                ModulePermission::query()->updateOrCreate(
                    ['role_id' => $role->id, 'module_id' => $moduleId],
                    $allActions
                );
            }
        });
    }
}
