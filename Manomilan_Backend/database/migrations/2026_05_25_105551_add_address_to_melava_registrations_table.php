<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddressToMelavaRegistrationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('melava_registrations')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                $table->text('address')->nullable()->after('city');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('melava_registrations')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }
}
