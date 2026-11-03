<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoBrideSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'ananya.sharma.demo@manomilan.local';

        DB::table('profiles')->updateOrInsert(
            ['email' => $email],
            [
                'reg_id' => 'MM-DEMO-BRIDE-001',
                'groomorbride' => 'Bride',
                'full_name' => 'Ananya Sharma',
                'email' => $email,
                'phone' => '9000000001',
                'birth_date' => '1998-06-15',
                'height_cm' => 162,
                'weight_kg' => 54,
                'has_spectacles' => false,
                'blood_group' => 'B+',
                'mother_tongue' => 'Hindi',
                'languages' => json_encode(['Hindi', 'English', 'Marathi']),
                'about_me' => 'Family-oriented, career-focused, and enjoys travel, reading, and music.',
                'marital_status' => 'Never Married',
                'annual_income' => 900000,
                'birth_place' => 'Pune, Maharashtra',
                'addresses' => json_encode([
                    'current' => [
                        'country' => 'India',
                        'state' => 'Maharashtra',
                        'city' => 'Pune',
                    ],
                ]),
                'verification_status' => 'Completed',
                'status' => 'active',
                'profile_completion' => 100,
                'photos' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->command?->info('Demo bride profile seeded: Ananya Sharma.');
    }
}
