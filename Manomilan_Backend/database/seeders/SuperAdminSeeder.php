<?php

namespace Database\Seeders;

use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('SUPER_ADMIN_EMAIL', ''));
        $password = (string) env('SUPER_ADMIN_PASSWORD', '');

        if ($email === '' && $password === '') {
            $this->command?->warn(
                'Super Admin role and permissions are seeded. Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD to seed a Super Admin login.'
            );

            return;
        }

        if ($email === '' || $password === '') {
            throw new RuntimeException('Both SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD must be configured to seed a Super Admin login.');
        }

        $role = Role::where('user_type', '00')->firstOrFail();
        $user = User::where('email', $email)->first();
        if ($user && ! $user->isSuperAdmin()) {
            throw new RuntimeException('The configured SUPER_ADMIN_EMAIL belongs to a non-Super Admin account; refusing to change its role.');
        }

        $user ??= new User();
        $user->fill([
            'name' => trim((string) env('SUPER_ADMIN_NAME', 'Super Admin')),
            'email' => $email,
            'username' => trim((string) env('SUPER_ADMIN_USERNAME', 'superadmin')),
            'password' => $password,
            'status' => 'active',
            'user_type' => '00',
            'is_password_changed' => false,
        ]);
        $user->role()->associate($role);
        $user->save();
    }
}
