<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('profiles', 'country')) {
                $table->string('country')->nullable()->after('permanent_address');
            }
            if (! Schema::hasColumn('profiles', 'state')) {
                $table->string('state')->nullable()->after('country');
            }
            if (! Schema::hasColumn('profiles', 'district')) {
                $table->string('district')->nullable()->after('state');
            }
            if (! Schema::hasColumn('profiles', 'taluka')) {
                $table->string('taluka')->nullable()->after('district');
            }
            if (! Schema::hasColumn('profiles', 'village')) {
                $table->string('village')->nullable()->after('taluka');
            }
            if (! Schema::hasColumn('profiles', 'pincode')) {
                $table->string('pincode', 20)->nullable()->after('village');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            foreach (['pincode', 'village', 'taluka', 'district', 'state', 'country'] as $column) {
                if (Schema::hasColumn('profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
