<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHasMedicalHistoryToProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('profiles', 'hasMedicalHistory')) {
                // Small width keeps the already near-limit InnoDB row size in check.
                $table->string('hasMedicalHistory', 3)->nullable()->after('medications');
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
            if (Schema::hasColumn('profiles', 'hasMedicalHistory')) {
                $table->dropColumn('hasMedicalHistory');
            }
        });
    }
}
