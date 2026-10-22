<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\PaletteCatalog;
use Illuminate\Support\Facades\DB;

/** A command pointing at a real CommandBus action, so its permission is the real one. */
class FakePaletteCommand extends PaletteCommand
{
    public function __construct(private readonly string $word, private readonly string $target = 'vendor.create', private readonly bool $broken = false) {}

    public function name(): string
    {
        return $this->word;
    }

    public function label(): string
    {
        return 'Fake '.$this->word;
    }

    public function sentence(): string
    {
        return $this->word.' {name} {note?}';
    }

    public function slots(): array
    {
        if ($this->broken) {
            throw new RuntimeException('bad declaration');
        }

        return ['name' => Slot::text(), 'note' => Slot::text()];
    }

    public function target(): string
    {
        return $this->target;
    }
}

class FakePaletteManifest extends ModuleManifest
{
    public static bool $enabled = true;

    public static array $commands = [];

    public function key(): string
    {
        return 'palette_fake';
    }

    public function isEnabledFor(Company $company): bool
    {
        return self::$enabled;
    }

    public function paletteCommands(): array
    {
        return self::$commands;
    }
}

/** A company with an owner and a second member holding $otherRole. */
function registryFixture(string $otherRole = 'operations'): array
{
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $company = Company::create([
        'name' => 'Registry Test',
        'slug' => 'registry-'.str()->lower(str()->random(8)),
        'owner_id' => $owner->id,
        'base_currency' => 'PKR',
    ]);
    enterCompany($company);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    foreach ([[$owner, 'owner'], [$other, $otherRole]] as [$u, $role]) {
        DB::table('auth.company_user')->insert([
            'company_id' => $company->id, 'user_id' => $u->id, 'role' => $role,
            'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($u, $role));
    }

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    // Direct PaletteCatalog calls have no request middleware to set the company the permission check reads.
    app(\App\Services\CompanyContextService::class)->setContext($company);

    return compact('company', 'owner', 'other');
}

function sentenceNames(User $user, Company $company): array
{
    return array_column(app(PaletteCatalog::class)->payload($user, $company)['sentences'], 'name');
}

beforeEach(function () {
    FakePaletteManifest::$enabled = true;
    FakePaletteManifest::$commands = [new FakePaletteCommand('zing')];
    app(ModuleRegistry::class)->register(new FakePaletteManifest);
});

test('a command from an enabled module is listed', function () {
    $f = registryFixture();

    expect(sentenceNames($f['owner'], $f['company']))->toContain('zing');
});

test('the same module disabled for the company lists nothing', function () {
    $f = registryFixture();
    FakePaletteManifest::$enabled = false;

    expect(sentenceNames($f['owner'], $f['company']))->not->toContain('zing');
});

test('a user without the target permission does not see the command', function () {
    $f = registryFixture('operations');

    expect(sentenceNames($f['owner'], $f['company']))->toContain('zing')
        ->and(sentenceNames($f['other'], $f['company']))->not->toContain('zing');
});

test('a command whose slots throw is skipped and the others still list', function () {
    $f = registryFixture();
    FakePaletteManifest::$commands = [new FakePaletteCommand('bad', broken: true), new FakePaletteCommand('good')];

    $names = sentenceNames($f['owner'], $f['company']);

    expect($names)->toContain('good')->not->toContain('bad');
});

test('a command is described for the browser', function () {
    $f = registryFixture();
    $zing = collect(app(PaletteCatalog::class)->payload($f['owner'], $f['company'])['sentences'])->firstWhere('name', 'zing');

    expect($zing)->toBe([
        'name' => 'zing',
        'aliases' => [],
        'label' => 'Fake zing',
        'sentence' => 'zing {name} {note?}',
        'key' => 'vendor.create',
        'slots' => [
            ['name' => 'name', 'kind' => 'text', 'optional' => false],
            ['name' => 'note', 'kind' => 'text', 'optional' => true],
        ],
        'generic' => false,
        'form_url' => null,
        'query' => false,
        'opens' => null,
        'undo' => null,
        'previewable' => false,
    ]);
});

test('the payload still carries the CommandBus catalog', function () {
    $f = registryFixture();
    $payload = app(PaletteCatalog::class)->payload($f['owner'], $f['company']);

    expect(array_column($payload['commands'], 'key'))->toContain('vendor.create');
});
