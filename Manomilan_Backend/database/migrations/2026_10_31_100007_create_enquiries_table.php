<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('profilePhoto')->nullable();
            $table->string('melavaNo');
            $table->string('name');
            $table->string('address');
            $table->string('city');
            $table->string('taluka');
            $table->string('dist');
            $table->string('contactPersonName1');
            $table->string('contactPersonMobile1');
            $table->string('contactPersonName2')->nullable();
            $table->string('contactPersonMobile2')->nullable();
            $table->string('contactPersonName3')->nullable();
            $table->string('contactPersonMobile3')->nullable();
            $table->string('email')->nullable();
            $table->string('education')->nullable();
            $table->string('gender')->nullable();
            $table->date('DOB')->nullable();
            $table->time('time')->nullable();
            $table->string('place')->nullable();
            $table->string('height')->nullable();
            $table->string('potJat')->nullable();
            $table->string('parentName')->nullable();
            $table->string('job')->nullable();
            $table->decimal('monthlyIncome', 10,2)->nullable();
            $table->text('expectation')->nullable();
            $table->string('extraInformation')->nullable();
            $table->string('location')->nullable();
            $table->string('mobileNo', 15)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
