<?php

namespace App\Dashboard;

/**
 * Keyed registry of dashboard widgets. Registration is cheap (no queries) —
 * modules register their widgets in their service provider boot():
 *
 *   $this->app->make(WidgetRegistry::class)->register(new DeparturesWidget());
 *
 * Bound as a singleton (see AppServiceProvider) so every module registers
 * into the same instance.
 */
class WidgetRegistry
{
    /** @var array<string, DashboardWidget> */
    private array $widgets = [];

    public function register(DashboardWidget $widget): void
    {
        $this->widgets[$widget->key()] = $widget;
    }

    public function has(string $key): bool
    {
        return isset($this->widgets[$key]);
    }

    public function get(string $key): ?DashboardWidget
    {
        return $this->widgets[$key] ?? null;
    }

    /**
     * @return array<string, DashboardWidget>
     */
    public function all(): array
    {
        return $this->widgets;
    }
}
