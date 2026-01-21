<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FiscalYearService
{
    public function createFiscalYear(string $companyId, array $data): FiscalYear
    {
        // Unset previous current fiscal year for the company
        FiscalYear::where('company_id', $companyId)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $fiscalYear = FiscalYear::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_current' => true,
            'is_closed' => false,
            'status' => 'open',
            'created_by_user_id' => auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ]);

        // Auto-create periods if requested
        if ($data['auto_create_periods'] ?? false) {
            $this->createPeriods($fiscalYear, $data['period_type']);
        }

        return $fiscalYear->load('periods');
    }

    public function createPeriods(FiscalYear $fiscalYear, string $periodType): Collection
    {
        $periods = collect();
        $startDate = Carbon::parse($fiscalYear->start_date);
        $endDate = Carbon::parse($fiscalYear->end_date);
        $currentDate = $startDate->copy();

        $periodNumber = 1;

        while ($currentDate <= $endDate) {
            $periodEndDate = $this->getPeriodEndDate($currentDate, $periodType, $endDate);

            // Skip if period would extend beyond fiscal year
            if ($periodEndDate > $endDate) {
                $periodEndDate = $endDate;
            }

            $periods->push(AccountingPeriod::create([
                'company_id' => $fiscalYear->company_id,
                'fiscal_year_id' => $fiscalYear->id,
                'name' => $this->getPeriodName($currentDate, $periodType, $periodNumber),
                'period_number' => $periodNumber,
                'start_date' => $currentDate->toDateString(),
                'end_date' => $periodEndDate->toDateString(),
                'period_type' => $periodType,
                'is_closed' => false,
                'is_adjustment' => false,
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]));

            $currentDate = $periodEndDate->copy()->addDay();
            $periodNumber++;

            // Stop if we've reached the end of the fiscal year
            if ($currentDate > $endDate) {
                break;
            }
        }

        return $periods;
    }

    private function getPeriodEndDate(Carbon $currentDate, string $periodType, Carbon $fiscalYearEnd): Carbon
    {
        return match ($periodType) {
            'monthly' => $currentDate->copy()->endOfMonth(),
            'quarterly' => $currentDate->copy()->endOfQuarter(),
            'yearly' => $fiscalYearEnd->copy(),
            default => $currentDate->copy()->endOfMonth(),
        };
    }

    private function getPeriodName(Carbon $currentDate, string $periodType, int $periodNumber): string
    {
        return match ($periodType) {
            'monthly' => $currentDate->format('F Y'),
            'quarterly' => 'Q' . $currentDate->quarter . ' ' . $currentDate->format('Y'),
            'yearly' => $currentDate->format('Y'),
            default => $currentDate->format('F Y'),
        };
    }

    public function getCurrentFiscalYear(string $companyId): ?FiscalYear
    {
        return FiscalYear::where('company_id', $companyId)
            ->where('is_current', true)
            ->where('is_closed', false)
            ->first();
    }

    public function getOpenPeriodForDate(string $companyId, string $date): ?AccountingPeriod
    {
        $dateObj = Carbon::parse($date);

        return AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
            ->where('acct.accounting_periods.company_id', $companyId)
            ->where('acct.accounting_periods.start_date', '<=', $dateObj)
            ->where('acct.accounting_periods.end_date', '>=', $dateObj)
            ->where('acct.accounting_periods.is_closed', false)
            ->where('acct.fiscal_years.is_closed', false)
            ->select('acct.accounting_periods.*')
            ->first();
    }

    public function ensureCurrentFiscalYearExists(string $companyId, Carbon|string|null $date = null): FiscalYear
    {
        $dateObj = $date instanceof Carbon ? $date : ($date ? Carbon::parse($date) : now());

        $currentYear = FiscalYear::where('company_id', $companyId)
            ->where('start_date', '<=', $dateObj->toDateString())
            ->where('end_date', '>=', $dateObj->toDateString())
            ->first();

        if ($currentYear) {
            if (! $currentYear->is_current && ! $currentYear->is_closed) {
                FiscalYear::where('company_id', $companyId)->where('is_current', true)->update(['is_current' => false]);
                $currentYear->is_current = true;
                $currentYear->save();
            }

            return $currentYear;
        }

        $company = Company::find($companyId);
        $startMonth = $company?->getFiscalYearStartMonth() ?? 1;
        $startYear = $dateObj->month >= $startMonth ? $dateObj->year : $dateObj->year - 1;

        $startDate = Carbon::create($startYear, $startMonth, 1);
        $endDate = $startDate->copy()->addYear()->subDay();

        $existing = FiscalYear::where('company_id', $companyId)
            ->where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->first();

        if ($existing) {
            if (! $existing->is_current && ! $existing->is_closed) {
                FiscalYear::where('company_id', $companyId)->where('is_current', true)->update(['is_current' => false]);
                $existing->is_current = true;
                $existing->save();
            }

            return $existing;
        }

        $periodType = $company?->period_frequency ?? $company?->getDefaultPeriodType() ?? 'monthly';
        $name = $startDate->format('Y') === $endDate->format('Y')
            ? "FY {$startDate->format('Y')}"
            : "FY {$startDate->format('Y')}-{$endDate->format('Y')}";

        if (FiscalYear::where('company_id', $companyId)->where('name', $name)->exists()) {
            $name = $startDate->format('Y') === $endDate->format('Y')
                ? "FY {$startDate->format('Y')} ({$startDate->format('M')}-{$endDate->format('M')})"
                : "FY {$startDate->format('Y')}-{$endDate->format('Y')}";

            $suffix = 2;
            $candidate = $name;
            while (FiscalYear::where('company_id', $companyId)->where('name', $candidate)->exists()) {
                $candidate = "{$name} {$suffix}";
                $suffix++;
            }
            $name = $candidate;
        }

        $currentYear = $this->createFiscalYear($companyId, [
            'name' => $name,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'period_type' => $periodType,
            'auto_create_periods' => true,
        ]);

        return $currentYear;
    }
}
