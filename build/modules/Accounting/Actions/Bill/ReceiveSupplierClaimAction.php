<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\DefaultAccountProvisioner;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Inventory\Models\StockReceiptLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceiveSupplierClaimAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'bill_id' => 'required|uuid',
            'receipt_line_id' => 'required|uuid',
            'received_date' => 'required|date',
            'received_amount' => 'required|numeric|min:0.01',
            'received_account_id' => 'required|uuid',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        return DB::transaction(function () use ($company, $params) {
            $line = StockReceiptLine::where('company_id', $company->id)
                ->where('variance_treatment', 'supplier_claim')
                ->where('claim_status', 'pending')
                ->whereHas('receipt', fn ($query) => $query->where('bill_id', $params['bill_id']))
                ->with(['receipt.bill:id,bill_number,vendor_id,currency,base_currency,exchange_rate', 'item:id,name'])
                ->lockForUpdate()
                ->findOrFail($params['receipt_line_id']);

            $claimAmount = abs(round((float) $line->variance_cost, 2));
            $receivedAmount = round((float) $params['received_amount'], 2);
            if (abs($receivedAmount - $claimAmount) > 0.01) {
                throw new \InvalidArgumentException('Claim received amount must equal the outstanding claim amount.');
            }

            $receivedAccount = Account::where('company_id', $company->id)
                ->where('id', $params['received_account_id'])
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereIn('subtype', ['bank', 'cash', 'other_current_asset', 'accounts_receivable'])
                ->first();

            if (! $receivedAccount) {
                throw new \InvalidArgumentException('Select an active cash, bank, or clearing account for the claim receipt.');
            }

            $claimAccountId = app(DefaultAccountProvisioner::class)
                ->ensureTransitVarianceAccounts($company)['supplier_claims_receivable_account_id'] ?? null;

            if (! $claimAccountId) {
                throw new \RuntimeException('Supplier Claims Receivable account is required.');
            }

            $bill = $line->receipt?->bill;
            $currency = $bill?->currency ?? $company->base_currency;
            $baseCurrency = $bill?->base_currency ?? $company->base_currency;

            $transaction = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_type' => 'supplier_claim_receipt',
                'date' => $params['received_date'],
                'currency' => $currency,
                'base_currency' => $baseCurrency,
                'exchange_rate' => $bill?->exchange_rate,
                'description' => 'Supplier claim received' . ($bill?->bill_number ? " for Bill {$bill->bill_number}" : ''),
                'reference_type' => 'inv.stock_receipt_lines',
                'reference_id' => $line->id,
                'metadata' => [
                    'stock_receipt_line_id' => $line->id,
                    'stock_receipt_id' => $line->stock_receipt_id,
                    'bill_id' => $bill?->id,
                    'bill_number' => $bill?->bill_number,
                    'item_name' => $line->item?->name,
                    'notes' => $params['notes'] ?? null,
                ],
            ], [
                [
                    'account_id' => $receivedAccount->id,
                    'type' => 'debit',
                    'amount' => $receivedAmount,
                    'description' => 'Supplier claim received',
                ],
                [
                    'account_id' => $claimAccountId,
                    'type' => 'credit',
                    'amount' => $receivedAmount,
                    'description' => 'Supplier claim cleared',
                ],
            ]);

            $line->claim_status = 'received';
            $line->claim_received_at = $params['received_date'];
            $line->claim_received_amount = $receivedAmount;
            $line->claim_received_account_id = $receivedAccount->id;
            $line->claim_received_transaction_id = $transaction->id;
            $line->notes = trim(($line->notes ? $line->notes . "\n" : '') . ($params['notes'] ?? ''));
            $line->created_by_user_id = $line->created_by_user_id ?: Auth::id();
            $line->save();

            return [
                'message' => 'Supplier claim received and posted.',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'receipt_line_id' => $line->id,
                ],
            ];
        });
    }
}
