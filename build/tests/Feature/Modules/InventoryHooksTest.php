<?php

use App\Actions\Company\UpdateModulesAction;
use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Listeners\SyncOpeningStockLedger;
use App\Modules\Inventory\Events\OpeningStockChanged;
use App\Modules\Inventory\Extensions\ItemAccountDefaults;
use App\Modules\Inventory\Extensions\ItemPriceHistory;
use App\Modules\Inventory\Extensions\StockReferenceLinks;
use App\Modules\Inventory\Services\OpeningStockService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

function inventoryHooksCompany(array $settings = []): Company
{
    $user = User::factory()->create();

    return Company::create([
        'name' => 'Inv Hooks Co', 'slug' => 'inv-hooks-'.str()->random(8), 'owner_id' => $user->id,
        'base_currency' => 'PKR', 'settings' => $settings,
    ]);
}

test('syncing opening stock dispatches OpeningStockChanged once, quietly', function () {
    Event::fake([OpeningStockChanged::class]);
    $company = inventoryHooksCompany();

    app(OpeningStockService::class)->syncLedger($company->id, null);

    Event::assertDispatchedTimes(OpeningStockChanged::class, 1);
    Event::assertDispatched(OpeningStockChanged::class, fn ($e) => $e->companyId === $company->id && $e->quiet === true);
});

test('fuel registers its inventory hooks and opening stock listener', function () {
    expect(array_keys(ItemAccountDefaults::hooks()))->toContain('fuel_station')
        ->and(array_keys(StockReferenceLinks::hooks()))->toContain('fuel_station')
        ->and(app(ItemPriceHistory::class)->correctionTypes())->toContain('fuel_lubricant_cost')
        ->and(Event::hasListeners(OpeningStockChanged::class))->toBeTrue();
    expect(class_exists(SyncOpeningStockLedger::class))->toBeTrue();
});

test('inventory cannot be disabled while the fuel station module is on, with the same message', function () {
    $company = inventoryHooksCompany(['modules' => ['fuel_station' => true]]);
    app(CurrentCompany::class)->set($company);

    try {
        app(UpdateModulesAction::class)->handle(['inventory' => false, 'payroll' => true]);
        $this->fail('expected a validation error');
    } catch (ValidationException $e) {
        expect($e->errors()['inventory'][0])->toBe('Inventory cannot be disabled while the Fuel Station module is enabled.');
    }
});

test('a general company can disable inventory', function () {
    $company = inventoryHooksCompany();
    app(CurrentCompany::class)->set($company);

    $result = app(UpdateModulesAction::class)->handle(['inventory' => false, 'payroll' => true]);

    expect($result['data']['modules']['inventory'])->toBeFalse();
});

test('core inventory and payroll no longer name the fuel module classes', function () {
    $hits = shell_exec('grep -rlE "Modules.(FuelStation|Umrah)" '.base_path('modules/Inventory').' '.base_path('modules/Payroll').' '.base_path('app/Actions').' --include=*.php');
    expect(trim((string) $hits))->toBe('');
});
