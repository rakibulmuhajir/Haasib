<?php

namespace App\Modules\Payroll\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;

/** Evidence from saved payslips, never today's employee salary or deduction settings. */
class PayrollValueTrail
{
    public function outstanding($payslip): float
    {
        return in_array($payslip->status, ['approved', 'draft'], true) ? (float) $payslip->net_pay : 0.0;
    }

    public function available(?User $user): bool
    {
        return $user && $user->showsValueTrails()
            && ($user->isGodMode() || $user->hasCompanyPermission(Permissions::PAYSLIP_VIEW));
    }

    public function build(Company $company, Collection $payslips, User $user, string $label): ?array
    {
        if (! $this->available($user)) {
            return null;
        }
        try {
            $nodes = $roots = [];
            foreach ($payslips as $payslip) {
                abort_unless($payslip->company_id === $company->id, 403);
                $payslip->loadMissing('lines', 'payrollPeriod');
                $prefix = 'payslip:'.$payslip->id;
                $source = [
                    'label' => $payslip->payslip_number,
                    'href' => route('payslips.show', ['company' => $company->slug, 'payslip' => $payslip->id]),
                    'recorded_at' => $payslip->created_at?->toIso8601String(),
                    'date' => $payslip->payrollPeriod?->period_end?->toDateString(),
                ];
                $make = function (string $id, string $name, float $value, array $children = [], ?string $formula = null, string $explanation = '', string $unit = 'money') use (&$nodes, $source, $payslip) {
                    $nodes[$id] = ['id' => $id, 'label' => $name, 'value' => $value, 'unit' => $unit,
                        'currency' => $payslip->currency, 'children' => $children, 'formula' => $formula,
                        'explanation' => $explanation, 'estimated' => false, 'source' => $children ? null : $source];

                    return $id;
                };
                foreach (['gross' => ['earning', 'gross_pay', 'Gross pay'], 'deductions' => ['deduction', 'total_deductions', 'Deductions']] as $key => [$type, $field, $name]) {
                    $children = [];
                    $sum = 0;
                    foreach ($payslip->lines->where('line_type', $type) as $line) {
                        $id = $prefix.':line:'.$line->id;
                        $inputs = [];
                        $reconciles = $line->quantity !== null && $line->rate !== null
                            && abs(round((float) $line->quantity * (float) $line->rate, 2) - (float) $line->amount) < 0.005;
                        if ($reconciles) {
                            $inputs[] = $make($id.':quantity', 'Saved quantity', (float) $line->quantity, unit: 'units');
                            $inputs[] = $make($id.':rate', 'Saved rate', (float) $line->rate);
                        }
                        $children[] = $make($id, $line->description ?: 'Saved '.$type, (float) $line->amount, $inputs,
                            $reconciles ? 'Saved quantity × saved rate' : null,
                            'Amount saved on this payslip line. Historical price-setting attribution is unavailable.');
                        $sum += (float) $line->amount;
                    }
                    $matches = abs($sum - (float) $payslip->$field) < 0.005;
                    $roots[$prefix.':'.$key] = $make($prefix.':'.$key, $name, (float) $payslip->$field,
                        $matches ? $children : [], $matches ? 'Sum of saved '.$type.' lines' : null,
                        $matches ? 'Uses the saved payslip amounts, including salary advance deductions where recorded.' : 'Saved total. Available lines do not reconcile, so a detailed calculation cannot be verified.');
                }
                $matches = abs((float) $payslip->gross_pay - (float) $payslip->total_deductions - (float) $payslip->net_pay) < 0.005;
                $roots[$prefix.':net'] = $make($prefix.':net', 'Net pay', (float) $payslip->net_pay,
                    $matches ? [$prefix.':gross', $prefix.':deductions'] : [], $matches ? 'Gross pay − deductions' : null,
                    $matches ? 'Saved earnings less saved deductions.' : 'Saved net pay. The available totals do not reconcile.');
                $paid = $payslip->status === 'paid' ? (float) $payslip->net_pay : 0.0;
                $payment = $make($prefix.':paid', 'Recorded payment', $paid, explanation: $payslip->status === 'paid' ? 'This payslip is recorded as fully paid. Payroll currently records whole payslip payments.' : 'No current payment is recorded. Undoing a payment restores the payslip to approved.');
                if ($payslip->payment_gl_transaction_id) {
                    $transaction = \App\Modules\Accounting\Models\Transaction::where('company_id', $company->id)->find($payslip->payment_gl_transaction_id);
                    $nodes[$payment]['source'] = ! $user->hasCompanyPermission(Permissions::JOURNAL_VIEW) && ! $user->isGodMode()
                        ? ['restricted' => true]
                        : ($transaction ? ['label' => $transaction->transaction_number,
                            'date' => $transaction->transaction_date?->toDateString(),
                            'href' => '/'.$company->slug.'/journals/'.$transaction->id,
                            'recorded_at' => $transaction->created_at?->toIso8601String()] : ['unavailable' => true]);
                }
                $undone = \App\Modules\Accounting\Models\Transaction::where('company_id', $company->id)
                    ->where('reference_type', 'pay.payslips')->where('reference_id', $payslip->id)
                    ->where('transaction_type', 'payroll_payment')->whereNotNull('reversed_by_id')->get();
                if ($undone->isNotEmpty()) {
                    $currentPayment = $prefix.':current_payment';
                    $nodes[$currentPayment] = array_merge($nodes[$payment], ['id' => $currentPayment]);
                    $nodes[$payment]['children'] = [$currentPayment];
                    $nodes[$payment]['source'] = null;
                    foreach ($undone as $transaction) {
                        $id = $make($prefix.':undone:'.$transaction->id, 'Undone payment', 0.0, explanation: 'This payment was reversed and contributes nothing to the current paid amount.');
                        $nodes[$id]['source'] = $user->isGodMode() || $user->hasCompanyPermission(Permissions::JOURNAL_VIEW)
                            ? ['label' => $transaction->transaction_number, 'date' => $transaction->transaction_date?->toDateString(),
                                'href' => '/'.$company->slug.'/journals/'.$transaction->id] : ['restricted' => true];
                        $nodes[$payment]['children'][] = $id;
                    }
                }
                $roots[$prefix.':outstanding'] = $make($prefix.':outstanding', 'Remaining to pay', $this->outstanding($payslip),
                    in_array($payslip->status, ['draft', 'approved', 'paid'], true) ? [$prefix.':net', $payment] : [],
                    in_array($payslip->status, ['draft', 'approved', 'paid'], true) ? 'Net pay − recorded payment' : null,
                    $payslip->status === 'draft' ? 'Draft amount, not yet approved for payment.' : 'Based on the current saved payslip payment state. Voided and cancelled payslips have nothing payable.');
            }

            return app(\App\Services\ValueTrailBatch::class)->present(['nodes' => $nodes, 'roots' => $roots, 'context' => ['label' => $label]]);
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'Payroll evidence could not be loaded. Please try again.'];
        }
    }
}
