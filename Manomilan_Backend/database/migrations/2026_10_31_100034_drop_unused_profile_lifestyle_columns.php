<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'cookingSkills',
        'hobbies',
        'extraCurricularActivities',
        'achievements',
        'sportsAndFitnessLikings',
        'diet',
        'smoking',
        'smokingFrequency',
        'drinking',
        'drinkingFrequency',
        'partying',
        'activeOnSocialSites',
        'instagram',
        'facebook',
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
            if (! Schema::hasColumn('profiles', 'cookingSkills')) {
                $table->string('cookingSkills')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'hobbies')) {
                $table->text('hobbies')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'extraCurricularActivities')) {
                $table->text('extraCurricularActivities')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'achievements')) {
                $table->text('achievements')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'sportsAndFitnessLikings')) {
                $table->text('sportsAndFitnessLikings')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'diet')) {
                $table->string('diet')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'smoking')) {
                $table->string('smoking', 10)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'smokingFrequency')) {
                $table->string('smokingFrequency')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'drinking')) {
                $table->string('drinking', 10)->nullable();
            }
            if (! Schema::hasColumn('profiles', 'drinkingFrequency')) {
                $table->string('drinkingFrequency')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'partying')) {
                $table->string('partying')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'activeOnSocialSites')) {
                $table->text('activeOnSocialSites')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'instagram')) {
                $table->string('instagram')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'facebook')) {
                $table->string('facebook')->nullable();
            }
        });
    }
};
