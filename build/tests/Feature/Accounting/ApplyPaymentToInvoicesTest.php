<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../FuelStation/CreditCloseFixtures.php';

/**
 * A payment that left money on account can be applied to the customer's invoices afterwards,
 * from the payment's own page: only what it has on account, and no new money moves.
 */
test('what a payment left on account is applied to an invoice from the payment page', function () {
    $f = creditCloseFixture();
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert([
        'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
    app(\App\Services\CurrentCompany::class)->set($f['company']);

    creditClosePost($f); // a 6,000 credit-sale invoice for the customer
    $invoice = Invoice::where('company_id', $f['company']->id)->where('customer_id', $f['customer']->id)->firstOrFail();

    // 1,000 received: 1 put on the invoice, 999 left on account.
    $result = app(CommandBus::class)->dispatch('payment.create', [
        'customer_id' => $f['customer']->id, 'amount' => 1000, 'date' => '2026-09-15', 'method' => 'cash', 'deposit_account_id' => $f['accounts']['1050']->id,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 1]],
    ], $f['user']);
    $payment = Payment::findOrFail($result['data']['id']);
    $before = (float) $invoice->fresh()->balance;

    // More than is on account is refused.
    $this->post("/{$f['company']->slug}/payments/{$payment->id}/apply", ['lines' => [['invoice_id' => $invoice->id, 'amount' => 1000]]])
        ->assertSessionHasErrors('lines');

    $this->post("/{$f['company']->slug}/payments/{$payment->id}/apply", ['lines' => [['invoice_id' => $invoice->id, 'amount' => 999]]])
        ->assertSessionHasNoErrors();

    expect((float) $invoice->fresh()->balance)->toBe($before - 999);
});
