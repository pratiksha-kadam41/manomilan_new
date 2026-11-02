<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('user_id');
                    $table->text('photo_upload');
                    $table->timestamps();
                    $table->string('updated_by')->nullable();
                    $table->index(['user_id', 'created_at'], 'payments_user_created_index');
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
