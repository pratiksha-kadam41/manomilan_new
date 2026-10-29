<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->timestamp('melava_registered_at')
                  ->nullable()
                  ->after('melava_batch');
        });
    }

    public function down(): void
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn('melava_registered_at');
        });
    }
};