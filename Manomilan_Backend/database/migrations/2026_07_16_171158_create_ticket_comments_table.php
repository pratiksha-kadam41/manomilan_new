<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_comments', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->unsignedBigInteger('ticket_id');

            $table->unsignedBigInteger('ticket_item_id')->nullable();

            $table->string('comment_by', 30);

            $table->unsignedBigInteger('user_id');

            $table->longText('comment');

            $table->string('attachment')->nullable();

            $table->boolean('is_internal')->default(false);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Foreign Keys
            |--------------------------------------------------------------------------
            */

            $table->foreign('ticket_id')
                  ->references('id')
                  ->on('tickets')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->foreign('ticket_item_id')
                  ->references('id')
                  ->on('ticket_items')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | Uncomment if user_id references users table
            |--------------------------------------------------------------------------
            */

            /*
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');
            */

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('ticket_id');
            $table->index('ticket_item_id');
            $table->index('user_id');
            $table->index('comment_by');
            $table->index('is_internal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticket_comments');
    }
}