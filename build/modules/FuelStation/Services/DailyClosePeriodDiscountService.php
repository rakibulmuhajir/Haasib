<?php

namespace App\Modules\FuelStation\Services;

use Illuminate\Support\Facades\DB;

/** Read existing credits for the day without creating cash movement or loading invoice snapshots. */
class DailyClosePeriodDiscountService
{
    public function forDate(string $companyId, string $date): array
    {
        return DB::table('acct.credit_notes as credit')
            ->join('acct.transactions as journal', function ($join) {
                $join->on('journal.id', '=', 'credit.transaction_id')->on('journal.company_id', '=', 'credit.company_id');
            })
            ->leftJoin('acct.customers as customer', function ($join) {
                $join->on('customer.id', '=', 'credit.customer_id')->on('customer.company_id', '=', 'credit.company_id');
            })
            ->where('credit.company_id', $companyId)->whereDate('credit.credit_date', $date)
            ->whereNotNull('credit.period_discount')->whereIn('credit.status', ['issued', 'applied'])
            ->whereNull('credit.deleted_at')->whereNull('journal.deleted_at')->whereNull('journal.reversed_by_id')
            ->whereIn('journal.status', ['posted', 'locked'])->orderBy('credit.credit_note_number')
            ->get(['credit.id', 'credit.credit_note_number', 'credit.amount', 'credit.currency', 'customer.name as customer_name',
                DB::raw("credit.period_discount->>'from' as period_from"), DB::raw("credit.period_discount->>'to' as period_to")])
            ->map(fn ($credit) => ['id' => $credit->id, 'number' => $credit->credit_note_number,
                'customer_name' => $credit->customer_name ?? 'Customer', 'from' => $credit->period_from, 'to' => $credit->period_to,
                'amount' => (float) $credit->amount, 'currency' => $credit->currency])->all();
    }
}
