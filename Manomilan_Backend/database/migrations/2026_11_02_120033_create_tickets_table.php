<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
                    $table->id();
                    $table->string('ticket_no', 30)->unique('tickets_ticket_no_unique');
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
                    $table->index('ticket_status', 'tickets_ticket_status_index');
                    $table->index(['user_id', 'created_at'], 'tickets_user_created_index');
                    $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete()->cascadeOnUpdate();
                    $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete()->cascadeOnUpdate();
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
