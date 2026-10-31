<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('user_type');
                $table->timestamps();

                $table->unique('name', 'roles_name_unique');
                $table->unique('user_type', 'roles_user_type_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
