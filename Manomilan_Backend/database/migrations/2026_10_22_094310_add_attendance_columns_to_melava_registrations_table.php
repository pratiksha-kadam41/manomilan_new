<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->enum('attendance_status', ['present', 'not_present'])
                  ->default('not_present')
                  ->after('melava_batch');

            $table->timestamp('attended_at')
                  ->nullable()
                  ->after('attendance_status');
        });
    }

    public function down(): void
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn(['attendance_status', 'attended_at']);
        });
    }
};

