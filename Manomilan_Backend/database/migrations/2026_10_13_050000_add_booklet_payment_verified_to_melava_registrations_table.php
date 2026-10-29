<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBookletPaymentVerifiedToMelavaRegistrationsTable extends Migration
{
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('melava_registrations', 'booklet_payment_verified')) {
                $table->boolean('booklet_payment_verified')->default(false)->after('booklet_payment_screenshot');
            }
        });
    }

    public function down()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('melava_registrations', 'booklet_payment_verified')) {
                $table->dropColumn('booklet_payment_verified');
            }
        });
    }
}
