<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_requests', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('candidate_requests');
    }
};
