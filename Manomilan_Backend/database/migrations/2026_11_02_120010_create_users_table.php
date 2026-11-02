<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id')->nullable()->index('users_profile_id_index');
                    $table->string('name');
                    $table->string('email')->unique('users_email_unique');
                    $table->timestamp('email_verified_at')->nullable();
                    $table->string('username')->index('users_username_index');
                    $table->string('password');
                    $table->enum('status', ['active', 'inactive'])->default('active');
                    $table->boolean('is_password_changed')->default(false);
                    $table->string('user_type')->nullable();
                    $table->unsignedBigInteger('role_id')->nullable()->index('users_role_id_index');
                    $table->rememberToken();
                    $table->timestamps();
                    $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
