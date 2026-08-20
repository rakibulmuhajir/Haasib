<?php

namespace App\Dashboard;

use App\Models\Company;
use App\Models\User;

/**
 * Turns a resolved layout into the payload the page renders.
 *
 * present() resolves the layout, then calls resolve() only on the widgets in
 * the active tab. Other tabs ship their titles/keys/spans but no data — a
 * tab switch is an Inertia partial reload of dashboard.tabs, so a
 * twelve-widget dashboard on four tabs runs three queries, not twelve.
 */
class DashboardPresenter
{
    public function __construct(
        private DashboardLayoutResolver $resolver,
        private WidgetRegistry $registry,
    ) {}

    /**
     * @return array<int, array{key: string, label: string, active: bool, widgets: array<int, array{key: string, title: string, description: string, span: int, minSpan: int, options: array<string, mixed>, data: ?array<string, mixed>}>}>
     */
    public function present(User $user, Company $company, string $dashboardKey, ?string $activeTab = null): array
    {
        $tabs = $this->resolver->resolve($user, $company, $dashboardKey);

        if (empty($tabs)) {
            return [];
        }

        $activeKey = collect($tabs)->pluck('key')->contains($activeTab)
            ? $activeTab
            : $tabs[0]['key'];

        return collect($tabs)
            ->map(function (array $tab) use ($activeKey, $company, $user): array {
                $isActive = $tab['key'] === $activeKey;

                $widgets = collect($tab['widgets'])
                    ->map(function (array $placement) use ($isActive, $company, $user): array {
                        $widget = $this->registry->get($placement['key']);

                        return [
                            'key' => $widget->key(),
                            'title' => $widget->title(),
                            'description' => $widget->description(),
                            'span' => $placement['span'],
                            'minSpan' => $widget->minSpan(),
                            'options' => $placement['options'],
                            'data' => $isActive ? $widget->resolve($company, $user, $placement['options']) : null,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'key' => $tab['key'],
                    'label' => $tab['label'],
                    'active' => $isActive,
                    'widgets' => $widgets,
                ];
            })
            ->values()
            ->all();
    }
}
