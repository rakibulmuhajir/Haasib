<?php

namespace App\Providers;

use App\Services\CommandBusService;
use Illuminate\Support\ServiceProvider;

class CommandBusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CommandBusService::class, function ($app) {
            return new CommandBusService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register command aliases for easy access
        $commandBus = $this->app->make(CommandBusService::class);
        
        // You can add any command bus bootstrapping here
        // For example, validating command configurations, warming caches, etc.
    }
}