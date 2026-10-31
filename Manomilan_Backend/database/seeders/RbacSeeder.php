<?php

namespace Database\Seeders;

use App\Module;
use App\Models\ModulePermission;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = [
                '00' => 'Super Admin',
                '01' => 'Admin',
                '21' => 'Candidate',
            ];

            foreach ($roles as $userType => $name) {
                $role = Role::query()->where('user_type', $userType)->first();
                if ($role) {
                    if (strtolower(preg_replace('/[^a-z]/', '', (string) $role->name))
                        !== strtolower(preg_replace('/[^a-z]/', '', $name))) {
                        throw new RuntimeException("Role user_type {$userType} is already assigned to another role.");
                    }

                    if (! $role->is_active) {
                        $role->is_active = true;
                        $role->save();
                    }

                    continue;
                }

                if (Role::query()->whereRaw(
                    "REPLACE(LOWER(name), ' ', '') = ?",
                    [strtolower(preg_replace('/[^a-z]/', '', $name))]
                )->exists()) {
                    throw new RuntimeException("The {$name} role already exists with a different user_type.");
                }

                Role::query()->create([
                    'name' => $name,
                    'user_type' => $userType,
                    'is_active' => true,
                ]);
            }

            $adminRole = Role::query()->where('user_type', '01')->firstOrFail();
            $adminPermissions = array_fill_keys(array_values(ModulePermission::ACTIONS), true);
            $adminPermissions[ModulePermission::ACTIONS['manage']] = false;

            foreach (Module::query()->pluck('id') as $moduleId) {
                ModulePermission::query()->updateOrCreate(
                    ['role_id' => $adminRole->id, 'module_id' => $moduleId],
                    $adminPermissions
                );
            }
        });
    }
}
