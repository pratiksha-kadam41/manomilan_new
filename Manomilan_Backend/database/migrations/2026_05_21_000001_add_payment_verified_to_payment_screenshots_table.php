<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentVerifiedToPaymentScreenshotsTable extends Migration
{
    public function up()
    {
        Schema::table('payment_screenshots', function (Blueprint $table) {
            $table->boolean('payment_verified')->default(false)->after('screenshot_path');
        });
    }

    public function down()
    {
        Schema::table('payment_screenshots', function (Blueprint $table) {
            $table->dropColumn('payment_verified');
        });
    }
}
