<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPermanentAddressAndBirthtimeAmpmToProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('profiles', 'permanent_address')) {
                $table->text('permanent_address')->nullable();
            }
            if (!Schema::hasColumn('profiles', 'birthTime_ampm')) {
                $table->string('birthTime_ampm')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['permanent_address', 'birthTime_ampm']);
        });
    }
}
