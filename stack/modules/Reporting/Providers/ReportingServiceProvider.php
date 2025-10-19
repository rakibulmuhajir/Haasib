<?php

namespace Modules\Reporting\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Reporting\Services\DashboardCacheService;
use Modules\Reporting\Services\DashboardMetricsService;
use Modules\Reporting\Services\FinancialStatementService;
use Modules\Reporting\Services\KpiComputationService;
use Modules\Reporting\Services\ReportTemplateService;
use Modules\Reporting\Services\TrialBalanceService;

class ReportingServiceProvider extends ServiceProvider
{
    public array $bindings = [
        DashboardMetricsService::class => DashboardMetricsService::class,
        DashboardCacheService::class => DashboardCacheService::class,
        FinancialStatementService::class => FinancialStatementService::class,
        TrialBalanceService::class => TrialBalanceService::class,
        ReportTemplateService::class => ReportTemplateService::class,
        KpiComputationService::class => KpiComputationService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            module_path('Reporting', 'config/module.php'),
            'reporting'
        );

        // Register command bus actions
        $this->app->register(\Modules\Reporting\Providers\CommandBusServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(module_path('Reporting', 'routes/api.php'));
        $this->loadViewsFrom(module_path('Reporting', 'resources/views'), 'reporting');
        $this->loadMigrationsFrom(module_path('Reporting', 'database/migrations'));

        $this->publishes([
            module_path('Reporting', 'config/module.php') => config_path('reporting.php'),
        ], 'reporting-config');
    }

    public function provides(): array
    {
        return [
            DashboardMetricsService::class,
            DashboardCacheService::class,
            FinancialStatementService::class,
            TrialBalanceService::class,
            ReportTemplateService::class,
            KpiComputationService::class,
        ];
    }
}
