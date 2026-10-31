<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->string('place')->nullable();
            $table->date('date');
            $table->time('time_slot');
            $table->string('spoke_to_matched_parents')->nullable();
            $table->tinyInteger('spoke_to_candidate')->default(0)->comment('0 - no, 1 - yes ');
            $table->unsignedBigInteger('other_candidate_id')->nullable();
            $table->string('day')->nullable();
            $table->tinyInteger('spoke_to_matched_profile')->default(0)->comment('0 - no, 1 - yes');
            $table->string('spoke_to_cand_parents')->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
