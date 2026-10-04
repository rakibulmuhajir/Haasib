<?php

use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\CustomerPeriodSummaryService;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';
require_once __DIR__.'/CustomerFuelDiscountFixtures.php';

/**
 * A customer with one invoice before the range (the opening), three discounted fuel invoices
 * inside it and one payment, all made through FuelSaleService like the Fuel -> Sales form.
 */
function periodSummaryFixture(): array
{
    $f = discountedCustomerFixture();
    $company = $f['company'];
    $user = $f['user'];

    RateChange::create(['company_id' => $company->id, 'item_id' => $f['petrol']->id, 'effective_date' => '2026-09-01', 'purchase_rate' => 240, 'sale_rate' => 280]);
    CustomerFuelDiscount::create(['company_id' => $company->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);

    $sell = fn (array $row) => app(FuelSaleService::class)->createSale($row + ['sale_type' => 'credit', 'customer_id' => $f['customer']->id]);

    $sell(['item_id' => $f['diesel']->id, 'quantity' => 40, 'sale_date' => '2026-09-05']);                              // opening: 12,000 - 120
    $sell(['item_id' => $f['diesel']->id, 'quantity' => 100, 'sale_date' => '2026-09-12']);                             // 30,000 - 300 (stored 3/L)
    $sell(['item_id' => $f['diesel']->id, 'quantity' => 200, 'sale_date' => '2026-09-20', 'discount_percent' => 5]);    // 60,000 - 3,000
    $sell(['item_id' => $f['petrol']->id, 'quantity' => 50, 'sale_date' => '2026-09-22', 'discount_percent' => 10]);    // 14,000 - 1,400

    Payment::create([
        'company_id' => $company->id, 'customer_id' => $f['customer']->id, 'payment_number' => 'PAY-SUM-1',
        'payment_date' => '2026-09-25', 'amount' => 20000, 'currency' => 'PKR', 'base_currency' => 'PKR',
        'exchange_rate' => 1, 'base_amount' => 20000, 'payment_method' => 'cash',
    ]);

    return $f;
}

test('the period summary adds up per product and in money, and closes where the statement closes', function () {
    $f = periodSummaryFixture();
    $company = $f['company'];

    $summary = app(CustomerPeriodSummaryService::class)->run($company->id, $f['customer']->id, '2026-09-10', '2026-09-30', $company->slug);

    $byName = collect($summary['products'])->keyBy('name');
    expect($byName['Diesel'])->toMatchArray(['quantity' => 300.0, 'gross' => 90000.0, 'discount' => 3300.0, 'net' => 86700.0])
        ->and($byName['Petrol'])->toMatchArray(['quantity' => 50.0, 'gross' => 14000.0, 'discount' => 1400.0, 'net' => 12600.0])
        ->and($summary['totals'])->toBe(['gross' => 104000.0, 'discount' => 4700.0, 'net' => 99300.0]);

    $money = $summary['money'];
    expect($money['opening'])->toBe(11880.0)
        ->and($money['bought'])->toBe(99300.0)
        ->and($money['paid'])->toBe(20000.0)
        ->and($money['payment_count'])->toBe(1)
        ->and($money['other'])->toBe(0.0)
        ->and($money['closing'])->toBe(91180.0);

    $statement = app(CustomerStatementService::class)->statement($f['customer'], '2026-09-10', '2026-09-30');
    expect($money['closing'])->toBe((float) $statement['closing_balance'])
        ->and($money['opening'])->toBe((float) $statement['opening_balance']);
});

test('a credit note shows as Other so the summary still reconciles', function () {
    $f = periodSummaryFixture();
    $company = $f['company'];

    DB::table('acct.credit_notes')->insert([
        'id' => (string) str()->uuid(), 'company_id' => $company->id, 'customer_id' => $f['customer']->id,
        'credit_note_number' => 'CN-SUM-1', 'reason' => 'Correction', 'credit_date' => '2026-09-28', 'amount' => 500, 'status' => 'issued',
        'currency' => 'PKR', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $money = app(CustomerPeriodSummaryService::class)->run($company->id, $f['customer']->id, '2026-09-10', '2026-09-30', $company->slug)['money'];

    expect($money['other'])->toBe(-500.0)
        ->and(round($money['opening'] + $money['bought'] - $money['paid'] + $money['other'], 2))->toBe($money['closing']);
});

test('the customer page carries the summary for the asked range', function () {
    $f = periodSummaryFixture();
    $company = $f['company'];
    $user = $f['user'];

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner', 'joined_at' => now(),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);
    $company->enableModule('fuel_station');

    $response = $this->actingAs($user)->get("/{$company->slug}/fuel/credit-customers/{$f['customer']->id}?from=2026-09-10&to=2026-09-30");
    $response->assertOk();
    $summary = $response->viewData('page')['props']['summary'];

    expect($summary['from'])->toBe('2026-09-10')
        ->and($summary['to'])->toBe('2026-09-30')
        ->and($summary['money']['closing'])->toBe(91180.0)
        ->and($summary['products'])->toHaveCount(2);

    $this->actingAs($user)->get("/{$company->slug}/fuel/credit-customers/{$f['customer']->id}?from=2026-09-30&to=2026-09-10")
        ->assertSessionHasErrors('to');
});
