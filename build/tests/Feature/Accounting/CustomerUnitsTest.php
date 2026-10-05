<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerUnit;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * A customer's own vehicles, sites, rooms or departments, picked on a sale instead of typed
 * free-hand into the reference field. See CustomerUnitController and CreateAction::execute.
 */
function customerUnitsFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Customer Units Co',
        'slug' => 'customer-units-'.str()->lower(str()->random(8)),
        'owner_id' => $owner->id,
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $owner->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $ar = Account::create([
        'company_id' => $company->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset',
        'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id, 'customer_number' => 'CUST-0001', 'name' => 'Transport Co',
        'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true, 'created_by_user_id' => $owner->id,
    ]);

    return compact('owner', 'company', 'ar', 'customer');
}

test('a unit can be added and renamed from the customer page', function () {
    $f = customerUnitsFixture();

    $response = $this->actingAs($f['owner'])
        ->post("/{$f['company']->slug}/customers/{$f['customer']->id}/units", ['name' => 'GAL-1804']);
    $response->assertRedirect();

    $unit = CustomerUnit::where('company_id', $f['company']->id)->where('customer_id', $f['customer']->id)->firstOrFail();
    expect($unit->name)->toBe('GAL-1804')->and($unit->is_active)->toBeTrue();

    $response = $this->actingAs($f['owner'])
        ->patch("/{$f['company']->slug}/customers/{$f['customer']->id}/units/{$unit->id}", ['name' => 'TLF-866']);
    $response->assertRedirect();

    expect($unit->fresh()->name)->toBe('TLF-866');
});

test('a unit can be deactivated without changing its name', function () {
    $f = customerUnitsFixture();
    $unit = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GAL-1804']);

    $this->actingAs($f['owner'])
        ->patch("/{$f['company']->slug}/customers/{$f['customer']->id}/units/{$unit->id}", ['name' => 'GAL-1804', 'is_active' => false])
        ->assertRedirect();

    expect($unit->fresh()->is_active)->toBeFalse();
});

test('invoice.create with a unit_id sets it on the invoice and leaves the reference for the slip number', function () {
    $f = customerUnitsFixture();
    $unit = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GAL-1804']);

    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id, 'currency' => 'PKR', 'date' => '2026-09-25', 'draft' => true,
        'unit_id' => $unit->id,
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
    ], $f['owner'], true));

    $invoice = Invoice::findOrFail($result['data']['id']);
    expect($invoice->unit_id)->toBe($unit->id)
        ->and($invoice->reference)->toBeNull();
});

test('a given reference is kept over the unit name when both are sent', function () {
    $f = customerUnitsFixture();
    $unit = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GAL-1804']);

    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id, 'currency' => 'PKR', 'date' => '2026-09-25', 'draft' => true,
        'unit_id' => $unit->id, 'reference' => 'Slip 42',
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
    ], $f['owner'], true));

    $invoice = Invoice::findOrFail($result['data']['id']);
    expect($invoice->unit_id)->toBe($unit->id)->and($invoice->reference)->toBe('Slip 42');
});

test('a unit belonging to another customer is refused', function () {
    $f = customerUnitsFixture();
    $otherCustomer = Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'CUST-0002', 'name' => 'Other Buyer',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true, 'created_by_user_id' => $f['owner']->id,
    ]);
    $foreignUnit = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $otherCustomer->id, 'name' => 'TLF-866']);

    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id, 'currency' => 'PKR', 'date' => '2026-09-25', 'draft' => true,
        'unit_id' => $foreignUnit->id,
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
    ], $f['owner'], true)))->toThrow(\Illuminate\Validation\ValidationException::class);
});
