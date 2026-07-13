<?php

namespace App\Modules\Umrah\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UmrahServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'umrah');

        Route::middleware('web')
            ->group(__DIR__.'/../Routes/umrah.php');
    }
}
