<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'profile_creator_name')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->string('profile_creator_name')->nullable()->after('creator_relation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'profile_creator_name')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('profile_creator_name');
            });
        }
    }
};
