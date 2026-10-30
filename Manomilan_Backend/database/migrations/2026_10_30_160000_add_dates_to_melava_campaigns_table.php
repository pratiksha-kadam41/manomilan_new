<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('melava_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('melava_campaigns', 'start_date')) {
                $table->date('start_date')->nullable()->after('description')->index();
            }
            if (! Schema::hasColumn('melava_campaigns', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('melava_campaigns', function (Blueprint $table) {
            foreach (['start_date', 'end_date'] as $column) {
                if (Schema::hasColumn('melava_campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
