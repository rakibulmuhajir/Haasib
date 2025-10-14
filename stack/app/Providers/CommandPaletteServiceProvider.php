<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class CommandPaletteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the default command palette configuration
        $this->mergeConfigFrom(
            config_path('command-palette.php'),
            'command-palette'
        );

        // Load environment-specific overrides
        $this->loadEnvironmentConfig();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration files
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/command-palette.php' => config_path('command-palette.php'),
            ], 'command-palette-config');

            $this->publishes([
                __DIR__.'/../../config/environments' => config_path('environments'),
            ], 'command-palette-environments');
        }

        // Register command palette views if they exist
        $this->loadViewsFrom(
            resource_path('views/vendor/command-palette'),
            'command-palette'
        );

        // Register middleware aliases
        $this->app->alias('command-palette.rate-limit', \App\Http\Middleware\ApiRateLimit::class);
        $this->app->alias('command-palette.company-context', \App\Http\Middleware\CompanyContext::class);
    }

    /**
     * Load environment-specific configuration.
     */
    protected function loadEnvironmentConfig(): void
    {
        $environment = $this->app->environment();
        $envConfigPath = config_path("environments/{$environment}/command-palette.php");

        if (File::exists($envConfigPath)) {
            $envConfig = require $envConfigPath;
            $currentConfig = $this->app->make('config')->get('command-palette', []);

            // Deep merge environment-specific config
            $mergedConfig = $this->deepMerge($currentConfig, $envConfig);

            $this->app->make('config')->set('command-palette', $mergedConfig);
        }

        // Apply environment-specific settings from main config
        $environments = $this->app->make('config')->get('command-palette.environments', []);
        if (isset($environments[$environment])) {
            $currentConfig = $this->app->make('config')->get('command-palette', []);
            $envSettings = $environments[$environment];

            $mergedConfig = $this->deepMerge($currentConfig, $envSettings);
            $this->app->make('config')->set('command-palette', $mergedConfig);
        }
    }

    /**
     * Deep merge two arrays.
     */
    protected function deepMerge(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->deepMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
