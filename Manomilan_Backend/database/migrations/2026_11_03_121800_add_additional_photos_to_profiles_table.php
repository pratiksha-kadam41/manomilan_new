<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'additional_photos')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->json('additional_photos')->nullable()->after('photos');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'additional_photos')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('additional_photos');
            });
        }
    }
};
