<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('melava_registrations');
    }

    public function down(): void
    {
        // The legacy denormalized table is intentionally not recreated.
    }
};
