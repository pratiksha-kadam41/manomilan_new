<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('success_stories', function (Blueprint $table) {
            $table->id();
            $table->string('groom_name');
            $table->string('bride_name');
            $table->date('marriage_date')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('groom_photo')->nullable();
            $table->string('bride_photo')->nullable();
            $table->string('couple_photo')->nullable();
            $table->string('wedding_photo')->nullable();
            $table->string('story_title');
            $table->text('short_description')->nullable();
            $table->longText('full_story')->nullable();
            $table->text('family_message')->nullable();
            $table->tinyInteger('rating')->default(5);
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('success_stories');
    }
};
