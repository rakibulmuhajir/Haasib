<?php

namespace App\Support;

use App\Models\Company;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * "Today" as a calendar date in the company's own timezone.
 *
 * The app clock stays UTC (timestamps, queues, commands). This gives the *date* a
 * business sees on its wall calendar: at 02:00 in Karachi the UTC date is still
 * yesterday. Use it wherever the server decides a date for business data (document
 * date defaults, validation bounds, report ranges, "this month"). Do not use it for
 * real instants such as created_at, locks or token expiry.
 *
 * A null or invalid company timezone, or no company context at all, falls back to
 * config('app.timezone').
 */
final class CompanyClock
{
    /** The company's timezone, else the app timezone. Defaults to the current company. */
    public function timezone(?Company $company = null): string
    {
        $company ??= $this->current();
        $tz = $company?->timezone;

        if (is_string($tz) && $tz !== '' && $this->valid($tz)) {
            return $tz;
        }

        return (string) config('app.timezone', 'UTC');
    }

    /** Now, in the company's timezone. */
    public function now(?Company $company = null): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone($company));
    }

    /** 'Y-m-d' for today in the company's timezone. */
    public function today(?Company $company = null): string
    {
        return $this->now($company)->toDateString();
    }

    private function current(): ?Company
    {
        return app(CurrentCompany::class)->get()
            ?? app(CompanyContextService::class)->getCompany();
    }

    private function valid(string $tz): bool
    {
        try {
            new DateTimeZone($tz);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
