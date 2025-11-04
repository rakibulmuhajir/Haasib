<?php

namespace Modules\Accounting\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AccountingServiceProvider extends ServiceProvider
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
        $this->registerRoutes();
        $this->loadMigrationsFrom(__DIR__.'/../../Database/migrations');
    }

    /**
     * Register the module routes.
     */
    protected function registerRoutes(): void
    {
        // Only load routes if files exist and have content
        $apiFile = __DIR__.'/../../Routes/api.php';
        $webFile = __DIR__.'/../../Routes/web.php';

        if (file_exists($apiFile) && filesize($apiFile) > 50) {
            Route::middleware('api')
                ->prefix('api')
                ->group($apiFile);
        }

        if (file_exists($webFile) && filesize($webFile) > 50) {
            Route::middleware('web')
                ->group($webFile);
        }
    }
}
