<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentScreenshotsTable extends Migration
{
    public function up()
    {
        Schema::create('payment_screenshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id');
            $table->string('screenshot_path');
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('profile_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_screenshots');
    }
}
