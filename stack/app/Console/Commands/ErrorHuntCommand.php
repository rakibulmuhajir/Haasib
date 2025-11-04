<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Http\Client\Response;

class ErrorHuntCommand extends Command
{
    protected $signature = 'error:hunt {--url=http://localhost:8000} {--fix}';
    protected $description = 'Hunt for application errors across pages and fix common issues automatically';

    protected $foundErrors = [];
    protected $fixedErrors = [];

    public function handle()
    {
        $this->info('🔍 Starting Comprehensive Error Hunt');
        $this->info('==================================');

        $baseUrl = $this->option('url');
        $autoFix = $this->option('fix');

        // 1. Check Laravel logs for errors
        $this->huntLogErrors();

        // 2. Test main navigation pages
        $this->huntPageErrors($baseUrl);

        // 3. Test API endpoints
        $this->huntApiErrors($baseUrl);

        // 4. Check database connections
        $this->huntDatabaseErrors();

        // 5. Check file permissions
        $this->huntFileErrors();

        // 6. Auto-fix common issues
        if ($autoFix) {
            $this->autoFixErrors();
        }

        // 7. Generate report
        $this->generateReport();

        return count($this->foundErrors) === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function huntLogErrors()
    {
        $this->info('📋 Checking Laravel logs...');

        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile)) {
            $this->warn('No Laravel log file found');
            return;
        }

        $logContent = file_get_contents($logFile);
        $errorPatterns = [
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*?ERROR\s+(.*?)\s*at.*?\n/s',
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*?Exception.*?\n.*?Stack trace:.*?\n.*?\n/s',
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?(500 Internal Server Error|404 Not Found|403 Forbidden)/s'
        ];

