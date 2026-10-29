<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'This development-only password reset migration may run only when APP_ENV=local.'
            );
        }

        DB::table('users')->update([
            'password' => Hash::make('password'),
        ]);
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This password reset is irreversible; restore the users table from a backup instead.'
        );
    }
};
