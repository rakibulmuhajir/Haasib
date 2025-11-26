<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'module:make {name} {--schema=acct : Database schema for the module} {--force : Overwrite existing module}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new module with the standard structure';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $schema = $this->option('schema');
        
        if (empty($name)) {
            $this->error('Module name is required.');
            return Command::FAILURE;
        }

        $moduleName = Str::studly($name);
        $modulePath = base_path("modules/{$moduleName}");

        if ($this->files->exists($modulePath) && !$this->option('force')) {
            $this->error("Module [{$moduleName}] already exists.");
            return Command::FAILURE;
        }

        $this->createModuleStructure($moduleName, $modulePath, $schema);
        $this->updateModulesConfig($moduleName, $schema);
        $this->info("Module [{$moduleName}] created successfully.");

        return Command::SUCCESS;
    }

    /**
     * Create the module directory structure.
     */
    protected function createModuleStructure(string $moduleName, string $modulePath, string $schema): void
    {
        $namespace = "Modules\\{$moduleName}";
        $lowerName = strtolower($moduleName);

        // Create directories
        $directories = [
            'CLI/Commands',
            'CLI/Palette',
            'Database/migrations',
            'Database/seeders',
            'Domain/Actions',
            'Domain/Services',
            'Domain/Policies',
            'Http/Controllers',
            'Http/Requests',
            'Http/Middleware',
            'Models',
            'Providers',
            'Resources/lang/en',
            'Resources/views',
            'Routes',
            'Tests/Feature',
            'Tests/Unit',
        ];

        foreach ($directories as $directory) {
            $this->files->makeDirectory("{$modulePath}/{$directory}", 0755, true);
        }

        // Create module.json
        $this->files->put(
            "{$modulePath}/module.json",
            $this->generateModuleJson($moduleName)
        );

        // Create main service provider
        $this->files->put(
            "{$modulePath}/Providers/{$moduleName}ServiceProvider.php",
            $this->generateServiceProvider($moduleName, $namespace, $schema)
        );

        // Create route files
        $this->files->put(
            "{$modulePath}/Routes/web.php",
            $this->generateWebRoutes($moduleName, $lowerName)
        );

        $this->files->put(
            "{$modulePath}/Routes/api.php",
            $this->generateApiRoutes($moduleName, $lowerName)
        );

        // Create example command
        $this->files->put(
            "{$modulePath}/CLI/Commands/{$moduleName}Command.php",
            $this->generateExampleCommand($moduleName, $namespace)
        );

        // Create CLI palette registry
        $this->files->put(
            "{$modulePath}/CLI/Palette/registry.php",
            $this->generatePaletteRegistry($moduleName)
        );

        // Create example action
        $this->files->put(
            "{$modulePath}/Domain/Actions/ExampleAction.php",
            $this->generateExampleAction($moduleName, $namespace)
        );

        // Create example test
        $this->files->put(
            "{$modulePath}/Tests/Feature/{$moduleName}Test.php",
            $this->generateExampleTest($moduleName, $namespace)
        );

        // Create .gitkeep files for empty directories
        $this->createGitkeepFiles($modulePath);
    }

    /**
     * Generate module.json content.
     */
    protected function generateModuleJson(string $moduleName): string
    {
        $data = [
            'name' => strtolower($moduleName),
            'description' => "{$moduleName} module",
            'version' => '1.0.0',
            'provider' => "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider",
            'aliases' => [],
            'dependencies' => [],
            'enabled' => true,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate service provider content.
     */
    protected function generateServiceProvider(string $moduleName, string $namespace, string $schema): string
    {
        return "<?php\n\nnamespace {$namespace}\\Providers;\n\nuse App\\Providers\\ModuleServiceProvider;\n\nclass {$moduleName}ServiceProvider extends ModuleServiceProvider\n{\n    /**\n     * Register any application services.\n     */\n    public function register(): void\n    {\n        parent::register();\n        \n        // Register module-specific services\n    }\n\n    /**\n     * Bootstrap any application services.\n     */\n    public function boot(): void\n    {\n        parent::boot();\n        \n        // Bootstrap module-specific features\n    }\n}";
    }

    /**
     * Generate web routes content.
     */
    protected function generateWebRoutes(string $moduleName, string $lowerName): string
    {
        return "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n/*\n|--------------------------------------------------------------------------\n| {$moduleName} Web Routes\n|--------------------------------------------------------------------------\n|\n| Here is where you can register web routes for your module. These routes\n| are loaded by the RouteServiceProvider within a group which\n| contains the \"web\" middleware group.\n|\n*/\n\nRoute::prefix('/{$lowerName}')->group(function () {\n    Route::get('/', function () {\n        return view('{$lowerName}::index');\n    })->name('{$lowerName}.index');\n});\n";
    }

    /**
     * Generate API routes content.
     */
    protected function generateApiRoutes(string $moduleName, string $lowerName): string
    {
        return "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n/*\n|--------------------------------------------------------------------------\n| {$moduleName} API Routes\n|--------------------------------------------------------------------------\n|\n| Here is where you can register API routes for your module. These\n| routes are loaded by the RouteServiceProvider within a group which\n| is assigned the \"api\" middleware group.\n|\n*/\n\nRoute::prefix('/{$lowerName}')->group(function () {\n    Route::get('/', function () {\n        return response()->json([\n            'module' => '{$moduleName}',\n            'version' => '1.0.0',\n        ]);\n    });\n});\n";
    }

    /**
     * Generate example command content.
     */
    protected function generateExampleCommand(string $moduleName, string $namespace): string
    {
        $lowerName = strtolower($moduleName);
        return "<?php\n\nnamespace {$namespace}\\CLI\\Commands;\n\nuse Illuminate\\Console\\Command;\n\nclass {$moduleName}Command extends Command\n{\n    /**\n     * The name and signature of the console command.\n     */\n    protected \$signature = '{$lowerName}:example';\n\n    /**\n     * The console command description.\n     */\n    protected \$description = 'Example command for {$moduleName} module';\n\n    /**\n     * Execute the console command.\n     */\n    public function handle(): int\n    {\n        \$this->info('{$moduleName} module is working!');\n        return Command::SUCCESS;\n    }\n}\n";
    }

    /**
     * Generate CLI palette registry content.
     */
    protected function generatePaletteRegistry(string $moduleName): string
    {
        $lowerName = strtolower($moduleName);
        return "<?php\n\n/*\n|--------------------------------------------------------------------------\n| {$moduleName} CLI Palette Registry\n|--------------------------------------------------------------------------\n|\n| This file defines the CLI commands that will be available in the\n| command palette for this module.\n|\n*/\n\nreturn [\n    // Example command definition\n    // [\n    //     'id' => '{$lowerName}.action',\n    //     'label' => 'Perform Action',\n    //     'aliases' => ['action'],\n    //     'needs' => ['company'],\n    //     'executeAction' => '{$lowerName}.action',\n    //     'rbac' => ['{$lowerName}.manage'],\n    // ],\n];\n";
    }

    /**
     * Generate example action content.
     */
    protected function generateExampleAction(string $moduleName, string $namespace): string
    {
        return "<?php\n\nnamespace {$namespace}\\Domain\\Actions;\n\nuse App\\Models\\Company;\nuse App\\Models\\User;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass ExampleAction\n{\n    /**\n     * Execute the action.\n     */\n    public function execute(Company \$company, array \$data, User \$user): mixed\n    {\n        // Example action implementation\n        return DB::transaction(function () use (\$company, \$data, \$user) {\n            // Your action logic here\n            return \$data;\n        });\n    }\n}\n";
    }

    /**
     * Generate example test content.
     */
    protected function generateExampleTest(string $moduleName, string $namespace): string
    {
        $lowerName = strtolower($moduleName);
        return "<?php\n\nnamespace {$namespace}\\Tests\\Feature;\n\nuse Tests\\TestCase;\nuse Illuminate\\Foundation\\Testing\\RefreshDatabase;\n\nclass {$moduleName}Test extends TestCase\n{\n    use RefreshDatabase;\n\n    /**\n     * Test that the module loads correctly.\n     */\n    public function test_module_loads(): void\n    {\n        \$response = \$this->get('/api/{$lowerName}');\n        \n        \$response->assertStatus(200)\n            ->assertJson([\n                'module' => '{$moduleName}',\n                'version' => '1.0.0',\n            ]);\n    }\n}\n";
    }

    /**
     * Create .gitkeep files for empty directories.
     */
    protected function createGitkeepFiles(string $modulePath): void
    {
        $directories = [
            'Database/migrations',
            'Database/seeders',
            'Domain/Actions',
            'Domain/Services',
            'Domain/Policies',
            'Http/Controllers',
            'Http/Requests',
            'Http/Middleware',
            'Models',
            'Resources/lang/en',
            'Resources/views',
            'Tests/Feature',
            'Tests/Unit',
        ];

        foreach ($directories as $directory) {
            $path = "{$modulePath}/{$directory}";
            if (is_dir($path) && count(scandir($path)) === 2) { // Only contains . and ..
                $this->files->put("{$path}/.gitkeep", '');
            }
        }
    }

    /**
     * Update the global modules configuration.
     */
    protected function updateModulesConfig(string $moduleName, string $schema): void
    {
        $configPath = config_path('modules.php');
        $config = include $configPath;

        $config['modules'][strtolower($moduleName)] = [
            'name' => $moduleName,
            'namespace' => "Modules\\{$moduleName}",
            'provider' => "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider",
            'schema' => $schema,
            'description' => "{$moduleName} module",
            'version' => '1.0.0',
            'enabled' => true,
            'routes' => [
                'web' => true,
                'api' => true,
            ],
            'cli' => [
                'commands' => true,
                'palette' => true,
            ],
            'permissions' => [
                // Add module-specific permissions here
            ],
        ];

        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        $this->files->put($configPath, $content);
    }
}
