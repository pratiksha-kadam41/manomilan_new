<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('melavaNo')->nullable();
            $table->string('profileImage')->nullable();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('candidateType')->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('stdCode', 50)->nullable();
            $table->string('stdNumber', 50)->nullable();
            $table->string('mobileNumber', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('education')->nullable();
            $table->string('jobType')->nullable();
            $table->string('workPlace')->nullable();
            $table->string('monthlyIncome', 100)->nullable();
            $table->date('DOB')->nullable();
            $table->string('birthTime', 50)->nullable();
            $table->string('birthPlace')->nullable();
            $table->string('height', 50)->nullable();
            $table->string('feet', 10)->nullable();
            $table->string('inch', 10)->nullable();
            $table->string('weight', 50)->nullable();
            $table->string('kgGram', 10)->nullable();
            $table->string('subCast')->nullable();
            $table->string('parentName')->nullable();
            $table->string('parentJobType')->nullable();
            $table->string('parentMonthlyIncome', 100)->nullable();
            $table->boolean('isGlasses')->nullable()->default(false);
            $table->boolean('isHoroscope')->nullable()->default(false);
            $table->boolean('isNonJain')->nullable()->default(false);
            $table->string('nonJainReligion', 100)->nullable();
            $table->string('whoNonJain', 100)->nullable();
            $table->text('expectation')->nullable();
            $table->string('extraName')->nullable();
            $table->string('extraCity')->nullable();
            $table->string('extraMobileNo', 50)->nullable();
            $table->string('skinColor', 50)->nullable();
            $table->string('bloodGroup', 10)->nullable();
            $table->string('motherTongue', 100)->nullable();
            $table->string('otherTongue', 100)->nullable();
            $table->integer('bortherMarriedNo')->nullable();
            $table->integer('bortherNotMarriedNo')->nullable();
            $table->integer('sisterMarriedNo')->nullable();
            $table->integer('sisterNotMarriedNo')->nullable();
            $table->string('companyName')->nullable();
            $table->string('companyDesignationAndNumber')->nullable();
            $table->text('hobbies')->nullable();
            $table->boolean('isAcknowledge')->nullable()->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
