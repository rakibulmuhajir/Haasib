<?php

namespace App\Support;

use App\Services\CurrentCompany;
use NumberFormatter;

class PaletteFormatter
{
    public static function table(array $headers, array $rows, ?string $footer = null): array
    {
        return [
            'type' => 'table',
            'headers' => $headers,
            'rows' => $rows,
            'footer' => $footer,
        ];
    }

    public static function money(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? app(CurrentCompany::class)->get()?->base_currency ?? 'USD';
        $locale = app(CurrentCompany::class)->get()?->locale ?? 'en_US';

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
