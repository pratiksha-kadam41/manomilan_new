<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuccessStoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('success_stories', function (Blueprint $table) {

            $table->bigIncrements('id');

            // Couple Information
            $table->string('groom_name');
            $table->string('bride_name');
            $table->date('marriage_date')->nullable();

            // Location
            $table->string('city')->nullable();
            $table->string('state')->nullable();

            // Images
            $table->string('groom_photo')->nullable();
            $table->string('bride_photo')->nullable();
            $table->string('couple_photo')->nullable();
            $table->string('wedding_photo')->nullable();

            // Story Details
            $table->string('story_title');
            $table->text('short_description')->nullable();
            $table->longText('full_story')->nullable();

            // Family Message
            $table->text('family_message')->nullable();

            // Rating (1 to 5)
            $table->tinyInteger('rating')->default(5);

            // Display Settings
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);

            // Status
            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('success_stories');
    }
}