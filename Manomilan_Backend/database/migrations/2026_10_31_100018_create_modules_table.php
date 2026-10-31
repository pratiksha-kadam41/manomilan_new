<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('module_name', 100);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['application_id', 'module_name'], 'modules_application_id_module_name_unique');

            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
