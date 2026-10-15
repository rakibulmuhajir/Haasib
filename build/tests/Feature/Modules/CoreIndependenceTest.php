<?php

/*
 * Core (Accounting, Inventory, Payroll, Tax and the app layer) must not know about
 * industry modules: modules plug in through ModuleRegistry, the Accounting/Inventory
 * extension points and events. These two checks keep it that way.
 */

arch('core never imports industry module classes')
    ->expect(['App\Modules\Accounting', 'App\Modules\Inventory', 'App\Modules\Payroll', 'App\Modules\Tax', 'App\Http', 'App\Services', 'App\Models', 'App\Actions', 'App\Support', 'App\Console'])
    ->not->toUse(['App\Modules\FuelStation', 'App\Modules\Umrah']);

/** String literals (comments excluded) in core PHP that name an industry module. */
function coreIndustryLiterals(): array
{
    $roots = ['modules/Accounting', 'modules/Inventory', 'modules/Payroll', 'modules/Tax', 'app'];
    $pattern = '/fuel_station|amanat|^[\'"](umrah|travel)[\'"]$/i';
    $hits = [];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            // Migrations are history: they describe the schema as it was built.
            if ($file->getExtension() !== 'php' || str_contains($path, '/Database/Migrations/')) {
                continue;
            }
            foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && preg_match($pattern, $token[1])) {
                    $hits[$path][] = $token[1];
                }
            }
        }
    }

    return $hits;
}

test('core PHP names no industry module outside the allowlist', function () {
    // Each entry stays on purpose.
    $allow = [
        'app/Constants/Permissions.php',                          // permission strings, synced to the DB
        'app/Support/Database/SchemaAwarePresenceVerifier.php',   // list of database schemas
        'modules/Accounting/Models/Vendor.php',                   // vendor types saved on existing rows
        'modules/Inventory/Http/Controllers/WarehouseController.php', // tank items only listed for fuel
    ];

    expect(array_keys(array_diff_key(coreIndustryLiterals(), array_flip($allow))))->toBe([]);
});
