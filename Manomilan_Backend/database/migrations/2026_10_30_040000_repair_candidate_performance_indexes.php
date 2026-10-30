<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            'profiles' => [
                'profiles_active_groom_bride_index' => ['active', 'groomOrBride'],
                'profiles_groom_bride_religion_index' => ['groomOrBride', 'religion'],
                'profiles_groom_bride_caste_index' => ['groomOrBride', 'caste'],
                'profiles_groom_bride_marital_index' => ['groomOrBride', 'maritalStatus'],
                'profiles_birth_date_index' => ['birthDate'],
                'profiles_location_index' => ['location'],
                'profiles_verification_status_index' => ['profileVerificationStatus'],
            ],
            'users' => [
                'users_profile_id_index' => ['profile_id'],
                'users_status_index' => ['status'],
            ],
            'user_interests' => [
                'user_interests_user_created_index' => ['user_id', 'created_at'],
                'user_interests_profile_created_index' => ['profile_id', 'created_at'],
                'user_interests_profile_status_index' => ['profile_id', 'status'],
                'user_interests_user_status_index' => ['user_id', 'status'],
                'user_interests_profile_status_created_index' => ['profile_id', 'status', 'created_at'],
            ],
            'profile_visitors' => [
                'profile_visitors_profile_visitor_index' => ['profile_id', 'visitor_id'],
                'profile_visitors_visitor_index' => ['visitor_id'],
                'profile_visitors_created_at_index' => ['created_at'],
            ],
            'candidate_notifications' => [
                'candidate_notifications_user_type_created_index' => ['user_id', 'type', 'created_at'],
            ],
        ];

        foreach ($definitions as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            foreach ($indexes as $name => $required) {
                if (! collect($required)->every(fn (string $column) => in_array($column, $columns, true))) {
                    continue;
                }

                if (! $this->hasIndex($table, $name)) {
                    $quotedColumns = collect($required)->map(fn (string $column) => '`'.$column.'`')->implode(', ');
                    DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$quotedColumns})");
                }
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'profiles' => ['profiles_location_index', 'profiles_birth_date_index'],
            'user_interests' => ['user_interests_profile_status_created_index'],
        ];

        foreach ($indexes as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($names as $name) {
                if ($this->hasIndex($table, $name)) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
                }
            }
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
