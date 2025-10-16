<?php

namespace Modules\Accounting\CLI\Commands;

use App\Console\Command;
use App\Models\JournalEntry;
use Illuminate\Console\Command as BaseCommand;

class JournalEntryList extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journal:list 
                            {--company= : Filter by company ID}
                            {--status= : Filter by status}
                            {--type= : Filter by type}
                            {--date-from= : Filter by date from (Y-m-d)}
                            {--date-to= : Filter by date to (Y-m-d)}
                            {--limit=25 : Number of entries to show}
                            {--search= : Search in description or reference}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List journal entries with filtering options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = JournalEntry::with(['transactions.account']);

        if ($company = $this->option('company')) {
            $query->where('company_id', $company);
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        if ($dateFrom = $this->option('date-from')) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo = $this->option('date-to')) {
            $query->where('date', '<=', $dateTo);
        }

        if ($search = $this->option('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                    ->orWhere('reference', 'ilike', "%{$search}%");
            });
        }

        $entries = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($this->option('limit'))
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No journal entries found matching the criteria.');

            return Command::SUCCESS;
        }

        $this->info("Journal Entries ({$entries->count()} shown):");

        $tableData = $entries->map(function ($entry) {
            $totalDebits = $entry->transactions->where('debit_credit', 'debit')->sum('amount');
            $totalCredits = $entry->transactions->where('debit_credit', 'credit')->sum('amount');

            return [
                $entry->id,
                $entry->date->format('Y-m-d'),
                $entry->description,
                $entry->type,
                $entry->status,
                number_format($totalDebits, 2),
                number_format($totalCredits, 2),
                $entry->reference ?? '-',
            ];
        });

        $this->table(
            ['ID', 'Date', 'Description', 'Type', 'Status', 'Total Debits', 'Total Credits', 'Reference'],
            $tableData
        );

        return Command::SUCCESS;
    }
}
