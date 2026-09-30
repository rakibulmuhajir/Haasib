<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * An employee's statement: a running balance of what the company owes the employee, like a
 * bank or customer statement, from what payroll already records.
 *
 *   + salary earned      each approved or paid payslip's gross pay, on its period's end; a month
 *                        not on a payslip yet (payroll runs at month end) counts the monthly salary
 *                        as expected, so advances taken mid-month read against it
 *   - deductions         that payslip's deductions other than advance recovery (tax, fines ...)
 *   - advance taken      each salary advance, on the day it was taken
 *   + advance repaid     cash handed back, or an advance written off (manual repayment / adjustment)
 *   - salary paid        a paid payslip's net pay, on the day it was paid
 *
 * An advance recovered on a payslip is not a separate movement -- the advance already came off
 * when it was taken, and the recovery is only why that payslip's net pay is smaller -- so it is
 * shown on the payslip's line for information and not counted twice.
 */
class EmployeeStatementService
{
    public function statement(Employee $employee, string $from, string $to): array
    {
        $movements = $this->movements($employee);

        $opening = round(array_sum(array_map(fn ($m) => $m['in'] - $m['out'], array_filter($movements, fn ($m) => $m['date'] < $from))), 2);
        $running = $opening;
        $rows = [[
            'date' => $from, 'type' => 'opening_balance', 'reference' => null, 'description' => 'Opening balance',
            'money_in' => 0.0, 'money_out' => 0.0, 'balance' => $opening, 'link' => null,
        ]];
        foreach ($movements as $m) {
            if ($m['date'] < $from || $m['date'] > $to) {
                continue;
            }
            $running = round($running + $m['in'] - $m['out'], 2);
            $rows[] = [
                'date' => $m['date'], 'type' => $m['type'], 'reference' => $m['reference'], 'description' => $m['description'],
                'money_in' => round($m['in'], 2), 'money_out' => round($m['out'], 2), 'balance' => $running, 'link' => $m['link'],
            ];
        }
        $rows[] = [
            'date' => $to, 'type' => 'closing_balance', 'reference' => null, 'description' => 'Closing balance',
            'money_in' => 0.0, 'money_out' => 0.0, 'balance' => $running, 'link' => null,
        ];

        $inRange = array_filter($movements, fn ($m) => $m['date'] >= $from && $m['date'] <= $to);
        $sum = fn (string $type, string $side) => round(array_sum(array_column(array_filter($inRange, fn ($m) => $m['type'] === $type), $side)), 2);

        return [
            'rows' => $rows,
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'from' => $from,
            'to' => $to,
            'party' => trim($employee->first_name.' '.$employee->last_name),
            // Totals for the period, for the summary above the statement.
            'totals' => [
                'salary' => (float) $employee->base_salary,
                'earned' => $sum('salary_earned', 'in'),
                'advances' => $sum('advance', 'out'),
                'advance_count' => count(array_filter($inRange, fn ($m) => $m['type'] === 'advance')),
                'repaid' => $sum('advance_repaid', 'in'),
                'deductions' => $sum('salary_earned', 'out'),
                'paid' => $sum('salary_paid', 'out'),
            ],
        ];
    }

