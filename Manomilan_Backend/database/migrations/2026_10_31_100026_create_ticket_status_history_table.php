<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('ticket_item_id')->nullable();
            $table->string('old_status', 30);
            $table->string('new_status', 30);
            $table->longText('remarks')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('ticket_item_id')->references('id')->on('ticket_items')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_history');
    }
};
