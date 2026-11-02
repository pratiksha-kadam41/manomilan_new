<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('candidate_requests');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_resets');
    }

    public function down(): void
    {
        if (! Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table): void {
                $table->string('email');
                $table->string('token');
                $table->timestamp('created_at')->nullable();
                $table->index('email', 'password_resets_email_index');
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('tokenable_type');
                $table->unsignedBigInteger('tokenable_id');
                $table->text('name');
                $table->string('token', 64);
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique('token', 'personal_access_tokens_token_unique');
                $table->index(
                    ['tokenable_type', 'tokenable_id'],
                    'personal_access_tokens_tokenable_type_tokenable_id_index'
                );
                $table->index('expires_at', 'personal_access_tokens_expires_at_index');
            });
        }

        if (! Schema::hasTable('candidates')) {
            Schema::create('candidates', function (Blueprint $table): void {
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

        if (! Schema::hasTable('candidate_requests')) {
            Schema::create('candidate_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('birth_date')->nullable();
                $table->time('birth_time')->nullable();
                $table->string('height', 50)->nullable();
                $table->string('marital_status', 50);
                $table->string('education')->nullable();
                $table->text('address')->nullable();
                $table->string('subcast', 100)->nullable();
                $table->string('occupation', 150)->nullable();
                $table->string('annual_income')->nullable();
                $table->string('married_brothers')->nullable()->default('0');
                $table->string('unmarried_brothers')->nullable()->default('0');
                $table->string('married_sisters')->nullable()->default('0');
                $table->string('unmarried_sisters')->nullable()->default('0');
                $table->text('expectations')->nullable();
                $table->boolean('status')->nullable()->default(true)->comment('1 = Active, 0 = Inactive');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                $table->string('image')->nullable();
                $table->boolean('terms_accepted')->nullable()->default(false);
            });
        }
    }
};
