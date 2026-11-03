<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_registrations', function (Blueprint $table): void {
            $table->foreignId('workshop_event_id')
                ->nullable()
                ->after('id')
                ->constrained('workshop_events')
                ->nullOnDelete();
            $table->decimal('registration_fee', 10, 2)->nullable()->after('transaction_id');
            $table->char('registration_currency', 3)->nullable()->after('registration_fee');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_registrations', function (Blueprint $table): void {
            $table->dropForeign(['workshop_event_id']);
            $table->dropColumn(['workshop_event_id', 'registration_fee', 'registration_currency']);
        });
    }
};
