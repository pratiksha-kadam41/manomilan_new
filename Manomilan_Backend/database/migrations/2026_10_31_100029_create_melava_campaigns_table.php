<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('melava_campaigns')) {
            Schema::create('melava_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('melava_code', 20)->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->char('currency', 3)->default('INR');
                $table->decimal('registration_fee', 10, 2)->default(0);
                $table->boolean('booklet_available')->default(false);
                $table->decimal('booklet_fee', 10, 2)->default(0);
                $table->string('qr_image_path')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['status', 'start_date']);
            });
        }

        if (Schema::hasTable('melava_registrations')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                if (! Schema::hasColumn('melava_registrations', 'melava_campaign_id')) {
                    $table->unsignedBigInteger('melava_campaign_id')->nullable()->index();
                }
                if (! Schema::hasColumn('melava_registrations', 'melava_title')) {
                    $table->string('melava_title')->nullable();
                }
                if (! Schema::hasColumn('melava_registrations', 'registration_fee')) {
                    $table->decimal('registration_fee', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('melava_registrations', 'booklet_fee')) {
                    $table->decimal('booklet_fee', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('melava_registrations', 'total_amount')) {
                    $table->decimal('total_amount', 10, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('melava_registrations')) {
            Schema::table('melava_registrations', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    Schema::hasColumn('melava_registrations', 'melava_campaign_id') ? 'melava_campaign_id' : null,
                    Schema::hasColumn('melava_registrations', 'melava_title') ? 'melava_title' : null,
                    Schema::hasColumn('melava_registrations', 'registration_fee') ? 'registration_fee' : null,
                    Schema::hasColumn('melava_registrations', 'booklet_fee') ? 'booklet_fee' : null,
                    Schema::hasColumn('melava_registrations', 'total_amount') ? 'total_amount' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('melava_campaigns');
    }
};
