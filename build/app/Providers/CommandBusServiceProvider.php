<?php

namespace App\Providers;

use App\Services\CommandBusService;
use App\Services\ServiceContext;
use Illuminate\Support\ServiceProvider;

class CommandBusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CommandBusService::class, function ($app) {
            // Create a default context for system-level operations
            $context = new ServiceContext(
                user: null,
                company: null,
                requestId: null,
                metadata: ['source' => 'command-bus', 'user_agent' => 'System']
            );

            return new CommandBusService($context);
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
