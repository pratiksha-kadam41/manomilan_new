<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'applications',
        'candidates',
        'candidate_notifications',
        'candidate_requests',
        'document_verifications',
        'enquiries',
        'meetings',
        'melava_campaigns',
        'melava_pending_registrations',
        'module_permissions',
        'modules',
        'payments',
        'payment_screenshots',
        'preferences',
        'profile_documents',
        'profile_education',
        'profile_employment',
        'profile_family_details',
        'profile_melava_registrations',
        'profile_partner_preferences',
        'profile_religion_details',
        'profile_visitors',
        'profiles',
        'roles',
        'success_stories',
        'ticket_comments',
        'ticket_items',
        'ticket_status_history',
        'tickets',
        'user_interests',
        'users',
        'website_downtime_settings',
        'website_maintenance_settings',
        'website_settings',
        'workshop_events',
        'workshop_registrations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $addCreatedBy = ! Schema::hasColumn($tableName, 'created_by_user_id');
            $addUpdatedBy = ! Schema::hasColumn($tableName, 'updated_by_user_id');
            if (! $addCreatedBy && ! $addUpdatedBy) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($addCreatedBy, $addUpdatedBy): void {
                if ($addCreatedBy) {
                    $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                }
                if ($addUpdatedBy) {
                    $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $dropCreatedBy = Schema::hasColumn($tableName, 'created_by_user_id')
                && Schema::hasIndex($tableName, $tableName.'_created_by_user_id_index');
            $dropUpdatedBy = Schema::hasColumn($tableName, 'updated_by_user_id')
                && Schema::hasIndex($tableName, $tableName.'_updated_by_user_id_index');
            if (! $dropCreatedBy && ! $dropUpdatedBy) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $dropCreatedBy, $dropUpdatedBy): void {
                if ($dropCreatedBy) {
                    $table->dropIndex($tableName.'_created_by_user_id_index');
                    $table->dropColumn('created_by_user_id');
                }
                if ($dropUpdatedBy) {
                    $table->dropIndex($tableName.'_updated_by_user_id_index');
                    $table->dropColumn('updated_by_user_id');
                }
            });
        }
    }
};
