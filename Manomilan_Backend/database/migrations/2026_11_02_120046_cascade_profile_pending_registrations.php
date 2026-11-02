<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::getConnection()->getDriverName() !== 'mysql'
            || ! Schema::hasTable('melava_pending_registrations')
        ) {
            return;
        }

        Schema::table('melava_pending_registrations', static function (Blueprint $table): void {
            $table->dropForeign('melava_pending_profile_id_foreign');
            $table->foreign('profile_id', 'melava_pending_profile_id_foreign')
                ->references('id')
                ->on('profiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (
            Schema::getConnection()->getDriverName() !== 'mysql'
            || ! Schema::hasTable('melava_pending_registrations')
        ) {
            return;
        }

        Schema::table('melava_pending_registrations', static function (Blueprint $table): void {
            $table->dropForeign('melava_pending_profile_id_foreign');
            $table->foreign('profile_id', 'melava_pending_profile_id_foreign')
                ->references('id')
                ->on('profiles')
                ->nullOnDelete();
        });
    }
};
