<?php

namespace App\Support\Palette\Commands;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Reads a typed date against the company's today: yesterday, tomorrow, "14 oct",
 * "14/10", "14-10-2026", "2026-10-14". Never uses the server clock.
 */
final class DateParser
{
    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3, 'apr' => 4, 'april' => 4,
        'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7, 'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12,
    ];

    private const RELATIVE = ['yesterday' => -1, 'today' => 0, 'tomorrow' => 1];

    /**
     * The day a typed date means. A leading "on" is allowed. With no year, the company's
     * year is used, unless that lands more than six months ahead (`28 dec` typed in
     * January means last December).
     *
     * @return array{ok: true, date: string}|array{ok: false, message: string}
     */
    public static function resolve(string $text, string $today): array
    {
        $phrase = preg_replace('/\s+/', ' ', trim($text));
        $phrase = preg_replace('/^on /i', '', $phrase);

        $offset = self::RELATIVE[strtolower($phrase)] ?? null;
        if ($offset !== null) {
            return ['ok' => true, 'date' => self::addDays($today, $offset)];
        }

        $parts = self::explicit($phrase);
        if ($parts === null) {
            return ['ok' => false, 'message' => "{$phrase} is not a date"];
        }
        [$day, $month, $year] = $parts;

        $useYear = $year ?? (int) substr($today, 0, 4);
        if (! checkdate($month, $day, $useYear)) {
            return ['ok' => false, 'message' => "{$phrase} is not a date"];
        }
        $date = self::iso($useYear, $month, $day);
        if ($year === null && $date > self::addDays($today, 183) && checkdate($month, $day, $useYear - 1)) {
            $date = self::iso($useYear - 1, $month, $day);
        }

        return ['ok' => true, 'date' => $date];
    }

    /**
     * Takes a trailing date phrase off a typed line ("yesterday", "on 14 oct"), so the
     * rest can be read as slots. A word that is not a date ("on 3 tea") stays in the line.
     *
     * @return array{line: string, date: ?string} date is the phrase, ready for resolve()
     */
    public static function extract(string $line): array
    {
        if (preg_match('/\s+(today|yesterday|tomorrow)$/iD', $line, $m, PREG_OFFSET_CAPTURE)) {
            return ['line' => substr($line, 0, $m[0][1]), 'date' => strtolower($m[1][0])];
        }

        $on = '/\s+on\s+(\d{1,2}\s+[a-z]{3,9}(?:\s+\d{2,4})?|\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{2,4})?|\d{4}-\d{1,2}-\d{1,2})$/iD';
        if (preg_match($on, $line, $m, PREG_OFFSET_CAPTURE) && self::explicit($m[1][0]) !== null) {
            return ['line' => substr($line, 0, $m[0][1]), 'date' => $m[1][0]];
        }

        return ['line' => $line, 'date' => null];
    }

    /** @return array{0: int, 1: int, 2: ?int}|null day, month, year (null when not typed) */
    private static function explicit(string $phrase): ?array
    {
        $text = preg_replace('/\s+/', ' ', trim($phrase));

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/D', $text, $m)) {
            return [(int) $m[3], (int) $m[2], (int) $m[1]];
        }
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{2,4}))?$/D', $text, $m)) {
            $month = (int) $m[2];
        } elseif (preg_match('/^(\d{1,2}) ([a-z]+)(?: (\d{2,4}))?$/iD', $text, $m)) {
            $month = self::MONTHS[strtolower($m[2])] ?? null; // "on 3 tea" is not a date
        } else {
            return null;
        }
        $year = self::year($m[3] ?? null);
        if ($month === null || $year === false) {
            return null;
        }

        return [(int) $m[1], $month, $year];
    }

    /** @return int|null|false null when no year was typed, false when it is not a year (3 digits) */
    private static function year(?string $token): int|null|false
    {
        return match (strlen($token ?? '')) {
            0 => null,
            2 => 2000 + (int) $token,
            4 => (int) $token,
            default => false,
        };
    }

    private static function iso(int $y, int $m, int $d): string
    {
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    private static function addDays(string $day, int $n): string
    {
        return (new DateTimeImmutable($day, new DateTimeZone('UTC')))->modify(sprintf('%+d days', $n))->format('Y-m-d');
    }
}
