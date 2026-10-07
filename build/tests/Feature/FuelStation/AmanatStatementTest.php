<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Services\AmanatStatementService;
use Illuminate\Support\Facades\DB;

/**
 * An Amanat holder's statement reads their deposits and payouts, not their customer ledger --
 * the Customer statement showed only the opening for them.
 */
test('a holder statement runs deposits in and payouts out from the opening', function () {
    $company = Company::create(['name' => 'Amanat Stmt Co', 'slug' => 'amst-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $holder = Customer::create(['company_id' => $company->id, 'customer_number' => 'AM-1', 'name' => 'Holder', 'base_currency' => 'PKR']);

    $move = function (string $type, float $amount, string $day) use ($company, $holder) {
        $row = AmanatTransaction::create([
            'company_id' => $company->id, 'customer_id' => $holder->id,
            'transaction_type' => $type, 'amount' => $amount,
        ]);
        DB::table('fuel.amanat_transactions')->where('id', $row->id)->update(['created_at' => "{$day} 10:00:00"]);
    };
    $move(AmanatTransaction::TYPE_DEPOSIT, 10000, '2026-08-20');   // before the range: the opening
    $move(AmanatTransaction::TYPE_WITHDRAWAL, 3000, '2026-09-05');
    $move(AmanatTransaction::TYPE_DEPOSIT, 5000, '2026-09-05');    // same day: listed before the payout
    $move(AmanatTransaction::TYPE_FUEL_PURCHASE, 1500, '2026-09-10');

    $s = app(AmanatStatementService::class)->statement($holder, '2026-09-01', '2026-09-30');
    $traced = app(AmanatStatementService::class)->statement($holder, '2026-09-01', '2026-09-30', true);
    $graph = $traced['valueTrail'];
    unset($traced['valueTrail']);
    expect($traced)->toBe($s);
    foreach ($graph['nodes'] as $node) {
        if ($node['children']) {
            expect(round(collect($node['children'])->sum(fn ($id) => $graph['nodes'][$id]['value']), 2))->toBe($node['value']);
        }
    }

    expect($s['opening_balance'])->toBe(10000.0)
        ->and($s['closing_balance'])->toBe(10500.0)
        ->and(collect($s['rows'])->pluck('type')->all())
        ->toBe(['opening_balance', 'deposit', 'withdrawal', 'fuel_purchase', 'closing_balance'])
        ->and(collect($s['rows'])->pluck('balance')->all())
        ->toBe([10000.0, 15000.0, 12000.0, 10500.0, 10500.0]);
});
