<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MakeModule extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'module:make
        {name : StudlyCase name of the module}
        {--schema=acct : Schema alias to associate with this module}
        {--cli-only : Generate CLI scaffolding only}
        {--force : Overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Scaffold a new self-contained module (routes, providers, CLI stubs, config entry)';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $slug = Str::kebab($name);
        $schema = Str::lower($this->option('schema') ?: 'acct');
        $cliOnly = (bool) $this->option('cli-only');
        $force = (bool) $this->option('force');

        $modulePath = base_path('modules/'.$name);

        if ($files->exists($modulePath) && ! $force) {
            $this->error("Module directory already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $this->scaffoldDirectories($files, $modulePath, $cliOnly);
        $this->generateFiles($files, $modulePath, $name, $slug, $schema, $cliOnly, $force);
        $this->registerInConfig($files, $name, $slug, $schema, $cliOnly);

        $this->info("Module [{$name}] created at modules/{$name}. Remember to run composer dump-autoload.");

        return self::SUCCESS;
    }

    protected function scaffoldDirectories(Filesystem $files, string $modulePath, bool $cliOnly): void
    {
        $directories = [
            'Domain/Actions',
            'Domain/Services',
            'Domain/Policies',
            'Domain/Jobs',
            'CLI/Commands',
            'CLI/Palette',
            'Providers',
            'Resources/lang/en',
            'Resources/lang/ar',
            'Resources/views',
            'Tests/Feature',
            'Tests/Unit',
        ];

        if (! $cliOnly) {
            $directories = array_merge($directories, [
                'Database/migrations',
                'Database/seeders',
                'Http/Controllers',
                'Http/Middleware',
                'routes',
            ]);
        }

        foreach ($directories as $directory) {
            $files->ensureDirectoryExists($modulePath.'/'.$directory);
        }
    }

    protected function generateFiles(
        Filesystem $files,
        string $modulePath,
        string $name,
        string $slug,
        string $schema,
        bool $cliOnly,
        bool $force
    ): void {
        $namespace = "Modules\\{$name}";

        $this->writeFile($files, $modulePath.'/module.json', $this->moduleJson($name, $slug, $schema), $force);
        $this->writeFile($files, $modulePath.'/CLI/Commands/'.$name.'Command.php', $this->cliCommandStub($namespace, $name), $force);
        $this->writeFile($files, $modulePath.'/CLI/Palette/registry.php', $this->paletteRegistryStub(), $force);
        $this->writeFile($files, $modulePath.'/CLI/Palette/parser.php', $this->paletteParserStub(), $force);
        $this->writeFile($files, $modulePath.'/Domain/Actions/registry.php', $this->actionsRegistryStub(), $force);
        $this->writeFile($files, $modulePath.'/Providers/ModuleServiceProvider.php', $this->moduleProviderStub($namespace, $name, $cliOnly), $force);
        $this->writeFile($files, $modulePath.'/Providers/RouteServiceProvider.php', $this->routeProviderStub($namespace), $force);
        $this->writeFile($files, $modulePath.'/Resources/lang/en/messages.php', $this->langStub('en'), $force);
        $this->writeFile($files, $modulePath.'/Resources/lang/ar/messages.php', $this->langStub('ar'), $force);
        $this->writeFile($files, $modulePath.'/Tests/Feature/'.$name.'FeatureTest.php', $this->featureTestStub($namespace, $name), $force);
        $this->writeFile($files, $modulePath.'/Tests/Unit/'.$name.'UnitTest.php', $this->unitTestStub($namespace, $name), $force);

        if (! $cliOnly) {
            $this->writeFile($files, $modulePath.'/routes/web.php', $this->webRoutesStub(), $force);
            $this->writeFile($files, $modulePath.'/routes/api.php', $this->apiRoutesStub(), $force);
        }
    }

    protected function registerInConfig(Filesystem $files, string $name, string $slug, string $schema, bool $cliOnly): void
    {
        $configPath = config_path('modules.php');
        $configArray = include $configPath;
        $modules = Arr::get($configArray, 'modules', []);

        $modules[$slug] = [
            'name' => $name,
            'namespace' => "Modules\\{$name}",
            'provider' => "Modules\\{$name}\\Providers\\ModuleServiceProvider",
            'schema' => $schema,
            'routes' => [
                'web' => ! $cliOnly,
                'api' => ! $cliOnly,
            ],
            'cli' => [
                'commands' => true,
                'palette' => true,
            ],
            'permissions' => [],
        ];

        ksort($modules);

        $export = var_export(['modules' => $modules], true);
        $php = "<?php\n\nreturn {$export};\n";

        $files->put($configPath, $php);
    }

    protected function moduleJson(string $name, string $slug, string $schema): string
    {
        $payload = [
            'name' => $name,
            'slug' => $slug,
            'version' => '0.1.0',
            'schema' => $schema,
            'description' => '',
            'permissions' => [],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    protected function cliCommandStub(string $namespace, string $name): string
    {
        $class = $name.'Command';

        return <<<PHP
<?php

namespace {$namespace}\CLI\Commands;

use Illuminate\Console\Command;

class {$class} extends Command
{
    protected \$signature = '{$this->defaultCliSignature($name)}';

    protected \$description = 'Example command for the {$name} module';

    public function handle(): int
    {
        // This is a template command - customize the logic below
        // Example of dispatching a command bus action:
        // \$result = Bus::dispatch('{$name}.action', [
        //     'data' => \$this->argument('data'),
        //     'context' => ServiceContext::forSystem()
        // ]);
        
        \$this->info('{$name} module command executed.');
        \$this->line('Customize this command in: ' . __CLASS__);

        return self::SUCCESS;
    }
}
PHP;
    }

    protected function defaultCliSignature(string $name): string
    {
        return Str::kebab($name).':example';
    }

    protected function paletteRegistryStub(): string
    {
        return <<<PHP
<?php

use App\Palette\Contracts\CommandDef;

/** @var CommandDef[] */
return [
    [
        'id' => 'example.action',
        'label' => 'example',
        'aliases' => ['example'],
        'needs' => [],
        'executeAction' => 'example.action',
        'rbac' => [],
    ],
];
PHP;
    }

    protected function paletteParserStub(): string
    {
        return <<<PHP
<?php

return [
    // e.g. ['pattern' => '/(?P<amount>\\d+)/', 'action' => 'example.action']
];
PHP;
    }

    protected function actionsRegistryStub(): string
    {
        return <<<PHP
<?php

return [
    // 'example.action' => Modules\\Example\\Domain\\Actions\\ExampleAction::class,
];
PHP;
    }

    protected function moduleProviderStub(string $namespace, string $name, bool $cliOnly): string
    {
        $modulePath = "__DIR__.'/..'";
        $loadRoutes = $cliOnly ? '' : <<<'PHP'
        $this->app->register(RouteServiceProvider::class);
PHP;

        $loadRoutes = rtrim($loadRoutes);

        return <<<PHP
<?php

namespace {$namespace}\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge palette + action registries when the module is active.
        \$this->registerCommandBus();
        \$this->registerPalette();
        \$this->registerCommands();
    }

    public function boot(): void
    {
        {$loadRoutes}

        \$moduleRoot = realpath({$modulePath});

        if (\$moduleRoot === false) {
            return;
        }

        \$this->loadTranslationsFrom(\$moduleRoot.'/Resources/lang', Str::kebab('{$name}'));
        \$this->loadViewsFrom(\$moduleRoot.'/Resources/views', Str::kebab('{$name}'));

        if (is_dir(\$moduleRoot.'/Database/migrations')) {
            \$this->loadMigrationsFrom(\$moduleRoot.'/Database/migrations');
        }
    }

    protected function registerCommandBus(): void
    {
        \$registryPath = __DIR__.'/../Domain/Actions/registry.php';

        if (! file_exists(\$registryPath)) {
            return;
        }

        \$actions = include \$registryPath;

        if (empty(\$actions)) {
            return;
        }

        if (\$this->app->bound('command.bus')) {
            \$bus = \$this->app->make('command.bus');

            if (method_exists(\$bus, 'extend')) {
                \$bus->extend($actions);
            }
        }
    }

    protected function registerPalette(): void
    {
        \$registryPath = __DIR__.'/../CLI/Palette/registry.php';

        if (! file_exists(\$registryPath)) {
            return;
        }

        \$fragments = include \$registryPath;

        if (empty(\$fragments)) {
            return;
        }

        if (\$this->app->bound('palette.registry')) {
            \$palette = \$this->app->make('palette.registry');

            if (is_array(\$palette)) {
                \$this->app->instance('palette.registry', array_merge($palette, $fragments));
            }
        }
    }

    protected function registerCommands(): void
    {
        \$directory = __DIR__.'/../CLI/Commands';

        if (! is_dir(\$directory)) {
            return;
        }

        \$commands = [];

        foreach (glob(\$directory.'/*Command.php') as \$file) {
            \$class = 'Modules\\{$name}\\CLI\\Commands\\'.basename(\$file, '.php');

            if (class_exists(\$class)) {
                \$commands[] = \$class;
            }
        }

        if (! empty(\$commands)) {
            $this->commands(\$commands);
        }
    }
}
PHP;
    }

    protected function routeProviderStub(string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace}\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function (): void {
            Route::middleware('web')
                ->group(__DIR__.'/../routes/web.php');

            Route::prefix('api')
                ->middleware('api')
                ->group(__DIR__.'/../routes/api.php');
        });
    }
}
PHP;
    }

    protected function langStub(string $locale): string
    {
        return <<<PHP
<?php

return [
    'example' => '{$locale} translation placeholder',
];
PHP;
    }

    protected function featureTestStub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace}\Tests\Feature;

use Tests\TestCase;

class {$name}FeatureTest extends TestCase
{
    public function test_example_feature(): void
    {
        \$this->markTestIncomplete('Write {$name} feature tests.');
    }
}
PHP;
    }

    protected function unitTestStub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace}\Tests\Unit;

use PHPUnit\Framework\TestCase;

class {$name}UnitTest extends TestCase
{
    public function test_example_unit(): void
    {
        \$this->markTestIncomplete('Write {$name} unit tests.');
    }
}
PHP;
    }

    protected function webRoutesStub(): string
    {
        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->prefix('module')
    ->group(function (): void {
        // Route::get('example', ExampleController::class);
    });
PHP;
    }

    protected function apiRoutesStub(): string
    {
        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('module')
    ->group(function (): void {
        // Route::get('example', ExampleApiController::class);
    });
PHP;
    }

    protected function writeFile(Filesystem $files, string $path, string $contents, bool $force): void
    {
        if ($files->exists($path) && ! $force) {
            return;
        }

        $files->put($path, $contents);
    }
}
