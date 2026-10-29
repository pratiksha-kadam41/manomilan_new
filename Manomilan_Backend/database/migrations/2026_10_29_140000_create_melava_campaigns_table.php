<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melava_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->boolean('booklet_available')->default(false);
            $table->decimal('booklet_fee', 10, 2)->default(0);
            $table->string('qr_image_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

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

        $batch = config('melava.batch', 'october_2026');
        $title = config('melava.batch_label', 'Melava 3 - October 2026');
        $now = now();

        $campaignId = DB::table('melava_campaigns')->insertGetId([
            'title' => $title,
            'description' => 'Legacy Melava event settings.',
            'currency' => config('melava.currency', 'INR'),
            'registration_fee' => config('melava.fee', 1200),
            'booklet_available' => true,
            'booklet_fee' => config('melava.booklet_fee', 300),
            'qr_image_path' => config('melava.qr_image_url'),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('melava_registrations')
            ->where('melava_batch', $batch)
            ->update([
                'melava_campaign_id' => $campaignId,
                'melava_title' => $title,
                'registration_fee' => config('melava.fee', 1200),
                'booklet_fee' => DB::raw('CASE WHEN is_booklet_req = 1 THEN '.(float) config('melava.booklet_fee', 300).' ELSE 0 END'),
                'total_amount' => DB::raw(
                    (float) config('melava.fee', 1200).' + CASE WHEN is_booklet_req = 1 THEN '.(float) config('melava.booklet_fee', 300).' ELSE 0 END'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('melava_registrations', function (Blueprint $table) {
            foreach (['melava_campaign_id', 'melava_title', 'registration_fee', 'booklet_fee', 'total_amount'] as $column) {
                if (Schema::hasColumn('melava_registrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('melava_campaigns');
    }
};
