<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'hasMedicalHistory')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('hasMedicalHistory', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'hasMedicalHistory')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('hasMedicalHistory');
            });
        }
    }
};
