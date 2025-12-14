<?php

namespace App\Support;

use App\Facades\CompanyContext;
use NumberFormatter;

class PaletteFormatter
{
    /**
     * Format data as a table for palette output.
     *
     * @param array $headers Column headers
     * @param array $rows Row data (display values)
     * @param string|null $footer Optional footer text
     * @param array|null $rowIds Optional array of row identifiers (UUIDs) for quick actions
     */
    public static function table(array $headers, array $rows, ?string $footer = null, ?array $rowIds = null): array
    {
        return [
            'type' => 'table',
            'headers' => $headers,
            'rows' => $rows,
            'footer' => $footer,
            'rowIds' => $rowIds,
        ];
    }

    public static function money(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? CompanyContext::getCompany()?->base_currency ?? 'USD';
        $locale = CompanyContext::getCompany()?->locale ?? 'en_US';

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    public static function status(string $status): string
    {
        return match ($status) {
            'paid' => '{success}● Paid{/}',
            'pending' => '{warning}◐ Pending{/}',
            'sent' => '{accent}◑ Sent{/}',
            'overdue' => '{error}⚠ Overdue{/}',
            'draft' => '{secondary}○ Draft{/}',
            'void' => '{secondary}⊘ Void{/}',
            default => $status,
        };
    }

    public static function relativeDate(\DateTimeInterface $date): string
    {
        $diff = now()->diffInDays($date, false);

        return match (true) {
            $diff === 0 => 'Today',
            $diff === 1 => 'Tomorrow',
            $diff === -1 => 'Yesterday',
            $diff > 0 && $diff <= 7 => "In {$diff} days",
            $diff < 0 && $diff >= -7 => abs($diff) . ' days ago',
            default => $date->format('M j'),
        };
    }

    public static function success(string $message): string
    {
        return "{success}✓{/} {$message}";
    }

    public static function error(string $message): string
    {
        return "{error}✗{/} {$message}";
    }

    public static function warning(string $message): string
    {
        return "{warning}⚠{/} {$message}";
    }
}
