<?php

namespace Modules\Ledger\Services;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Exception;
use Illuminate\Support\Collection;
use OfxParser\Parser as OfxParser;

class BankStatementImportService
{
    private array $csvMapping = [
        'date' => ['date', 'transaction date', 'transaction_date', 'posted date', 'posted_date'],
        'description' => ['description', 'memo', 'details', 'transaction description'],
        'amount' => ['amount', 'transaction amount', 'debit', 'credit', 'value'],
        'balance' => ['balance', 'running balance', 'account balance'],
        'reference' => ['reference', 'reference number', 'ref', 'transaction id', 'transaction_id', 'fitid'],
    ];

    public function parseStatement(BankStatement $statement): Collection
    {
        return match ($statement->format) {
            'csv' => $this->parseCsv($statement),
            'ofx', 'qfx' => $this->parseOfx($statement),
            default => throw new Exception("Unsupported format: {$statement->format}"),
        };
    }

    private function parseCsv(BankStatement $statement): Collection
    {
        $filePath = storage_path('app/'.$statement->file_path);

        if (! file_exists($filePath)) {
            throw new Exception("Statement file not found: {$statement->file_path}");
        }

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        if (empty($lines) || empty(trim($lines[0]))) {
            throw new Exception('CSV file is empty or invalid');
        }

        $headers = str_getcsv($lines[0]);
        $columnMapping = $this->detectCsvColumns($headers);

        if (empty($columnMapping)) {
            throw new Exception('Could not detect required CSV columns. Expected: date, description, amount');
        }

        $statementLines = collect();
        $lineNumber = 1;

        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) {
                continue;
            }