        foreach ($errorPatterns as $pattern) {
            preg_match_all($pattern, $logContent, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $error) {
                    $this->foundErrors[] = [
                        'type' => 'log_error',
                        'message' => substr($error, 0, 200) . '...',
                        'severity' => 'high'
                    ];
                }
            }
        }

        $this->info('Found ' . count($this->foundErrors) . ' errors in logs');
    }

    protected function huntPageErrors($baseUrl)
    {
        $this->info('🌐 Testing page navigation...');

        $pages = [
            '/' => 'Home Page',
            '/login' => 'Login Page',
            '/dashboard' => 'Dashboard',
            '/customers' => 'Customers',
            '/customers/create' => 'Create Customer',
            '/invoices' => 'Invoices',
            '/invoices/create' => 'Create Invoice',
            '/payments' => 'Payments',
            '/journal' => 'Journal Entries',
            '/reports' => 'Reports',
        ];

        foreach ($pages as $url => $name) {
            try {
                $response = Http::timeout(10)->get($baseUrl . $url);
                $statusCode = $response->status();

                if ($statusCode >= 400) {
                    $this->foundErrors[] = [
                        'type' => 'page_error',
                        'url' => $url,
                        'name' => $name,
                        'status_code' => $statusCode,
                        'message' => $this->extractErrorFromResponse($response),
                        'severity' => $statusCode >= 500 ? 'high' : 'medium'
                    ];
                }

                // Check for JavaScript errors in HTML
                if ($response->successful()) {
                    $this->checkJsErrors($response->body(), $baseUrl . $url);
                }

                $this->info("  {$name}: {$statusCode}");

            } catch (\Exception $e) {
                $this->foundErrors[] = [
                    'type' => 'page_error',
                    'url' => $url,
                    'name' => $name,
                    'status_code' => 'exception',
                    'message' => $e->getMessage(),
                    'severity' => 'high'
                ];
                $this->error("  {$name}: Exception - " . $e->getMessage());
            }
        }
    }

    protected function huntApiErrors($baseUrl)
    {
        $this->info('🔌 Testing API endpoints...');

        $endpoints = [
            '/api/customers' => 'Customers API',
            '/api/invoices' => 'Invoices API',
            '/api/payments' => 'Payments API',
            '/api/journal-entries' => 'Journal Entries API',
            '/api/companies' => 'Companies API',
        ];

        foreach ($endpoints as $endpoint => $name) {
            try {
                $response = Http::timeout(10)->get($baseUrl . $endpoint);
                $statusCode = $response->status();

                if ($statusCode >= 400) {
                    $this->foundErrors[] = [
                        'type' => 'api_error',
                        'endpoint' => $endpoint,
                        'name' => $name,
                        'status_code' => $statusCode,
                        'message' => $response->json('message', 'Unknown error'),
                        'severity' => $statusCode >= 500 ? 'high' : 'medium'
                    ];
                }

                $this->info("  {$name}: {$statusCode}");

            } catch (\Exception $e) {
                $this->foundErrors[] = [
                    'type' => 'api_error',
                    'endpoint' => $endpoint,
                    'name' => $name,
                    'status_code' => 'exception',
                    'message' => $e->getMessage(),
                    'severity' => 'high'
                ];
                $this->error("  {$name}: Exception - " . $e->getMessage());
            }
        }
    }

    protected function huntDatabaseErrors()
    {
        $this->info('🗄️ Checking database connections...');

        try {
            DB::connection()->getPdo();
            $this->info('  Database connection: OK');

            // Check if tables exist
            $tables = ['users', 'companies', 'customers', 'invoices', 'payments'];
            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    $this->info("  Table {$table}: OK ({$count} records)");
                } catch (\Exception $e) {
                    $this->foundErrors[] = [
                        'type' => 'database_error',
                        'table' => $table,
                        'message' => $e->getMessage(),
                        'severity' => 'high'
                    ];
                    $this->error("  Table {$table}: ERROR - " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            $this->foundErrors[] = [
                'type' => 'database_error',
                'message' => $e->getMessage(),
                'severity' => 'high'
            ];
            $this->error('  Database connection: FAILED');
        }
    }

    protected function huntFileErrors()
    {
        $this->info('📁 Checking file permissions...');

        $directories = [
            storage_path(),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            public_path('build'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                $this->foundErrors[] = [
                    'type' => 'file_error',
                    'path' => $dir,
                    'message' => 'Directory does not exist',
                    'severity' => 'high'
                ];
            } elseif (!is_writable($dir)) {
                $this->foundErrors[] = [
                    'type' => 'file_error',
                    'path' => $dir,
                    'message' => 'Directory is not writable',
                    'severity' => 'medium'
                ];
            } else {
                $this->info("  {$dir}: OK");
            }
        }
    }

    protected function checkJsErrors($html, $url)
    {
        $crawler = new Crawler($html);
        $errorPatterns = [
            '/console\.error/i',
            '/Uncaught.*?Error/i',
            '/Cannot read property/i',
            '/undefined is not a function/i'
        ];

        foreach ($errorPatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                $this->foundErrors[] = [
                    'type' => 'js_error',
                    'url' => $url,
                    'message' => 'JavaScript error detected',
                    'severity' => 'medium'
                ];
                break;
            }
        }
    }

    protected function extractErrorFromResponse($response)
    {
        if ($response->successful()) {
            return 'Page loaded but may have errors';
        }

        if ($response->json()) {
            return $response->json('message', 'Unknown error');
        }

        return substr($response->body(), 0, 200);
    }

    protected function autoFixErrors()
    {
        $this->info('🔧 Auto-fixing common issues...');

        foreach ($this->foundErrors as $error) {
            if ($this->canAutoFix($error)) {
                $this->fixError($error);
                $this->fixedErrors[] = $error;
            }
        }
    }

    protected function canAutoFix($error)
    {
        $fixableErrors = [
            'file_error',
            'permission_error'
        ];

        return in_array($error['type'], $fixableErrors) &&
               ($error['severity'] === 'medium');
    }

    protected function fixError($error)
    {
        switch ($error['type']) {
            case 'file_error':
                if (strpos($error['message'], 'Directory does not exist') !== false) {
                    mkdir($error['path'], 0755, true);
                    $this->info("  Created directory: {$error['path']}");
                }
                break;
            case 'permission_error':
                chmod($error['path'], 0755);
                $this->info("  Fixed permissions for: {$error['path']}");
                break;
        }
    }

    protected function generateReport()
    {
        $this->info('');
        $this->info('📊 Error Hunt Report');
        $this->info('=====================');

        $totalErrors = count($this->foundErrors);
        $fixedErrors = count($this->fixedErrors);
        $remainingErrors = $totalErrors - $fixedErrors;

        $this->info("Total errors found: {$totalErrors}");
        $this->info("Auto-fixed: {$fixedErrors}");
        $this->info("Remaining: {$remainingErrors}");

        if ($remainingErrors > 0) {
            $this->error('');
            $this->error('🚨 Issues still need attention:');

            $groupByType = [];
            foreach ($this->foundErrors as $error) {
                $groupByType[$error['type']][] = $error;
            }

            foreach ($groupByType as $type => $errors) {
                $this->error("  {$type}: " . count($errors) . " issues");
                foreach ($errors as $error) {
                    $this->error("    - {$error['message']}");
                    if (isset($error['url'])) {
                        $this->error("      URL: {$error['url']}");
                    }
                }
            }
        } else {
            $this->info('✅ No critical errors found!');
        }

        // Save detailed report
        $reportPath = storage_path('app/error-hunt-report.json');
        $report = [
            'timestamp' => now()->toISOString(),
            'total_errors' => $totalErrors,
            'fixed_errors' => $fixedErrors,
            'remaining_errors' => $remainingErrors,
            'errors' => $this->foundErrors,
            'fixed_errors_list' => $this->fixedErrors,
        ];

        File::makeDirectory(dirname($reportPath), 0755, true);
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        $this->info('');
        $this->info("📄 Detailed report saved to: {$reportPath}");
    }
}