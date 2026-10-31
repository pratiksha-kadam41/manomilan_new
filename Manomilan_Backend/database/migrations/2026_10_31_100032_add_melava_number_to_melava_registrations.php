<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('melava_registrations')
            || Schema::hasColumn('melava_registrations', 'melava_number')) {
            return;
        }

        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->string('melava_number', 20)->nullable()->after('melava_title');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('melava_registrations')
            || ! Schema::hasColumn('melava_registrations', 'melava_number')) {
            return;
        }

        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn('melava_number');
        });
    }
};
