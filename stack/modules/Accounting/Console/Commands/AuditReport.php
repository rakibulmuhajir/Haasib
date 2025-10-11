<?php

namespace Modules\Accounting\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Accounting\Models\AuditEntry;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\User;
use Modules\Accounting\Services\CompanyService;

class AuditReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:audit
                            {action : The action to perform (report, cleanup, summary)}
                            {--company= : Company ID or slug}
                            {--user= : User email address}
                            {--action= : Filter by specific action}
                            {--entity= : Filter by entity type}
                            {--days=30 : Number of days to look back}
                            {--export= : Export to file (csv, json)}
                            {--cleanup-days=90 : Days before which to clean up entries}
                            {--summary= : Show summary statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and manage audit reports';

    public function __construct(
        private CompanyService $companyService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'report' => $this->generateReport(),
            'cleanup' => $this->cleanupEntries(),
            'summary' => $this->showSummary(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * Generate audit report.
     */
    private function generateReport(): int
    {
        $companyId = $this->option('company');
        $userEmail = $this->option('user');
        $actionFilter = $this->option('action');
        $entityFilter = $this->option('entity');
        $days = (int) $this->option('days');
        $export = $this->option('export');

        if ($days <= 0) {
            $this->error('Days must be a positive number.');

            return 1;
        }

        $query = AuditEntry::with(['user', 'company'])
            ->where('created_at', '>=', now()->subDays($days));

        // Apply filters
        if ($companyId) {
            $company = $this->getCompany($companyId);
            if (! $company) {
                return 1;
            }
            $query->where('company_id', $company->id);
            $this->info("Company: {$company->name}");
        }

        if ($userEmail) {
            $user = User::where('email', $userEmail)->first();
            if (! $user) {
                $this->error("User '{$userEmail}' not found.");

                return 1;
            }
            $query->where('user_id', $user->id);
            $this->info("User: {$user->name} ({$userEmail})");
        }

        if ($actionFilter) {
            $query->where('action', $actionFilter);
            $this->info("Action: {$actionFilter}");
        }

        if ($entityFilter) {
            $query->where('entity_type', $entityFilter);
            $this->info("Entity Type: {$entityFilter}");
        }

        $this->info("Period: Last {$days} days");
        $this->info(str_repeat('-', 80));

        // Get statistics
        $totalEntries = $query->count();
        if ($totalEntries === 0) {
            $this->info('No audit entries found matching the criteria.');

            return 0;
        }

        $this->info("Total Entries: {$totalEntries}");

        // Show action breakdown
        $actionBreakdown = (clone $query)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $this->info("\nAction Breakdown:");
        foreach ($actionBreakdown as $action) {
            $this->info("  {$action->action}: {$action->count}");
        }

        // Show user breakdown
        $userBreakdown = (clone $query)
            ->selectRaw('u.name, u.email, COUNT(*) as count')
            ->join('auth.users as u', 'u.id', '=', 'auth.audit_entries.user_id')
            ->groupBy('u.id', 'u.name', 'u.email')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if (! $userBreakdown->isEmpty()) {
            $this->info("\nTop Users by Activity:");
            foreach ($userBreakdown as $user) {
                $this->info("  {$user->name}: {$user->count} entries");
            }
        }

        // Show hourly activity for last 24 hours
        if ($days >= 1) {
            $hourlyActivity = (clone $query)
                ->selectRaw('EXTRACT(HOUR FROM created_at) as hour, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            if (! $hourlyActivity->isEmpty()) {
                $this->info("\nActivity (Last 24 Hours):");
                for ($hour = 0; $hour < 24; $hour++) {
                    $count = $hourlyActivity->firstWhere('hour', $hour)?->count ?? 0;
                    $bar = str_repeat('█', min($count / 10, 20));
                    $this->info(sprintf('  %02d:00 | %-20s %3d', $hour, $bar, $count));
                }
            }
        }

        // Show recent entries
        $recentEntries = $query->latest()->limit(20)->get();
        if (! $recentEntries->isEmpty()) {
            $this->info("\nRecent Entries:");
            $headers = ['Time', 'User', 'Company', 'Action', 'Entity', 'Details'];
            $rows = [];

            foreach ($recentEntries as $entry) {
                $details = $this->formatAuditDetails($entry);
                $rows[] = [
                    $entry->created_at->format('Y-m-d H:i:s'),
                    $entry->user?->name ?? 'System',
                    $entry->company?->name ?? 'N/A',
                    $entry->action,
                    $entry->entity_type ?? 'N/A',
                    substr($details, 0, 100),
                ];
            }

            $this->table($headers, $rows);

            if ($totalEntries > 20) {
                $this->info("\nShowing 20 of {$totalEntries} entries. Use --export to export all entries.");
            }
        }

        // Export if requested
        if ($export) {
            $this->exportEntries($query, $export);
        }

        return 0;
    }

    /**
     * Clean up old audit entries.
     */
    private function cleanupEntries(): int
    {
        $cleanupDays = (int) $this->option('cleanup-days');
        $companyId = $this->option('company');

        if ($cleanupDays <= 0) {
            $this->error('Cleanup days must be a positive number.');

            return 1;
        }

        $query = AuditEntry::where('created_at', '<', now()->subDays($cleanupDays));

        if ($companyId) {
            $company = $this->getCompany($companyId);
            if (! $company) {
                return 1;
            }
            $query->where('company_id', $company->id);
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info("No audit entries older than {$cleanupDays} days found.");

            return 0;
        }

        $this->info("Found {$count} audit entries older than {$cleanupDays} days.");

        if (! $this->confirm('Delete these entries? This cannot be undone.')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            $deleted = $query->delete();
            $this->info("✓ Deleted {$deleted} audit entries.");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to delete audit entries: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Show audit summary statistics.
     */
    private function showSummary(): int
    {
        $companyId = $this->option('company');
        $days = (int) $this->option('days');

        $query = AuditEntry::where('created_at', '>=', now()->subDays($days));

        if ($companyId) {
            $company = $this->getCompany($companyId);
            if (! $company) {
                return 1;
            }
            $query->where('company_id', $company->id);
        }

        $this->info('Audit Summary Statistics');
        $this->info("Period: Last {$days} days");
        if ($companyId) {
            $this->info("Company: {$company->name}");
        }
        $this->info(str_repeat('-', 50));

        // Total entries
        $totalEntries = $query->count();
        $this->info("\nTotal Entries: {$totalEntries}");

        // Entries per day
        $dailyStats = (clone $query)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        if (! $dailyStats->isEmpty()) {
            $this->info("\nDaily Activity:");
            foreach ($dailyStats as $day) {
                $date = Carbon::parse($day->date)->format('M j, Y');
                $this->info("  {$date}: {$day->count} entries");
            }

            $avgPerDay = round($totalEntries / $dailyStats->count(), 1);
            $this->info("  Average: {$avgPerDay} entries per day");
        }

        // Top actions
        $topActions = (clone $query)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if (! $topActions->isEmpty()) {
            $this->info("\nTop Actions:");
            foreach ($topActions as $action) {
                $percentage = round(($action->count / $totalEntries) * 100, 1);
                $this->info("  {$action->action}: {$action->count} ({$percentage}%)");
            }
        }

        // Top entities
        $topEntities = (clone $query)
            ->selectRaw('entity_type, COUNT(*) as count')
            ->whereNotNull('entity_type')
            ->groupBy('entity_type')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if (! $topEntities->isEmpty()) {
            $this->info("\nTop Entity Types:");
            foreach ($topEntities as $entity) {
                $percentage = round(($entity->count / $totalEntries) * 100, 1);
                $this->info("  {$entity->entity_type}: {$entity->count} ({$percentage}%)");
            }
        }

        // Error and security events
        $securityEvents = (clone $query)
            ->whereIn('action', ['login_failed', 'unauthorized_access', 'permission_denied', 'security_violation'])
            ->count();

        if ($securityEvents > 0) {
            $this->info("\nSecurity Events: {$securityEvents}");
        }

        // Peak activity hour
        $peakHour = (clone $query)
            ->selectRaw('EXTRACT(HOUR FROM created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        if ($peakHour) {
            $this->info("\nPeak Activity Hour: {$peakHour->hour}:00 ({$peakHour->count} entries)");
        }

        return 0;
    }

    /**
     * Format audit details for display.
     */
    private function formatAuditDetails(AuditEntry $entry): string
    {
        $details = [];

        if ($entry->old_values) {
            $details[] = 'Old: '.json_encode($entry->old_values);
        }

        if ($entry->new_values) {
            $details[] = 'New: '.json_encode($entry->new_values);
        }

        if ($entry->metadata) {
            $details[] = 'Meta: '.json_encode($entry->metadata);
        }

        return implode(' | ', $details);
    }

    /**
     * Export audit entries to file.
     */
    private function exportEntries($query, string $format): void
    {
        $entries = $query->latest()->get();
        $filename = 'audit_report_'.now()->format('Y-m-d_H-i-s');

        try {
            switch (strtolower($format)) {
                case 'csv':
                    $filename .= '.csv';
                    $handle = fopen($filename, 'w');

                    // Header
                    fputcsv($handle, [
                        'ID', 'Created At', 'User', 'User Email', 'Company',
                        'Action', 'Entity Type', 'Entity ID', 'IP Address',
                        'User Agent', 'Old Values', 'New Values', 'Metadata',
                    ]);

                    // Data
                    foreach ($entries as $entry) {
                        fputcsv($handle, [
                            $entry->id,
                            $entry->created_at->format('Y-m-d H:i:s'),
                            $entry->user?->name ?? '',
                            $entry->user?->email ?? '',
                            $entry->company?->name ?? '',
                            $entry->action,
                            $entry->entity_type ?? '',
                            $entry->entity_id ?? '',
                            $entry->ip_address ?? '',
                            $entry->user_agent ?? '',
                            $entry->old_values ? json_encode($entry->old_values) : '',
                            $entry->new_values ? json_encode($entry->new_values) : '',
                            $entry->metadata ? json_encode($entry->metadata) : '',
                        ]);
                    }

                    fclose($handle);
                    break;

                case 'json':
                    $filename .= '.json';
                    file_put_contents($filename, json_encode($entries->toArray(), JSON_PRETTY_PRINT));
                    break;

                default:
                    $this->error("Unsupported export format: {$format}");

                    return;
            }

            $this->info("\n✓ Exported {$entries->count()} entries to {$filename}");
        } catch (\Exception $e) {
            $this->error("Failed to export entries: {$e->getMessage()}");
        }
    }

    /**
     * Get company by option or selection.
     */
    private function getCompany(?string $identifier = null): ?Company
    {
        if ($identifier) {
            // Try by ID first
            if (is_numeric($identifier)) {
                $company = Company::find($identifier);
                if ($company) {
                    return $company;
                }
            }

            // Try by slug
            return Company::where('slug', $identifier)->first();
        }

        // List companies for selection
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->error('No companies found.');

            return null;
        }

        $choices = $companies->mapWithKeys(function ($company) {
            return [$company->id => "{$company->name} ({$company->slug})"];
        })->toArray();

        $selected = $this->choice('Select a company', $choices);
        $companyId = array_search($selected, $choices);

        return Company::find($companyId);
    }
}
