<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
                    $table->id();
                    $table->string('app_code', 50)->unique('applications_app_code_unique');
                    $table->string('app_name', 100);
                    $table->text('description')->nullable();
                    $table->string('current_version', 20)->nullable();
                    $table->string('base_url')->nullable();
                    $table->string('api_key')->nullable();
                    $table->boolean('status')->default(true);
                    $table->timestamps();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
