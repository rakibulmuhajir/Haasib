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
