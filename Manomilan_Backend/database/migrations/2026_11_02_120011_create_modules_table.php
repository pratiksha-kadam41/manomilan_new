<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('application_id');
                    $table->string('module_name', 100);
                    $table->string('module_key', 120)->nullable()->unique('modules_module_key_unique');
                    $table->string('route', 255)->nullable();
                    $table->text('frontend_routes')->nullable();
                    $table->string('icon', 100)->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->text('api_prefixes')->nullable();
                    $table->text('description')->nullable();
                    $table->boolean('status')->default(true);
                    $table->timestamps();
                    $table->unique(['application_id', 'module_name'], 'modules_application_id_module_name_unique');
                    $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete()->cascadeOnUpdate();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
