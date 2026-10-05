<?php

namespace App\Modules\Accounting\Actions\VendorCredit;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use Illuminate\Support\Facades\Auth;

/**
 * A draft is edited freely. A posted (received, not yet applied) credit is edited by
 * reversing its journal on its own date and posting the new one, the way a bill is.
 */
class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'vendor_id' => 'required|uuid',
            'vendor_credit_number' => 'nullable|string|max:100',
            'credit_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3|uppercase',
            'base_currency' => 'required|string|size:3|uppercase',
            'exchange_rate' => 'nullable|numeric|min:0.00000001|decimal:8',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
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
        return \App\Services\AccountingWriteTransaction::run(function () use ($params) {
            $company = CompanyContext::requireCompany();
            $credit = VendorCredit::where('company_id', $company->id)->lockForUpdate()->findOrFail($params['id']);

            if (! in_array($credit->status, ['draft', 'received'], true)) {
                throw new \InvalidArgumentException('This credit can no longer be edited');
            }
            if ($credit->applications()->exists()) {
                throw new \InvalidArgumentException('This credit has been applied to a bill and can no longer be edited');
            }

            $vendor = Vendor::where('company_id', $company->id)->findOrFail($params['vendor_id']);

            if ($params['currency'] !== $params['base_currency'] && $credit->bill_id === null) {
                throw new \InvalidArgumentException('Currency must equal company base when not tied to a bill');
            }

            $posted = $credit->status === 'received';
            $lock = app(DocumentDateLock::class);
            $newDate = \Illuminate\Support\Carbon::parse($params['credit_date'])->toDateString();
            if ($posted) {
                $lock->assertOpen($company->id, $credit->credit_date->toDateString(), "Vendor credit {$credit->credit_number}");
                $lock->assertOpen($company->id, $newDate, "Vendor credit {$credit->credit_number}");
            }

            $exchangeRate = $params['currency'] === $params['base_currency'] ? null : ($params['exchange_rate'] ?? null);

            $credit->fill([
                'vendor_id' => $vendor->id,
                'vendor_credit_number' => $params['vendor_credit_number'] ?? null,
                'credit_date' => $newDate,
                'amount' => $params['amount'],
                'currency' => $params['currency'],
                'base_currency' => $params['base_currency'],
                'exchange_rate' => $exchangeRate,
                'base_amount' => round($params['amount'] * ($exchangeRate ?? 1), 2),
                'reason' => $params['reason'],
                'notes' => $params['notes'] ?? null,
                'ap_account_id' => $params['ap_account_id'] ?? $vendor->ap_account_id,
                'updated_by_user_id' => Auth::id(),
            ]);
            $credit->save();

            VendorCreditLines::replace($credit, $params['line_items'] ?? []);

            if ($posted) {
                $old = $credit->transaction_id
                    ? Transaction::where('company_id', $company->id)->find($credit->transaction_id)
                    : null;
                if ($old) {
                    app(PostingService::class)->reverseTransaction($old, 'Document amended', $old->transaction_date);
                }
                $new = app(GlPostingService::class)->postVendorCredit($credit->fresh(), Transaction::generateJournalNumber($company->id));
                $credit->transaction_id = $new->id;
                $credit->save();
            }

            return [
                'message' => "Vendor credit {$credit->credit_number} updated",
                'data' => ['id' => $credit->id],
            ];
        });
    }
}
