<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Validation\ValidationException;

/**
 * Reads a bank statement exported as CSV. Banks lay these out differently, so the columns are
 * found by their headings (in the first rows -- many exports put the account details above the
 * table): a date, a description, an optional reference, and either one signed amount column or
 * separate withdrawal/deposit columns, plus an optional running balance.
 *
 * Amounts come out signed from the station's side: money into the bank +, money out -.
 */
class BankStatementCsvParser
{
    private const HEADINGS = [
        'date' => ['transaction date', 'txn date', 'tran date', 'posting date', 'booking date', 'date', 'value date'],
        'description' => ['description', 'narration', 'particulars', 'details', 'transaction details', 'remarks', 'memo'],
        'reference' => ['cheque', 'chq', 'instrument', 'reference', 'ref'],
        'out' => ['withdrawal', 'withdrawals', 'debit', 'debits', 'dr', 'paid out', 'money out'],
        'in' => ['deposit', 'deposits', 'credit', 'credits', 'cr', 'paid in', 'money in'],
        'amount' => ['amount', 'transaction amount'],
        'balance' => ['balance', 'running balance', 'closing balance', 'available balance'],
    ];

    /** @return array<int,array{line_date:string,description:?string,reference:?string,amount:float,balance:?float}> */
    public function parse(string $path): array
    {
        $rows = $this->rows($path);
        [$headerIndex, $columns] = $this->findHeader($rows);

        $lines = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $date = $this->date($row[$columns['date']] ?? '');
            if (! $date) {
                continue; // totals, blank or note rows
            }
            if (isset($columns['amount'])) {
                $amount = $this->number($row[$columns['amount']] ?? '');
            } else {
                $in = $this->number($row[$columns['in']] ?? '') ?? 0.0;
                $out = $this->number($row[$columns['out']] ?? '') ?? 0.0;
                $amount = abs($in) - abs($out);
            }
            if ($amount === null || abs($amount) < 0.005) {
                continue;
            }
            $lines[] = [
                'line_date' => $date,
                'description' => isset($columns['description']) ? mb_substr(trim((string) ($row[$columns['description']] ?? '')), 0, 500) ?: null : null,
                'reference' => isset($columns['reference']) ? mb_substr(trim((string) ($row[$columns['reference']] ?? '')), 0, 100) ?: null : null,
                'amount' => round($amount, 2),
                'balance' => isset($columns['balance']) ? $this->number($row[$columns['balance']] ?? '') : null,
            ];
        }

        if (! $lines) {
            throw ValidationException::withMessages(['statement' => 'No transactions found in this file.']);
        }

        return $lines;
    }

    /** @return array<int,array<int,string>> */
    private function rows(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // UTF-8 BOM
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{0:int,1:array<string,int>} */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 40, true) as $index => $row) {
            $columns = [];
            foreach ($row as $col => $cell) {
                $cell = strtolower(trim(preg_replace('/[^a-z ]+/i', ' ', $cell)));
                $cell = preg_replace('/\s+/', ' ', $cell);
                if ($cell === '') {
                    continue;
                }
                foreach (self::HEADINGS as $key => $names) {
                    // "Cheque No", "Debit Amount", "Ref No." count as their first word.
                    $hit = collect($names)->contains(fn ($n) => $cell === $n || str_starts_with($cell, $n.' '));
                    if (! isset($columns[$key]) && $hit) {
                        $columns[$key] = $col;
                        break;
                    }
                }
            }
            $hasMoney = isset($columns['amount']) || (isset($columns['in']) && isset($columns['out']));
            if (isset($columns['date']) && $hasMoney) {
                return [$index, $columns];
            }
        }

        $seen = implode(', ', array_filter($rows[0] ?? []));
        throw ValidationException::withMessages(['statement' => 'Could not find the date and amount columns. The file needs a Date column and either Amount or Debit and Credit columns'.($seen ? " (first row: {$seen})" : '').'.']);
    }

    private function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }
        $value = preg_replace('/\s+/', ' ', $value);
        // Pakistani banks write the day first. A format only counts when it reads the whole value.
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd-M-Y', 'd-M-y', 'd M Y', 'd M y', 'd-F-Y', 'd F Y', 'Y-m-d', 'Y/m/d', 'M d, Y', 'd/m/Y H:i', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            $parsed = \DateTime::createFromFormat('!'.$format, $value);
            $errors = \DateTime::getLastErrors();
            if ($parsed !== false && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function number(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }
        $negative = str_starts_with($value, '(') || str_starts_with($value, '-') || preg_match('/\bdr\.?$/i', $value);
        $digits = preg_replace('/[^0-9.]/', '', $value);
        if ($digits === '' || ! is_numeric($digits)) {
            return null;
        }

        return ($negative ? -1 : 1) * (float) $digits;
    }
}
