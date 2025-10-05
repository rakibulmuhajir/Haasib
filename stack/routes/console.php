<?php

use App\Jobs\SyncExchangeRates;
use Database\Seeders\Core\CoreCountriesSeeder;
use Database\Seeders\Core\CoreCurrenciesSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Publish OpenAPI YAML into L5-Swagger docs (JSON + YAML)
Artisan::command('openapi:publish', function () {
    $yamlPath = base_path('docs/openapi/invoicing.yaml');
    if (! file_exists($yamlPath)) {
        // Fallback when running within nested app/ folder
        $alt = base_path('../docs/openapi/invoicing.yaml');
        if (file_exists($alt)) {
            $yamlPath = realpath($alt);
        }
    }
    $docsDir = storage_path('api-docs');
    if (! file_exists($yamlPath)) {
        $this->error("YAML not found: {$yamlPath}");

        return 1;
    }
    if (! is_dir($docsDir)) {
        mkdir($docsDir, 0775, true);
    }
    try {
        $yaml = \Symfony\Component\Yaml\Yaml::parseFile($yamlPath);
        $json = json_encode($yaml, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($docsDir.'/api-docs.json', $json);
        copy($yamlPath, $docsDir.'/api-docs.yaml');
        $this->info('OpenAPI published to storage/api-docs (api-docs.json, api-docs.yaml)');

        return 0;
    } catch (\Throwable $e) {
        $this->error('Failed to publish OpenAPI: '.$e->getMessage());

        return 2;
    }
})->purpose('Publish docs/openapi/*.yaml for L5-Swagger UI');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Seed core reference data (currencies, countries) into core.* schema
Artisan::command('core:seed-reference', function () {
    try {
        (new CoreCurrenciesSeeder)->run();
        (new CoreCountriesSeeder)->run();
        $this->info('Core reference data seeded (currencies, countries).');

        return 0;
    } catch (\Throwable $e) {
        $this->error('Failed seeding core reference: '.$e->getMessage());

        return 1;
    }
})->purpose('Seed ISO currencies and countries into core schema');

// Trigger FX sync via job
Artisan::command('fx:sync {provider=ecb}', function (string $provider) {
    SyncExchangeRates::dispatchSync($provider);
    $this->info("FX sync dispatched synchronously using provider: {$provider}");
})->purpose('Sync exchange rates from external provider');

// Module creation command
Artisan::command('module:make {name : The name of the module}', function () {
    $moduleName = $this->argument('name');
    $modulePath = app_path("../modules/{$moduleName}");

    if (is_dir($modulePath)) {
        $this->error("Module {$moduleName} already exists!");
        return 1;
    }

    // Create module directory structure
    $directories = [
        'Domain/Models',
        'Domain/Actions',
        'Domain/Events',
        'Domain/Listeners',
        'Domain/Exceptions',
        'Domain/ValueObjects',
        'CLI/Commands',
        'CLI/Console',
        'Http/Controllers',
        'Http/Requests',
        'Http/Resources',
        'Http/Middleware',
        'Database/migrations',
        'Database/seeders',
        'Providers',
        'Routes',
        'Resources/js',
        'Resources/css',
        'Tests/Unit',
        'Tests/Feature',
    ];

    $this->info("Creating module: {$moduleName}");

    // Create directories
    foreach ($directories as $directory) {
        $path = "{$modulePath}/{$directory}";
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $this->line("Created directory: {$path}");
    }

    // Create ModuleServiceProvider
    $providerTemplate = file_get_contents(__DIR__.'/../Console/stubs/module-provider.stub');
    $providerContent = str_replace([
        '{{ModuleName}}',
        '{{moduleName}}',
        '{{ModuleNamespace}}'
    ], [
        $moduleName,
        strtolower($moduleName),
        "Modules\\{$moduleName}"
    ], $providerTemplate);
    file_put_contents("{$modulePath}/Providers/{$moduleName}ServiceProvider.php", $providerContent);
    $this->line("Created: {$modulePath}/Providers/{$moduleName}ServiceProvider.php");

    // Create module.json manifest
    $manifest = [
        'name' => strtolower($moduleName),
        'description' => "{$moduleName} module",
        'version' => '1.0.0',
        'provider' => "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider",
        'aliases' => [],
        'dependencies' => [],
        'enabled' => true,
    ];
    file_put_contents("{$modulePath}/module.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->line("Created: {$modulePath}/module.json");

    // Create base routes files
    $apiRoutes = "<?php\n\n// {$moduleName} API routes\nRoute::prefix('api/".strtolower($moduleName)."')->group(function () {\n    // Add your API routes here\n});\n";
    file_put_contents("{$modulePath}/Routes/api.php", $apiRoutes);
    $this->line("Created: {$modulePath}/Routes/api.php");

    $webRoutes = "<?php\n\n// {$moduleName} Web routes\n";
    file_put_contents("{$modulePath}/Routes/web.php", $webRoutes);
    $this->line("Created: {$modulePath}/Routes/web.php");

    // Create README.md
    $readme = "# {$moduleName} Module\n\nThis module handles {$moduleName} functionality.\n";
    file_put_contents("{$modulePath}/README.md", $readme);
    $this->line("Created: {$modulePath}/README.md");

    $this->info("Module {$moduleName} created successfully!");
    $this->info("Don't forget to register the provider in config/modules.php");
})->purpose('Create a new custom module');
