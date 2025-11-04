<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure target schema exists.
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        $tables = [
            'customer_contacts',
            'customer_addresses',
            'customer_communications',
            'customer_credit_limits',
            'customer_statements',
            'customer_aging_snapshots',
            'customer_groups',
            'customer_group_members',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE IF EXISTS invoicing.{$table} SET SCHEMA acct");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS invoicing');

        $tables = [
            'customer_contacts',
            'customer_addresses',
            'customer_communications',
            'customer_credit_limits',
            'customer_statements',
            'customer_aging_snapshots',
            'customer_groups',
            'customer_group_members',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE IF EXISTS acct.{$table} SET SCHEMA invoicing");
        }
    }
};
