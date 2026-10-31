<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melava_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->boolean('is_existing_website_user')->default(false);
            $table->string('fullName')->nullable();
            $table->string('groomOrBride')->nullable();
            $table->date('birthDate')->nullable();
            $table->string('username')->comment('mobile number');
            $table->string('maritalStatus')->nullable();
            $table->string('caste')->nullable();
            $table->string('subCaste')->nullable();
            $table->string('education')->nullable();
            $table->text('profession');
            $table->text('city')->comment('professional City');
            $table->text('address')->nullable();
            $table->string('height')->nullable();
            $table->string('parents_contact')->nullable()->comment('reference Contact Number');
            $table->string('fathersName')->nullable();
            $table->string('mothersName')->nullable();
            $table->integer('noOfBrothers')->nullable();
            $table->integer('noOfSisters')->nullable();
            $table->integer('age_from')->nullable()->comment('preferred_age_from');
            $table->integer('age_to')->nullable()->comment('preferred_age_to');
            $table->string('height_from')->nullable()->comment('preferred_height_from');
            $table->string('height_to')->nullable()->comment('preferred_height_to');
            $table->string('preferred_education')->nullable()->comment('preferred_education');
            $table->string('preferred_working_field')->nullable()->comment('preferred occupation');
            $table->string('preferred_caste')->nullable()->comment('preferred_caste');
            $table->string('preferred_location')->nullable()->comment('preferred_location');
            $table->text('other_expectations')->comment('other_expectations');
            $table->text('biodata')->nullable();
            $table->string('birth_verification_doc')->nullable();
            $table->text('candidate_photos')->nullable();
            $table->string('link_token', 64)->nullable();
            $table->timestamps();
            $table->string('updated_by')->nullable();
            $table->string('source_of_information')->nullable();
            $table->string('payment_screenshot')->nullable();
            $table->boolean('payment_verified')->default(false);
            $table->string('melava_batch', 20)->nullable();
            $table->timestamp('melava_registered_at')->nullable();
            $table->boolean('is_booklet_req')->default(false);
            $table->string('booklet_payment_screenshot')->nullable();
            $table->boolean('booklet_payment_verified')->default(false);
            $table->enum('attendance_status', ['present', 'not_present'])->default('not_present');
            $table->timestamp('attended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('melava_registrations');
    }
};
