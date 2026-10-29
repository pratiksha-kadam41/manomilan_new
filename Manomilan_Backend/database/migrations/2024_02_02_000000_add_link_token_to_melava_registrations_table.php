<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\MelavaRegistration;

class AddLinkTokenToMelavaRegistrationsTable extends Migration
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
                if (!Schema::hasColumn('melava_registrations', 'link_token')) {
                    $table->string('link_token', 64)->nullable()->after('updated_by');
                }
            });

            // Backfill existing records with a token
            $registrations = MelavaRegistration::whereNull('link_token')->get();
            foreach ($registrations as $registration) {
                $registration->link_token = Str::random(40);
                $registration->save();
            }
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
                $table->dropColumn('link_token');
            });
        }
    }
}
