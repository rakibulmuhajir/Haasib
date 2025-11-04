<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Customers\Events\CustomersImported;
use Modules\Accounting\Domain\Customers\Jobs\ProcessCustomerImport;
use Modules\Accounting\Domain\Customers\Telemetry\CustomerMetrics;

class ImportCustomersAction
{
    /**
     * Execute customer import.
     */
    public function execute(array $data): array
    {
        // Validate input data
        $validated = Validator::make($data, [
            'company_id' => 'required|uuid|exists:companies,id',
            'source_type' => 'required|string|in:csv,json,manual',
            'file' => 'required_if:source_type,csv|file|mimes:csv,txt|max:5120', // 5MB max
            'entries' => 'required_if:source_type,json,manual|array|min:1',
            'entries.*.name' => 'required|string|max:255',
            'entries.*.email' => 'nullable|email|max:255',
            'entries.*.phone' => 'nullable|string|max:50',
            'entries.*.address_line_1' => 'nullable|string|max:255',
            'entries.*.address_line_2' => 'nullable|string|max:255',
            'entries.*.city' => 'nullable|string|max:100',
            'entries.*.state' => 'nullable|string|max:100',
            'entries.*.postal_code' => 'nullable|string|max:20',
            'entries.*.country' => 'nullable|string|max:2',
            'entries.*.tax_id' => 'nullable|string|max:50',
            'entries.*.website' => 'nullable|url|max:255',
            'entries.*.notes' => 'nullable|string',
            'entries.*.custom_fields' => 'nullable|array',
            'options' => 'nullable|array',
            'options.skip_duplicates' => 'boolean',
            'options.update_existing' => 'boolean',
            'options.validate_data' => 'boolean',
            'options.send_welcome' => 'boolean',
            'options.default_currency' => 'nullable|string|max:3',
            'options.default_payment_terms' => 'nullable|integer|min:0|max:365',
            'options.default_credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $sourceType = $validated['source_type'];
            $companyId = $validated['company_id'];
            $createdBy = $validated['created_by_user_id'] ?? null;

            // Verify company exists and user has permission
            $company = Company::findOrFail($companyId);

            // Generate unique import batch ID
            $importBatchId = $this->generateImportBatchId($companyId);

            // Prepare import metadata
            $metadata = $validated['metadata'] ?? [];
            $metadata['source_type'] = $sourceType;
            $metadata['import_batch_id'] = $importBatchId;

            // Process based on source type
            $result = match ($sourceType) {
                'csv' => $this->processCsvImport($validated, $companyId, $importBatchId, $metadata),
                'json', 'manual' => $this->processEntriesImport($validated, $companyId, $importBatchId, $metadata),
                default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}")
            };

            // Record import metrics
            CustomerMetrics::customersImported($companyId, $sourceType, $result['total_count'], $result['imported_count']);

            // Emit import started event
            event(new CustomersImported([
                'import_batch_id' => $importBatchId,
                'company_id' => $companyId,
                'source_type' => $sourceType,
                'total_count' => $result['total_count'],
                'imported_count' => $result['imported_count'],
                'skipped_count' => $result['skipped_count'],
                'error_count' => $result['error_count'],
                'created_by_user_id' => $createdBy,
                'metadata' => $metadata,
                'timestamp' => now()->toISOString(),
            ]));

            return [
                'import_batch_id' => $importBatchId,
                'company_id' => $companyId,
                'source_type' => $sourceType,
                'total_count' => $result['total_count'],
                'imported_count' => $result['imported_count'],
                'skipped_count' => $result['skipped_count'],
                'error_count' => $result['error_count'],
                'status' => $result['immediate'] ? 'completed' : 'processing',
                'errors' => $result['errors'] ?? [],
                'warnings' => $result['warnings'] ?? [],
                'created_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Generate unique import batch ID.
     */
    private function generateImportBatchId(string $companyId): string
    {
        $today = now()->format('Ymd');
        $sequence = DB::table('acct.customers')
            ->where('company_id', $companyId)
            ->whereRaw("metadata->>'import_batch_id' LIKE ?", ["IMPORT-{$today}-%"])
            ->distinct(DB::raw("metadata->>'import_batch_id'"))
            ->count() + 1;

        return "IMPORT-{$today}-".str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Process CSV import.
     */
    private function processCsvImport(array $validated, string $companyId, string $importBatchId, array $metadata): array
    {
        $file = $validated['file'];
        $options = $validated['options'] ?? [];

        // Store file for processing
        $filePath = $file->store('customer-imports/'.$importBatchId, 'local');
        $originalFilename = $file->getClientOriginalName();
        $fileHash = hash_file('sha256', $file->path());

        // Parse and validate CSV
        $entries = $this->parseCsvFile($file->path());
        $validation = $this->validateCustomerEntries($entries, $companyId, $options);

        // Update metadata with file info
        $metadata['original_filename'] = $originalFilename;
        $metadata['file_hash'] = $fileHash;
        $metadata['file_path'] = $filePath;
        $metadata['file_size'] = $file->getSize();

        // Process immediately for small files, queue for large ones
        $immediate = count($validation['valid']) <= 100;

        if ($immediate) {
            return $this->processCustomersImmediately($validation['valid'], $companyId, $importBatchId, $options, $metadata);
        } else {
            // Queue for batch processing
            $this->queueCustomerImport($companyId, $importBatchId, $validation['valid'], $options, $metadata);

            return [
                'total_count' => $validation['total_count'],
                'imported_count' => 0, // Will be updated during processing
                'skipped_count' => $validation['skipped_count'],
                'error_count' => $validation['error_count'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'] ?? [],
                'immediate' => false,
            ];
        }
    }

    /**
     * Process entries import (JSON/manual).
     */
    private function processEntriesImport(array $validated, string $companyId, string $importBatchId, array $metadata): array
    {
        $entries = $validated['entries'];
        $options = $validated['options'] ?? [];

        // Validate entries
        $validation = $this->validateCustomerEntries($entries, $companyId, $options);

        // Update metadata
        $metadata['entry_count'] = count($entries);

        // Process immediately
        $result = $this->processCustomersImmediately($validation['valid'], $companyId, $importBatchId, $options, $metadata);
        $result['errors'] = $validation['errors'];
        $result['warnings'] = $validation['warnings'] ?? [];
        $result['immediate'] = true;

        return $result;
    }

    /**
     * Parse CSV file into array of entries.
     */
    private function parseCsvFile(string $filePath): array
    {
        $entries = [];
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new \InvalidArgumentException('Cannot read CSV file');
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV file is empty or invalid');
        }

        // Normalize headers
        $normalizedHeaders = array_map(fn ($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '_', $h))), $headers);

        // Read data rows
        $rowIndex = 1;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($normalizedHeaders)) {
                $entry = array_combine($normalizedHeaders, $row);
                $entry['_row_index'] = $rowIndex;
                $entries[] = $entry;
            }
            $rowIndex++;
        }

        fclose($handle);

        return $entries;
    }

