<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentVerifiedToMelavaRegistrationsTable extends Migration
{
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->boolean('payment_verified')->default(false)->after('payment_screenshot');
        });
    }

    public function down()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn('payment_verified');
        });
    }
}