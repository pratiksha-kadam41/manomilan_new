<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMelavaBatchToProfilesAndMelavaRegistrationsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('profiles', 'melava_batch')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('melava_batch', 20)->nullable();
            });
        }

        if (!Schema::hasColumn('melava_registrations', 'melava_batch')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                $table->string('melava_batch', 20)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('melava_registrations', 'melava_batch')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                $table->dropColumn('melava_batch');
            });
        }

        if (Schema::hasColumn('profiles', 'melava_batch')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('melava_batch');
            });
        }
    }
}
