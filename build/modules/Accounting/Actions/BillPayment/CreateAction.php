<?php

namespace App\Modules\Accounting\Actions\BillPayment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            'payment_account_id' => 'nullable|uuid',
            'payment_splits' => 'nullable|array',
            'payment_splits.*.payment_account_id' => 'required_with:payment_splits|uuid',
            'payment_splits.*.amount' => 'required_with:payment_splits|numeric|min:0.01',
            'payment_splits.*.payment_method' => 'required_with:payment_splits|string|max:50',
            'payment_splits.*.reference_number' => 'nullable|string|max:100',
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

        $exchangeRate = $params['currency'] === $params['base_currency'] ? null : ($params['exchange_rate'] ?? null);
        $splits = $this->normalizeSplits($params);
        $splitTotal = round(collect($splits)->sum('amount'), 6);
        $paymentAmount = round((float) $params['amount'], 6);
        if (abs($splitTotal - $paymentAmount) > 0.000001) {
            throw new \InvalidArgumentException('Payment splits must equal the total payment amount.');
        }

        $paymentNumbers = $this->paymentNumbers($company->id, $params['payment_number'] ?? null, count($splits));
        foreach ($paymentNumbers as $paymentNumber) {
            $exists = BillPayment::where('company_id', $company->id)
                ->where('payment_number', $paymentNumber)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                throw new \InvalidArgumentException("Payment number {$paymentNumber} already exists");
            }
        }

        return DB::transaction(function () use ($company, $params, $paymentNumbers, $exchangeRate, $splits) {
            $allocationPool = $this->validateAndBuildAllocationPool($company->id, $params);
            $createdPayments = [];
            $paymentGroupId = (string) Str::uuid();
            $paymentGroupNumber = $params['payment_number'] ?? $paymentNumbers[0];

            foreach ($splits as $index => $split) {
                $amount = round((float) $split['amount'], 6);
                $baseAmount = round($amount * ($exchangeRate ?? 1), 2);
                $paymentAllocations = $this->takeAllocationsForAmount($allocationPool, $amount);

                $payment = BillPayment::create([
                'company_id' => $company->id,
                'vendor_id' => $params['vendor_id'],
                    'payment_group_id' => $paymentGroupId,
                    'payment_group_number' => $paymentGroupNumber,
                    'payment_number' => $paymentNumbers[$index],
                'payment_date' => $params['payment_date'],
                    'amount' => $amount,
                'currency' => $params['currency'],
                'exchange_rate' => $exchangeRate,
                'base_currency' => $params['base_currency'],
                'base_amount' => $baseAmount,
                    'payment_method' => $split['payment_method'],
                    'payment_account_id' => $split['payment_account_id'],
                    'reference_number' => $split['reference_number'] ?? $params['reference_number'] ?? null,
                'notes' => $params['notes'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

                foreach ($paymentAllocations as $allocation) {
                    BillPaymentAllocation::create([
                        'company_id' => $company->id,
                        'bill_payment_id' => $payment->id,
                        'bill_id' => $allocation['bill']->id,
                        'amount_allocated' => $allocation['amount_allocated'],
                        'base_amount_allocated' => round($allocation['amount_allocated'] * ($payment->exchange_rate ?? 1), 2),
                        'applied_at' => now(),
                    ]);

                    $bill = $allocation['bill'];
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

                $createdPayments[] = $payment;
            }

            return [
                'message' => count($createdPayments) === 1
                    ? "Payment {$createdPayments[0]->payment_number} recorded for Daily Close"
                    : count($createdPayments) . ' split payments recorded for Daily Close',
                'data' => ['id' => $createdPayments[0]->id, 'ids' => collect($createdPayments)->pluck('id')->all()],
            ];
        });
    }

    private function normalizeSplits(array $params): array
    {
        $splits = collect($params['payment_splits'] ?? [])
            ->filter(fn ($split) => (float) ($split['amount'] ?? 0) > 0)
            ->map(fn ($split) => [
                'payment_account_id' => $split['payment_account_id'],
                'amount' => round((float) $split['amount'], 6),
                'payment_method' => $split['payment_method'] ?? $params['payment_method'],
                'reference_number' => $split['reference_number'] ?? null,
            ])
            ->values()
            ->all();

        if (! empty($splits)) {
            return $splits;
        }

        if (empty($params['payment_account_id'])) {
            throw new \InvalidArgumentException('Payment account is required.');
        }

        return [[
            'payment_account_id' => $params['payment_account_id'],
            'amount' => round((float) $params['amount'], 6),
            'payment_method' => $params['payment_method'],
            'reference_number' => $params['reference_number'] ?? null,
        ]];
    }

    private function validateAndBuildAllocationPool(string $companyId, array $params): array
    {
        $allocations = collect($params['allocations'] ?? [])
            ->filter(fn ($allocation) => (float) ($allocation['amount_allocated'] ?? 0) > 0)
            ->values();

        $sumAlloc = round((float) $allocations->sum('amount_allocated'), 6);
        if (abs($sumAlloc - round((float) $params['amount'], 6)) > 0.000001) {
            throw new \InvalidArgumentException('Allocations must equal payment amount.');
        }

        return $allocations->map(function ($allocation) use ($companyId, $params) {
            $bill = Bill::where('company_id', $companyId)->findOrFail($allocation['bill_id']);
            if (!in_array($params['currency'], [$bill->currency, $bill->base_currency], true)) {
                throw new \InvalidArgumentException('Payment currency must match bill currency or company base');
            }

            $amount = round((float) $allocation['amount_allocated'], 6);
            if ($amount > ((float) $bill->balance + 0.000001)) {
                throw new \InvalidArgumentException("Allocation exceeds balance for bill {$bill->bill_number}.");
            }

            return [
                'bill' => $bill,
                'remaining' => $amount,
            ];
        })->all();
    }

    private function takeAllocationsForAmount(array &$allocationPool, float $amount): array
    {
        $remaining = round($amount, 6);
        $taken = [];

        foreach ($allocationPool as &$poolItem) {
            if ($remaining <= 0.000001) {
                break;
            }
            if ($poolItem['remaining'] <= 0.000001) {
                continue;
            }

            $take = min($poolItem['remaining'], $remaining);
            $take = round($take, 6);
            $taken[] = [
                'bill' => $poolItem['bill'],
                'amount_allocated' => $take,
            ];
            $poolItem['remaining'] = round($poolItem['remaining'] - $take, 6);
            $remaining = round($remaining - $take, 6);
        }

        if ($remaining > 0.000001) {
            throw new \InvalidArgumentException('Payment splits exceed available allocations.');
        }

        return $taken;
    }

    private function paymentNumbers(string $companyId, ?string $requested, int $count): array
    {
        if ($count === 1) {
            return [$requested ?: $this->nextNumber($companyId)];
        }

        if ($requested) {
            return collect(range(1, $count))
                ->map(fn ($index) => substr("{$requested}-{$index}", 0, 50))
                ->all();
        }

        $first = $this->nextNumber($companyId);
        if (! preg_match('/^(.*?)(\d+)$/', $first, $matches)) {
            return collect(range(1, $count))
                ->map(fn ($index) => substr("{$first}-{$index}", 0, 50))
                ->all();
        }

        $prefix = $matches[1];
        $number = (int) $matches[2];
        $width = strlen($matches[2]);

        return collect(range(0, $count - 1))
            ->map(fn ($offset) => $prefix . str_pad((string) ($number + $offset), $width, '0', STR_PAD_LEFT))
            ->all();
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
