<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Route;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The module namespace.
     */
    protected string $moduleNamespace;

    /**
     * The module path.
     */
    protected string $modulePath;

    /**
     * The module name.
     */
    protected string $moduleName;

    /**
     * The module configuration.
     */
    protected array $moduleConfig;

    /**
     * Create a new service provider instance.
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->detectModuleInfo();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerAliases();
        $this->registerProviders();
        $this->registerCommands();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootRoutes();
        $this->bootMigrations();
        $this->bootTranslations();
        $this->bootViews();
        $this->bootCommands();
    }

    /**
     * Detect module information from the service provider class.
     */
    protected function detectModuleInfo(): void
    {
        $reflection = new \ReflectionClass($this);
        $namespace = $reflection->getNamespaceName();
        
        // Extract module name from namespace (Modules\Acct\Providers\AcctServiceProvider -> Acct)
        $parts = explode('\\', $namespace);
        $this->moduleName = $parts[1] ?? 'Unknown';
        $this->moduleNamespace = "Modules\\{$this->moduleName}";
        $this->modulePath = base_path("modules/{$this->moduleName}");
        
        // Load module configuration from global modules config
        $this->moduleConfig = config("modules.modules.{$this->moduleName}", []);
    }

    /**
     * Register module configuration.
     */
    protected function registerConfig(): void
    {
        $configFile = "{$this->modulePath}/config/module.php";
        
        if (file_exists($configFile)) {
            $this->mergeConfigFrom($configFile, "modules.{$this->moduleName}");
        }
    }

    /**
     * Register module aliases.
     */
    protected function registerAliases(): void
    {
        // Register any module-specific aliases if needed
    }

    /**
     * Register additional service providers for the module.
     */
    protected function registerProviders(): void
    {
        // Auto-register additional providers from module
        $providerPath = "{$this->modulePath}/Providers";
        
        if (is_dir($providerPath)) {
            foreach (glob("{$providerPath}/*ServiceProvider.php") as $providerFile) {
                $providerClass = $this->moduleNamespace . '\\Providers\\' . basename($providerFile, '.php');
                
                if ($providerClass !== static::class && class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }
        }
    }

    /**
     * Register module commands.
     */
    protected function registerCommands(): void
    {
        // Commands will be registered in bootCommands()
    }

    /**
     * Boot module routes.
     */
    protected function bootRoutes(): void
    {
        $routes = $this->moduleConfig['routes'] ?? [];
        if (!($routes['web'] ?? false)) {
            return;
        }

        $webRouteFile = file_exists("{$this->modulePath}/Routes/web.php")
            ? "{$this->modulePath}/Routes/web.php"
            : "{$this->modulePath}/routes/web.php";

        if (file_exists($webRouteFile)) {
            Route::middleware(['web', 'auth'])
                ->prefix('dashboard')
                ->group($webRouteFile);
        }

        $apiRouteFile = file_exists("{$this->modulePath}/Routes/api.php")
            ? "{$this->modulePath}/Routes/api.php"
            : "{$this->modulePath}/routes/api.php";

        if (file_exists($apiRouteFile)) {
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('api')
                ->group($apiRouteFile);
        }
    }

    /**
     * Boot module migrations.
     */
    protected function bootMigrations(): void
    {
        $this->loadMigrationsFrom($this->modulePath . '/Database/migrations');
    }

    /**
     * Boot module translations.
     */
    protected function bootTranslations(): void
    {
        $this->loadTranslationsFrom(
            $this->modulePath . '/Resources/lang',
            strtolower($this->moduleName)
        );

        $this->loadJsonTranslationsFrom($this->modulePath . '/Resources/lang');
    }

    /**
     * Boot module views.
     */
    protected function bootViews(): void
    {
        $this->loadViewsFrom($this->modulePath . '/Resources/views', strtolower($this->moduleName));
    }

    /**
     * Boot module console commands.
     */
    protected function bootCommands(): void
    {
        $cli = $this->moduleConfig['cli'] ?? [];
        if (!($cli['commands'] ?? false)) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $commandPath = $this->modulePath . '/CLI/Commands';
            
            if (is_dir($commandPath)) {
                foreach (glob("{$commandPath}/*.php") as $commandFile) {
                    $commandClass = $this->moduleNamespace . '\\CLI\\Commands\\' . basename($commandFile, '.php');
                    
                    if (class_exists($commandClass)) {
                        $this->commands([$commandClass]);
                    }
                }
            }
        }
    }

    /**
     * Get the module name.
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Get the module configuration.
     */
    public function getModuleConfig(): array
    {
        return $this->moduleConfig;
    }

    /**
     * Check if the module should be loaded in current context.
     */
    protected function shouldLoad(): bool
    {
        // Always load for now - can be extended for company-specific loading
        return true;
    }
}
