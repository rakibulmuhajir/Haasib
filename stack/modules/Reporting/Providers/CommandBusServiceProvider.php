<?php

namespace Modules\Reporting\Providers;

use Illuminate\Support\ServiceProvider;

class CommandBusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register command bus action handlers
        $this->registerReportingActions();
    }

    private function registerReportingActions(): void
    {
        // Register action handlers as singletons for performance
        $this->app->singleton(\Modules\Reporting\Actions\Dashboard\RefreshDashboardAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Dashboard\InvalidateDashboardCacheAction::class);

        $this->app->singleton(\Modules\Reporting\Actions\Reports\GenerateReportAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Reports\DeliverReportAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Reports\ExpireReportAction::class);

        $this->app->singleton(\Modules\Reporting\Actions\Templates\CreateReportTemplateAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Templates\UpdateReportTemplateAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Templates\DeleteReportTemplateAction::class);

        $this->app->singleton(\Modules\Reporting\Actions\Schedules\CreateReportScheduleAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Schedules\UpdateReportScheduleAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Schedules\DeleteReportScheduleAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Schedules\RunScheduledReportsAction::class);

        $this->app->singleton(\Modules\Reporting\Actions\Kpi\CreateKpiDefinitionAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Kpi\UpdateKpiDefinitionAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Kpi\DeleteKpiDefinitionAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Kpi\RecomputeKpiSnapshotsAction::class);

        $this->app->singleton(\Modules\Reporting\Actions\Exports\GenerateReportExportAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\Exports\DeliverReportExportAction::class);

        $this->app->singleton(\Modules\Reporting\Actions\System\RefreshMaterializedViewsAction::class);
        $this->app->singleton(\Modules\Reporting\Actions\System\CleanupExpiredReportsAction::class);
    }

    public function provides(): array
    {
        return [
            \Modules\Reporting\Actions\Dashboard\RefreshDashboardAction::class,
            \Modules\Reporting\Actions\Dashboard\InvalidateDashboardCacheAction::class,
            \Modules\Reporting\Actions\Reports\GenerateReportAction::class,
            \Modules\Reporting\Actions\Reports\DeliverReportAction::class,
            \Modules\Reporting\Actions\Reports\ExpireReportAction::class,
            \Modules\Reporting\Actions\Templates\CreateReportTemplateAction::class,
            \Modules\Reporting\Actions\Templates\UpdateReportTemplateAction::class,
            \Modules\Reporting\Actions\Templates\DeleteReportTemplateAction::class,
            \Modules\Reporting\Actions\Schedules\CreateReportScheduleAction::class,
            \Modules\Reporting\Actions\Schedules\UpdateReportScheduleAction::class,
            \Modules\Reporting\Actions\Schedules\DeleteReportScheduleAction::class,
            \Modules\Reporting\Actions\Schedules\RunScheduledReportsAction::class,
            \Modules\Reporting\Actions\Kpi\CreateKpiDefinitionAction::class,
            \Modules\Reporting\Actions\Kpi\UpdateKpiDefinitionAction::class,
            \Modules\Reporting\Actions\Kpi\DeleteKpiDefinitionAction::class,
            \Modules\Reporting\Actions\Kpi\RecomputeKpiSnapshotsAction::class,
            \Modules\Reporting\Actions\Exports\GenerateReportExportAction::class,
            \Modules\Reporting\Actions\Exports\DeliverReportExportAction::class,
            \Modules\Reporting\Actions\System\RefreshMaterializedViewsAction::class,
            \Modules\Reporting\Actions\System\CleanupExpiredReportsAction::class,
        ];
    }
}