    /**
     * Validate customer entries.
     */
    private function validateCustomerEntries(array $entries, string $companyId, array $options): array
    {
        $valid = [];
        $errors = [];
        $warnings = [];
        $validCount = 0;
        $skippedCount = 0;

        $skipDuplicates = $options['skip_duplicates'] ?? true;
        $updateExisting = $options['update_existing'] ?? false;
        $validateData = $options['validate_data'] ?? true;

        foreach ($entries as $index => $entry) {
            $entryErrors = [];
            $entryWarnings = [];

            // Remove metadata fields
            $customerData = $this->extractCustomerData($entry);

            // Validate required fields
            if (empty($customerData['name'])) {
                $entryErrors[] = 'Name is required';
            }

            // Validate email format if provided
            if (! empty($customerData['email']) && ! filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
                $entryErrors[] = 'Invalid email format';
            }

            // Check for duplicates
            if ($skipDuplicates && $this->isDuplicateCustomer($customerData, $companyId)) {
                if ($updateExisting) {
                    $entryWarnings[] = 'Existing customer found and will be updated';
                } else {
                    $entryWarnings[] = 'Duplicate customer skipped';
                    $skippedCount++;

                    continue;
                }
            }

            // Additional validation if enabled
            if ($validateData) {
                $entryWarnings = array_merge($entryWarnings, $this->validateCustomerData($customerData));
            }

            if (empty($entryErrors)) {
                $customerData['_source_index'] = $entry['_row_index'] ?? $index;
                $valid[] = $customerData;
                $validCount++;
            } else {
                $rowLabel = $entry['_row_index'] ?? ('row_'.($index + 1));
                $errors[$rowLabel] = $entryErrors;
            }

            if (! empty($entryWarnings)) {
                $rowLabel = $entry['_row_index'] ?? ('row_'.($index + 1));
                $warnings[$rowLabel] = $entryWarnings;
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'total_count' => count($entries),
            'valid_count' => $validCount,
            'skipped_count' => $skippedCount,
            'error_count' => count($errors),
        ];
    }

    /**
     * Extract customer data from entry.
     */
    private function extractCustomerData(array $entry): array
    {
        $mappedFields = [
            'name' => ['name', 'customer_name', 'customer'],
            'email' => ['email', 'email_address'],
            'phone' => ['phone', 'phone_number', 'telephone'],
            'address_line_1' => ['address_line_1', 'address', 'street'],
            'address_line_2' => ['address_line_2', 'address_2', 'suite'],
            'city' => ['city'],
            'state' => ['state', 'province', 'region'],
            'postal_code' => ['postal_code', 'zip', 'zipcode'],
            'country' => ['country', 'country_code'],
            'tax_id' => ['tax_id', 'vat_number', 'tax_number'],
            'website' => ['website', 'url'],
            'notes' => ['notes', 'comments', 'remarks'],
        ];

        $customerData = [];

        foreach ($mappedFields as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($entry[$alias]) && ! empty($entry[$alias])) {
                    $customerData[$field] = trim($entry[$alias]);
                    break;
                }
            }
        }

