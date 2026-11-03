<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_registrations', function (Blueprint $table): void {
            $table->string('legacy_transaction_id')->nullable()->after('transaction_id');
        });

        DB::table('workshop_registrations')
            ->whereRaw('CHAR_LENGTH(transaction_id) > 22')
            ->update(['legacy_transaction_id' => DB::raw('transaction_id')]);

        DB::table('workshop_registrations')
            ->whereRaw('CHAR_LENGTH(transaction_id) > 22')
            ->update(['transaction_id' => DB::raw('LEFT(transaction_id, 22)')]);

        Schema::table('workshop_registrations', function (Blueprint $table): void {
            $table->string('transaction_id', 22)->change();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_registrations', function (Blueprint $table): void {
            $table->string('transaction_id')->change();
        });

        DB::table('workshop_registrations')
            ->whereNotNull('legacy_transaction_id')
            ->update(['transaction_id' => DB::raw('legacy_transaction_id')]);

        Schema::table('workshop_registrations', function (Blueprint $table): void {
            $table->dropColumn('legacy_transaction_id');
        });
    }
};
