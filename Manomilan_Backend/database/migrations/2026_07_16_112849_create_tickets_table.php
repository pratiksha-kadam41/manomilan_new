<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->string('ticket_no', 30)->unique();

            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('user_id');

            $table->string('subject');
            $table->longText('description');

            $table->string('category', 50);

            $table->string('ticket_status', 30)->default('Open');

            $table->string('attachment')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Foreign Keys
            |--------------------------------------------------------------------------
            */

            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->foreign('module_id')
                  ->references('id')
                  ->on('modules')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('ticket_no');
            $table->index('application_id');
            $table->index('module_id');
            $table->index('user_id');
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
        Schema::dropIfExists('tickets');
    }
}