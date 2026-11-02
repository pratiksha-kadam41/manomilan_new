<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ApplicationModuleSeeder::class,
            WebsiteManagementModuleSeeder::class,
            RbacSeeder::class,
            CandidateRoleAccessSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
