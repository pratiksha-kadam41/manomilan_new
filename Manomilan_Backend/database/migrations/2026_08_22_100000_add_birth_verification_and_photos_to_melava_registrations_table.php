<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBirthVerificationAndPhotosToMelavaRegistrationsTable extends Migration
{
    public function up()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('melava_registrations', 'birth_verification_doc')) {
                $table->string('birth_verification_doc')->nullable()->after('biodata');
            }
            if (!Schema::hasColumn('melava_registrations', 'candidate_photos')) {
                $table->text('candidate_photos')->nullable()->after('birth_verification_doc');
            }
        });
    }

    public function down()
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('melava_registrations', 'birth_verification_doc')) {
                $table->dropColumn('birth_verification_doc');
            }
            if (Schema::hasColumn('melava_registrations', 'candidate_photos')) {
                $table->dropColumn('candidate_photos');
            }
        });
    }
}
