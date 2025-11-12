<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalEntriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company for testing
        $company = DB::table('auth.companies')->first();

        // Get the first user for testing
        $user = DB::table('auth.users')->first();

        if (! $company) {
            $this->command->warn('No company found. Please run company seeder first.');

            return;
        }

        if (! $user) {
            $this->command->warn('No user found. Please run user seeder first.');

            return;
        }

        $this->command->info('Seeding journal entries for company: '.$company->name);

        // Get accounts from chart of accounts (bypass RLS for seeding)
        DB::statement("SET app.current_company_id = '{$company->id}'");
        DB::statement('SET app.is_super_admin = true');

        $accounts = DB::table('acct.chart_of_accounts')
            ->where('company_id', $company->id)
            ->pluck('id', 'account_number')
            ->toArray();

        // Reset RLS settings
        DB::statement('RESET app.current_company_id');
        DB::statement('RESET app.is_super_admin');

        if (empty($accounts)) {
            $this->command->warn('No accounts found. Please run ChartOfAccountsSeeder first.');

            return;
        }

        $this->command->info('Found '.count($accounts).' accounts');

        // Sample journal entries
        $journalEntries = [
            [
                [
                    'entry_number' => 'JE-000001',
                    'description' => 'Initial cash investment',
                    'reference' => 'INV-001',
                    'entry_date' => now()->subMonths(3)->format('Y-m-d'),
                    'status' => 'posted',
                    'lines' => [
                        ['account_number' => '1000', 'debit_amount' => 50000, 'credit_amount' => 0, 'description' => 'Initial cash deposit'],
                        ['account_number' => '3000', 'debit_amount' => 0, 'credit_amount' => 50000, 'description' => 'Capital stock issuance'],
                    ],
                ],
                [
                    'entry_number' => 'JE-000002',
                    'description' => 'Equipment purchase',
                    'reference' => 'PUR-001',
                    'entry_date' => now()->subMonths(2)->format('Y-m-d'),
                    'status' => 'posted',
                    'lines' => [
                        ['account_number' => '1500', 'debit_amount' => 25000, 'credit_amount' => 0, 'description' => 'Office equipment purchase'],
                        ['account_number' => '2000', 'debit_amount' => 0, 'credit_amount' => 15000, 'description' => 'Equipment purchase - partial payment'],
                        ['account_number' => '2000', 'debit_amount' => 0, 'credit_amount' => 10000, 'description' => 'Equipment purchase - remaining balance'],
                    ],
                ],
                [
                    'entry_number' => 'JE-000003',
                    'description' => 'Building purchase',
                    'reference' => 'PUR-002',
                    'entry_date' => now()->subMonths(2)->format('Y-m-d'),
                    'status' => 'posted',
                    'lines' => [
                        ['account_number' => '1600', 'debit_amount' => 100000, 'credit_amount' => 0, 'description' => 'Office building purchase'],
                        ['account_number' => '2500', 'debit_amount' => 0, 'credit_amount' => 100000, 'description' => 'Building purchase financed'],
                    ],
                ],
                [
                    'entry_number' => 'JE-000004',
                    'description' => 'Monthly rent payment',
                    'reference' => 'EXP-001',
                    'entry_date' => now()->subMonth()->format('Y-m-d'),
                    'status' => 'posted',
                    'lines' => [
                        ['account_number' => '5200', 'debit_amount' => 2000, 'credit_amount' => 0, 'description' => 'Monthly office rent'],
                        ['account_number' => '1000', 'debit_amount' => 0, 'credit_amount' => 2000, 'description' => 'Rent payment from cash'],
                    ],
                ],
                [
                    'entry_number' => 'JE-000005',
                    'description' => 'Sales revenue',
                    'reference' => 'INV-002',
                    'entry_date' => now()->subWeek()->format('Y-m-d'),
                    'status' => 'posted',
                    'lines' => [
                        ['account_number' => '1100', 'debit_amount' => 15000, 'credit_amount' => 0, 'description' => 'Sales on account'],
                        ['account_number' => '4000', 'debit_amount' => 0, 'credit_amount' => 15000, 'description' => 'Sales revenue earned'],
                    ],
                ],
            ],
        ];

        foreach ($journalEntries[0] as $entryData) {
            // Bypass RLS policies for seeding
            DB::statement("SET app.current_company_id = '{$company->id}'");
            DB::statement('SET app.is_super_admin = true');

            $journalEntryId = Str::uuid();
            $totalDebits = array_sum(array_column($entryData['lines'], 'debit_amount'));
            $totalCredits = array_sum(array_column($entryData['lines'], 'credit_amount'));

            // Insert journal entry
            DB::table('acct.journal_entries')->insert([
                'id' => $journalEntryId,
                'company_id' => $company->id,
                'entry_number' => $entryData['entry_number'],
                'description' => $entryData['description'],
                'reference' => $entryData['reference'],
                'entry_date' => $entryData['entry_date'],
                'status' => $entryData['status'],
                'created_by_id' => $user->id, // Use actual user ID
                'created_at' => now(),
                'updated_at' => now(),
                'posted_at' => $entryData['status'] === 'posted' ? now() : null,
                'approved_by_id' => $entryData['status'] === 'posted' ? $user->id : null,
                'approved_at' => $entryData['status'] === 'posted' ? now() : null,
            ]);

            // Insert journal lines
            foreach ($entryData['lines'] as $line) {
                $accountId = $accounts[$line['account_number']] ?? null;
                if (! $accountId) {
                    $this->command->warn("Account number {$line['account_number']} not found, skipping line");

                    continue;
                }

                // Get account details from chart of accounts
                $accountDetails = DB::table('acct.chart_of_accounts')
                    ->where('id', $accountId)
                    ->first();

                DB::table('acct.journal_lines')->insert([
                    'id' => Str::uuid(),
                    'company_id' => $company->id,
                    'journal_entry_id' => $journalEntryId,
                    'account_id' => $accountId,
                    'account_number' => $line['account_number'],
                    'account_name' => $accountDetails->account_name ?? 'Unknown Account',
                    'description' => $line['description'],
                    'debit_amount' => $line['debit_amount'],
                    'credit_amount' => $line['credit_amount'],
                    'created_by_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Reset RLS settings
            DB::statement('RESET app.current_company_id');
            DB::statement('RESET app.is_super_admin');
        }

        $this->command->info('Successfully seeded '.count($journalEntries).' journal entries.');
    }
}
