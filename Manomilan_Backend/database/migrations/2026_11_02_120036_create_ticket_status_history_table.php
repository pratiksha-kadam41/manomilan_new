<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_status_history', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('ticket_id');
                    $table->unsignedBigInteger('ticket_item_id')->nullable();
                    $table->string('old_status', 30);
                    $table->string('new_status', 30);
                    $table->longText('remarks')->nullable();
                    $table->unsignedBigInteger('changed_by')->nullable();
                    $table->timestamp('created_at')->useCurrent();
                    $table->index(['ticket_id', 'created_at'], 'ticket_history_ticket_created_index');
                    $table->index(['ticket_item_id', 'created_at'], 'ticket_history_item_created_index');
                    $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
                    $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete()->cascadeOnUpdate();
                    $table->foreign('ticket_item_id')->references('id')->on('ticket_items')->cascadeOnDelete()->cascadeOnUpdate();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_history');
    }
};
