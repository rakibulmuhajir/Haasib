<?php

namespace App\Modules\Umrah\Providers;

use App\Dashboard\WidgetRegistry;
use App\Modules\Umrah\Dashboard\Widgets\AgentBalancesWidget;
use App\Modules\Umrah\Dashboard\Widgets\CashBookWidget;
use App\Modules\Umrah\Dashboard\Widgets\CashPositionWidget;
use App\Modules\Umrah\Dashboard\Widgets\DeparturesWidget;
use App\Modules\Umrah\Dashboard\Widgets\NeedsAttentionWidget;
use App\Modules\Umrah\Dashboard\Widgets\RefundsAwaitingDecisionWidget;
use App\Modules\Umrah\Dashboard\Widgets\TransportReadinessWidget;
use App\Modules\Umrah\Dashboard\Widgets\VendorBalancesWidget;
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

        $this->registerDashboardWidgets();
    }

    private function registerDashboardWidgets(): void
    {
        $registry = $this->app->make(WidgetRegistry::class);

        $registry->register(new DeparturesWidget);
        $registry->register(new CashBookWidget);
        $registry->register(new CashPositionWidget);
        $registry->register(new AgentBalancesWidget);
        $registry->register(new VendorBalancesWidget);
        $registry->register(new NeedsAttentionWidget);
        $registry->register(new TransportReadinessWidget);
        $registry->register(new RefundsAwaitingDecisionWidget);
    }
}
