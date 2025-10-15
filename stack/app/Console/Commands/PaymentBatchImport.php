<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Bus;
use Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction;
use App\Models\PaymentBatch;

class PaymentBatchImport extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payment:batch:import 
                            {source : Source type (manual, csv, bank-feed)}
                            {--file= : CSV file path for csv source}
                            {--entries= : JSON file path with payment entries for manual/bank-feed sources}
                            {--notes= : Optional notes for the batch}
                            {--metadata= : JSON metadata string}
                            {--watch : Watch for file changes and auto-import}
                            {--format=table : Output format (table, json)}
                            {--dry-run : Validate entries without creating batch}';

    /**
     * The console command description.
     */
    protected $description = 'Import payment receipts from CSV files or manual entries';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = $this->argument('source');
        $format = $this->option('format');
        $dryRun = $this->option('dry-run');

        try {
            // Validate source type
            if (!in_array($source, ['manual', 'csv', 'bank-feed'])) {
                $this->error('Invalid source type. Must be: manual, csv, or bank-feed');
                return 1;
            }

            // Set company context
            $companyId = $this->getCompanyId();
            if (!$companyId) {
                $this->error('Company context is required. Set APP_COMPANY_ID environment variable.');
                return 1;
            }

            DB::statement("SET app.current_company = ?", [$companyId]);

            // Process based on source type
            switch ($source) {
                case 'csv':
                    return $this->processCsvImport($format, $dryRun);
                
                case 'manual':
                case 'bank-feed':
                    return $this->processEntriesImport($source, $format, $dryRun);
                
                default:
                    $this->error("Unsupported source type: {$source}");
                    return 1;
            }

        } catch (\Throwable $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Process CSV file import.
     */
    private function processCsvImport(string $format, bool $dryRun): int
    {
        $filePath = $this->option('file');
        
        if (!$filePath) {
            $this->error('File path is required for CSV imports. Use --file option.');
            return 1;
        }

        // Validate file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Validate file format
        if (!str_ends_with(strtolower($filePath), '.csv')) {
            $this->error('File must be a CSV file (.csv extension)');
            return 1;
        }

        $this->info("Processing CSV file: {$filePath}");

        // Parse and validate CSV
        $createBatchAction = new CreatePaymentBatchAction();
        
        try {
            // Store file temporarily for parsing
            $tempPath = $this->storeFileTemporarily($filePath);
            $entries = $createBatchAction->parseAndValidateCsv($tempPath);
            $validation = $createBatchAction->validateCsvEntries($entries, $this->getCompanyId());

            // Display validation results
            $this->displayValidationResults($validation);

            if ($validation['error_count'] > 0 && !$this->confirm("Continue with import despite {$validation['error_count']} errors?")) {
                $this->info('Import cancelled.');
                return 0;
            }

            if ($dryRun) {
                $this->info('Dry run completed. No batch was created.');
                return 0;
            }

            // Create batch
            $batch = $this->createCsvBatch($tempPath, $validation['valid_entries']);

            $this->displayBatchResults($batch, $format);

            return 0;

        } finally {
            // Cleanup temporary file
            if (isset($tempPath) && Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }
        }
    }

    /**
     * Process manual/bank-feed entries import.
     */
    private function processEntriesImport(string $source, string $format, bool $dryRun): int
    {
        $entriesPath = $this->option('entries');
        
        if (!$entriesPath) {
            $this->error('Entries file path is required. Use --entries option.');
            return 1;
        }

        if (!file_exists($entriesPath)) {
            $this->error("File not found: {$entriesPath}");
            return 1;
        }

        $this->info("Processing entries file: {$entriesPath}");

        // Parse JSON entries
        $entriesJson = file_get_contents($entriesPath);
        $entries = json_decode($entriesJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON in entries file: ' . json_last_error_msg());
            return 1;
        }

        if (!is_array($entries) || empty($entries)) {
            $this->error('Entries file must contain a non-empty array of payment entries.');
            return 1;
        }

        // Validate entries
        $validation = $this->validateEntries($entries);

        // Display validation results
        $this->displayValidationResults([
            'valid_entries' => $validation['valid'],
            'errors' => $validation['errors'],
            'total_rows' => count($entries),
            'valid_count' => $validation['valid_count'],
            'error_count' => $validation['error_count'],
        ]);

        if ($validation['error_count'] > 0 && !$this->confirm("Continue with import despite {$validation['error_count']} errors?")) {
            $this->info('Import cancelled.');
            return 0;
        }

        if ($dryRun) {
            $this->info('Dry run completed. No batch was created.');
            return 0;
        }

        // Create batch
        $batch = $this->createEntriesBatch($source, $validation['valid']);

        $this->displayBatchResults($batch, $format);

        return 0;
    }

    /**
     * Validate payment entries.
     */
    private function validateEntries(array $entries): array
    {
        $valid = [];
        $errors = [];
        $validCount = 0;

        foreach ($entries as $index => $entry) {
            $entryErrors = [];

            // Validate required fields
            if (!isset($entry['entity_id'])) {
                $entryErrors[] = 'entity_id is required';
            }

            if (!isset($entry['payment_method'])) {
                $entryErrors[] = 'payment_method is required';
            } elseif (!in_array($entry['payment_method'], ['cash', 'bank_transfer', 'card', 'cheque', 'other'])) {
                $entryErrors[] = 'Invalid payment_method';
            }

            if (!isset($entry['amount'])) {
                $entryErrors[] = 'amount is required';
            } elseif (!is_numeric($entry['amount']) || (float) $entry['amount'] <= 0) {
                $entryErrors[] = 'Amount must be a positive number';
            }

            if (!isset($entry['payment_date'])) {
                $entryErrors[] = 'payment_date is required';
            }

            if (!empty($entryErrors)) {
                $errors["entry_" . ($index + 1)] = $entryErrors;
            } else {
                $valid[] = $entry;
                $validCount++;
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'valid_count' => $validCount,
            'error_count' => count($errors),
        ];
    }

    /**
     * Create CSV batch.
     */
    private function createCsvBatch(string $filePath, array $entries): PaymentBatch
    {
        $this->info('Creating CSV batch...');

        $file = new \Illuminate\Http\UploadedFile(
            storage_path('app/' . $filePath),
            basename($filePath),
            'text/csv',
            null,
            true,
            true
        );

        $batchData = [
            'source_type' => 'csv_import',
            'file' => $file,
            'notes' => $this->option('notes'),
            'metadata' => $this->parseMetadata(),
            'company_id' => $this->getCompanyId(),
            'created_by_user_id' => auth()->id(),
        ];

        $createBatchAction = new CreatePaymentBatchAction();
        $result = $createBatchAction->execute($batchData);

        return PaymentBatch::findOrFail($result['batch_id']);
    }

    /**
     * Create entries batch.
     */
    private function createEntriesBatch(string $source, array $entries): PaymentBatch
    {
        $this->info('Creating entries batch...');

        $batchData = [
            'source_type' => $source === 'bank-feed' ? 'bank_feed' : 'manual',
            'entries' => $entries,
            'notes' => $this->option('notes'),
            'metadata' => $this->parseMetadata(),
            'company_id' => $this->getCompanyId(),
            'created_by_user_id' => auth()->id(),
        ];

        $createBatchAction = new CreatePaymentBatchAction();
        $result = $createBatchAction->execute($batchData);

        return PaymentBatch::findOrFail($result['batch_id']);
    }

    /**
     * Store file temporarily.
     */
    private function storeFileTemporarily(string $originalPath): string
    {
        $filename = 'temp-' . time() . '-' . basename($originalPath);
        $tempPath = 'batch-temp/' . $filename;
        
        Storage::disk('local')->put($tempPath, file_get_contents($originalPath));
        
        return $tempPath;
    }

    /**
     * Display validation results.
     */
    private function displayValidationResults(array $validation): void
    {
        $this->info("Validation Results:");
        $this->info("  Total rows: {$validation['total_rows']}");
        $this->info("  Valid entries: {$validation['valid_count']}");
        $this->info("  Errors: {$validation['error_count']}");

        if (!empty($validation['errors'])) {
            $this->warn("\nValidation Errors:");
            foreach ($validation['errors'] as $row => $errors) {
                $this->warn("  {$row}: " . implode(', ', $errors));
            }
        }

        if ($validation['valid_count'] > 0) {
            $this->info("\nValid entries will be processed.");
        }
    }

    /**
     * Display batch results.
     */
    private function displayBatchResults(PaymentBatch $batch, string $format): void
    {
        if ($format === 'json') {
            $this->line(json_encode([
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'status' => $batch->status,
                'receipt_count' => $batch->receipt_count,
                'total_amount' => $batch->total_amount,
                'currency' => $batch->currency,
                'source_type' => $batch->source_type,
                'created_at' => $batch->created_at->toISOString(),
            ], JSON_PRETTY_PRINT));
            return;
        }

        $this->info("\n✅ Batch Created Successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['Batch ID', $batch->id],
                ['Batch Number', $batch->batch_number],
                ['Status', $batch->status_label],
                ['Source Type', $batch->source_type],
                ['Receipt Count', $batch->receipt_count],
                ['Total Amount', $batch->currency . ' ' . number_format($batch->total_amount, 2)],
                ['Created At', $batch->created_at->format('Y-m-d H:i:s')],
            ]
        );

        if ($batch->isProcessing()) {
            $this->info("\n📋 Batch is currently processing. You can check status with:");
            $this->info("php artisan payment:batch:status {$batch->id}");
        }
    }

    /**
     * Parse metadata option.
     */
    private function parseMetadata(): array
    {
        $metadataOption = $this->option('metadata');
        
        if (!$metadataOption) {
            return [];
        }

        $metadata = json_decode($metadataOption, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn('Invalid JSON in metadata option, using empty metadata');
            return [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Get company ID from environment.
     */
    private function getCompanyId(): ?string
    {
        return env('APP_COMPANY_ID');
    }
}