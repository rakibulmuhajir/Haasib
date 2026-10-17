<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * The withholding tax register: what we deducted from vendors ("made") and what customers
 * deducted from us ("received"), for the monthly statement and the annual claim.
 *
 * Figures are in base currency, as stored on the payment. A voided payment stays on the
 * register as its original line plus a reversal line (negative, dated the day the journal was
 * reversed), so the totals net to zero and the trail is not a deleted row.
 */
class WithholdingRegisterService
{
    public const SIDES = ['all', 'made', 'received'];

    /**
     * @return array{rows: list<array<string, mixed>>, sections: list<array<string, mixed>>, totals: array{gross: float, wht: float}}
     */
    public function run(string $companyId, string $slug, string $from, string $to, string $side = 'all'): array
    {
        $rows = collect();
        if (in_array($side, ['all', 'made'], true)) {
            $rows = $rows->merge($this->made($companyId, $slug, $from, $to));
        }
        if (in_array($side, ['all', 'received'], true)) {
            $rows = $rows->merge($this->received($companyId, $slug, $from, $to));
        }

        $rows = $rows->merge(app(\App\Modules\Accounting\Extensions\WithholdingRegisterSources::class)->lines($companyId, $slug, $from, $to, $side));

        $rows = $rows->sortBy([['date', 'asc'], ['payment_number', 'asc'], ['kind', 'asc']])->values();

        $sections = $rows
            ->groupBy(fn ($r) => $r['side'].'|'.($r['section'] ?? ''))
            ->map(fn (Collection $group) => [
                'side' => $group->first()['side'],
                'section' => $group->first()['section'],
                'count' => $group->where('kind', 'deduction')->count(),
                'gross' => round((float) $group->sum('gross'), 2),
                'wht' => round((float) $group->sum('wht'), 2),
            ])
            ->sortBy([['side', 'asc'], ['section', 'asc']])
            ->values();

        return [
            'rows' => $rows->all(),
            'sections' => $sections->all(),
            'totals' => [
                'gross' => round((float) $rows->sum('gross'), 2),
                'wht' => round((float) $rows->sum('wht'), 2),
            ],
        ];
    }

    private function made(string $companyId, string $slug, string $from, string $to): Collection
    {
        $payments = BillPayment::withTrashed()->with('vendor:id,name,tax_id,filer_status')
            ->where('company_id', $companyId)->where('wht_amount', '>', 0)
            ->where(fn ($q) => $q->whereBetween('payment_date', [$from, $to])->orWhereNotNull('deleted_at'))
            ->get();

        return $this->lines($payments, 'made', 'vendor', $from, $to, "/{$slug}/bill-payments");
    }

    private function received(string $companyId, string $slug, string $from, string $to): Collection
    {
        $payments = Payment::withTrashed()->with('customer:id,name,tax_id,filer_status')
            ->where('company_id', $companyId)->where('wht_amount', '>', 0)
            ->where(fn ($q) => $q->whereBetween('payment_date', [$from, $to])->orWhereNotNull('deleted_at'))
            ->get();

        return $this->lines($payments, 'received', 'customer', $from, $to, "/{$slug}/payments");
    }

    /** One line per payment in the period, plus a reversal line for each voided one reversed in it. */
    private function lines(Collection $payments, string $side, string $party, string $from, string $to, string $base): Collection
    {
        $reversedOn = Transaction::whereIn('reversal_of_id', $payments->pluck('transaction_id')->filter()->all())
            ->pluck('transaction_date', 'reversal_of_id');

        $lines = collect();
        foreach ($payments as $payment) {
            $date = $payment->payment_date->toDateString();
            $gross = round((float) $payment->base_amount, 2);
            $wht = round((float) $payment->wht_amount, 2);
            $rate = $payment->wht_rate !== null
                ? (float) $payment->wht_rate
                : ($gross > 0 ? round($wht / $gross * 100, 4) : null);
            $partyModel = $payment->{$party};

            $line = [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_group_number ?? $payment->payment_number,
                'reference' => $payment->reference_number,
                'url' => "{$base}/{$payment->id}",
                'side' => $side,
                'party_id' => $partyModel?->id,
                'party' => $partyModel?->name,
                'tax_id' => $partyModel?->tax_id,
                'filer_status' => $payment->wht_filer_status ?? $partyModel?->filer_status,
                'section' => $payment->wht_section,
                'rate' => $rate,
            ];

            if ($date >= $from && $date <= $to) {
                $lines->push($line + ['date' => $date, 'kind' => 'deduction', 'gross' => $gross, 'wht' => $wht]);
            }
            if ($payment->trashed()) {
                $reversed = $reversedOn[$payment->transaction_id] ?? $payment->deleted_at;
                $reversalDate = \Illuminate\Support\Carbon::parse($reversed)->toDateString();
                if ($reversalDate >= $from && $reversalDate <= $to) {
                    $lines->push($line + ['date' => $reversalDate, 'kind' => 'reversal', 'gross' => -$gross, 'wht' => -$wht]);
                }
            }
        }

        return $lines;
    }

    /** CSV text for the same rows. */
    public function csv(array $report): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Date', 'Deducted', 'Type', 'Party', 'NTN / CNIC', 'Filer status', 'Section', 'Gross', 'Rate %', 'WHT', 'Payment', 'Reference'], ',', '"', '\\');
        foreach ($report['rows'] as $r) {
            fputcsv($out, [
                $r['date'],
                $r['side'] === 'made' ? 'By us' : 'From us',
                $r['kind'] === 'reversal' ? 'Reversal' : 'Deduction',
                $r['party'], $r['tax_id'], $r['filer_status'], $r['section'],
                number_format($r['gross'], 2, '.', ''),
                $r['rate'] !== null ? rtrim(rtrim(number_format($r['rate'], 4, '.', ''), '0'), '.') : '',
                number_format($r['wht'], 2, '.', ''),
                $r['payment_number'], $r['reference'],
            ], ',', '"', '\\');
        }
        fputcsv($out, ['Total', '', '', '', '', '', '', number_format($report['totals']['gross'], 2, '.', ''), '', number_format($report['totals']['wht'], 2, '.', ''), '', ''], ',', '"', '\\');
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
