<?php

namespace App\Dashboard;

use App\Models\Company;
use App\Models\DashboardLayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the tab/widget layout for a (user, company, dashboard) triple.
 *
 * Order of precedence:
 *   1. The user's saved layout for this (user, company, dashboard).
 *   2. The role default from config('dashboards').
 *   3. An empty layout — the page renders its empty state, never an error.
 *
 * Then filters: drop any widget whose key is unregistered, and any whose
 * permission() the user lacks. Filtering happens after the layout is chosen,
 * so a permission change is reflected immediately without rewriting saved
 * layouts.
 *
 * Nothing here is module-specific — Umrah is just the first consumer.
 */
class DashboardLayoutResolver
{
    public function __construct(private WidgetRegistry $registry) {}

    /**
     * @return array<int, array{key: string, label: string, widgets: array<int, array{key: string, span: int, options: array<string, mixed>}>}>
     */
    public function resolve(User $user, Company $company, string $dashboardKey): array
    {
        $tabs = $this->savedTabs($user, $company, $dashboardKey)
            ?? $this->roleDefaultTabs($user, $company, $dashboardKey)
            ?? [];

        return $this->filterTabs($tabs, $user);
    }

    private function savedTabs(User $user, Company $company, string $dashboardKey): ?array
    {
        $layout = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('dashboard_key', $dashboardKey)
            ->first();

        if (! $layout) {
            return null;
        }

        $tabs = $layout->tabs;

        return is_array($tabs) ? $tabs : null;
    }

    private function roleDefaultTabs(User $user, Company $company, string $dashboardKey): ?array
    {
        $role = $this->companyRole($user, $company);

        if ($role === null) {
            return null;
        }

        $tabs = config("dashboards.{$dashboardKey}.roles.{$role}");

        return is_array($tabs) ? $tabs : null;
    }

    private function companyRole(User $user, Company $company): ?string
    {
        if ($user->isGodMode()) {
            return 'owner';
        }

        return DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('role');
    }

    /**
     * @param  array<int, array<string, mixed>>  $tabs
     * @return array<int, array{key: string, label: string, widgets: array<int, array{key: string, span: int, options: array<string, mixed>}>}>
     */
    private function filterTabs(array $tabs, User $user): array
    {
        return collect($tabs)
            ->map(function (array $tab) use ($user): array {
                $widgets = collect($tab['widgets'] ?? [])
                    ->filter(function (array $placement) use ($user): bool {
                        $widget = $this->registry->get($placement['key'] ?? '');

                        if (! $widget) {
                            return false;
                        }

                        $permission = $widget->permission();

                        return $permission === null || $user->hasCompanyPermission($permission);
                    })
                    ->map(fn (array $placement): array => [
                        'key' => $placement['key'],
                        'span' => $placement['span'] ?? $this->registry->get($placement['key'])->defaultSpan(),
                        'options' => $placement['options'] ?? [],
                    ])
                    ->values()
                    ->all();

                return [
                    'key' => $tab['key'],
                    'label' => $tab['label'],
                    'widgets' => $widgets,
                ];
            })
            ->values()
            ->all();
    }
}