        // Handle custom fields
        $customFields = [];
        foreach ($entry as $key => $value) {
            if (! in_array($key, array_keys($mappedFields)) && ! str_starts_with($key, '_')) {
                $customFields[$key] = $value;
            }
        }

        if (! empty($customFields)) {
            $customerData['custom_fields'] = $customFields;
        }

        return $customerData;
    }

    /**
     * Check if customer is a duplicate.
     */
    private function isDuplicateCustomer(array $customerData, string $companyId): bool
    {
        $query = DB::table('acct.customers')->where('company_id', $companyId);

        if (! empty($customerData['email'])) {
            $query->orWhere('email', $customerData['email']);
        }

        if (! empty($customerData['tax_id'])) {
            $query->orWhere('tax_id', $customerData['tax_id']);
        }

        if (! empty($customerData['name']) && ! empty($customerData['phone'])) {
            $query->orWhere(function ($q) use ($customerData) {
                $q->where('name', $customerData['name'])
                    ->where('phone', $customerData['phone']);
            });
        }

        return $query->exists();
    }

    /**
     * Validate customer data quality.
     */
    private function validateCustomerData(array $customerData): array
    {
        $warnings = [];

        // Check phone number format
        if (! empty($customerData['phone']) && ! preg_match('/^[\d\s\-\+\(\)]{10,}$/', $customerData['phone'])) {
            $warnings[] = 'Phone number format may be invalid';
        }

        // Check postal code format based on country
        if (! empty($customerData['postal_code']) && ! empty($customerData['country'])) {
            $country = strtoupper($customerData['country']);
            $postalCode = $customerData['postal_code'];

            if ($country === 'US' && ! preg_match('/^\d{5}(-\d{4})?$/', $postalCode)) {
                $warnings[] = 'US ZIP code format appears invalid';
            }
        }

        // Check website URL
        if (! empty($customerData['website']) && ! str_starts_with($customerData['website'], 'http')) {
            $warnings[] = 'Website URL should start with http:// or https://';
        }

        return $warnings;
    }

    /**
     * Process customers immediately.
     */
    private function processCustomersImmediately(array $customers, string $companyId, string $importBatchId, array $options, array $metadata): array
    {
        $importedCount = 0;
        $skippedCount = 0;
        $updateExisting = $options['update_existing'] ?? false;
        $defaultCurrency = $options['default_currency'] ?? 'USD';
        $defaultPaymentTerms = $options['default_payment_terms'] ?? 30;
        $defaultCreditLimit = $options['default_credit_limit'] ?? 0;

        foreach ($customers as $customerData) {
            try {
                $customerData['company_id'] = $companyId;
                $customerData['currency_id'] = $customerData['currency_id'] ?? $defaultCurrency;
                $customerData['payment_terms'] = $customerData['payment_terms'] ?? $defaultPaymentTerms;
                $customerData['credit_limit'] = $customerData['credit_limit'] ?? $defaultCreditLimit;
                $customerData['status'] = 'active';
                $customerData['metadata']['import_batch_id'] = $importBatchId;
                $customerData['metadata']['import_source_index'] = $customerData['_source_index'] ?? null;

                // Remove internal fields
                unset($customerData['_source_index']);

                if ($updateExisting && $this->isDuplicateCustomer($customerData, $companyId)) {
                    // Update existing customer
                    $existing = $this->findExistingCustomer($customerData, $companyId);
                    DB::table('acct.customers')
                        ->where('id', $existing->id)
                        ->update($customerData);
                } else {
                    // Create new customer
                    $customerData['id'] = Str::uuid();
                    DB::table('acct.customers')->insert($customerData);
                }

                $importedCount++;

            } catch (\Exception $e) {
                $skippedCount++;
                // Log error but continue processing
                \Log::warning('Failed to import customer', [
                    'customer_data' => $customerData,
                    'error' => $e->getMessage(),
                    'import_batch_id' => $importBatchId,
                ]);
            }
        }

        return [
            'total_count' => count($customers),
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'error_count' => 0,
            'immediate' => true,
        ];
    }

    /**
     * Find existing customer for update.
     */
    private function findExistingCustomer(array $customerData, string $companyId)
    {
        $query = DB::table('acct.customers')->where('company_id', $companyId);

        if (! empty($customerData['email'])) {
            $existing = $query->where('email', $customerData['email'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($customerData['tax_id'])) {
            $existing = $query->where('tax_id', $customerData['tax_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($customerData['name']) && ! empty($customerData['phone'])) {
            $existing = $query->where('name', $customerData['name'])
                ->where('phone', $customerData['phone'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * Queue customer import for batch processing.
     */
    private function queueCustomerImport(string $companyId, string $importBatchId, array $customers, array $options, array $metadata): void
    {
        Queue::push(ProcessCustomerImport::class, [
            'company_id' => $companyId,
            'import_batch_id' => $importBatchId,
            'customers' => $customers,
            'options' => $options,
            'metadata' => $metadata,
        ]);
    }
}
