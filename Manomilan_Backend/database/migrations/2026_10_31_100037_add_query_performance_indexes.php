<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        ['profiles', ['registration_number'], 'profiles_registration_number_index'],
        ['profiles', ['active', 'groomOrBride', 'birthDate'], 'profiles_active_gender_birthdate_index'],
        ['users', ['username'], 'users_username_index'],
        ['users', ['profile_id'], 'users_profile_id_index'],
        ['users', ['role_id'], 'users_role_id_index'],
        ['enquiries', ['created_at'], 'enquiries_created_at_index'],
        ['workshop_registrations', ['created_at'], 'workshop_registrations_created_at_index'],
        ['melava_pending_registrations', ['mobile', 'status', 'id'], 'melava_pending_mobile_status_id_index'],
        ['melava_registrations', ['profile_id', 'created_at'], 'melava_reg_profile_created_index'],
        ['melava_registrations', ['user_id', 'created_at'], 'melava_reg_user_created_index'],
        ['melava_registrations', ['melava_batch', 'id'], 'melava_reg_batch_id_index'],
        ['meetings', ['candidate_id', 'date'], 'meetings_candidate_date_index'],
        ['meetings', ['other_candidate_id'], 'meetings_other_candidate_id_index'],
        ['meetings', ['created_at'], 'meetings_created_at_index'],
        ['payments', ['user_id', 'created_at'], 'payments_user_created_index'],
        ['profile_visitors', ['profile_id', 'visitor_id'], 'profile_visitors_profile_visitor_index'],
        ['profile_visitors', ['visitor_id', 'profile_id'], 'profile_visitors_visitor_profile_index'],
        ['payment_screenshots', ['profile_id', 'payment_verified', 'created_at'], 'payment_screenshots_profile_status_created_index'],
        ['user_interests', ['profile_id', 'created_at'], 'user_interests_profile_created_index'],
        ['tickets', ['user_id', 'created_at'], 'tickets_user_created_index'],
        ['ticket_items', ['ticket_id', 'ticket_status', 'created_at'], 'ticket_items_ticket_status_created_index'],
        ['ticket_comments', ['ticket_id', 'created_at'], 'ticket_comments_ticket_created_index'],
        ['ticket_status_history', ['ticket_id', 'created_at'], 'ticket_history_ticket_created_index'],
        ['ticket_status_history', ['ticket_item_id', 'created_at'], 'ticket_history_item_created_index'],
        ['success_stories', ['status', 'deleted_at', 'display_order'], 'success_stories_status_deleted_order_index'],
        ['failed_jobs', ['failed_at'], 'failed_jobs_failed_at_index'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$tableName, $columns, $indexName]) {
            $this->addIndexIfMissing($tableName, $columns, $indexName);
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$tableName, $columns, $indexName]) {
            if (Schema::hasTable($tableName) && Schema::hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        foreach (Schema::getIndexes($tableName) as $existingIndex) {
            $existingColumns = $existingIndex['columns'] ?? [];
            if (array_slice($existingColumns, 0, count($columns)) === $columns) {
                return;
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }
};
