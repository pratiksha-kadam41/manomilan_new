<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profiles')) {
            Schema::table('profiles', function (Blueprint $table) {
                $columns = Schema::getColumnListing('profiles');
                $indexes = [
                    'profiles_active_groom_bride_index' => [['active', 'groomOrBride']],
                    'profiles_groom_bride_religion_index' => [['groomOrBride', 'religion']],
                    'profiles_groom_bride_caste_index' => [['groomOrBride', 'caste']],
                    'profiles_groom_bride_marital_index' => [['groomOrBride', 'maritalStatus']],
                    'profiles_birth_date_index' => ['birthDate'],
                    'profiles_location_index' => ['location'],
                    'profiles_verification_status_index' => ['profileVerificationStatus'],
                ];

                foreach ($indexes as $name => $definition) {
                    $required = is_array($definition[0]) ? $definition[0] : $definition;
                    if (collect($required)->every(fn ($column) => in_array($column, $columns, true))
                        && ! $this->hasIndex('profiles', $name)) {
                        $table->index($required, $name);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = Schema::getColumnListing('users');
                foreach (['profile_id' => 'users_profile_id_index', 'status' => 'users_status_index'] as $column => $name) {
                    if (in_array($column, $columns, true) && ! $this->hasIndex('users', $name)) {
                        $table->index($column, $name);
                    }
                }
            });
        }

        if (Schema::hasTable('user_interests')) {
            Schema::table('user_interests', function (Blueprint $table) {
                $columns = Schema::getColumnListing('user_interests');
                $indexes = [
                    'user_interests_user_created_index' => ['user_id', 'created_at'],
                    'user_interests_profile_created_index' => ['profile_id', 'created_at'],
                    'user_interests_profile_status_index' => ['profile_id', 'status'],
                    'user_interests_user_status_index' => ['user_id', 'status'],
                ];
                foreach ($indexes as $name => $required) {
                    if (collect($required)->every(fn ($column) => in_array($column, $columns, true))
                        && ! $this->hasIndex('user_interests', $name)) {
                        $table->index($required, $name);
                    }
                }
            });
        }

        if (Schema::hasTable('profile_visitors')) {
            Schema::table('profile_visitors', function (Blueprint $table) {
                $columns = Schema::getColumnListing('profile_visitors');
                $indexes = [
                    'profile_visitors_profile_visitor_index' => ['profile_id', 'visitor_id'],
                    'profile_visitors_visitor_index' => ['visitor_id'],
                    'profile_visitors_created_at_index' => ['created_at'],
                ];
                foreach ($indexes as $name => $required) {
                    if (collect($required)->every(fn ($column) => in_array($column, $columns, true))
                        && ! $this->hasIndex('profile_visitors', $name)) {
                        $table->index($required, $name);
                    }
                }
            });
        }

        if (Schema::hasTable('candidate_notifications')) {
            Schema::table('candidate_notifications', function (Blueprint $table) {
                $columns = Schema::getColumnListing('candidate_notifications');
                $required = ['user_id', 'type', 'created_at'];
                if (collect($required)->every(fn ($column) => in_array($column, $columns, true))
                    && ! $this->hasIndex('candidate_notifications', 'candidate_notifications_user_type_created_index')) {
                    $table->index($required, 'candidate_notifications_user_type_created_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('profiles')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropIndex('profiles_active_groom_bride_index');
                $table->dropIndex('profiles_groom_bride_religion_index');
                $table->dropIndex('profiles_groom_bride_caste_index');
                $table->dropIndex('profiles_groom_bride_marital_index');
                $table->dropIndex('profiles_birth_date_index');
                $table->dropIndex('profiles_location_index');
                $table->dropIndex('profiles_verification_status_index');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_profile_id_index');
                $table->dropIndex('users_status_index');
            });
        }

        if (Schema::hasTable('user_interests')) {
            Schema::table('user_interests', function (Blueprint $table) {
                $table->dropIndex('user_interests_user_created_index');
                $table->dropIndex('user_interests_profile_created_index');
                $table->dropIndex('user_interests_profile_status_index');
                $table->dropIndex('user_interests_user_status_index');
            });
        }

        if (Schema::hasTable('profile_visitors')) {
            Schema::table('profile_visitors', function (Blueprint $table) {
                $table->dropIndex('profile_visitors_profile_visitor_index');
                $table->dropIndex('profile_visitors_visitor_index');
                $table->dropIndex('profile_visitors_created_at_index');
            });
        }

        if (Schema::hasTable('candidate_notifications')) {
            Schema::table('candidate_notifications', function (Blueprint $table) {
                $table->dropIndex('candidate_notifications_user_type_created_index');
            });
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
