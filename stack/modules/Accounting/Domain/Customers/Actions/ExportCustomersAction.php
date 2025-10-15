<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Customers\Events\CustomersExported;
use Modules\Accounting\Domain\Customers\Telemetry\CustomerMetrics;

class ExportCustomersAction
{
    /**
     * Execute customer export.
     */
    public function execute(array $data): array
    {
        // Validate input data
        $validated = Validator::make($data, [
            'company_id' => 'required|uuid|exists:companies,id',
            'format' => 'required|string|in:csv,xlsx,json',
            'filters' => 'nullable|array',
            'filters.status' => 'nullable|array|in:active,inactive,pending,blocked',
            'filters.created_after' => 'nullable|date',
            'filters.created_before' => 'nullable|date',
            'filters.has_invoices' => 'nullable|boolean',
            'filters.has_outstanding_balance' => 'nullable|boolean',
            'filters.country' => 'nullable|string|max:2',
            'filters.payment_terms' => 'nullable|array',
            'filters.min_balance' => 'nullable|numeric|min:0',
            'filters.max_balance' => 'nullable|numeric|min:0',
            'filters.search' => 'nullable|string|max:255',
            'columns' => 'nullable|array',
            'columns.*' => 'string|in:id,name,email,phone,address_line_1,address_line_2,city,state,postal_code,country,tax_id,website,status,payment_terms,credit_limit,balance,total_invoiced,total_paid,currency,created_at,updated_at,custom_fields',
            'sort_by' => 'nullable|string|in:name,email,created_at,updated_at,balance,total_invoiced',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:10000',
            'include_invoices' => 'nullable|boolean',
            'include_payments' => 'nullable|boolean',
            'include_aging' => 'nullable|boolean',
            'compress' => 'nullable|boolean',
            'options' => 'nullable|array',
            'options.date_format' => 'nullable|string|in:Y-m-d,m/d/Y,d/m/Y',
            'options.currency_format' => 'nullable|string|in:symbol,code,both',
            'options.include_headers' => 'nullable|boolean',
            'options.encoding' => 'nullable|string|in:utf-8,latin1',
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ])->validate();

        $companyId = $validated['company_id'];
        $format = $validated['format'];
        $filters = $validated['filters'] ?? [];
        $columns = $validated['columns'] ?? $this->getDefaultColumns();
        $options = $validated['options'] ?? [];
        $createdBy = $validated['created_by_user_id'] ?? null;

        // Verify company exists
        $company = Company::findOrFail($companyId);

        // Generate unique export batch ID
        $exportBatchId = $this->generateExportBatchId($companyId);

        // Build customer query
        $query = $this->buildCustomerQuery($companyId, $filters, $validated);

        // Get total count
        $totalCount = $query->count();

        // Apply limit if specified
        if (isset($validated['limit'])) {
            $query->limit($validated['limit']);
        }

        // Retrieve customers
        $customers = $query->get();

        // Prepare export data
        $exportData = $this->prepareExportData($customers, $columns, $options, $validated);

        // Generate export file
        $filename = $this->generateExportFilename($companyId, $exportBatchId, $format, $options['compress'] ?? false);
        $filePath = $this->generateExportFile($exportData, $format, $filename, $options);

        // Prepare export metadata
        $metadata = $validated['metadata'] ?? [];
        $metadata['export_batch_id'] = $exportBatchId;
        $metadata['format'] = $format;
        $metadata['total_count'] = $totalCount;
        $metadata['exported_count'] = $customers->count();
        $metadata['filters'] = $filters;
        $metadata['columns'] = $columns;
        $metadata['file_size'] = Storage::size($filePath);
        $metadata['file_hash'] = hash_file('sha256', Storage::path($filePath));

        // Record export metrics
        CustomerMetrics::customersExported($companyId, $format, $customers->count());

        // Emit export completed event
        event(new CustomersExported([
            'export_batch_id' => $exportBatchId,
            'company_id' => $companyId,
            'format' => $format,
            'total_count' => $totalCount,
            'exported_count' => $customers->count(),
            'file_path' => $filePath,
            'file_size' => $metadata['file_size'],
            'created_by_user_id' => $createdBy,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]));

        return [
            'export_batch_id' => $exportBatchId,
            'company_id' => $companyId,
            'format' => $format,
            'total_count' => $totalCount,
            'exported_count' => $customers->count(),
            'file_url' => Storage::url($filePath),
            'file_name' => basename($filePath),
            'file_size' => $metadata['file_size'],
            'file_hash' => $metadata['file_hash'],
            'download_url' => Storage::temporaryUrl($filePath, now()->addHours(24)),
            'expires_at' => now()->addHours(24)->toISOString(),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate unique export batch ID.
     */
    private function generateExportBatchId(string $companyId): string
    {
        $today = now()->format('Ymd');
        $sequence = DB::table('audit_entries')
            ->where('company_id', $companyId)
            ->where('action', 'customers.exported')
            ->whereDate('created_at', $today)
            ->count() + 1;

        return "EXPORT-{$today}-".str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get default columns for export.
     */
    private function getDefaultColumns(): array
    {
        return [
            'id', 'name', 'email', 'phone', 'address_line_1', 'address_line_2',
            'city', 'state', 'postal_code', 'country', 'tax_id', 'website',
            'status', 'payment_terms', 'credit_limit', 'balance', 'currency',
            'total_invoiced', 'total_paid', 'created_at', 'updated_at',
        ];
    }

    /**
     * Build customer query based on filters.
     */
    private function buildCustomerQuery(string $companyId, array $filters, array $options)
    {
        $query = DB::table('invoicing.customers')
            ->where('company_id', $companyId);

        // Apply status filter
        if (! empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        // Apply date filters
        if (! empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (! empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        // Apply country filter
        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        // Apply payment terms filter
        if (! empty($filters['payment_terms'])) {
            $query->whereIn('payment_terms', $filters['payment_terms']);
        }

        // Apply balance filters
        if (isset($filters['min_balance'])) {
            $query->where('balance', '>=', $filters['min_balance']);
        }

        if (isset($filters['max_balance'])) {
            $query->where('balance', '<=', $filters['max_balance']);
        }

        // Apply has_invoices filter
        if (isset($filters['has_invoices'])) {
            if ($filters['has_invoices']) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('invoicing.invoices')
                        ->whereRaw('invoicing.invoices.customer_id = invoicing.customers.id');
                });
            } else {
                $query->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('invoicing.invoices')
                        ->whereRaw('invoicing.invoices.customer_id = invoicing.customers.id');
                });
            }
        }

        // Apply has_outstanding_balance filter
        if (isset($filters['has_outstanding_balance'])) {
            if ($filters['has_outstanding_balance']) {
                $query->where('balance', '>', 0);
            } else {
                $query->where('balance', '<=', 0);
            }
        }

        // Apply search filter
        if (! empty($filters['search'])) {
            $searchTerm = '%'.$filters['search'].'%';
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'ilike', $searchTerm)
                    ->orWhere('email', 'ilike', $searchTerm)
                    ->orWhere('phone', 'ilike', $searchTerm)
                    ->orWhere('tax_id', 'ilike', $searchTerm);
            });
        }

        // Apply sorting
        $sortBy = $options['sort_by'] ?? 'name';
        $sortDirection = $options['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        return $query;
    }

    /**
     * Prepare export data from customers.
     */
    private function prepareExportData($customers, array $columns, array $options, array $validated): array
    {
        $exportData = [];
        $dateFormat = $options['date_format'] ?? 'Y-m-d';
        $currencyFormat = $options['currency_format'] ?? 'symbol';
        $includeInvoices = $validated['include_invoices'] ?? false;
        $includePayments = $validated['include_payments'] ?? false;
        $includeAging = $validated['include_aging'] ?? false;

        foreach ($customers as $customer) {
            $row = [];

            foreach ($columns as $column) {
                $value = $this->getCustomerColumnValue($customer, $column, $dateFormat, $currencyFormat);

                // Add related data if requested
                if ($column === 'custom_fields' && is_string($value)) {
                    $value = json_decode($value, true) ?? [];
                }

                $row[$column] = $value;
            }

            // Add invoice data if requested
            if ($includeInvoices) {
                $row['_invoices'] = $this->getCustomerInvoices($customer->id, $dateFormat);
            }

            // Add payment data if requested
            if ($includePayments) {
                $row['_payments'] = $this->getCustomerPayments($customer->id, $dateFormat);
            }

            // Add aging data if requested
            if ($includeAging) {
                $row['_aging'] = $this->getCustomerAging($customer->id);
            }

            $exportData[] = $row;
        }

        return $exportData;
    }

    /**
     * Get formatted value for customer column.
     */
    private function getCustomerColumnValue($customer, string $column, string $dateFormat, string $currencyFormat)
    {
        $value = $customer->{$column} ?? null;

        if ($value === null) {
            return '';
        }

        // Format dates
        if (in_array($column, ['created_at', 'updated_at'])) {
            return date($dateFormat, strtotime($value));
        }

        // Format currency values
        if (in_array($column, ['balance', 'credit_limit', 'total_invoiced', 'total_paid'])) {
            return $this->formatCurrency($value, $customer->currency ?? 'USD', $currencyFormat);
        }

        // Format status
        if ($column === 'status') {
            return ucwords(str_replace('_', ' ', $value));
        }

        return $value;
    }

    /**
     * Format currency value.
     */
    private function formatCurrency(float $amount, string $currency, string $format): string
    {
        switch ($format) {
            case 'symbol':
                $symbol = $this->getCurrencySymbol($currency);

                return $symbol.number_format($amount, 2);
            case 'code':
                return number_format($amount, 2).' '.$currency;
            case 'both':
                $symbol = $this->getCurrencySymbol($currency);

                return $symbol.number_format($amount, 2).' '.$currency;
            default:
                return number_format($amount, 2);
        }
    }

    /**
     * Get currency symbol.
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF ',
            'CNY' => '¥',
        ];

        return $symbols[$currency] ?? $currency.' ';
    }

    /**
     * Get customer invoices.
     */
    private function getCustomerInvoices(string $customerId, string $dateFormat): array
    {
        return DB::table('invoicing.invoices')
            ->where('customer_id', $customerId)
            ->select(['id', 'invoice_number', 'issue_date', 'due_date', 'total_amount', 'status', 'balance'])
            ->orderBy('issue_date', 'desc')
            ->get()
            ->map(function ($invoice) use ($dateFormat) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'issue_date' => date($dateFormat, strtotime($invoice->issue_date)),
                    'due_date' => date($dateFormat, strtotime($invoice->due_date)),
                    'total_amount' => $invoice->total_amount,
                    'balance' => $invoice->balance,
                    'status' => $invoice->status,
                ];
            })
            ->toArray();
    }

    /**
     * Get customer payments.
     */
    private function getCustomerPayments(string $customerId, string $dateFormat): array
    {
        return DB::table('invoicing.receipts')
            ->join('invoicing.invoices', 'invoicing.receipts.invoice_id', '=', 'invoicing.invoices.id')
            ->where('invoicing.invoices.customer_id', $customerId)
            ->select(['invoicing.receipts.id', 'invoicing.receipts.receipt_number', 'invoicing.receipts.receipt_date', 'invoicing.receipts.amount', 'invoicing.receipts.payment_method'])
            ->orderBy('invoicing.receipts.receipt_date', 'desc')
            ->get()
            ->map(function ($payment) use ($dateFormat) {
                return [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'receipt_date' => date($dateFormat, strtotime($payment->receipt_date)),
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                ];
            })
            ->toArray();
    }

    /**
     * Get customer aging data.
     */
    private function getCustomerAging(string $customerId): array
    {
        $aging = DB::table('invoicing.customer_aging_snapshots')
            ->where('customer_id', $customerId)
            ->whereDate('snapshot_date', now()->toDateString())
            ->first();

        if (! $aging) {
            return [
                'current' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_90_plus' => 0,
                'total' => 0,
            ];
        }

        return [
            'current' => $aging->bucket_current ?? 0,
            'days_1_30' => $aging->bucket_1_30 ?? 0,
            'days_31_60' => $aging->bucket_31_60 ?? 0,
            'days_61_90' => $aging->bucket_61_90 ?? 0,
            'days_90_plus' => $aging->bucket_90_plus ?? 0,
            'total' => ($aging->bucket_current ?? 0) + ($aging->bucket_1_30 ?? 0) + ($aging->bucket_31_60 ?? 0) + ($aging->bucket_61_90 ?? 0) + ($aging->bucket_90_plus ?? 0),
        ];
    }

    /**
     * Generate export filename.
     */
    private function generateExportFilename(string $companyId, string $exportBatchId, string $format, bool $compress): string
    {
        $company = Company::find($companyId);
        $companyName = Str::slug($company->name ?? 'unknown');
        $date = now()->format('Y-m-d');
        $extension = $compress ? 'zip' : $this->getFileExtension($format);

        return "customers-{$companyName}-{$date}-{$exportBatchId}.{$extension}";
    }

    /**
     * Get file extension for format.
     */
    private function getFileExtension(string $format): string
    {
        return match ($format) {
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'json' => 'json',
            default => 'txt',
        };
    }

    /**
     * Generate export file.
     */
    private function generateExportFile(array $data, string $format, string $filename, array $options): string
    {
        $includeHeaders = $options['include_headers'] ?? true;
        $encoding = $options['encoding'] ?? 'utf-8';
        $compress = $options['compress'] ?? false;

        $directory = 'customer-exports/'.date('Y/m');
        $filePath = $directory.'/'.$filename;

        switch ($format) {
            case 'csv':
                $content = $this->generateCsvContent($data, $includeHeaders, $encoding);
                break;
            case 'xlsx':
                $content = $this->generateXlsxContent($data, $includeHeaders);
                break;
            case 'json':
                $content = $this->generateJsonContent($data);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }

        if ($compress) {
            $filePath = $this->compressFile($content, $filePath, $format);
        } else {
            Storage::put($filePath, $content);
        }

        return $filePath;
    }

    /**
     * Generate CSV content.
     */
    private function generateCsvContent(array $data, bool $includeHeaders, string $encoding): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Add headers if requested
        if ($includeHeaders) {
            $headers = array_keys($data[0]);
            fputcsv($output, $headers);
        }

        // Add data rows
        foreach ($data as $row) {
            $flatRow = $this->flattenRow($row);
            fputcsv($output, $flatRow);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        // Convert encoding if needed
        if ($encoding !== 'utf-8') {
            $content = mb_convert_encoding($content, $encoding, 'utf-8');
        }

        return $content;
    }

    /**
     * Generate XLSX content.
     */
    private function generateXlsxContent(array $data, bool $includeHeaders): string
    {
        // For now, return CSV content (can be enhanced with a proper XLSX library)
        return $this->generateCsvContent($data, $includeHeaders, 'utf-8');
    }

    /**
     * Generate JSON content.
     */
    private function generateJsonContent(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Flatten row for CSV export (handle nested arrays).
     */
    private function flattenRow(array $row): array
    {
        $flattened = [];

        foreach ($row as $key => $value) {
            if (is_array($value)) {
                // Convert arrays to JSON strings for CSV
                $flattened[$key] = json_encode($value);
            } elseif (is_object($value)) {
                $flattened[$key] = json_encode($value);
            } else {
                $flattened[$key] = $value;
            }
        }

        return $flattened;
    }

    /**
     * Compress file content.
     */
    private function compressFile(string $content, string $filePath, string $originalFormat): string
    {
        $tempPath = sys_get_temp_dir().'/'.uniqid().'.'.$originalFormat;
        file_put_contents($tempPath, $content);

        $zip = new \ZipArchive;
        $zipFilePath = str_replace('.zip', '.zip', $filePath);

        if ($zip->open(Storage::path($zipFilePath), \ZipArchive::CREATE) === true) {
            $zip->addFile($tempPath, basename($filePath));
            $zip->close();
        }

        unlink($tempPath);

        return $zipFilePath;
    }
}
