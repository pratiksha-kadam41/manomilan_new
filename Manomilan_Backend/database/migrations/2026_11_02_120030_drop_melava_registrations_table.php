<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The legacy table is still used for compatibility and must be preserved.
    }

    public function down(): void
    {
        // No schema change is performed by this compatibility migration.
    }
};
