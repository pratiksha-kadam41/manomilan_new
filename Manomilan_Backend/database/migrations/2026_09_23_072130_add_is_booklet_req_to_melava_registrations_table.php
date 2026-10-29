<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsBookletReqToMelavaRegistrationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('melava_registrations', 'is_booklet_req')) {
                $table->boolean('is_booklet_req')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('melava_registrations', 'is_booklet_req')) {
                $table->dropColumn('is_booklet_req');
            }
        });
    }
}