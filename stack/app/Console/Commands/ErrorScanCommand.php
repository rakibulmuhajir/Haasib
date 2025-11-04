<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Client\Response;

class ErrorScanCommand extends Command
{
    protected $signature = 'error:scan {--url=http://localhost:8000}';
    protected $description = 'Scan application for common errors and provide fixes';

    protected $errors = [];
    protected $fixes = [];

    public function handle()
    {
        $this->info('🔍 Scanning Application for Errors');
        $this->info('==================================');

        $baseUrl = $this->option('url');

        // Clear previous scan results
        $this->errors = [];
        $this->fixes = [];

        // 1. Check Laravel logs
        $this->scanLogs();

        // 2. Check database
        $this->scanDatabase();

        // 3. Check file permissions
        $this->scanFiles();

        // 4. Test main pages
        $this->scanPages($baseUrl);

        // 5. Test API endpoints
        $this->scanApis($baseUrl);

        // 6. Generate fixes
        $this->generateFixes();

        // 7. Show results
        $this->showResults();

        return count($this->errors) === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function scanLogs()
    {
        $this->info('📋 Scanning Laravel logs...');

        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile)) {
            $this->warn('  No log file found');
            return;
        }

        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if (str_contains($line, 'ERROR') || str_contains($line, 'Exception')) {
                $this->errors[] = [
                    'type' => 'log',
                    'message' => substr($line, 0, 200),
                    'severity' => 'high',
                    'suggested_fix' => $this->suggestLogFix($line)
                ];
            } elseif (str_contains($line, '500') || str_contains($line, '404') || str_contains($line, '403')) {
                $this->errors[] = [
                    'type' => 'http',
                    'message' => substr($line, 0, 200),
                    'severity' => 'medium',
                    'suggested_fix' => 'Check page routing and controller methods'
                ];
            }
        }

        $this->info('  Found ' . count($this->errors) . ' issues in logs');
    }

    protected function scanDatabase()
    {
        $this->info('🗄️ Scanning database...');

        // Test database connection
        try {
            DB::connection()->getPdo();
            $this->info('  Database connection: OK');
        } catch (\Exception $e) {
            $this->errors[] = [
                'type' => 'database',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'severity' => 'high',
                'suggested_fix' => 'Check .env database configuration and run migrations'
            ];
            return;
        }

        // Check critical tables
        $criticalTables = ['users', 'companies', 'customers', 'invoices', 'payments'];
        foreach ($criticalTables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->info("  Table {$table}: {$count} records");
            } catch (\Exception $e) {
                $this->errors[] = [
                    'type' => 'database',
                    'message' => "Table {$table} issue: " . $e->getMessage(),
                    'severity' => 'high',
                    'suggested_fix' => "Run 'php artisan migrate' to create {$table}"
                ];
            }
        }
    }

    protected function scanFiles()
    {
        $this->info('📁 Scanning file permissions...');

        $directories = [
            storage_path(),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            public_path('build'),
            base_path('bootstrap/cache'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                $this->errors[] = [
                    'type' => 'file',
                    'message' => "Missing directory: {$dir}",
                    'severity' => 'high',
                    'suggested_fix' => "mkdir -p {$dir}"
                ];
            } elseif (!is_writable($dir)) {
                $this->errors[] = [
                    'type' => 'file',
                    'message' => "Not writable: {$dir}",
                    'severity' => 'medium',
                    'suggested_fix' => "chmod -R 755 {$dir}"
                ];
            }
        }
    }

    protected function scanPages($baseUrl)
    {
        $this->info('🌐 Scanning main pages...');

        $pages = [
            '/' => 'Home',
            '/login' => 'Login',
            '/dashboard' => 'Dashboard',
            '/customers' => 'Customers',
            '/invoices' => 'Invoices',
            '/payments' => 'Payments',
            '/reports' => 'Reports',
        ];

        foreach ($pages as $url => $name) {
            try {
                $response = Http::timeout(5)->get($baseUrl . $url);
                $status = $response->status();

                if ($status >= 400) {
                    $this->errors[] = [
                        'type' => 'page',
                        'message' => "Page {$name} returned {$status}",
                        'url' => $url,
                        'severity' => $status >= 500 ? 'high' : 'medium',
                        'suggested_fix' => $this->suggestPageFix($status, $url)
                    ];
                }

                $this->info("  {$name}: {$status}");

            } catch (\Exception $e) {
                $this->errors[] = [
                    'type' => 'page',
                    'message' => "Page {$name} failed: " . $e->getMessage(),
                    'url' => $url,
                    'severity' => 'high',
                    'suggested_fix' => 'Check web server and Laravel application'
                ];
                $this->error("  {$name}: FAILED");
            }
        }
    }

    protected function scanApis($baseUrl)
    {
        $this->info('🔌 Scanning API endpoints...');

        $endpoints = [
            '/api/customers',
            '/api/invoices',
            '/api/payments',
            '/api/companies',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(5)->get($baseUrl . $endpoint);
                $status = $response->status();

                if ($status >= 400) {
                    $message = $response->json('message', 'API error');
                    $this->errors[] = [
                        'type' => 'api',
                        'message' => "API {$endpoint}: {$message}",
                        'endpoint' => $endpoint,
                        'severity' => $status >= 500 ? 'high' : 'medium',
                        'suggested_fix' => 'Check API routes and controller methods'
                    ];
                }

                $this->info("  API {$endpoint}: {$status}");

            } catch (\Exception $e) {
                $this->errors[] = [
                    'type' => 'api',
                    'message' => "API {$endpoint} failed: " . $e->getMessage(),
                    'endpoint' => $endpoint,
                    'severity' => 'high',
                    'suggested_fix' => 'Check API routes and controller methods'
                ];
                $this->error("  API {$endpoint}: FAILED");
            }
        }
    }

    protected function suggestLogFix($line)
    {
        if (str_contains($line, 'SQL')) {
            return 'Check database connection and migrations';
        } elseif (str_contains($line, 'View')) {
            return 'Check view files and ensure variables are defined';
        } elseif (str_contains($line, 'Route')) {
            return 'Check web.php routes and ensure methods exist';
        } elseif (str_contains($line, 'permission')) {
            return 'Check file permissions and user ownership';
        }
        return 'Check Laravel logs for detailed error';
    }

    protected function suggestPageFix($status, $url)
    {
        switch ($status) {
            case 404:
                return "Check routes/web.php for '{$url}' route";
            case 403:
                return "Check authentication and permissions";
            case 500:
                return "Check Laravel logs for detailed error";
            default:
                return "Check page controller and dependencies";
        }
    }

    protected function generateFixes()
    {
        $this->info('🔧 Generating automatic fixes...');

        foreach ($this->errors as $error) {
            if (isset($error['suggested_fix'])) {
                $this->fixes[] = $error['suggested_fix'];
            }
        }

        // Remove duplicates
        $this->fixes = array_unique($this->fixes);

        $this->info('  Generated ' . count($this->fixes) . ' fix suggestions');
    }

    protected function showResults()
    {
        $this->info('');
        $this->info('📊 Scan Results');
        $this->info('================');

        $totalErrors = count($this->errors);
        if ($totalErrors === 0) {
            $this->info('✅ No critical errors found!');
            return;
        }

        $this->error("🚨 Found {$totalErrors} issues that need attention:");

        $byType = [];
        foreach ($this->errors as $error) {
            $byType[$error['type']][] = $error;
        }

        foreach ($byType as $type => $issues) {
            $this->error("  {$type} (" . count($issues) . "):");
            foreach ($issues as $issue) {
                $this->error("    - {$issue['message']}");
                if ($issue['severity'] === 'high') {
                    $this->error("      SEVERITY: HIGH ⚠️");
                }
            }
        }

        $this->info('');
        $this->info('🔧 Suggested Fixes:');
        foreach ($this->fixes as $fix) {
            $this->info("  {$fix}");
        }

        // Save report
        $report = [
            'timestamp' => now()->toISOString(),
            'total_errors' => $totalErrors,
            'errors' => $this->errors,
            'fixes' => $this->fixes,
        ];

        $reportPath = storage_path('app/scan-report.json');
        File::makeDirectory(dirname($reportPath), 0755, true);
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        $this->info('');
        $this->info("📄 Detailed report saved to: {$reportPath}");
    }
}