<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceOfInformationToMelavaRegistrationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('melava_registrations', 'source_of_information')) {
                $table->string('source_of_information')->nullable()->after('updated_by');
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
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('melava_registrations', 'source_of_information')) {
                $table->dropColumn('source_of_information');
            }
        });
    }
}
