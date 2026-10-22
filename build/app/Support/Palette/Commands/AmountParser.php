<?php

namespace App\Support\Palette\Commands;

/**
 * Reads a typed amount: 50000, 50,000, 1,50,000, 50k, 1.5 lakh, 2 lac.
 * Never rounds a figure into a different one (12.345 is refused) and refuses
 * words it does not understand ("50 thousand") rather than reading just the digits.
 */
final class AmountParser
{
    private const MAX = 999_999_999.99;

    private const SUFFIX = ['k' => 1_000, 'lakh' => 100_000, 'lakhs' => 100_000, 'lac' => 100_000, 'lacs' => 100_000];

    public static function parse(string $text): ?float
    {
        $tokens = preg_split('/\s+/', trim($text));
        $first = $tokens[0];
        if ($first === '' || count($tokens) > 2) {
            return null;
        }

        $multiplier = 1;
        if (preg_match('/^(.*?)(k|lakhs?|lacs?)$/iD', $first, $m) && $m[1] !== '') {
            if (count($tokens) > 1) {
                return null;
            }
            $base = self::number($m[1]);
            $multiplier = self::SUFFIX[strtolower($m[2])];
        } else {
            $base = self::number($first);
            if (count($tokens) > 1) {
                $suffix = self::SUFFIX[strtolower($tokens[1])] ?? null;
                if ($suffix === null) {
                    return null;
                }
                $multiplier = $suffix;
            }
        }
        if ($base === null) {
            return null;
        }

        $cents = round($base * $multiplier * 100);
        if (abs($base * $multiplier * 100 - $cents) > 1e-6) {
            return null; // 1.234 lakh is fine, 12.345 is not
        }
        $amount = $cents / 100;

        return $amount > 0 && $amount <= self::MAX ? $amount : null;
    }

    private static function number(string $token): ?float
    {
        // 50,000 / 1,234,567 (thousands) and 1,50,000 (lakhs): the grouping must be consistent.
        $valid = preg_match('/^\d+(?:\.\d+)?$/D', $token)
            || preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/D', $token)
            || preg_match('/^\d{1,2}(?:,\d{2})*,\d{3}(?:\.\d+)?$/D', $token);

        return $valid ? (float) str_replace(',', '', $token) : null;
    }
}
