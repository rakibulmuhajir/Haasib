<?php

namespace App\Console\Commands\Concerns;

trait HandlesInvoiceOperations
{
    /**
     * Validate company access for the current user.
     */
    protected function validateCompanyAccess(int $companyId): bool
    {
        $user = $this->user ?? auth()->user();

        if (! $user) {
            $this->error('Authentication required.');

            return false;
        }

        // Check if user has access to the company
        $hasAccess = $user->companies()->where('companies.id', $companyId)->exists();

        if (! $hasAccess) {
            $this->error("Access denied to company #{$companyId}.");

            return false;
        }

        return true;
    }

    /**
     * Handle service exceptions with user-friendly messages.
     */
    protected function handleServiceException(\Throwable $exception): void
    {
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $this->error('Validation failed:');
            foreach ($exception->errors() as $field => $errors) {
                foreach ($errors as $error) {
                    $this->line("  - {$field}: {$error}");
                }
            }
        } elseif ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            $this->error('You are not authorized to perform this action.');
        } elseif ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->error('The requested resource was not found.');
        } else {
            $this->error('An error occurred: '.$exception->getMessage());

            if (config('app.debug')) {
                $this->line('Stack trace:');
                $this->line($exception->getTraceAsString());
            }
        }
    }

    /**
     * Format command output based on format preference.
     */
    protected function formatOutput(mixed $data, string $format = 'table'): void
    {
        match ($format) {
            'table' => $this->formatAsTable($data),
            'json' => $this->formatAsJson($data),
            'csv' => $this->formatAsCsv($data),
            'text' => $this->formatAsText($data),
            default => $this->formatAsTable($data),
        };
    }

    /**
     * Format data as a table.
     */
    protected function formatAsTable(mixed $data): void
    {
        if (empty($data)) {
            $this->line('No results found.');

            return;
        }

        // Handle single record
        if (isset($data['id']) && ! is_array($data['id'])) {
            $tableData = [];
            foreach ($data as $key => $value) {
                $tableData[] = [ucfirst(str_replace('_', ' ', $key)), $this->formatValue($value)];
            }
            $this->table(['Property', 'Value'], $tableData);

            return;
        }

        // Handle collection of records
        if (is_array($data) && ! isset($data[0])) {
            $data = [$data];
        }

        if (! empty($data) && isset($data[0])) {
            $headers = array_map(fn ($key) => ucfirst(str_replace('_', ' ', $key)), array_keys($data[0]));
            $rows = array_map(fn ($row) => array_map(fn ($value) => $this->formatValue($value), $row), $data);
            $this->table($headers, $rows);
        }
    }

    /**
     * Format data as JSON.
     */
    protected function formatAsJson(mixed $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Format data as CSV.
     */
    protected function formatAsCsv(mixed $data): void
    {
        if (empty($data)) {
            return;
        }

        if (isset($data['id']) && ! is_array($data['id'])) {
            // Single record
            $this->line(implode(',', array_keys($data)));
            $this->line(implode(',', array_map(fn ($value) => $this->escapeCsvValue($value), $data)));
        } else {
            // Multiple records
            $headers = array_keys($data[0]);
            $this->line(implode(',', $headers));

            foreach ($data as $row) {
                $this->line(implode(',', array_map(fn ($value) => $this->escapeCsvValue($value), $row)));
            }
        }
    }

    /**
     * Format data as plain text.
     */
    protected function formatAsText(mixed $data): void
    {
        if (empty($data)) {
            $this->line('No results found.');

            return;
        }

        if (isset($data['id']) && ! is_array($data['id'])) {
            foreach ($data as $key => $value) {
                $this->line(ucfirst(str_replace('_', ' ', $key)).': '.$this->formatValue($value));
            }
        } else {
            foreach ($data as $index => $item) {
                $this->line('Record #'.($index + 1).':');
                foreach ($item as $key => $value) {
                    $this->line('  '.ucfirst(str_replace('_', ' ', $key)).': '.$this->formatValue($value));
                }
                $this->line('');
            }
        }
    }

    /**
     * Format a value for display.
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Escape a value for CSV output.
     */
    protected function escapeCsvValue(mixed $value): string
    {
        $value = $this->formatValue($value);

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    /**
     * Get the authenticated user.
     */
    protected function getCurrentUser(): ?\App\Models\User
    {
        return $this->user ?? auth()->user();
    }

    /**
     * Get the current company context.
     */
    protected function getCurrentCompany(): ?\App\Models\Company
    {
        $user = $this->getCurrentUser();

        if (! $user) {
            return null;
        }

        // Try to get company from option or context
        $companyId = $this->option('company') ?? session('current_company_id');

        if ($companyId) {
            return $user->companies()->where('companies.id', $companyId)->first();
        }

        // Return user's primary company if no specific company is set
        return $user->companies()->first();
    }

    /**
     * Display success message with optional details.
     */
    protected function success(string $message, array $details = []): void
    {
        $this->info("✓ {$message}");

        foreach ($details as $key => $value) {
            $this->line("  {$key}: {$this->formatValue($value)}");
        }
    }

    /**
     * Display warning message.
     */
    protected function warning(string $message): void
    {
        $this->warn("⚠ {$message}");
    }

    /**
     * Display error message.
     */
    public function error($message, $verbosity = null): void
    {
        parent::error("✗ {$message}", $verbosity);
    }
}
