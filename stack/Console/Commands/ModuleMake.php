<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMake extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make {name : The name of the module}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new custom module';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $moduleName = $this->argument('name');
        $modulePath = base_path("modules/{$moduleName}");

        if (File::exists($modulePath)) {
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
            File::makeDirectory($path, 0755, true);
            $this->line("Created directory: {$path}");
        }

        // Create ModuleServiceProvider
        $providerContent = $this->getServiceProviderContent($moduleName);
        File::put("{$modulePath}/Providers/{$moduleName}ServiceProvider.php", $providerContent);
        $this->line("Created: {$modulePath}/Providers/{$moduleName}ServiceProvider.php");

        // Create module.json manifest
        $manifestContent = $this->getManifestContent($moduleName);
        File::put("{$modulePath}/module.json", $manifestContent);
        $this->line("Created: {$modulePath}/module.json");

        // Create base routes files
        $apiRoutesContent = $this->getApiRoutesContent($moduleName);
        File::put("{$modulePath}/Routes/api.php", $apiRoutesContent);
        $this->line("Created: {$modulePath}/Routes/api.php");

        $webRoutesContent = $this->getWebRoutesContent($moduleName);
        File::put("{$modulePath}/Routes/web.php", $webRoutesContent);
        $this->line("Created: {$modulePath}/Routes/web.php");

        // Create composer.json for module
        $composerContent = $this->getComposerContent($moduleName);
        File::put("{$modulePath}/composer.json", $composerContent);
        $this->line("Created: {$modulePath}/composer.json");

        // Create README.md
        $readmeContent = $this->getReadmeContent($moduleName);
        File::put("{$modulePath}/README.md", $readmeContent);
        $this->line("Created: {$modulePath}/README.md");

        // Create .gitkeep for empty directories
        foreach ($directories as $directory) {
            File::put("{$modulePath}/{$directory}/.gitkeep", '');
        }

        $this->info("Module {$moduleName} created successfully!");
        $this->info("Don't forget to register the provider in config/modules.php");

        return 0;
    }

    protected function getServiceProviderContent($moduleName)
    {
        $namespace = "Modules\\{$moduleName}\\Providers";
        $className = "{$moduleName}ServiceProvider";

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class {$className} extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        \$this->registerRoutes();
        \$this->loadMigrationsFrom(__DIR__.'/../../Database/migrations');
    }

    /**
     * Register the module routes.
     */
    protected function registerRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../../Routes/api.php');

        Route::middleware('web')
            ->group(__DIR__.'/../../Routes/web.php');
    }
}
PHP;
    }

    protected function getManifestContent($moduleName)
    {
        $manifest = [
            'name' => Str::kebab($moduleName),
            'description' => "{$moduleName} module",
            'version' => '1.0.0',
            'provider' => "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider",
            'aliases' => [],
            'dependencies' => [],
            'enabled' => true,
        ];

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function getApiRoutesContent($moduleName)
    {
        $prefix = Str::kebab($moduleName);

        return <<<PHP
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$moduleName} API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('{$prefix}')->group(function () {
    // Add your API routes here
});
PHP;
    }

    protected function getWebRoutesContent($moduleName)
    {
        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| {$moduleName} Web Routes
|--------------------------------------------------------------------------
*/

// Add your web routes here
PHP;
    }

    protected function getComposerContent($moduleName)
    {
        $namespace = "Modules\\{$moduleName}";
        $composer = [
            'name' => "haasib/{$moduleName}-module",
            'description' => "{$moduleName} module for Haasib",
            'type' => 'haasib-module',
            'version' => '1.0.0',
            'autoload' => [
                'psr-4' => [
                    $namespace => ''
                ]
            ],
            'autoload-dev' => [
                'psr-4' => [
                    "Modules\\{$moduleName}\\Tests\\" => 'Tests/'
                ]
            ],
            'extra' => [
                'haasib' => [
                    'name' => $moduleName,
                    'provider' => "{$namespace}\\Providers\\{$moduleName}ServiceProvider"
                ]
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true
        ];

        return json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function getReadmeContent($moduleName)
    {
        return <<<MD
# {$moduleName} Module

## Description
This module handles {$moduleName} functionality for the Haasib application.

## Structure
- **Domain/**: Business logic, models, and domain-specific code
  - **Models/**: Eloquent models
  - **Actions/**: Domain actions (commands)
  - **Events/**: Domain events
  - **Listeners/**: Event listeners
  - **Exceptions/**: Domain-specific exceptions
  - **ValueObjects/**: Value objects
- **CLI/**: Command-line interface components
- **Http/**: HTTP layer components
  - **Controllers/**: API/HTTP controllers
  - **Requests/**: Form request classes
  - **Resources/**: API resource transformers
  - **Middleware/**: Custom middleware
- **Database/**: Database-related files
  - **migrations/**: Database migrations
  - **seeders/**: Database seeders
- **Providers/**: Service providers
- **Routes/**: Route definitions
- **Resources/**: Frontend assets
- **Tests/**: Unit and feature tests

## Usage
Add usage instructions here.

## Dependencies
List any module dependencies here.
MD;
    }
}