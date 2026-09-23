<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Removes a demo or scenario company and everything it owns, so its seeder can rebuild it.
 *
 * There used to be two copies of this - DemoSupport::purgeDemoCompany and
 * ScenarioFuelStationSeeder::purge - and they had drifted: one knew the umrah and role tables,
 * the other the newer daily close tables. Both shared three faults.
 *
 *  - Items were deleted before tanks. Deleting an item nulls the tank's linked_item_id, which
 *    a tank may not have (tank_requires_item_and_capacity), so on any fuel company that step
 *    failed.
 *
 *  - Every delete was wrapped in catch (\Throwable) and skipped. The failure above was never
 *    seen; the routine carried on and left the company half removed.
 *
 *  - The daily close unlock trail and audit evidence are append-only by trigger, with no way
 *    around it, and posted journals are protected the same way. Any company with posted
 *    history could not be removed at all - the deletes were swallowed, and the final company
 *    delete then tripped the same triggers through its cascade.
 *
 * So: one transaction, so a failure leaves nothing half done; no swallowed errors, because
 * only tables that really carry a company_id are touched; a sweep of every such table after
 * the ordered list, so a table added later is not left behind; and the user-defined triggers
 * on the affected tables disabled for the length of the transaction only. Foreign-key
 * enforcement is untouched - those are internal triggers, which DISABLE TRIGGER USER leaves
 * on - and a rollback restores the triggers along with everything else.
 *
 * Disabling audit protection is acceptable for synthetic companies and for nothing else,
 * which is why this refuses any slug that is not a demo or scenario one. A real company is
 * removed deliberately: a backup, a dry run, then the delete.
 */
final class CompanyPurger
{
    private const ALLOWED_SLUG_PREFIXES = ['demo-', 'scenario-'];

    /**
     * Children before parents. Order only matters between tables that reference each other;
     * anything not listed is caught by the sweep that follows.
     */
    private const ORDER = [
        'fuel.daily_close_unlocks', 'fuel.daily_close_activity',
        'fuel.daily_close_reading_corrections', 'fuel.daily_close_drafts',

        'umrah.payment_allocations', 'umrah.group_payments', 'umrah.voucher_passengers', 'umrah.vouchers',
        'umrah.passengers', 'umrah.group_transport_items', 'umrah.visa_groups', 'umrah.transport_services',
        'umrah.transport_package_sectors', 'umrah.transport_packages', 'umrah.transport_fares',
        'umrah.transport_sectors', 'umrah.hotel_room_rates', 'umrah.hotels', 'umrah.hotel_vendors',
        'umrah.visa_services', 'umrah.visa_vendors', 'umrah.expenses', 'umrah.drivers', 'umrah.agents',
        'umrah.change_logs',

        'fuel.nozzle_readings', 'fuel.pump_readings', 'fuel.tank_readings', 'fuel.attendant_handovers',
        'fuel.amanat_transactions', 'fuel.sale_metadata', 'fuel.rate_changes', 'fuel.nozzles', 'fuel.pumps',
        'fuel.dip_chart_entries', 'fuel.dip_sticks', 'fuel.investor_lots', 'fuel.investors',
        'fuel.customer_profiles', 'fuel.station_settings',

        'pay.payslip_lines', 'pay.payslips', 'pay.salary_advance_recoveries', 'pay.salary_advances',
        'pay.employee_benefits', 'pay.leave_requests', 'pay.payroll_periods', 'pay.employees',
        'pay.benefit_plans', 'pay.deduction_types', 'pay.earning_types', 'pay.leave_types',

        'inv.cogs_entries', 'inv.cost_layers', 'inv.item_costs', 'inv.stock_movements', 'inv.stock_levels',
        'inv.stock_receipt_lines', 'inv.stock_receipts',
        // Tanks before items - see the class comment.
        'inv.warehouses', 'inv.items', 'inv.item_categories', 'inv.cost_policies',

        'acct.transaction_attachments',
        'acct.payment_allocations', 'acct.payments', 'acct.bill_payment_allocations', 'acct.bill_payments',
        'acct.credit_note_applications', 'acct.credit_note_items', 'acct.credit_notes',
        'acct.vendor_credit_applications', 'acct.vendor_credit_items', 'acct.vendor_credits',
        'acct.invoice_line_items', 'acct.invoices', 'acct.bill_line_items', 'acct.bills',
        'acct.journal_entries', 'acct.transactions', 'acct.bank_transactions', 'acct.bank_reconciliations',
        'acct.bank_rules', 'acct.company_bank_accounts', 'acct.customers', 'acct.vendors',
        'acct.posting_template_lines', 'acct.posting_templates', 'acct.accounting_periods',
        'acct.fiscal_years', 'acct.company_tax_registrations', 'acct.company_tax_settings', 'acct.accounts',

        'auth.company_onboarding', 'auth.company_user',

        // Roles are per company and unique on (company_id, name, guard_name), so they must go
        // or the rebuilt company collides with its predecessor's.
        'public.model_has_permissions', 'public.model_has_roles', 'public.roles',
    ];

