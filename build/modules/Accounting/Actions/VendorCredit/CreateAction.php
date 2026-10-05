<?php

namespace App\Modules\Accounting\Actions\VendorCredit;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'vendor_id' => 'required|uuid',
            'bill_id' => 'nullable|uuid',
            'credit_number' => 'nullable|string|max:50',
            'vendor_credit_number' => 'nullable|string|max:100',
            'credit_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3|uppercase',
            'base_currency' => 'required|string|size:3|uppercase',
            'exchange_rate' => 'nullable|numeric|min:0.00000001|decimal:8',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,received',
            'ap_account_id' => 'nullable|uuid',
            'line_items' => 'nullable|array',
            'line_items.*.description' => 'sometimes|required|string|max:500',
            'line_items.*.quantity' => 'sometimes|required|numeric|min:0.01',
            'line_items.*.unit_price' => 'sometimes|required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.expense_account_id' => 'nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::VENDOR_CREDIT_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $vendor = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->findOrFail($params['vendor_id']);

        if ($params['currency'] !== $params['base_currency']) {
            if (($params['bill_id'] ?? null) === null) {
                throw new \InvalidArgumentException('Currency must equal company base when not tied to a bill');
            }
        }

        $creditNumber = $params['credit_number'] ?? $this->nextNumber($company->id);
        $exists = VendorCredit::where('company_id', $company->id)
            ->where('credit_number', $creditNumber)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            throw new \InvalidArgumentException('Credit number already exists');
        }

        $exchangeRate = $params['currency'] === $params['base_currency'] ? null : ($params['exchange_rate'] ?? null);
        $baseAmount = round($params['amount'] * ($exchangeRate ?? 1), 2);

        return DB::transaction(function () use ($company, $params, $creditNumber, $exchangeRate, $baseAmount, $vendor) {
            $credit = VendorCredit::create([
                'company_id' => $company->id,
                'vendor_id' => $params['vendor_id'],
                'bill_id' => $params['bill_id'] ?? null,
                'credit_number' => $creditNumber,
                'vendor_credit_number' => $params['vendor_credit_number'] ?? null,
                'credit_date' => $params['credit_date'],
                'amount' => $params['amount'],
                'currency' => $params['currency'],
                'base_currency' => $params['base_currency'],
                'exchange_rate' => $exchangeRate,
                'base_amount' => $baseAmount,
                'reason' => $params['reason'],
                'status' => ($params['status'] ?? 'draft') === 'received' ? 'received' : 'draft',
                'notes' => $params['notes'] ?? null,
                'ap_account_id' => $params['ap_account_id'] ?? $vendor->ap_account_id,
                'created_by_user_id' => Auth::id(),
            ]);

            VendorCreditLines::replace($credit, $params['line_items'] ?? []);

            if (($params['status'] ?? 'draft') === 'received') {
                $transaction = app(GlPostingService::class)->postVendorCredit($credit->fresh());
                $credit->transaction_id = $transaction->id;
                $credit->received_at = now();
                $credit->save();
            }

            return [
                'message' => "Vendor credit {$credit->credit_number} created",
                'data' => ['id' => $credit->id],
            ];
        });
    }

    private function nextNumber(string $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $last = VendorCredit::where('company_id', $companyId)
                ->whereNotNull('credit_number')
                ->lockForUpdate()
                ->orderByDesc('credit_number')
                ->value('credit_number');

            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            } else {
                $seq = 1;
            }

            return 'VCRED-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        });
    }
}
