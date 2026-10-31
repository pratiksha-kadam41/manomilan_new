<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('username');
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_password_changed')->default(false);
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique('email', 'users_email_unique');

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
