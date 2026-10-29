<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProfileIdToMelavaRegistrationsTable extends Migration
{
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_id')
                ->nullable()
                ->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn('profile_id');
        });
    }
}