            try {
                $rowData = str_getcsv($lines[$i]);

                if (count($rowData) < count($headers)) {
                    // Skip malformed rows but log warning
                    continue;
                }

                $statementLine = $this->createStatementLineFromCsvRow($rowData, $headers, $columnMapping, $statement, $lineNumber);

                if ($statementLine) {
                    $statementLines->push($statementLine);
                }

                $lineNumber++;
            } catch (Exception $e) {
                // Log warning for individual row errors but continue processing
                continue;
            }
        }

        if ($statementLines->isEmpty()) {
            throw new Exception('No valid transaction lines found in CSV file');
        }

        return $statementLines;
    }

    private function parseOfx(BankStatement $statement): Collection
    {
        $filePath = storage_path('app/'.$statement->file_path);

        if (! file_exists($filePath)) {
            throw new Exception("Statement file not found: {$statement->file_path}");
        }

        try {
            $parser = new OfxParser;
            $ofx = $parser->load($filePath);

            $statementLines = collect();
            $lineNumber = 1;

            foreach ($ofx->bankAccounts as $bankAccount) {
                foreach ($bankAccount->statement->transactions as $transaction) {
                    try {
                        $statementLine = $this->createStatementLineFromOfxTransaction($transaction, $statement, $lineNumber);
                        $statementLines->push($statementLine);
                        $lineNumber++;
                    } catch (Exception $e) {
                        // Continue processing other transactions
                        continue;
                    }
                }
            }

            if ($statementLines->isEmpty()) {
                throw new Exception('No valid transactions found in OFX file');
            }

            return $statementLines;
        } catch (Exception $e) {
            throw new Exception('Error parsing OFX file: '.$e->getMessage());
        }
    }

    private function detectCsvColumns(array $headers): array
    {
        $mapping = [];
        $normalizedHeaders = array_map('strtolower', $headers);

        foreach ($this->csvMapping as $field => $possibleNames) {
            foreach ($possibleNames as $name) {
                $index = array_search($name, $normalizedHeaders);
                if ($index !== false) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        // Required fields: date, description, amount
        $requiredFields = ['date', 'description', 'amount'];
        foreach ($requiredFields as $field) {
            if (! isset($mapping[$field])) {
                throw new Exception("Required column not found: {$field}");
            }
        }

        return $mapping;
    }

    private function createStatementLineFromCsvRow(array $rowData, array $headers, array $columnMapping, BankStatement $statement, int $lineNumber): array
    {
        $date = $this->parseDate($rowData[$columnMapping['date']]);
        $description = trim($rowData[$columnMapping['description']] ?? '');
        $amount = $this->parseAmount($rowData[$columnMapping['amount']]);
        $balance = isset($columnMapping['balance']) ? $this->parseAmount($rowData[$columnMapping['balance']]) : null;
        $reference = isset($columnMapping['reference']) ? trim($rowData[$columnMapping['reference']] ?? '') : null;

        if (empty($description)) {
            throw new Exception('Description cannot be empty');
        }

        if ($amount == 0) {
            throw new Exception('Amount cannot be zero');
        }

        return [
            'statement_id' => $statement->id,
            'company_id' => $statement->company_id,
            'transaction_date' => $date,
            'description' => substr($description, 0, 500),
            'reference_number' => $reference ? substr($reference, 0, 50) : null,
            'amount' => $amount,
            'balance_after' => $balance,
            'external_id' => $reference,
            'line_hash' => BankStatementLine::generateHash([
                'transaction_date' => $date,
                'description' => $description,
                'amount' => $amount,
                'reference_number' => $reference,
            ]),
            'line_number' => $lineNumber,
        ];
    }

    private function createStatementLineFromOfxTransaction($transaction, BankStatement $statement, int $lineNumber): array
    {
        $date = $this->parseOfxDate($transaction->date);
        $description = trim($transaction->memo ?? $transaction->name ?? '');
        $amount = (float) $transaction->amount;
        $reference = trim($transaction->fitid ?? '');

        if (empty($description)) {
            $description = 'Transaction';
        }

        return [
            'statement_id' => $statement->id,
            'company_id' => $statement->company_id,
            'transaction_date' => $date,
            'description' => substr($description, 0, 500),
            'reference_number' => $reference ? substr($reference, 0, 50) : null,
            'amount' => $amount,
            'balance_after' => null, // OFX doesn't always provide running balance
            'external_id' => $reference,
            'line_hash' => BankStatementLine::generateHash([
                'transaction_date' => $date,
                'description' => $description,
                'amount' => $amount,
                'reference_number' => $reference,
            ]),
            'line_number' => $lineNumber,
        ];
    }

    private function parseDate(string $date): string
    {
        // Try multiple date formats commonly found in bank statements
        $formats = [
            'Y-m-d',
            'm/d/Y',
            'd/m/Y',
            'Y/m/d',
            'm-d-Y',
            'd-m-Y',
            'Y-m-d H:i:s',
            'm/d/Y H:i:s',
            'd/m/Y H:i:s',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, trim($date));
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        throw new Exception("Unable to parse date: {$date}");
    }

    private function parseOfxDate(string $date): string
    {
        // OFX dates are in YYYYMMDD format
        if (strlen($date) >= 8) {
            $year = substr($date, 0, 4);
            $month = substr($date, 4, 2);
            $day = substr($date, 6, 2);

            if (checkdate($month, $day, $year)) {
                return "{$year}-{$month}-{$day}";
            }
        }

        throw new Exception("Unable to parse OFX date: {$date}");
    }

    private function parseAmount(string $amount): float
    {
        // Remove currency symbols, commas, and whitespace
        $cleaned = preg_replace('/[^0-9.-]/', '', $amount);

        if ($cleaned === '' || $cleaned === '-' || $cleaned === '.') {
            throw new Exception("Invalid amount format: {$amount}");
        }

        $parsed = (float) $cleaned;

        if (! is_numeric($cleaned) || is_nan($parsed) || is_infinite($parsed)) {
            throw new Exception("Invalid amount: {$amount}");
        }

        return $parsed;
    }

    public function validateStatement(BankStatement $statement, Collection $lines): array
    {
        $errors = [];
        $warnings = [];

        // Check if statement period matches transaction dates
        $transactionDates = $lines->pluck('transaction_date')->sort()->values();

        if (! $transactionDates->isEmpty()) {
            $firstTransactionDate = $transactionDates->first();
            $lastTransactionDate = $transactionDates->last();

            if ($firstTransactionDate < $statement->statement_start_date) {
                $warnings[] = "First transaction date ({$firstTransactionDate}) is before statement period start ({$statement->statement_start_date})";
            }

            if ($lastTransactionDate > $statement->statement_end_date) {
                $warnings[] = "Last transaction date ({$lastTransactionDate}) is after statement period end ({$statement->statement_end_date})";
            }
        }

        // Check for duplicate lines
        $hashes = $lines->pluck('line_hash');
        $duplicateHashes = $hashes->duplicates()->unique();

        if ($duplicateHashes->isNotEmpty()) {
            $warnings[] = "Found {$duplicateHashes->count()} potential duplicate transactions";
        }

        // Calculate expected vs actual balance if possible
        $calculatedBalance = $statement->opening_balance + $lines->sum('amount');
        $balanceDifference = abs($calculatedBalance - $statement->closing_balance);

        if ($balanceDifference > 0.01) { // Allow for rounding differences
            $warnings[] = "Calculated balance ({$calculatedBalance}) differs from statement balance ({$statement->closing_balance}) by {$balanceDifference}";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'total_lines' => $lines->count(),
                'total_amount' => $lines->sum('amount'),
                'calculated_balance' => $calculatedBalance,
                'balance_difference' => $balanceDifference,
                'date_range' => [
                    'first' => $transactionDates->first(),
                    'last' => $transactionDates->last(),
                ],
            ],
        ];
    }
}
