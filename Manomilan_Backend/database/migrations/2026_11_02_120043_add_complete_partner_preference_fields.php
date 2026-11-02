<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_partner_preferences', function (Blueprint $table): void {
            $table->string('preferred_religion')->nullable()->after('profile_id');
            $table->string('preferred_sub_caste')->nullable()->after('preferred_caste');
            $table->string('working_partner')->nullable()->after('preferred_working_field');
            $table->string('expected_income')->nullable()->after('working_partner');
            $table->string('family_type')->nullable()->after('expected_income');
            $table->string('financial_background')->nullable()->after('family_type');
            $table->string('family_values')->nullable()->after('financial_background');
            $table->string('preferred_country')->nullable()->after('preferred_location');
            $table->string('preferred_state')->nullable()->after('preferred_country');
            $table->string('preferred_city')->nullable()->after('preferred_state');
        });
    }

    public function down(): void
    {
        Schema::table('profile_partner_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'preferred_religion',
                'preferred_sub_caste',
                'working_partner',
                'expected_income',
                'family_type',
                'financial_background',
                'family_values',
                'preferred_country',
                'preferred_state',
                'preferred_city',
            ]);
        });
    }
};
