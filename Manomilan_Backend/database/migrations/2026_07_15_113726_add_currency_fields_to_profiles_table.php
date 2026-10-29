<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrencyFieldsToProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('income_currency', 10)->default('INR')->after('annualIncome');
            $table->decimal('original_monthly_income', 15, 2)->nullable()->after('income_currency');
            $table->decimal('original_annual_income', 15, 2)->nullable()->after('original_monthly_income');
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
            $table->dropColumn(['income_currency', 'original_monthly_income', 'original_annual_income']);
        });
    }
}
