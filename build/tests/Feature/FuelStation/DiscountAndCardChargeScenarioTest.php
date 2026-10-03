<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\ProfitStatementService;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

/*
 * Scenario with concrete numbers: 100 L petrol at 300 = 30,000. Credit sale 20 L = 6,000 for a
 * customer on a 3.5/L discount (70, net 5,930). POS receipt 9,000 on a channel charging 1%
 * (90, so 8,910 reaches the clearing/bank account).
 */
test('a per-litre customer discount and a card channel charge book end to end at the daily close', function () {
    $f = creditCloseFixture();
    app(CurrentCompany::class)->set($f['company']);
    $companyId = $f['company']->id;

    $petrol = Item::where('company_id', $companyId)->where('sku', 'PETROL')->sole();
    // The close posts the card charge to POS / bank charges (6160).
    $chargesAccount = Account::create([
        'company_id' => $companyId, 'code' => '6160', 'name' => 'POS/Bank Charges',
        'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    // 1. Customer discount of 3.5 per litre on petrol.
    CustomerFuelDiscount::create(['company_id' => $companyId, 'customer_id' => $f['customer']->id, 'item_id' => $petrol->id, 'discount_type' => 'per_litre', 'value' => 3.5]);

    // 2. The POS channel charges 1%.
    $settings = StationSettings::where('company_id', $companyId)->first();
    $channels = $settings->payment_channels;
    $channels[0]['fee_percent'] = 1;
    $settings->update(['payment_channels' => $channels]);

    // The credit row names the fuel and the litres (20 L of the 100 L sold).
    $f['payload']['credit_sales'] = [[
        'customer_id' => $f['customer']->id, 'amount' => 6000, 'reference' => 'Slip 42',
        'item_id' => $petrol->id, 'litres' => 20,
    ]];

    // 3. Post the close.
    $posted = creditClosePost($f);
    $txId = $posted['transaction_id'];

    // Invoice.
    $detail = collect($posted['metadata']['credit_sale_details'])->sole();
    $invoice = Invoice::with('lineItems')->findOrFail($detail['invoice_id']);
    $line = $invoice->lineItems->sole();
    expect((float) $invoice->subtotal)->toBe(6000.0)
        ->and((float) $invoice->discount_amount)->toBe(70.0)
        ->and((float) $invoice->total_amount)->toBe(5930.0)
        ->and((float) $line->line_total)->toBe(6000.0)
        // The line holds the gross; the discount lives on the invoice header, so the line total is net.
        ->and((float) $line->total)->toBe(5930.0);

    // Ledger.
    $discountAccount = Account::where('company_id', $companyId)->where('code', '4210')->firstOrFail();
    $sum = fn (string $accountId, string $col, ?string $tx = null) => (float) DB::table('acct.journal_entries')
        ->where('account_id', $accountId)->when($tx, fn ($q) => $q->where('transaction_id', $tx))->sum($col);

    $chargeLine = DB::table('acct.journal_entries')->where('transaction_id', $txId)->where('account_id', $chargesAccount->id)->sole();
    expect($sum($discountAccount->id, 'debit_amount', $txId))->toBe(70.0)
        ->and($sum($f['accounts']['1100']->id, 'debit_amount', $txId))->toBe(5930.0)
        ->and($sum($f['accounts']['4100']->id, 'credit_amount', $txId))->toBe(30000.0)
        ->and($sum($f['accounts']['1020']->id, 'debit_amount', $txId))->toBe(8910.0)
        ->and((float) $chargeLine->debit_amount)->toBe(90.0)
        ->and($chargeLine->description)->toContain('charge 1%');

    // Customer statement.
    $result = app(CustomerStatementService::class)->statement($f['customer'], '2026-09-01', '2026-09-30');
    $statement = collect($result['rows']);
    $row = $statement->firstWhere('type', 'invoice');
    expect($row['date'])->toBe('2026-09-15')
        ->and($row['debit'])->toBe(5930.0)
        ->and($result['closing_balance'])->toBe(5930.0);

    // Profit statement.
    $profit = app(ProfitStatementService::class)->run($companyId, '2026-09-01', '2026-09-30');
    $lines = collect($profit['lines'])->keyBy('key');
    $discountRow = collect($lines['sales']['details'])->firstWhere('name', 'Discounts given');
    $chargeRow = collect($lines['other_costs']['details'])->firstWhere('account_id', $chargesAccount->id);
    expect($discountRow)->not->toBeNull()
        ->and((float) $discountRow['amount'])->toBe(-70.0)
        ->and($chargeRow)->not->toBeNull()
        ->and((float) $chargeRow['amount'])->toBe(90.0)
        ->and($profit['net_profit'])->toBe($profit['ledger_total']);

    fwrite(STDERR, "\n".implode("\n", [
        sprintf('%-34s %12s', 'Gross fuel sales (4100 cr)', number_format($sum($f['accounts']['4100']->id, 'credit_amount', $txId), 2)),
        sprintf('%-34s %12s', 'Invoice gross / discount / total', number_format((float) $invoice->subtotal, 2).' / '.number_format((float) $invoice->discount_amount, 2).' / '.number_format((float) $invoice->total_amount, 2)),
        sprintf('%-34s %12s', 'Sales Discounts 4210 (dr)', number_format($sum($discountAccount->id, 'debit_amount', $txId), 2)),
        sprintf('%-34s %12s', 'AR 1100 (dr)', number_format($sum($f['accounts']['1100']->id, 'debit_amount', $txId), 2)),
        sprintf('%-34s %12s', 'Clearing 1020 (dr)', number_format($sum($f['accounts']['1020']->id, 'debit_amount', $txId), 2)),
        sprintf('%-34s %12s', 'Card charges 6160 (dr)', number_format((float) $chargeLine->debit_amount, 2)),
        sprintf('%-34s %12s', 'Statement invoice debit', number_format($row['debit'], 2)),
        sprintf('%-34s %12s', 'Profit: discounts given', number_format((float) $discountRow['amount'], 2)),
        sprintf('%-34s %12s', 'Profit: card charges', number_format((float) $chargeRow['amount'], 2)),
        sprintf('%-34s %12s', 'Net profit / ledger total', number_format($profit['net_profit'], 2).' / '.number_format($profit['ledger_total'], 2)),
    ])."\n");
});
