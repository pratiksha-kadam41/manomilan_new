<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('profiles', 'source_of_information')) {
                $table->string('source_of_information')->nullable()->after('email');
            }
            if (! Schema::hasColumn('profiles', 'marital_status')) {
                $table->string('marital_status', 50)->nullable()->after('birth_date');
            }
            if (! Schema::hasColumn('profiles', 'has_medical_history')) {
                $table->boolean('has_medical_history')->nullable()->after('medications');
            }
            if (! Schema::hasColumn('profiles', 'profile_creator_name')) {
                $table->string('profile_creator_name')->nullable()->after('creator_relation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('profiles', 'source_of_information') ? 'source_of_information' : null,
                Schema::hasColumn('profiles', 'marital_status') ? 'marital_status' : null,
                Schema::hasColumn('profiles', 'has_medical_history') ? 'has_medical_history' : null,
                Schema::hasColumn('profiles', 'profile_creator_name') ? 'profile_creator_name' : null,
            ]);
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
