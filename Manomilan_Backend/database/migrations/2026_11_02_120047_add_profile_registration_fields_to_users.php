<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('users', 'birth_date') ? 'birth_date' : null,
                Schema::hasColumn('users', 'gender') ? 'gender' : null,
            ]);

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
