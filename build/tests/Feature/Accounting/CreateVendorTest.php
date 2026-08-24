<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/*
 * Vendor creation answered HTTP 500 on production with
 *
 *   Command handler class not found:
 *   App\Modules\Accounting\Actions\Vendor\CreateAction
 *
 * because config/command-bus.php mapped all five vendor commands into a
 * directory that was never written. Nothing exercised the route, so the
 * failure only appeared when someone submitted the form.
 *
 * These go through the real HTTP route rather than dispatching the action
 * directly: the missing class sat between the controller and the model, and a
 * test that called the action would have skipped exactly the piece that was
 * broken.
 */
function vendorTestCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Vendor Create Test '.str()->random(8),
        'slug' => 'vendor-create-test-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return [$owner, $company];
}

test('a vendor can be created through the form', function () {
    [$owner, $company] = vendorTestCompany();

    $response = $this->actingAs($owner)->post("/{$company->slug}/vendors", [
        'name' => 'QA Ticket Supplier 2026',
        'email' => 'supplier@example.test',
        'phone' => '03001234567',
        'vendor_type' => Vendor::TYPE_GENERAL,
        'payment_terms' => 15,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $vendor = Vendor::where('company_id', $company->id)->first();

    expect($vendor)->not->toBeNull()
        ->and($vendor->name)->toBe('QA Ticket Supplier 2026')
        ->and($vendor->email)->toBe('supplier@example.test')
        ->and($vendor->phone)->toBe('03001234567')
        ->and($vendor->payment_terms)->toBe(15)
        ->and($vendor->base_currency)->toBe('PKR')
        ->and($vendor->is_active)->toBeTrue();
});

test('the first vendor is numbered and the next one follows it', function () {
    [$owner, $company] = vendorTestCompany();

    $this->actingAs($owner)->post("/{$company->slug}/vendors", ['name' => 'First Supplier']);
    $this->actingAs($owner)->post("/{$company->slug}/vendors", ['name' => 'Second Supplier']);

    $numbers = Vendor::where('company_id', $company->id)
        ->orderBy('vendor_number')
        ->pluck('vendor_number')
        ->all();

    expect($numbers)->toBe(['VEND-00001', 'VEND-00002']);
});

test('every field the form collects survives the command bus', function () {
    // The bus replaces its params with Validator::validate()'s output, which
    // returns only the keys the handler's rules() names. A field the handler
    // forgot is not rejected -- it is dropped in silence, and the vendor saves
    // without it. This is the regression that would be invisible on screen.
    [$owner, $company] = vendorTestCompany();

    $this->actingAs($owner)->post("/{$company->slug}/vendors", [
        'name' => 'Full Supplier',
        'email' => 'full@example.test',
        'phone' => '03007654321',
        'vendor_type' => Vendor::TYPE_SERVICE_PROVIDER,
        'address' => [
            'street' => '12 Jinnah Road',
            'city' => 'Karachi',
            'country' => 'PK',
        ],
        'tax_id' => 'NTN-99887',
        'payment_terms' => 45,
        'account_number' => 'ACCT-5150',
        'notes' => 'Ticketing supplier for QA.',
        'website' => 'https://example.test',
    ])->assertSessionHasNoErrors();

    $vendor = Vendor::where('company_id', $company->id)->firstOrFail();

    expect($vendor->vendor_type)->toBe(Vendor::TYPE_SERVICE_PROVIDER)
        ->and($vendor->tax_id)->toBe('NTN-99887')
        ->and($vendor->account_number)->toBe('ACCT-5150')
        ->and($vendor->notes)->toBe('Ticketing supplier for QA.')
        ->and($vendor->website)->toBe('https://example.test')
        ->and($vendor->address)->toMatchArray([
            'street' => '12 Jinnah Road',
            'city' => 'Karachi',
            'country' => 'PK',
        ]);
});

test('a second vendor cannot reuse an existing email', function () {
    [$owner, $company] = vendorTestCompany();

    $this->actingAs($owner)->post("/{$company->slug}/vendors", [
        'name' => 'Original Supplier',
        'email' => 'duplicate@example.test',
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->post("/{$company->slug}/vendors", [
        'name' => 'Copycat Supplier',
        'email' => 'duplicate@example.test',
    ])->assertSessionHasErrors('email');

    expect(Vendor::where('company_id', $company->id)->count())->toBe(1);
});

test('the quick-add form creates a vendor too', function () {
    // quickStore reads $result['data']['id'] straight back out of the bus
    // response, so a handler that returned no id would fail there rather than
    // in store().
    [$owner, $company] = vendorTestCompany();

    $this->actingAs($owner)->post("/{$company->slug}/vendors/quick-store", [
        'name' => 'Quick Supplier',
    ])->assertSessionHasNoErrors();

    expect(Vendor::where('company_id', $company->id)->where('name', 'Quick Supplier')->exists())->toBeTrue();
});