    /**
     * @return int rows removed, including the company row itself; 0 when there was no such company
     */
    public function purge(string $slug): int
    {
        $this->guardSyntheticSlug($slug);

        $db = DB::connection('pgsql');

        $db->select("SELECT set_config('app.is_super_admin', 'true', false)");
        $company = $db->table('auth.companies')->where('slug', $slug)->first();

        if (! $company) {
            return 0;
        }

        $db->select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        $companyTables = collect($db->select("
            select c.table_schema || '.' || c.table_name as t
            from information_schema.columns c
            join information_schema.tables tb
              on tb.table_schema = c.table_schema and tb.table_name = c.table_name
            where c.column_name = 'company_id' and tb.table_type = 'BASE TABLE'
              and c.table_schema not in ('pg_catalog', 'information_schema')
        "))->pluck('t')->reject(fn ($t) => $t === 'auth.companies')->values()->all();

        $accountRefs = collect($db->select("
            select column_name from information_schema.columns
            where table_schema = 'auth' and table_name = 'companies' and column_name like '%\\_account\\_id'
        "))->pluck('column_name')->all();

        return $db->transaction(function () use ($db, $company, $companyTables, $accountRefs) {
            // Only the tables this company actually has rows in, plus the company table the
            // cascade runs from. Nothing else is locked or has its triggers touched.
            $occupied = array_values(array_filter(
                $companyTables,
                fn ($t) => $db->table($t)->where('company_id', $company->id)->exists(),
            ));
            $guarded = [...$occupied, 'auth.companies'];

            foreach ($guarded as $table) {
                $db->statement('ALTER TABLE '.$this->quote($table).' DISABLE TRIGGER USER');
            }

            if ($accountRefs) {
                $db->table('auth.companies')->where('id', $company->id)
                    ->update(array_fill_keys($accountRefs, null));
            }

            $removed = 0;
            $ordered = array_values(array_intersect(self::ORDER, $occupied));
            $rest = array_values(array_diff($occupied, $ordered));

            foreach ([...$ordered, ...$rest] as $table) {
                $removed += $db->table($table)->where('company_id', $company->id)->delete();
            }

            $removed += $db->table('auth.companies')->where('id', $company->id)->delete();

            $left = array_filter(
                $companyTables,
                fn ($t) => $db->table($t)->where('company_id', $company->id)->exists(),
            );

            if ($left) {
                // Throwing rolls everything back, triggers included.
                throw new RuntimeException("Purging {$company->slug} left rows behind in: ".implode(', ', $left));
            }

            foreach ($guarded as $table) {
                $db->statement('ALTER TABLE '.$this->quote($table).' ENABLE TRIGGER USER');
            }

            return $removed;
        });
    }

    private function guardSyntheticSlug(string $slug): void
    {
        foreach (self::ALLOWED_SLUG_PREFIXES as $prefix) {
            if (str_starts_with($slug, $prefix)) {
                return;
            }
        }

        throw new RuntimeException(
            "Refusing to purge [{$slug}]: only demo- and scenario- companies can be removed by a seeder. "
            .'A real company is removed deliberately - take a backup, dry-run, then delete.'
        );
    }

    private function quote(string $table): string
    {
        return implode('.', array_map(fn ($part) => '"'.str_replace('"', '""', $part).'"', explode('.', $table)));
    }
}
