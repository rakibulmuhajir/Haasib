<?php

use App\Models\Company;
use App\Models\User;
use App\Support\Modules\ModuleRegistry;

function moduleCompany(array $attrs): Company
{
    $user = User::factory()->create();

    return Company::create(array_merge([
        'name' => 'Mod Co', 'slug' => 'mod-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR',
    ], $attrs));
}

test('fuel and umrah register manifests', function () {
    $registry = app(ModuleRegistry::class);
    expect($registry->get('fuel_station'))->not->toBeNull()
        ->and($registry->get('umrah'))->not->toBeNull()
        ->and($registry->get('umrah')->industryCodes())->toBe(['umrah', 'travel']);
});

test('hasModule keeps the old OR semantics: flag, industry_code or industry', function () {
    expect(moduleCompany(['industry_code' => 'fuel_station'])->hasModule('fuel_station'))->toBeTrue()
        ->and(moduleCompany(['industry' => 'fuel_station'])->hasModule('fuel_station'))->toBeTrue()
        ->and(moduleCompany(['settings' => ['modules' => ['fuel_station' => true]]])->hasModule('fuel_station'))->toBeTrue()
        // An explicit false never overrode the industry before; it still doesn't.
        ->and(moduleCompany(['industry_code' => 'fuel_station', 'settings' => ['modules' => ['fuel_station' => false]]])->hasModule('fuel_station'))->toBeTrue()
        ->and(moduleCompany(['industry_code' => 'travel'])->hasModule('umrah'))->toBeTrue()
        ->and(moduleCompany(['industry_code' => 'other'])->hasModule('fuel_station'))->toBeFalse()
        ->and(moduleCompany(['industry_code' => 'other'])->hasModule('umrah'))->toBeFalse();
});

test('capabilities come from enabled manifests only', function () {
    expect(moduleCompany(['industry_code' => 'fuel_station'])->capable('daily_close'))->toBeTrue()
        ->and(moduleCompany(['industry_code' => 'other'])->capable('daily_close'))->toBeFalse();
});

test('a general company shares no enabled modules', function () {
    $company = moduleCompany(['industry_code' => 'other']);
    expect(array_keys(app(ModuleRegistry::class)->enabledFor($company)))->toBe([]);
});
