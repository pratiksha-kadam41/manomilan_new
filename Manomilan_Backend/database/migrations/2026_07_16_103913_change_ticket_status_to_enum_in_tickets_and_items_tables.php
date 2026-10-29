<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ChangeTicketStatusToEnumInTicketsAndItemsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Change ticket_status column to ENUM in tickets table
        DB::statement("
            ALTER TABLE tickets
            MODIFY COLUMN ticket_status
            ENUM('Open', 'In Progress', 'Resolved', 'Closed')
            NOT NULL DEFAULT 'Open'
        ");

        // Change ticket_status column to ENUM in ticket_items table
        DB::statement("
            ALTER TABLE ticket_items
            MODIFY COLUMN ticket_status
            ENUM('Open', 'In Progress', 'Resolved', 'Closed')
            NOT NULL DEFAULT 'Open'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert ticket_status column back to VARCHAR in tickets table
        DB::statement("
            ALTER TABLE tickets
            MODIFY COLUMN ticket_status
            VARCHAR(30)
            NOT NULL DEFAULT 'Open'
        ");

        // Revert ticket_status column back to VARCHAR in ticket_items table
        DB::statement("
            ALTER TABLE ticket_items
            MODIFY COLUMN ticket_status
            VARCHAR(30)
            NOT NULL DEFAULT 'Open'
        ");
    }
}