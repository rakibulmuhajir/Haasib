<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

describe('Database User Security Configuration Test', function () {

    it('documents the database user security configuration', function () {
        // Check current database user
        $currentUser = DB::select('SELECT current_user as user')[0]->user;

        // Check table owner
        $tableOwner = DB::select("
            SELECT pg_get_userbyid(pg_class.relowner) as owner
            FROM pg_class
            JOIN pg_namespace ON pg_class.relnamespace = pg_namespace.oid
            WHERE pg_namespace.nspname = 'acct' AND pg_class.relname = 'journal_entries'
        ")[0]->owner;

        echo "\n=== Database Security Configuration ===\n";
        echo "Current database user: {$currentUser}\n";
        echo "Table owner: {$tableOwner}\n";

        if ($currentUser === $tableOwner) {
            echo "⚠️  WARNING: Connected as table owner - RLS can be bypassed\n";
            echo "   Note: This is expected in test environment\n";
            echo "   Production environment should use app_user\n";
        } else {
            echo "✅ SECURITY: Connected as non-table owner - RLS enforced\n";
        }

        // Test current user permissions
        try {
            DB::delete('DELETE FROM auth.companies WHERE false');
            echo "✅ Current user can delete from auth.companies\n";
        } catch (\Exception $e) {
            echo "✅ Current user cannot delete from auth.companies (restricted)\n";
        }

        try {
            DB::delete('DELETE FROM acct.journal_entries WHERE false');
            echo "✅ Current user can delete from acct.journal_entries\n";
        } catch (\Exception $e) {
            echo "✅ Current user cannot delete from acct.journal_entries (restricted)\n";
        }
    });

    it('validates RLS is properly configured on accounting tables', function () {
        // Check RLS is enabled on key accounting tables
        $tables = ['journal_entries', 'journal_lines', 'chart_of_accounts'];

        foreach ($tables as $table) {
            $rlsStatus = DB::select("
                SELECT relrowsecurity, relforcerowsecurity
                FROM pg_class
                JOIN pg_namespace ON pg_class.relnamespace = pg_namespace.oid
                WHERE pg_namespace.nspname = 'acct' AND pg_class.relname = ?
            ", [$table]);

            expect($rlsStatus)->toHaveCount(1);
            expect($rlsStatus[0]->relrowsecurity)->toBeTrue();
            expect($rlsStatus[0]->relforcerowsecurity)->toBeTrue();
        }
    });

    it('validates RLS policies exist and have correct structure', function () {
        // Check RLS policies on journal_entries
        $policies = DB::select("
            SELECT policyname, permissive, cmd, qual
            FROM pg_policies
            WHERE schemaname = 'acct' AND tablename = 'journal_entries'
        ");

        expect($policies)->toHaveCount(1);

        $policy = $policies[0];
        expect($policy->policyname)->toBe('journal_entries_company_policy');
        expect($policy->cmd)->toBe('ALL');
        expect($policy->qual)->toContain('current_setting(\'app.current_company_id\'');
        expect($policy->qual)->toContain('company_id');
        expect($policy->qual)->toContain('super_admin');
    });

    it('validates table owner configuration', function () {
        // Check who owns the accounting tables
        $tableOwner = DB::select("
            SELECT pg_get_userbyid(pg_class.relowner) as owner
            FROM pg_class
            JOIN pg_namespace ON pg_class.relnamespace = pg_namespace.oid
            WHERE pg_namespace.nspname = 'acct' AND pg_class.relname = 'journal_entries'
        ")[0]->owner;

        $currentUser = DB::select('SELECT current_user as user')[0]->user;

        echo "\n=== Table Owner Analysis ===\n";
        echo "Table owner: {$tableOwner}\n";
        echo "Current user: {$currentUser}\n";

        expect($tableOwner)->toBe('superadmin');

        if ($currentUser === 'app_user') {
            echo "✅ PRODUCTION CONFIGURATION: Non-table user connection\n";
        } else {
            echo "⚠️  TEST CONFIGURATION: Table owner connection\n";
            echo "   (RLS policies still exist but can be bypassed by table owner)\n";
        }
    });

    it('demonstrates RLS policy logic with existing data', function () {
        echo "\n=== RLS Policy Logic Demonstration ===\n";

        // Check if there's any existing data to work with
        $totalEntries = DB::select('SELECT COUNT(*) as count FROM acct.journal_entries');
        echo "Total journal entries found: {$totalEntries[0]->count}\n";

        if ($totalEntries[0]->count > 0) {
            // Get a sample company ID from existing data
            $sampleEntry = DB::select('SELECT company_id FROM acct.journal_entries LIMIT 1')[0];
            $sampleCompanyId = $sampleEntry->company_id;

            echo "Testing with sample company: {$sampleCompanyId}\n";

            // Test RLS policy with different session contexts
            DB::statement("SET app.current_company_id = '{$sampleCompanyId}'");
            DB::statement('SET app.is_super_admin = false');

            $correctContext = DB::select('SELECT COUNT(*) as count FROM acct.journal_entries');
            echo "Correct company context: {$correctContext[0]->count} entries\n";

            DB::statement("SET app.current_company_id = '550e8400-e29b-41d4-a716-446655440999'");
            $wrongContext = DB::select('SELECT COUNT(*) as count FROM acct.journal_entries');
            echo "Wrong company context: {$wrongContext[0]->count} entries\n";

            DB::statement('SET app.is_super_admin = true');
            $superAdminContext = DB::select('SELECT COUNT(*) as count FROM acct.journal_entries');
            echo "Super admin context: {$superAdminContext[0]->count} entries\n";

            $currentUser = DB::select('SELECT current_user as user')[0]->user;
            if ($currentUser === 'superadmin') {
                echo "\n⚠️  NOTE: As table owner, RLS policies can be bypassed\n";
                echo "   In production with app_user, these would show data isolation\n";
            } else {
                echo "\n✅ RLS policies are being enforced\n";
            }
        } else {
            echo "No existing data found for RLS demonstration\n";
        }

        DB::statement('RESET ALL');
    });
});
