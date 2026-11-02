<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;

class ProfileSchema
{
    public static function define(Blueprint $table): void
    {
        $table->id();
        $table->string('reg_id')->nullable();
        $table->enum('groomorbride', ['Groom', 'Bride']);
        $table->string('full_name');
        $table->string('email')->nullable();
        $table->string('phone', 20)->nullable();
        $table->date('birth_date')->nullable();
        $table->decimal('height_cm', 5, 2)->nullable();
        $table->decimal('weight_kg', 5, 2)->nullable();
        $table->boolean('has_spectacles')->nullable();
        $table->string('blood_group', 5)->nullable();
        $table->string('mother_tongue', 100)->nullable();
        $table->json('languages')->nullable();
        $table->text('special_cases')->nullable();
        $table->text('about_me')->nullable();
        $table->json('photos')->nullable();
        $table->decimal('monthly_income', 15, 2)->nullable();
        $table->decimal('annual_income', 15, 2)->nullable();
        $table->text('personal_medical_history')->nullable();
        $table->text('current_medical_problems')->nullable();
        $table->text('medications')->nullable();
        $table->string('birth_place')->nullable();
        $table->time('birth_time')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->string('creator_relation', 100)->nullable();
        $table->json('addresses')->nullable();
        $table->enum('verification_status', ['Pending', 'Completed'])->default('Pending');
        $table->enum('status', ['active', 'deactive'])->default('active');
        $table->timestamp('created_at')->nullable()->useCurrent();
        $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

        $table->index('full_name', 'idx_full_name');
        $table->index('phone', 'idx_phone');
        $table->index('birth_date', 'idx_birth_date');
        $table->index('groomorbride', 'idx_gender');
        $table->index('status', 'idx_status');
        $table->index('created_by', 'idx_created_by');
    }
}
