<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Inventory\Models\Item;
use App\Services\CommandBus;
use Illuminate\Support\Facades\DB;

/** Entry convenience for the reconciliation hub; uses the existing Accounting journal. */
class DailyCloseEntryService
{
    public function expense(string $companyId, string $date, array $expense): Transaction
    {
        return DB::transaction(function () use ($companyId, $date, $expense) {
            $account = Account::where('company_id', $companyId)->where('is_active', true)
                ->where('type', 'expense')->findOrFail($expense['account_id']);
            $cashId = app(DailyCloseService::class)->cashAccountId($companyId);
            $amount = round((float) $expense['amount'], 2);
            if ($amount <= 0 || !$cashId) {
                throw new \InvalidArgumentException('A positive amount and a configured cash account are required.');
            }
            $currency = \App\Models\Company::whereKey($companyId)->value('base_currency') ?: 'PKR';
            return app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $companyId, 'transaction_type' => 'expense', 'date' => $date,
                'currency' => $currency, 'description' => $expense['description'],
                'reference_type' => 'fuel.daily_close_expense',
            ], [
                ['account_id' => $account->id, 'type' => 'debit', 'amount' => $amount, 'description' => $expense['description']],
                ['account_id' => $cashId, 'type' => 'credit', 'amount' => $amount, 'description' => $expense['description']],
            ]);
        });
    }

    /**
     * Enter a supplier bill / fuel purchase inline inside the Daily Close, instead of via the
     * Bills module. Dispatches the same bill.create / bill_payment.create commands the Bills
     * module uses (never hand-rolled), so it produces one ordinary canonical bill, an optional
     * stock receipt into the chosen tank, and an optional immediate cash payment. The close
     * then picks these up automatically as sources, like any other canonical activity for the
     * date.
     *
     * @return array{bill_id:string, bill_transaction_id:?string, payment_transaction_id:?string}
     */
    /**
     * The lines of one inline purchase. A purchase is one supplier bill: `lines` holds each
     * product on it; a draft saved before multi-line purchases carried a single product on the
     * row itself, which reads as one line. Incomplete lines (no item or quantity) are dropped.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function purchaseLines(array $purchase): array
    {
        $lines = isset($purchase['lines']) && is_array($purchase['lines']) ? $purchase['lines'] : [$purchase];

        return array_values(array_filter($lines, fn ($line) => is_array($line)
            && ! empty($line['item_id']) && (float) ($line['quantity'] ?? 0) > 0));
    }

    public function purchase(string $companyId, string $date, array $purchase, User $user): array
    {
        $company = Company::findOrFail($companyId);
        $lineItems = [];
        foreach (self::purchaseLines($purchase) as $line) {
            $item = Item::where('company_id', $companyId)->find($line['item_id']);
            $directQuantity = round((float) ($line['direct_quantity'] ?? 0), 3);
            // A delivery sold entirely straight to a customer never reaches a tank.
            if ($item?->fuel_category && empty($line['tank_id']) && (float) $line['quantity'] - $directQuantity > 0.0005) {
                throw new \InvalidArgumentException("A tank is required for {$item->name}.");
            }
            $lineItems[] = array_filter([
                'item_id' => $line['item_id'],
                'warehouse_id' => $line['tank_id'] ?? null,
                'description' => $line['description'] ?? ($item->name ?? 'Purchase'),
                'quantity' => $line['quantity'],
                // Sold directly: its cost goes to COGS on the bill and it is never received.
                'direct_quantity' => $directQuantity > 0 ? $directQuantity : null,
                'unit_price' => $line['unit_cost'] ?? null,
                // The total actually billed, when the supplier priced this delivery to
                // more decimals than the row's rate field carries -- bill.create derives
                // the exact rate from it instead of the rounded one. See BillLineTotals.
                'line_total' => $line['line_total'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        }
        if (! $lineItems) {
            throw new \InvalidArgumentException('A purchase needs at least one product with a quantity.');
        }

        $billResult = app(CommandBus::class)->dispatch('bill.create', [
            'vendor_id' => $purchase['supplier_id'],
            'vendor_invoice_number' => $purchase['supplier_invoice_number'] ?? null,
            'bill_date' => $date,
            'status' => 'received',
            'currency' => $company->base_currency ?: 'PKR',
            'base_currency' => $company->base_currency ?: 'PKR',
            'notes' => $purchase['notes'] ?? null,
            'line_items' => $lineItems,
        ], $user);

        $bill = Bill::where('company_id', $companyId)->findOrFail($billResult['data']['id']);

        // An inline close purchase is the operator declaring "this delivery physically
        // arrived today into this tank" — receive it unconditionally, regardless of the
        // item's delivery_mode. bill.create only auto-receives 'immediate' items, and
        // fuel items are 'requires_receiving' (App\Modules\FuelStation\Actions\Product\
        // SetupAction), so without this the goods would never be received: no stock
        // moves, quantity_received stays 0, and the tank loop's reconciling movement
        // silently absorbs the missing litres as a fake variance.
        $bill->loadMissing('lineItems');
        // Receive only what is still outstanding: an 'immediate' item was already received
        // by bill.create's own auto-receive, and receiving its full quantity again here
        // would double the stock. Mirrors Bill\CreateAction::autoReceiveImmediateItems().
        $receiveLines = $bill->lineItems
            ->filter(fn ($line) => $line->warehouse_id && $line->item_id)
            ->map(fn ($line) => [
                'line_id' => $line->id,
                'quantity' => round((float) $line->quantity - (float) $line->direct_quantity - (float) $line->quantity_received, 6),
                'warehouse_id' => $line->warehouse_id,
            ])
            ->filter(fn ($line) => $line['quantity'] > 0)
            ->values()
            ->all();

        if (!empty($receiveLines)) {
            app(CommandBus::class)->dispatch('bill.receive_goods', [
                'id' => $bill->id,
                'receipt_date' => $date,
                'lines' => $receiveLines,
            ], $user);
            $bill->refresh();
        }

        $paymentTransactionId = null;

        if (filter_var($purchase['paid_now'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $cashId = app(DailyCloseService::class)->cashAccountId($companyId);
            if (!$cashId) {
                throw new \RuntimeException('No cash account is configured for this company.');
            }
            $paymentResult = app(CommandBus::class)->dispatch('bill_payment.create', [
                'vendor_id' => $bill->vendor_id,
                'payment_date' => $date,
                'amount' => (float) $bill->total_amount,
                'currency' => $bill->currency,
                'base_currency' => $bill->base_currency,
                'payment_method' => 'cash',
                'payment_account_id' => $cashId,
                'ap_account_id' => $bill->vendor?->ap_account_id,
                'allocations' => [['bill_id' => $bill->id, 'amount_allocated' => (float) $bill->total_amount]],
            ], $user);
            $payment = BillPayment::where('company_id', $companyId)->findOrFail($paymentResult['data']['id']);
            $paymentTransactionId = $payment->transaction_id;
        }

        return [
            'bill_id' => $bill->id,
            'bill_transaction_id' => $bill->fresh()->transaction_id,
            'payment_transaction_id' => $paymentTransactionId,
        ];
    }
}
