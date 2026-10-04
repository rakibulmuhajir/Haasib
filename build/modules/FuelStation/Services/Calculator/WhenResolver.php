<?php

namespace App\Modules\FuelStation\Services\Calculator;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The "when" of a value: explicit dates, or a preset worked out on every run (so "this month"
 * is always the current month). A range metric takes {from,to}, {on} or a range preset; a
 * single-day metric takes {on} or a day preset.
 */
final class WhenResolver
{
    public const RANGE_PRESETS = ['today', 'yesterday', 'this_month', 'last_month', 'this_year', 'last_n_days'];

    public const DAY_PRESETS = ['today', 'yesterday', 'month_end_last'];

    public static function validate(array $when, bool $takesDay): ?string
    {
        $preset = $when['preset'] ?? null;
        if ($preset !== null) {
            if (! is_string($preset) || ! in_array($preset, $takesDay ? self::DAY_PRESETS : self::RANGE_PRESETS, true)) {
                return 'Unknown period.';
            }
            if ($preset === 'last_n_days') {
                $n = $when['n'] ?? null;
                if ((! is_int($n) && ! (is_string($n) && ctype_digit($n))) || (int) $n < 1 || (int) $n > 366) {
                    return 'Days must be 1 to 366.';
                }
            }

            return null;
        }
        if (isset($when['on'])) {
            return self::date($when['on']) ? null : 'Pick a valid date.';
        }
        if (! $takesDay && isset($when['from'], $when['to'])) {
            $from = self::date($when['from']);
            $to = self::date($when['to']);

            return $from && $to && $from->lte($to) ? null : 'Pick a valid date range.';
        }

        return 'Pick a period.';
    }

    /** @return array{0:string,1:string,2:string} from, to, label */
    public static function range(array $when, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        switch ($when['preset'] ?? null) {
            case 'today':
                return [$today->toDateString(), $today->toDateString(), 'today'];
            case 'yesterday':
                $d = $today->copy()->subDay()->toDateString();

                return [$d, $d, 'yesterday'];
            case 'this_month':
                return [$today->copy()->startOfMonth()->toDateString(), $today->toDateString(), 'this month'];
            case 'last_month':
                $last = $today->copy()->subMonthNoOverflow();

                return [$last->copy()->startOfMonth()->toDateString(), $last->copy()->endOfMonth()->toDateString(), 'last month'];
            case 'this_year':
                return [$today->copy()->startOfYear()->toDateString(), $today->toDateString(), 'this year'];
            case 'last_n_days':
                $n = max(1, min(366, (int) ($when['n'] ?? 1)));

                return [$today->copy()->subDays($n - 1)->toDateString(), $today->toDateString(), "last {$n} days"];
        }

        if (isset($when['on'])) {
            $d = self::date($when['on'])?->toDateString() ?? $today->toDateString();

            return [$d, $d, self::shown($d)];
        }

        $from = self::date($when['from'] ?? null)?->toDateString() ?? $today->copy()->startOfMonth()->toDateString();
        $to = self::date($when['to'] ?? null)?->toDateString() ?? $today->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, self::shown($from).' to '.self::shown($to)];
    }

    /** @return array{0:string,1:string} the day, label */
    public static function day(array $when, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        switch ($when['preset'] ?? null) {
            case 'month_end_last':
                return [$today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(), 'last month end'];
            case 'yesterday':
                return [$today->copy()->subDay()->toDateString(), 'yesterday'];
            case 'today':
                return [$today->toDateString(), 'today'];
        }
        $d = self::date($when['on'] ?? null)?->toDateString() ?? $today->toDateString();

        return [$d, 'on '.self::shown($d)];
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            $d = Carbon::createFromFormat('Y-m-d', $value);

            return $d && $d->toDateString() === $value ? $d->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function shown(string $date): string
    {
        return Carbon::parse($date)->format('j M Y');
    }
}
