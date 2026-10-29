<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_items', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->unsignedBigInteger('ticket_id');

            $table->string('attachment')->nullable();

            $table->string('title');
            $table->longText('description')->nullable();

            $table->string('item_type', 30);

            $table->string('priority', 20);

            $table->string('severity', 20);

            $table->string('ticket_status', 30)->default('Open');

            $table->boolean('status')->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Foreign Key
            |--------------------------------------------------------------------------
            */

            $table->foreign('ticket_id')
                  ->references('id')
                  ->on('tickets')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('ticket_id');
            $table->index('item_type');
            $table->index('priority');
            $table->index('severity');
            $table->index('ticket_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticket_items');
    }
}