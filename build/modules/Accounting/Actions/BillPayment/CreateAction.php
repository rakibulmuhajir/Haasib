<?php

namespace App\Modules\Accounting\Actions\BillPayment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'vendor_id' => 'required|uuid',
            'payment_number' => 'nullable|string|max:50',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3|uppercase',
            'base_currency' => 'required|string|size:3|uppercase',
            'exchange_rate' => 'nullable|numeric|min:0.00000001|decimal:8',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'payment_account_id' => 'required|uuid',
            'ap_account_id' => 'nullable|uuid',
            'allocations' => 'nullable|array',
            'allocations.*.bill_id' => 'required_with:allocations|uuid',
            'allocations.*.amount_allocated' => 'required_with:allocations|numeric|min:0',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_PAY;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $paymentNumber = $params['payment_number'] ?? $this->nextNumber($company->id);
        $exists = BillPayment::where('company_id', $company->id)
            ->where('payment_number', $paymentNumber)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            throw new \InvalidArgumentException('Payment number already exists');
        }

        $exchangeRate = $params['currency'] === $params['base_currency'] ? null : ($params['exchange_rate'] ?? null);
        $baseAmount = round($params['amount'] * ($exchangeRate ?? 1), 2);

        return DB::transaction(function () use ($company, $params, $paymentNumber, $exchangeRate, $baseAmount) {
            $payment = BillPayment::create([
                'company_id' => $company->id,
                'vendor_id' => $params['vendor_id'],
                'payment_number' => $paymentNumber,
                'payment_date' => $params['payment_date'],
                'amount' => $params['amount'],
                'currency' => $params['currency'],
                'exchange_rate' => $exchangeRate,
                'base_currency' => $params['base_currency'],
                'base_amount' => $baseAmount,
                'payment_method' => $params['payment_method'],
                'payment_account_id' => $params['payment_account_id'] ?? null,
                'reference_number' => $params['reference_number'] ?? null,
                'notes' => $params['notes'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

            if (!empty($params['allocations'])) {
                $sumAlloc = collect($params['allocations'])->sum('amount_allocated');
                if ($sumAlloc > $payment->amount + 0.000001) {
                    throw new \InvalidArgumentException('Allocations exceed payment amount');
                }

                foreach ($params['allocations'] as $allocation) {
                    $bill = Bill::where('company_id', $company->id)->findOrFail($allocation['bill_id']);
                    if (!in_array($payment->currency, [$bill->currency, $bill->base_currency], true)) {
                        throw new \InvalidArgumentException('Payment currency must match bill currency or company base');
                    }

                    BillPaymentAllocation::create([
                        'company_id' => $company->id,
                        'bill_payment_id' => $payment->id,
                        'bill_id' => $bill->id,
                        'amount_allocated' => $allocation['amount_allocated'],
                        'base_amount_allocated' => round($allocation['amount_allocated'] * ($payment->exchange_rate ?? 1), 2),
                        'applied_at' => now(),
                    ]);

                    $bill->paid_amount += $allocation['amount_allocated'];
                    $bill->balance = $bill->total_amount - $bill->paid_amount;
                    if ($bill->balance <= 0) {
                        $bill->status = 'paid';
                        $bill->paid_at = now();
                    } else {
                        $bill->status = 'partial';
                    }
                    $bill->save();
                }
            }

            if (!empty($params['payment_account_id'])) {
                $vendor = Vendor::where('company_id', $company->id)->find($params['vendor_id']);
                $apAccountId = $params['ap_account_id']
                    ?? $vendor?->ap_account_id
                    ?? $company->ap_account_id;
                if (!$apAccountId) {
                    throw new \RuntimeException('AP account is required to post bill payment.');
                }
                $transaction = app(GlPostingService::class)->postBillPayment($payment, $params['payment_account_id'], $apAccountId);
                $payment->transaction_id = $transaction->id;
                $payment->save();
            }

            return [
                'message' => "Payment {$payment->payment_number} created",
                'data' => ['id' => $payment->id],
            ];
        });
    }

    private function nextNumber(string $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $last = BillPayment::where('company_id', $companyId)
                ->whereNotNull('payment_number')
                ->lockForUpdate()
                ->orderByDesc('payment_number')
                ->value('payment_number');

            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            } else {
                $seq = 1;
            }

            return 'PMT-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        });
    }
}
