<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMelavaPendingRegistrationsTable extends Migration
{
    public function up()
    {
        Schema::create('melava_pending_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Secure token used to resume the payment process
            $table->string('token', 64)->unique();

            // Candidate details
            $table->string('mobile', 10)->index();

            $table->boolean('is_existing_website_user')->default(false);

            // Existing website user/profile, if applicable
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();

            // All first-page form data and uploaded file paths
            $table->longText('registration_data');

            // pending or completed
            $table->string('status', 20)->default('pending')->index();

            // Optional expiry for abandoned registrations
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('melava_pending_registrations');
    }
}