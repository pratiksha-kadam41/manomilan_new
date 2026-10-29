<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentScreenshotToMelavaRegistrationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->string('payment_screenshot')->nullable()->after('source_of_information');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn('payment_screenshot');
        });
    }
}
