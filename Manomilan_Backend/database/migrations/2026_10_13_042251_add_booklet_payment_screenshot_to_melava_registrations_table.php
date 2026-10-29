<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBookletPaymentScreenshotToMelavaRegistrationsTable extends Migration
{
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->string('booklet_payment_screenshot')->nullable();
        });
    }

    public function down()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn('booklet_payment_screenshot');
        });
    }
}