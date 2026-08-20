<?php

namespace App\Dashboard;

use App\Models\Company;
use App\Models\User;

/**
 * A dashboard widget owns its own query. The presenter never inspects or
 * aggregates widget data — it only asks a widget for its key/title/permission
 * up front, and calls resolve() when (and only when) the widget is visible
 * on the active tab.
 */
interface DashboardWidget
{
    /**
     * Module-prefixed, stable forever — e.g. 'umrah.cash_book'.
     */
    public function key(): string;

    /**
     * Shown as the widget's card title.
     */
    public function title(): string;

    /**
     * One line, shown in the customise picker.
     */
    public function description(): string;

    /**
     * A Permissions::* constant, or null if the widget is always visible
     * to anyone who can see the dashboard.
     */
    public function permission(): ?string;

    /**
     * 6 or 12 — half or full width on a 12-col grid.
     */
    public function defaultSpan(): int;

    /**
     * Smallest span that still reads correctly.
     */
    public function minSpan(): int;

    /**
     * A pure function of company, user and options. $options is the
     * per-placement config from the saved layout (e.g. {"limit": 8}) and
     * must be handled correctly when empty. Never read anything from the
     * request — resolve() has no access to it.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function resolve(Company $company, User $user, array $options): array;
}
