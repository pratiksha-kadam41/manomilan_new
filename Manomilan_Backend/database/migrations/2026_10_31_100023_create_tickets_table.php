<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 30);
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('user_id');
            $table->string('subject');
            $table->longText('description');
            $table->string('category', 50);
            $table->enum('ticket_status', ['Open', 'In Progress', 'Resolved', 'Closed'])->nullable()->default('Open');
            $table->string('attachment')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique('ticket_no', 'tickets_ticket_no_unique');
            $table->index('ticket_status', 'tickets_ticket_status_index');

            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
