<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/** A company with an owner and (optionally) a second member holding $otherRole. */
function paletteFixture(bool $fuelStation, ?string $otherRole = null): array
{
    $owner = User::factory()->create();
    $company = Company::create([
        'name' => 'Palette Test',
        'slug' => 'palette-'.str()->lower(str()->random(8)),
        'owner_id' => $owner->id,
        'base_currency' => 'PKR',
    ]);
    enterCompany($company);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    $other = $otherRole ? User::factory()->create() : null;
    $members = [[$owner, 'owner']];
    if ($other) {
        $members[] = [$other, $otherRole];
    }

    foreach ($members as [$u, $role]) {
        DB::table('auth.company_user')->insert([
            'company_id' => $company->id, 'user_id' => $u->id, 'role' => $role,
            'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($u, $role));
    }

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    if ($fuelStation) {
        $company->enableModule('fuel_station');
    }

    return compact('company', 'owner', 'other');
}

function paletteCatalog($test, User $user, Company $company): array
{
    return $test->actingAs($user)->withHeader("X-Company-Slug", $company->slug)->getJson("/api/palette/catalog")->assertOk()->json('commands');
}

test('a general company catalog has core commands and no module commands', function () {
    $f = paletteFixture(false);
    $keys = array_column(paletteCatalog($this, $f['owner'], $f['company']), 'key');

    expect($keys)->toContain('vendor.create', 'invoice.create', 'payment.create');
    foreach ($keys as $k) {
        expect(str_starts_with($k, 'fuel.') || str_starts_with($k, 'calculator.'))->toBeFalse($k);
    }
});

test('a fuel company catalog includes the module commands', function () {
    $f = paletteFixture(true);
    $keys = array_column(paletteCatalog($this, $f['owner'], $f['company']), 'key');

    expect($keys)->toContain('fuel.amanat.movement', 'vendor.create');
});

test('a user without the permission does not see the command and execute still refuses it', function () {
    $f = paletteFixture(false, 'operations');
    $keys = array_column(paletteCatalog($this, $f['other'], $f['company']), 'key');
    expect($keys)->not->toContain('vendor.create');

    $this->actingAs($f["other"])->withHeader("X-Company-Slug", $f["company"]->slug)->postJson("/api/palette/execute", [
        'entity' => 'vendor', 'verb' => 'create', 'params' => ['name' => 'Nope'],
    ])->assertStatus(403);
});

test('entries carry the documented shape and array-input commands are forms', function () {
    $f = paletteFixture(false);
    $commands = collect(paletteCatalog($this, $f['owner'], $f['company']));
    $vendor = $commands->firstWhere('key', 'vendor.create');

    expect(array_keys($vendor))->toBe(['key', 'entity', 'verb', 'description', 'query', 'form', 'form_url', 'args', 'flags'])
        ->and($vendor['entity'])->toBe('vendor')->and($vendor['verb'])->toBe('create')
        ->and($vendor['description'])->toBe('Create Vendor')
        ->and($vendor['args'][0]['name'])->toBe('name')
        ->and($vendor['form'])->toBeFalse()
        ->and($commands->firstWhere('key', 'invoice.create')['form'])->toBeTrue()
        ->and($commands->firstWhere('key', 'vendor.list')['query'])->toBeTrue();
});
