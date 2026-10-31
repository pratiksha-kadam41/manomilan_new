<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('attachment')->nullable();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('item_type', 30);
            $table->string('priority', 20);
            $table->string('severity', 20);
            $table->enum('ticket_status', ['Open', 'In Progress', 'Resolved', 'Closed'])->nullable()->default('Open');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('item_type', 'ticket_items_item_type_index');
            $table->index('priority', 'ticket_items_priority_index');
            $table->index('severity', 'ticket_items_severity_index');
            $table->index('ticket_status', 'ticket_items_ticket_status_index');

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_items');
    }
};
