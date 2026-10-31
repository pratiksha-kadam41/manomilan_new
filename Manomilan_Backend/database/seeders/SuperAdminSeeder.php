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
        $email = trim((string) env('SUPER_ADMIN_EMAIL', 'super'));
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'password');

        $email = trim((string) env('SUPER_ADMIN_EMAIL', 'super'));
$password = (string) env('SUPER_ADMIN_PASSWORD', 'password');
        if (($email === '') !== ($password === '')) {
            throw new RuntimeException('Both SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD must be configured to seed a Super Admin login.');
        }

        DB::transaction(function () use ($email, $password): void {
            $role = Role::query()->where('user_type', '00')->first();
            if (! $role) {
                if (Role::query()->whereRaw("REPLACE(LOWER(name), ' ', '') = ?", ['superadmin'])->exists()) {
                    throw new RuntimeException('A Super Admin role exists with a different user_type; refusing to alter it.');
                }

                $role = Role::query()->create([
                    'name' => 'Super Admin',
                    'user_type' => '00',
                    'is_active' => true,
                ]);
            } elseif (preg_replace('/[^a-z]/', '', strtolower((string) $role->name)) !== 'superadmin') {
                throw new RuntimeException('The role for user_type 00 is not Super Admin; refusing to alter it.');
            }

            if (! $role->is_active) {
                $role->is_active = true;
                $role->save();
            }

            $allActions = array_fill_keys(array_values(ModulePermission::ACTIONS), true);
            foreach (Module::query()->pluck('id') as $moduleId) {
                ModulePermission::query()->updateOrCreate(
                    ['role_id' => $role->id, 'module_id' => $moduleId],
                    $allActions
                );
            }

            if ($email === '' && $password === '') {
                $this->command?->warn(
                    'Super Admin role and permissions are seeded. Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD to seed a Super Admin login.'
                );

                return;
            }

            $username = trim((string) env('SUPER_ADMIN_USERNAME', 'superadmin'));
            if ($username === '') {
                throw new RuntimeException('SUPER_ADMIN_USERNAME cannot be empty.');
            }

            $user = User::query()->where('email', $email)->first();
            $usernameUser = User::query()->where('username', $username)->first();
            if ($user && ! $user->isSuperAdmin()) {
                throw new RuntimeException('The configured SUPER_ADMIN_EMAIL belongs to a non-Super Admin account; refusing to change its role.');
            }
            if ($usernameUser && ! $usernameUser->isSuperAdmin()) {
                throw new RuntimeException('The configured Super Admin username belongs to a non-Super Admin account; refusing to change its role.');
            }
            if ($user && $user->username !== $username) {
                throw new RuntimeException('The configured Super Admin email and username do not identify the same account.');
            }
            if ($usernameUser && $usernameUser->email !== $email) {
                throw new RuntimeException('The configured Super Admin username and email do not identify the same account.');
            }
            if ($user && $usernameUser && $user->isNot($usernameUser)) {
                throw new RuntimeException('The configured Super Admin email and username belong to different accounts.');
            }

            if (! $user && ! $usernameUser) {
                User::query()->create([
                    'name' => trim((string) env('SUPER_ADMIN_NAME', 'Super Admin')),
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'status' => 'active',
                    'user_type' => '00',
                    'role_id' => $role->id,
                    'is_password_changed' => false,
                ]);
            }
        });
    }
}
