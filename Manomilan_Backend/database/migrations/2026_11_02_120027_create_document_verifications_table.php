<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id')->unique('document_verifications_profile_id_unique');
                    foreach ([
                        'identificationProof',
                        'birthDateProof',
                        'addressProof',
                        'educationProof',
                        'professionProof',
                        'incomeProof',
                        'casteCertificate',
                        'medicalFitnessCertificate',
                        'selfieVerification',
                    ] as $key) {
                        $table->enum($key.'_status', ['pending', 'verified', 'rejected', 'not_eligible'])->default('pending');
                        $table->text($key.'_remarks')->nullable();
                    }
                    $table->timestamps();
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
