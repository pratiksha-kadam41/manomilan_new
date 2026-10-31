<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('ticket_item_id')->nullable();
            $table->string('comment_by', 30);
            $table->unsignedBigInteger('user_id');
            $table->longText('comment');
            $table->string('attachment')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index('user_id', 'ticket_comments_user_id_index');
            $table->index('comment_by', 'ticket_comments_comment_by_index');
            $table->index('is_internal', 'ticket_comments_is_internal_index');

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('ticket_item_id')->references('id')->on('ticket_items')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};
