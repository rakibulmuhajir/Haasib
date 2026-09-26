<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';
require_once __DIR__.'/CustomerFuelDiscountFixtures.php';

/**
 * Applying a discount to a credit-sale invoice AFTER its close has already posted and been
 * locked (Owner's decision: "Allow unlock, and then it'd display the invoice amount and the
 * discount applied. In the invoice, the discount should also be visible.").
 *
 * Uses discountedCustomerFixture() (CustomerFuelDiscountFixtures.php) for the diesel item /
 * rate / nozzle, plus RBAC bootstrapping (same shape as DailyCloseUnlockAuditTest's
 * unlockAuditFixture()) so lock/unlock and the new discount endpoint can be hit over HTTP
 * with real permissions.
 */
function postCloseDiscountFixture(): array
{
    $f = discountedCustomerFixture();
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert([
        'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
    app(\App\Services\CurrentCompany::class)->set($f['company']);

    $payload = $f['payload'];
    $payload['nozzle_readings'] = [[
        'nozzle_id' => $f['dieselNozzle']->id, 'item_id' => $f['diesel']->id,
        'opening_electronic' => 0, 'closing_electronic' => 1000, 'liters_sold' => 1000, 'sale_rate' => 290,
    ]];
    $payload['payment_receipts'] = [];
    $payload['credit_sales'] = [[
        'customer_id' => $f['customer']->id, 'amount' => 290000, 'reference' => 'Diesel credit',
        'item_id' => $f['diesel']->id, 'litres' => 1000,
    ]];
    // All 290,000 of revenue is credit; nothing landed in the drawer beyond the opening float.
    $payload['opening_cash'] = 10000;
    $payload['closing_cash'] = 10000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);
    $f['transaction'] = Transaction::findOrFail($posted['transaction_id']);
    $f['creditDetail'] = collect($posted['metadata']['credit_sale_details'])->sole();
    $f['invoice'] = Invoice::findOrFail($f['creditDetail']['invoice_id']);

    return $f;
}

test('a close-created invoice for a discounted customer stores the gross line, the discount, and the net total', function () {
    $f = postCloseDiscountFixture();

    // No discount was on file for this customer/fuel when this fixture built the close, so
    // this pins the plain gross-line shape (item A) before the per-litre discount test below
    // adds one: quantity = litres, unit_price = gross/litres, discount_amount on the line.
    expect((float) $f['invoice']->total_amount)->toBe(290000.0)
        ->and((float) $f['invoice']->discount_amount)->toBe(0.0);

    $line = InvoiceLineItem::where('invoice_id', $f['invoice']->id)->sole();
    expect((float) $line->quantity)->toBe(1000.0)
        ->and((float) $line->unit_price)->toBe(290.0)
        ->and((float) $line->line_total)->toBe(290000.0)
        ->and((float) $line->total)->toBe(290000.0);
});

test('a close-created invoice for a customer with a per-litre discount stores the gross line, the discount, and the net total', function () {
    $f = discountedCustomerFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);

    $payload = $f['payload'];
    $payload['nozzle_readings'] = [[
        'nozzle_id' => $f['dieselNozzle']->id, 'item_id' => $f['diesel']->id,
        'opening_electronic' => 0, 'closing_electronic' => 1000, 'liters_sold' => 1000, 'sale_rate' => 290,
    ]];
    $payload['payment_receipts'] = [];
    $payload['credit_sales'] = [[
        'customer_id' => $f['customer']->id, 'amount' => 290000, 'reference' => 'Diesel credit',
        'item_id' => $f['diesel']->id, 'litres' => 1000,
    ]];
    $payload['opening_cash'] = 10000;
    $payload['closing_cash'] = 10000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);
    $creditDetail = collect($posted['metadata']['credit_sale_details'])->sole();
    $invoice = Invoice::findOrFail($creditDetail['invoice_id']);

    expect((float) $invoice->discount_amount)->toBe(3000.0)
        ->and((float) $invoice->total_amount)->toBe(287000.0)
        ->and((float) $invoice->balance)->toBe(287000.0);

    $line = InvoiceLineItem::where('invoice_id', $invoice->id)->sole();
    expect((float) $line->quantity)->toBe(1000.0)
        ->and((float) $line->unit_price)->toBe(290.0)
        ->and((float) $line->line_total)->toBe(290000.0)
        ->and((float) $line->total)->toBe(287000.0);
});

