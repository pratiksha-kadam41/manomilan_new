<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('user_name_snapshot')->nullable();
                    $table->string('action', 100);
                    $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
                    $table->string('module_name_snapshot')->nullable();
                    $table->string('entity_type', 120)->nullable();
                    $table->string('entity_id', 120)->nullable();
                    $table->string('candidate_id', 120)->nullable();
                    $table->json('old_values')->nullable();
                    $table->json('new_values')->nullable();
                    $table->text('description')->nullable();
                    $table->string('ip_address', 45)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->string('request_method', 12)->nullable();
                    $table->text('request_url')->nullable();
                    $table->timestamps();
                    $table->index(['created_at', 'action']);
                    $table->index(['user_id', 'created_at']);
                    $table->index(['candidate_id', 'created_at']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
