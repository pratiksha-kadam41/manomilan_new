<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->enum('identificationProof_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('birthDateProof_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('addressProof_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('educationProof_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('professionProof_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('incomeProof_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('casteCertificate_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('medicalFitnessCertificate_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->enum('selfieVerification_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
            $table->text('identificationProof_remarks')->nullable();
            $table->text('birthDateProof_remarks')->nullable();
            $table->text('addressProof_remarks')->nullable();
            $table->text('educationProof_remarks')->nullable();
            $table->text('professionProof_remarks')->nullable();
            $table->text('incomeProof_remarks')->nullable();
            $table->text('casteCertificate_remarks')->nullable();
            $table->text('medicalFitnessCertificate_remarks')->nullable();
            $table->text('selfieVerification_remarks')->nullable();
            $table->timestamps();

            $table->unique('profile_id', 'document_verifications_profile_id_unique');

            $table->foreign('profile_id')->references('id')->on('profiles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
