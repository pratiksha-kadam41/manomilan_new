<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement('ALTER TABLE profiles ROW_FORMAT=DYNAMIC');
        }

        foreach ([
            'source_of_information',
            'registration_type',
            'caste',
            'subCaste',
            'motherTongue',
            'language',
            'education',
            'religion',
            'horoscopeCaste',
            'horoscopeSubCaste',
            'rashi',
            'nakshatra',
            'charan',
            'gan',
            'gotra',
            'nadi',
            'identificationProof',
            'birthDateProof',
            'addressProof',
            'educationProof',
            'professionProof',
            'incomeProof',
            'casteCertificate',
            'medicalFitnessCertificate',
        ] as $proofColumn) {
            Schema::getConnection()->statement(
                "ALTER TABLE profiles MODIFY COLUMN `{$proofColumn}` VARCHAR(191) NULL"
            );
        }

        Schema::getConnection()->statement(
            'ALTER TABLE profiles MODIFY COLUMN cookingSkills TEXT NULL'
        );

        if (! Schema::hasColumn('profiles', 'full_body_photo')) {
            Schema::getConnection()->statement('ALTER TABLE profiles ADD COLUMN full_body_photo VARCHAR(255) NULL');
        }
        if (! Schema::hasColumn('profiles', 'half_body_photo')) {
            Schema::getConnection()->statement('ALTER TABLE profiles ADD COLUMN half_body_photo VARCHAR(255) NULL');
        }
        if (! Schema::hasColumn('profiles', 'selfie_photo')) {
            Schema::getConnection()->statement('ALTER TABLE profiles ADD COLUMN selfie_photo VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['full_body_photo', 'half_body_photo', 'selfie_photo']);
        });
    }
};
