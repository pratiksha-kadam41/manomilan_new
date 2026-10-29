<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketStatusHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_status_history', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->unsignedBigInteger('ticket_id');

            $table->unsignedBigInteger('ticket_item_id')->nullable();

            $table->string('old_status', 30);

            $table->string('new_status', 30);

            $table->longText('remarks')->nullable();

            $table->unsignedBigInteger('changed_by')->nullable();

            $table->timestamp('created_at')->useCurrent();

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
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('ticket_id');
            $table->index('ticket_item_id');
            $table->index('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticket_status_history');
    }
}