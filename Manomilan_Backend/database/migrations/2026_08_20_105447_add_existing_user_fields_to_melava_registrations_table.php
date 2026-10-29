<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExistingUserFieldsToMelavaRegistrationsTable extends Migration
{
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->after('id');

            $table->boolean('is_existing_website_user')
                ->default(false)
                ->after('user_id');
        });
    }   

    public function down()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'user_id',
                'is_existing_website_user'
            ]);
        });
    }
}