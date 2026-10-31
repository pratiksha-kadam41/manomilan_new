<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // This migration follows the dynamic RBAC migration that creates these module keys.
        DB::table('modules')
            ->where('module_key', 'reports_data_export')
            ->update(['api_prefixes' => json_encode(['/reports'])]);

        DB::table('modules')
            ->where('module_key', 'admin_dashboard')
            ->update(['api_prefixes' => json_encode(['/dashboard'])]);
    }

    public function down(): void
    {
        DB::table('modules')
            ->where('module_key', 'reports_data_export')
            ->update(['api_prefixes' => json_encode(['/dashboard'])]);
    }
};