test('applying a post-close discount while the close is still locked is refused', function () {
    $f = postCloseDiscountFixture();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/lock")
        ->assertRedirect();
    expect($f['transaction']->fresh()->is_locked)->toBeTrue();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/credit-sales/{$f['invoice']->id}/discount", [
            'item_id' => $f['diesel']->id, 'litres' => 1000, 'discount_amount' => 3000,
        ])
        ->assertRedirect();

    expect(session('error'))->not->toBeNull();
    $f['invoice']->refresh();
    expect((float) $f['invoice']->discount_amount)->toBe(0.0)
        ->and((float) $f['invoice']->total_amount)->toBe(290000.0);
});

test('after unlocking, a post-close discount amends the invoice, posts a dated journal, leaves the snapshot unchanged, and appears as post-close activity', function () {
    $f = postCloseDiscountFixture();

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/lock")->assertRedirect();
    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/unlock", ['reason' => 'Customer negotiated a late discount.'])
        ->assertRedirect();
    expect($f['transaction']->fresh()->is_locked)->toBeFalse();

    $snapshotBefore = $f['transaction']->fresh()->metadata['posting_snapshot'];

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/credit-sales/{$f['invoice']->id}/discount", [
            'item_id' => $f['diesel']->id, 'litres' => 1000, 'discount_amount' => 3000,
        ])
        ->assertRedirect();
    expect(session('error'))->toBeNull();

    $f['invoice']->refresh();
    expect((float) $f['invoice']->discount_amount)->toBe(3000.0)
        ->and((float) $f['invoice']->total_amount)->toBe(287000.0)
        ->and((float) $f['invoice']->balance)->toBe(287000.0);

    $businessDate = $f['transaction']->transaction_date->toDateString();
    $discountAccount = fuelDiscountsAccount($f['company']->id);
    $journal = Transaction::where('company_id', $f['company']->id)
        ->where('transaction_type', 'fuel_daily_close_discount')
        ->where('reference_id', $f['invoice']->id)
        ->sole();
    expect($journal->transaction_date->toDateString())->toBe($businessDate)
        ->and((float) DB::table('acct.journal_entries')->where('transaction_id', $journal->id)->where('account_id', $discountAccount->id)->sum('debit_amount'))->toBe(3000.0)
        ->and((float) DB::table('acct.journal_entries')->where('transaction_id', $journal->id)->where('account_id', $f['accounts']['1100']->id)->sum('credit_amount'))->toBe(3000.0);

    // The posted snapshot itself -- the frozen record of what the close counted at the
    // moment it posted -- never changes. Only the reconciled/"current" read model (below)
    // reflects the discount.
    expect($f['transaction']->fresh()->metadata['posting_snapshot'])->toBe($snapshotBefore);

    $view = app(\App\Modules\FuelStation\Services\DailyCloseReconciliationService::class)->view($f['transaction']->fresh());
    expect($view['has_post_close_activity'])->toBeTrue();
    $activityRow = collect($view['activity'])->firstWhere('id', $journal->id);
    expect($activityRow)->not->toBeNull()
        ->and($activityRow['description'])->toContain("Discount on {$f['invoice']->invoice_number}")
        // Never touches drawer cash.
        ->and((float) $activityRow['cash_effect'])->toBe(0.0);
    expect((float) $view['current']['variance'])->toBe((float) $view['snapshot']['totals']['variance']);
});

test('a discount larger than the invoice balance is refused', function () {
    $f = postCloseDiscountFixture();
    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/lock")->assertRedirect();
    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/unlock", ['reason' => 'Testing the balance ceiling.'])
        ->assertRedirect();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/credit-sales/{$f['invoice']->id}/discount", [
            'item_id' => $f['diesel']->id, 'litres' => 1000, 'discount_amount' => 300000,
        ])
        ->assertRedirect();

    expect(session('error'))->not->toBeNull();
    expect((float) $f['invoice']->fresh()->discount_amount)->toBe(0.0);
});

test('creating an invoice dated a day whose close is locked is refused', function () {
    $f = postCloseDiscountFixture();
    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/daily-close/{$f['transaction']->id}/lock")->assertRedirect();

    $businessDate = $f['transaction']->transaction_date->toDateString();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/invoices", [
        'customer_id' => $f['customer']->id,
        'invoice_date' => $businessDate,
        'currency' => $f['company']->base_currency,
        'line_items' => [
            ['description' => 'Late invoice', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0],
        ],
    ]);

    $response->assertSessionHasErrors();
});
