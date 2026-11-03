<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'profile_completion')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->unsignedTinyInteger('profile_completion')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'profile_completion')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('profile_completion');
            });
        }
    }
};
