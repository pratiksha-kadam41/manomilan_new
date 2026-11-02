<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'branch',
        'location',
        'meeting_date',
        'day',
        'meeting_time',
        'remarks',
        'spoke_to',
        'rashi',
        'nakshatra',
        'charan',
        'gan',
        'gotra',
        'nadi',
        'matchPatrika',
        'kundali',
        'homeStatus',
        'ownedRealEstate',
        'hasVehicle',
        'vehicleType',
        'otherInvestments',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        $columns = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('profiles', $column)
        ));

        if ($columns !== []) {
            Schema::table('profiles', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        Schema::table('profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('profiles', 'branch')) {
                $table->string('branch')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'location')) {
                $table->string('location')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'meeting_date')) {
                $table->date('meeting_date')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'day')) {
                $table->string('day')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'meeting_time')) {
                $table->string('meeting_time')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'remarks')) {
                $table->text('remarks')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'spoke_to')) {
                $table->string('spoke_to')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'rashi')) {
                $table->string('rashi', 191)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'nakshatra')) {
                $table->string('nakshatra', 191)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'charan')) {
                $table->string('charan', 191)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'gan')) {
                $table->string('gan', 191)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'gotra')) {
                $table->string('gotra', 191)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'nadi')) {
                $table->string('nadi', 191)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'matchPatrika')) {
                $table->string('matchPatrika')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'kundali')) {
                $table->string('kundali')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'homeStatus')) {
                $table->string('homeStatus', 50)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'ownedRealEstate')) {
                $table->text('ownedRealEstate')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'hasVehicle')) {
                $table->string('hasVehicle', 10)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'vehicleType')) {
                $table->string('vehicleType')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'otherInvestments')) {
                $table->text('otherInvestments')->nullable();
            }
        });
    }
};
