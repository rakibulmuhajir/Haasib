<?php

/**
 * Guards against a whole family of silent blank pages: a page that renders blank in
 * production because the string passed to Inertia::render() differs in case from the
 * actual .vue file path on disk. Windows and macOS default to case-insensitive
 * filesystems, so this is invisible in local dev and even in `npm run build` there --
 * it only surfaces on the case-sensitive filesystem Linux CI/production run on. One of
 * the last seven production bugs was exactly this.
 *
 * This test never uses file_exists()/glob() to check a candidate path, because both
 * silently succeed on a case-insensitive filesystem even when the case is wrong. Instead
 * it walks the real page directories once with scandir(), records the exact on-disk
 * spelling of every .vue file, and compares each Inertia::render() name against that list
 * with a byte-for-byte (case-sensitive) string comparison -- reproducing what Linux would
 * actually see, regardless of which OS this test happens to run on.
 *
 * The candidate resolution below mirrors resources/js/app.ts::resolvePage(): local pages
 * root, the "accounting/" stripped variant, the leaf-folder "<folder>/Index" shortcut, and
 * module-slug-prefixed names (e.g. "inventory/categories/Create").
 */

function collectVuePagePaths(string $root): array
{
    $paths = [];
    if (! is_dir($root)) {
        return $paths;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'vue') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $paths[] = substr($relative, 0, -4); // strip ".vue", keep exact on-disk case
    }

    sort($paths);

    return $paths;
}

function inertiaRenderNames(): array
{
    $names = [];
    $roots = [base_path('app'), base_path('modules')];

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (! str_contains($contents, 'Inertia::render(')) {
                continue;
            }
            if (preg_match_all("/Inertia::render\\(\\s*['\"]([^'\"]+)['\"]/", $contents, $matches)) {
                foreach ($matches[1] as $name) {
                    $names[$name] = $file->getPathname();
                }
            }
        }
    }

    return $names;
}

function moduleSlugMap(string $modulesRoot): array
{
    $map = [];
    foreach (glob($modulesRoot.'/*', GLOB_ONLYDIR) as $moduleDir) {
        $moduleName = basename($moduleDir);
        $map[strtolower($moduleName)] = $moduleName;
    }

    return $map;
}

/**
 * Case-sensitive candidate resolution mirroring resources/js/app.ts::resolvePage().
 * Returns true if ANY candidate exactly matches a real on-disk page (local or module).
 */
function inertiaNameResolvesCaseSensitively(
    string $name,
    array $localPages,
    array $modulePagesByModule,
    array $moduleSlugs,
): bool {
    $normalized = ltrim($name, '/');

    // Local pages root: resources/js/pages/{normalized}.vue
    if (in_array($normalized, $localPages, true)) {
        return true;
    }

    $stripped = preg_replace('#^accounting/#', '', $normalized);

    $candidates = array_unique([$normalized, $stripped]);

    $parts = explode('/', $stripped);
    if (count($parts) > 1 && end($parts) === 'Index') {
        $folder = $parts[count($parts) - 2];
        $candidates[] = "{$folder}/Index";
    }

    $moduleAndRest = explode('/', $stripped, 2);
    if (count($moduleAndRest) === 2 && isset($moduleSlugs[strtolower($moduleAndRest[0])])) {
        $candidates[] = $moduleAndRest[1];
    }

    foreach (array_unique($candidates) as $candidate) {
        // A module page glob key ends with "/{candidate}.vue" in the real resolver, i.e.
        // any module's page tree may contain it at that relative path.
        foreach ($modulePagesByModule as $pages) {
            if (in_array($candidate, $pages, true)) {
                return true;
            }
        }

        // Module-slug-prefixed candidate: "inventory/categories/Create" -> module
        // "Inventory", rest "categories/Create".
        $slugAndRest = explode('/', $candidate, 2);
        if (count($slugAndRest) === 2) {
            $moduleName = $moduleSlugs[strtolower($slugAndRest[0])] ?? null;
            if ($moduleName !== null && isset($modulePagesByModule[$moduleName])
                && in_array($slugAndRest[1], $modulePagesByModule[$moduleName], true)) {
                return true;
            }
        }
    }

    return false;
}

test('every Inertia::render page name resolves to a real file, case-sensitively', function () {
    $modulesRoot = base_path('modules');
    $localPages = collectVuePagePaths(base_path('resources/js/pages'));

    $modulePagesByModule = [];
    foreach (glob($modulesRoot.'/*', GLOB_ONLYDIR) as $moduleDir) {
        $moduleName = basename($moduleDir);
        $pagesRoot = $moduleDir.'/Resources/js/pages';
        $modulePagesByModule[$moduleName] = collectVuePagePaths($pagesRoot);
    }

    $moduleSlugs = moduleSlugMap($modulesRoot);

    $renderCalls = inertiaRenderNames();
    expect($renderCalls)->not->toBeEmpty();

    // Pages this guard found already broken on the day it was written: the controller
    // renders them but no .vue file exists, so the route answers with a blank screen.
    // They are real bugs, quarantined here so the guard can still fail on anything NEW
    // rather than being disabled outright. Delete an entry as its page is written.
    $knownMissingPages = [
        'inventory/stock/ItemStock',
        'Payroll/DeductionTypes/Edit',
        'Payroll/EarningTypes/Edit',
        'Payroll/LeaveRequests/Show',
        'Payroll/LeaveRequests/Edit',
        'Payroll/LeaveTypes/Edit',
        'Payroll/Payslips/Edit',
    ];

    $unresolved = [];
    $stillMissing = [];
    foreach ($renderCalls as $name => $sourceFile) {
        if (inertiaNameResolvesCaseSensitively($name, $localPages, $modulePagesByModule, $moduleSlugs)) {
            continue;
        }

        if (in_array($name, $knownMissingPages, true)) {
            $stillMissing[] = $name;

            continue;
        }

        $unresolved[] = "{$name} (from {$sourceFile})";
    }

    expect($unresolved)->toBe([]);

    // Once a quarantined page is written, its entry has to go: a stale allowlist would
    // quietly re-admit that page name if it ever broke again.
    expect(array_values(array_diff($knownMissingPages, $stillMissing)))
        ->toBe([], 'These pages now exist -- remove them from $knownMissingPages.');
});