    /** @return array<int,array{date:string,type:string,reference:?string,description:string,in:float,out:float,link:?string}> */
    private function movements(Employee $employee): array
    {
        $companyId = $employee->company_id;
        $movements = [];

        $recoveredOn = DB::table('pay.salary_advance_recoveries')
            ->where('company_id', $companyId)->where('recovery_type', 'payroll_deduction')->whereNotNull('payslip_id')
            ->groupBy('payslip_id')->selectRaw('payslip_id, SUM(amount) as amount')->pluck('amount', 'payslip_id');

        $payslips = DB::table('pay.payslips as p')
            ->leftJoin('pay.payroll_periods as pp', 'pp.id', '=', 'p.payroll_period_id')
            ->where('p.company_id', $companyId)->where('p.employee_id', $employee->id)
            ->whereIn('p.status', ['approved', 'paid'])
            ->get(['p.id', 'p.payslip_number', 'p.status', 'p.gross_pay', 'p.total_deductions', 'p.net_pay', 'p.paid_at', 'pp.period_end', 'pp.period_start']);
        foreach ($payslips as $p) {
            $recovered = round((float) ($recoveredOn[$p->id] ?? 0), 2);
            $otherDeductions = max(0.0, round((float) $p->total_deductions - $recovered, 2));
            $earnedOn = substr((string) ($p->period_end ?? $p->paid_at), 0, 10);
            $period = $p->period_start ? date('M Y', strtotime((string) $p->period_start)) : 'Salary';
            $note = $recovered > 0 ? ' · advances recovered '.number_format($recovered, 0) : '';
            $movements[] = ['date' => $earnedOn, 'type' => 'salary_earned', 'reference' => $p->payslip_number,
                'description' => "Salary {$period}".($otherDeductions > 0 ? ' less deductions' : '').$note,
                'in' => (float) $p->gross_pay, 'out' => $otherDeductions, 'link' => "payslips/{$p->id}"];
            if ($p->status === 'paid' && $p->paid_at) {
                $movements[] = ['date' => substr((string) $p->paid_at, 0, 10), 'type' => 'salary_paid', 'reference' => $p->payslip_number,
                    'description' => 'Salary paid', 'in' => 0.0, 'out' => (float) $p->net_pay, 'link' => "payslips/{$p->id}"];
            }
        }

        $advances = DB::table('pay.salary_advances')
            ->where('company_id', $companyId)->where('employee_id', $employee->id)
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'advance_date', 'amount', 'reason']);
        foreach ($advances as $a) {
            $movements[] = ['date' => substr((string) $a->advance_date, 0, 10), 'type' => 'advance', 'reference' => null,
                'description' => 'Advance'.($a->reason ? ' · '.$a->reason : ''), 'in' => 0.0, 'out' => (float) $a->amount, 'link' => null];
        }

        $repaid = DB::table('pay.salary_advance_recoveries as r')
            ->join('pay.salary_advances as a', 'a.id', '=', 'r.salary_advance_id')
            ->where('r.company_id', $companyId)->where('a.employee_id', $employee->id)
            ->whereIn('r.recovery_type', ['manual_repayment', 'adjustment'])
            ->where('a.status', '!=', 'cancelled')
            ->get(['r.recovery_date', 'r.amount', 'r.recovery_type']);
        foreach ($repaid as $r) {
            $movements[] = ['date' => substr((string) $r->recovery_date, 0, 10), 'type' => 'advance_repaid', 'reference' => null,
                'description' => $r->recovery_type === 'adjustment' ? 'Advance written off' : 'Advance repaid',
                'in' => (float) $r->amount, 'out' => 0.0, 'link' => null];
        }

        // Months with advances (or this month) but no payslip yet: the salary they are taken against.
        $salary = (float) $employee->base_salary;
        if ($salary > 0) {
            $onPayslip = $payslips->map(fn ($p) => substr((string) ($p->period_start ?? $p->period_end), 0, 7))->filter()->all();
            $months = collect($advances)->map(fn ($a) => substr((string) $a->advance_date, 0, 7))->push(now()->format('Y-m'))->unique();
            foreach ($months as $month) {
                if (in_array($month, $onPayslip, true)) {
                    continue;
                }
                $monthEnd = date('Y-m-t', strtotime($month.'-01'));
                $movements[] = ['date' => min($monthEnd, now()->toDateString()), 'type' => 'salary_earned', 'reference' => null,
                    'description' => 'Salary '.date('M Y', strtotime($month.'-01')).' · expected, not on a payslip yet',
                    'in' => $salary, 'out' => 0.0, 'link' => null];
            }
        }

        // Oldest first; within a day earnings before money out, as on the other statements.
        $order = ['salary_earned' => 0, 'advance_repaid' => 1, 'advance' => 2, 'salary_paid' => 3];
        usort($movements, fn ($x, $y) => [$x['date'], $order[$x['type']]] <=> [$y['date'], $order[$y['type']]]);

        return $movements;
    }
}
