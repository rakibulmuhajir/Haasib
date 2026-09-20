<?php

/**
 * A guard, not a feature test.
 *
 * Laravel's exists/unique rules split a dotted table name on its FIRST dot into
 * connection + table. config/database.php defines connections literally named acct, inv,
 * tax, pay, fuel and auth — so `Rule::exists('acct.accounts', 'id')` does not mean "the
 * accounts table in the acct schema", it means "the accounts table on the acct
 * CONNECTION": a second Postgres session carrying neither the request's RLS context nor
 * its open transaction. Under enforced RLS that session sees nothing, so the rule rejects
 * every value it is given.
 *
 * This has now bitten the codebase three times: invoices and bills (fixed, see
 * tests/Feature/Accounting/ExistsRuleConnectionRoutingTest.php), then the expense form,
 * which silently rejected every account for as long as it existed because nothing tested
 * the HTTP route. Naming the MODEL routes the query through the model's own connection and
 * table, which is always what was meant.
 *
 * A scan rather than one test per request: there are dozens of these rules and the failure
 * is textual, so the cheap comprehensive check beats thirty expensive specific ones.
 */

/** Connection names in config/database.php that also read as schema prefixes. */
const AMBIGUOUS_PREFIXES = ['acct', 'auth', 'inv', 'pay', 'tax', 'fuel', 'hsp', 'crm', 'umrah'];

/**
 * Plain SPL rather than the File facade and base_path(): tests/Unit does not boot the
 * application container (see tests/Pest.php, which binds TestCase to Feature only), so a
 * facade here dies with "Target class [files] does not exist".
 */
function validationRuleRoot(): string
{
    return dirname(__DIR__, 2);
}

function validationRuleFiles(): array
{
    $root = validationRuleRoot();
    $files = [];

    foreach (['app', 'modules'] as $directory) {
        $path = $root.DIRECTORY_SEPARATOR.$directory;
        if (! is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

test('no validation rule names a schema-qualified table, which Laravel reads as a connection', function () {
    $prefixes = implode('|', AMBIGUOUS_PREFIXES);
    $patterns = [
        // Rule::exists('acct.accounts', …) / Rule::unique('tax.tax_rates', …)
        '/Rule::(?:exists|unique)\(\s*[\'"](?:'.$prefixes.')\./',
        // 'exists:acct.accounts,id' / '…|unique:tax.tax_rates,name'
        '/(?:exists|unique):(?:'.$prefixes.')\./',
    ];

    $offenders = [];
    foreach (validationRuleFiles() as $path) {
        $contents = file_get_contents($path);
        foreach (explode("\n", $contents) as $number => $line) {
            // Commented-out examples are explanation, not behaviour.
            if (preg_match('/^\s*(\/\/|\*)/', $line)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $relative = str_replace(validationRuleRoot().DIRECTORY_SEPARATOR, '', $path);
                    $offenders[] = $relative.':'.($number + 1).'  '.trim($line);
                    break;
                }
            }
        }
    }

    expect($offenders)->toBe([], "These rules run on a separate connection with no RLS context, so they reject every value.\n"
        ."Name the model instead — Rule::exists(Account::class, 'id').\n\n".implode("\n", $offenders));
});
