<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'vendor_id' => 'required|uuid',
            'bill_number' => 'nullable|string|max:50',
            'vendor_invoice_number' => 'nullable|string|max:100',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string|size:3|uppercase',
            'base_currency' => 'required|string|size:3|uppercase',
            'exchange_rate' => 'nullable|numeric|min:0.00000001|decimal:8',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.account_id' => 'nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $billNumber = $params['bill_number'] ?? $this->nextNumber($company->id);
        $billNumberExists = Bill::where('company_id', $company->id)
            ->where('bill_number', $billNumber)
            ->whereNull('deleted_at')
            ->exists();
        if ($billNumberExists) {
            throw new \InvalidArgumentException('Bill number already exists');
        }

        $billDate = $params['bill_date'];
        $paymentTerms = $params['payment_terms'] ?? 30;
        $dueDate = $params['due_date'] ?? now()->parse($billDate)->addDays($paymentTerms)->toDateString();

        $exchangeRate = $params['currency'] === $params['base_currency'] ? null : ($params['exchange_rate'] ?? null);

        return DB::transaction(function () use ($company, $params, $billNumber, $dueDate, $paymentTerms, $exchangeRate) {
            $lineTotals = collect($params['line_items'])->map(function ($item) {
                $lineTotal = round(($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0), 6);
                $taxAmount = round($lineTotal * (($item['tax_rate'] ?? 0) / 100), 6);
                $discountAmount = round($lineTotal * (($item['discount_rate'] ?? 0) / 100), 6);
                $total = $lineTotal + $taxAmount - $discountAmount;
                return ['line_total' => $lineTotal, 'tax_amount' => $taxAmount, 'total' => $total, 'source' => $item];
            });

            $subtotal = $lineTotals->sum('line_total');
            $taxAmount = $lineTotals->sum('tax_amount');
            $discountAmount = $lineTotals->sum(fn ($l) => ($l['source']['discount_rate'] ?? 0) ? $l['line_total'] * ($l['source']['discount_rate'] ?? 0) / 100 : 0);
            $totalAmount = $lineTotals->sum('total');
            $baseAmount = round($totalAmount * ($exchangeRate ?? 1), 2);

            $bill = Bill::create([
                'company_id' => $company->id,
                'vendor_id' => $params['vendor_id'],
                'bill_number' => $billNumber,
                'vendor_invoice_number' => $params['vendor_invoice_number'] ?? null,
                'bill_date' => $params['bill_date'],
                'due_date' => $dueDate,
                'status' => 'draft',
                'currency' => $params['currency'],
                'base_currency' => $params['base_currency'],
                'exchange_rate' => $exchangeRate,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance' => $totalAmount,
                'base_amount' => $baseAmount,
                'payment_terms' => $paymentTerms,
                'notes' => $params['notes'] ?? null,
                'internal_notes' => $params['internal_notes'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

            foreach ($lineTotals as $index => $line) {
                $source = $line['source'];
                BillLineItem::create([
                    'company_id' => $company->id,
                    'bill_id' => $bill->id,
                    'line_number' => $index + 1,
                    'description' => $source['description'],
                    'quantity' => $source['quantity'],
                    'unit_price' => $source['unit_price'],
                    'tax_rate' => $source['tax_rate'] ?? 0,
                    'discount_rate' => $source['discount_rate'] ?? 0,
                    'line_total' => $line['line_total'],
                    'tax_amount' => $line['tax_amount'],
                    'total' => $line['total'],
                    'account_id' => $source['account_id'] ?? null,
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            return [
                'message' => "Bill {$bill->bill_number} created",
                'data' => ['id' => $bill->id],
            ];
        });
    }

    private function nextNumber(string $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $last = Bill::where('company_id', $companyId)
                ->whereNotNull('bill_number')
                ->lockForUpdate()
                ->orderByDesc('bill_number')
                ->value('bill_number');

            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            } else {
                $seq = 1;
            }

            return 'BILL-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        });
    }
}
