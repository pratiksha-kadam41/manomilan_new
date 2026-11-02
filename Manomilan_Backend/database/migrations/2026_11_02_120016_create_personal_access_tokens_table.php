<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
                    $table->id();
                    $table->string('tokenable_type');
                    $table->unsignedBigInteger('tokenable_id');
                    $table->text('name');
                    $table->string('token', 64)->unique('personal_access_tokens_token_unique');
                    $table->text('abilities')->nullable();
                    $table->timestamp('last_used_at')->nullable();
                    $table->timestamp('expires_at')->nullable()->index('personal_access_tokens_expires_at_index');
                    $table->timestamps();
                    $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
