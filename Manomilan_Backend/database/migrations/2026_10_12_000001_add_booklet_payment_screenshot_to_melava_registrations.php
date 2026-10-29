<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBookletPaymentScreenshotToMelavaRegistrations extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('melava_registrations', 'booklet_payment_screenshot')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                $table->string('booklet_payment_screenshot')->nullable()->after('payment_screenshot');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('melava_registrations', 'booklet_payment_screenshot')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                $table->dropColumn('booklet_payment_screenshot');
            });
        }
    }
}
