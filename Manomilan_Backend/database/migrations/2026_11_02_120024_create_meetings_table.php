<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('candidate_id')->nullable()->index('meetings_candidate_date_index');
                    $table->string('place')->nullable();
                    $table->date('date');
                    $table->time('time_slot');
                    $table->string('spoke_to_matched_parents')->nullable();
                    $table->tinyInteger('spoke_to_candidate')->default(0)->comment('0 - no, 1 - yes ');
                    $table->unsignedBigInteger('other_candidate_id')->nullable()->index('meetings_other_candidate_id_index');
                    $table->string('day')->nullable();
                    $table->tinyInteger('spoke_to_matched_profile')->default(0)->comment('0 - no, 1 - yes');
                    $table->string('spoke_to_cand_parents')->nullable();
                    $table->string('remark')->nullable();
                    $table->timestamps();
                    $table->index('created_at', 'meetings_created_at_index');
                    $table->index(['candidate_id', 'date'], 'meetings_candidate_date_composite_index');
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
