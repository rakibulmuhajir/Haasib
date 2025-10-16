<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;

class JournalTemplateListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'journal:template:list 
                            {--company-id= : Filter by company ID}
                            {--active : Show only active templates}
                            {--inactive : Show only inactive templates}
                            {--frequency= : Filter by frequency}
                            {--due-today : Show templates due today}
                            {--due-this-week : Show templates due this week}
                            {--limit=20 : Number of templates to show}';

    /**
     * The console command description.
     */
    protected $description = 'List recurring journal templates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Listing recurring journal templates...');

        try {
            $query = \App\Models\RecurringJournalTemplate::with(['lines.account']);

            // Apply filters
            if ($companyId = $this->option('company-id')) {
                $query->where('company_id', $companyId);
            }

            if ($this->option('active')) {
                $query->where('is_active', true);
            } elseif ($this->option('inactive')) {
                $query->where('is_active', false);
            }

            if ($frequency = $this->option('frequency')) {
                $query->where('frequency', $frequency);
            }

            if ($this->option('due-today')) {
                $query->where('is_active', true)
                    ->where('next_generation_date', '<=', now()->toDateString());
            }

            if ($this->option('due-this-week')) {
                $query->where('is_active', true)
                    ->where('next_generation_date', '<=', now()->addWeek()->toDateString());
            }

            $limit = (int) $this->option('limit');
            $templates = $query->orderBy('created_at', 'desc')->limit($limit)->get();

            if ($templates->isEmpty()) {
                $this->info('No templates found matching the criteria.');

                return self::SUCCESS;
            }

            $this->displayTemplates($templates);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to list templates: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Display templates in a formatted table.
     */
    protected function displayTemplates($templates): void
    {
        $this->table(
            ['ID', 'Name', 'Frequency', 'Active', 'Next Generation', 'Amount', 'Lines'],
            $templates->map(function ($template) {
                return [
                    $template->id,
                    $this->truncate($template->name, 30),
                    $template->frequency,
                    $template->is_active ? 'Yes' : 'No',
                    $template->next_generation_date ?? 'N/A',
                    $template->currency.' '.number_format($template->total_debit, 2),
                    $template->lines->count(),
                ];
            })->toArray()
        );

        $this->info("Found {$templates->count()} template(s).");
    }

    /**
     * Truncate text to specified length.
     */
    protected function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3).'...' : $text;
    }
}
