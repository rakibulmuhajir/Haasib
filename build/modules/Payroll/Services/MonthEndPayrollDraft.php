<?php

namespace App\Modules\Payroll\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A month's payroll, drafted -- never posted -- once its last day is closed.
 *
 * Advances paid out of the daily close are money staff owe back (Employee Advances), not salary:
 * the month's salary only becomes a cost when its payroll is approved, which is also where each
 * person's advances come off their pay. Nobody remembered to run it, so September's P&L carried
 * no salary at all. Drafting it when the month's last close posts, and saying so where the closes
 * are, leaves one step: review and Approve on the Payroll page. A draft can be wrong (leave, a
 * bonus, someone who left), so nothing is approved for anyone.
 */
class MonthEndPayrollDraft
{
    public function __construct(private readonly PayrollPostingService $posting) {}

    /**
     * Draft $month's payroll (any date in it), or refresh the drafts it already has so their
     * advance deductions are current. Returns how many payslips were newly drafted. A closed
     * period, or a company without payroll, is left alone.
     */
    public function prepare(Company $company, string $month): int
    {
        if (! $company->isModuleEnabled('payroll')) {
            return 0;
        }

        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        $period = PayrollPeriod::firstOrCreate(
            ['company_id' => $company->id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString()],
            ['payment_date' => $end->toDateString(), 'status' => 'open'],
        );
        if (! in_array($period->status, ['open', 'processing'], true)) {
            return 0;
        }

        $created = $this->posting->generatePayslipsForPeriod($period, $company->base_currency ?? 'PKR');

        // Daily and hourly pay is worked out from the month's advances / logged hours, which keep
        // changing while the draft waits: re-work each draft's earning line. The payslip_lines
        // trigger recomputes the payslip's earnings, gross, net and base_* totals.
        Payslip::with(['employee', 'lines'])
            ->where('company_id', $company->id)
            ->where('payroll_period_id', $period->id)
            ->where('status', 'draft')
            ->get()
            ->each(function (Payslip $payslip) use ($period) {
                if (! in_array($payslip->employee?->pay_frequency, ['daily', 'hourly'], true)) {
                    return;
                }
                $line = $payslip->lines->firstWhere('line_type', 'earning');
                if (! $line) {
                    return;
                }
                $earning = $this->posting->monthEarning($payslip->employee, $period);
                if ($earning['amount'] <= 0) {
                    return;
                }
                $line->update([
                    'description' => $earning['description'],
                    'quantity' => $earning['quantity'],
                    'rate' => $earning['rate'],
                    'amount' => $earning['amount'],
                ]);
            });

        // Drafts made before the month's advances were paid show no deductions: bring them up to date.
        Payslip::where('company_id', $company->id)
            ->where('payroll_period_id', $period->id)
            ->where('status', 'draft')
            ->get()
            ->each(fn (Payslip $payslip) => $this->posting->prepareAutomaticAdvanceDeductions($payslip));

        return $created;
    }

    /** After a close posts: only the last day of a month drafts that month. */
    public function prepareAfterClose(Company $company, string $date): int
    {
        $day = Carbon::parse($date);

        return $day->isSameDay($day->copy()->endOfMonth()) ? $this->prepare($company, $date) : 0;
    }

    /**
     * The month waiting on payroll: the latest month whose last day is closed and whose payroll is
     * not yet approved. Null when there is none, or the company has no payroll.
     *
     * @return array{month: string, label: string, drafts: int}|null
     */
    public function reminder(Company $company): ?array
    {
        if (! $company->isModuleEnabled('payroll')) {
            return null;
        }

        $lastMonthEnd = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->whereRaw("transaction_date = (date_trunc('month', transaction_date) + interval '1 month - 1 day')::date")
            ->max('transaction_date');
        if (! $lastMonthEnd) {
            return null;
        }

        $start = Carbon::parse($lastMonthEnd)->startOfMonth();
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
        $period = PayrollPeriod::where('company_id', $company->id)
            ->whereDate('period_start', $start->toDateString())
            ->first();
        $payslips = $period
            ? Payslip::where('company_id', $company->id)->where('payroll_period_id', $period->id)
                ->whereNotIn('status', ['voided', 'void', 'cancelled'])->pluck('status')
            : collect();

        if ($payslips->isNotEmpty() && $payslips->every(fn ($s) => in_array($s, ['approved', 'paid'], true))) {
            return null;
        }

        return [
            'month' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'drafts' => $payslips->filter(fn ($s) => $s === 'draft')->count(),
        ];
    }
}
