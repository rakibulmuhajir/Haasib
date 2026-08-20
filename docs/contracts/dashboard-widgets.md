# Dashboard Widget Contract

A dashboard is a **layout over a registry**. Nothing about it is Umrah-specific;
Umrah is the first consumer. Any module registers widgets the same way.

Three rules the whole design hangs off:

1. **A widget owns its own query.** The page controller resolves nothing. It asks
   the layout which widgets are visible and asks each of those for its data.
   A hidden widget costs zero queries — this is what makes customisation cheap.
2. **A tab is just a named layout.** Not a component, not a route. Adding a tab
   is adding a row to an array.
3. **Unknown widget keys are ignored, never fatal.** A saved layout referencing a
   widget that was renamed or removed drops silently. Layouts outlive code.

---

## Backend

### `app/Dashboard/DashboardWidget.php` (interface)

```php
interface DashboardWidget
{
    public function key(): string;          // 'umrah.cash_book' — module-prefixed, stable forever
    public function title(): string;        // 'What's been happening'
    public function description(): string;  // one line, shown in the customise picker
    public function permission(): ?string;  // Permissions::* constant, or null for always
    public function defaultSpan(): int;     // 6 or 12 (half or full width on a 12-col grid)
    public function minSpan(): int;         // smallest width that still reads correctly
    public function resolve(Company $company, User $user, array $options): array;
}
```

`$options` is per-placement config from the saved layout (`{"limit": 8, "days": 14}`).
A widget must work with `$options === []`. Never read anything else from the
request — a widget is a pure function of company, user and options.

### `app/Dashboard/WidgetRegistry.php`

Keyed by `key()`. Modules register in their service provider `boot()`:

```php
$this->app->make(WidgetRegistry::class)->register(new DeparturesWidget());
```

Registration is cheap (no queries) — construction must stay side-effect free.

### `app/Dashboard/DashboardLayoutResolver.php`

`resolve(User $user, Company $company, string $dashboardKey): array`

Order of precedence:
1. The user's saved layout for this (user, company, dashboard).
2. The role default from `config/dashboards.php`.
3. An empty layout — the page renders its empty state, never an error.

Then it **filters**: drop any widget whose key is unregistered, and any whose
`permission()` the user lacks. Filtering happens after the layout is chosen, so
a permission change is reflected immediately without rewriting saved layouts.

### Storage — `auth.dashboard_layouts`

| column | type | note |
|---|---|---|
| `id` | uuid pk | |
| `user_id` | uuid | fk `auth.users` |
| `company_id` | uuid | fk `auth.companies` — layouts are per company |
| `dashboard_key` | text | `'umrah'`, `'accounting'`, … |
| `tabs` | jsonb | the layout, shape below |
| timestamps | | |

Unique on `(user_id, company_id, dashboard_key)`. RLS by `company_id` as usual.

```jsonc
{
  "tabs": [
    {
      "key": "operations",
      "label": "Operations",
      "widgets": [
        { "key": "umrah.departures", "span": 12, "options": { "limit": 8 } },
        { "key": "umrah.transport_readiness", "span": 6, "options": {} }
      ]
    }
  ]
}
```

Order in the array is display order. `span` is 6 or 12. No free-form x/y —
a one-dimensional ordered list with a width is enough, and it survives resizing
and reordering without a layout engine.

### `config/dashboards.php`

Defaults per dashboard per role. Same JSON shape. This is the file that answers
"what does a brand-new owner see on day one" and it is the only place that
answers it.

### Controller

```php
$tabs = app(DashboardPresenter::class)->present($user, $company, 'umrah', $activeTab);
```

`present()` resolves the layout, then calls `resolve()` **only on the widgets in
the active tab**. Other tabs ship their titles and widget keys but no data;
switching tab is an Inertia partial reload of `dashboard.tabs`. A twelve-widget
dashboard on four tabs runs three queries, not twelve.

---

## Frontend

| Component | Owns |
|---|---|
| `components/dashboard/DashboardGrid.vue` | the 12-col grid, span → col-span, order |
| `components/dashboard/DashboardTabs.vue` | tab strip, active state, partial reload on switch |
| `components/dashboard/WidgetFrame.vue` | Card + title + optional footer link + empty state + loading skeleton |
| `components/dashboard/widgets/index.ts` | `key → component` map |

Every widget component receives exactly `{ data, options }` and renders inside a
`WidgetFrame`. **A widget never renders its own Card, heading, footer or empty
state** — the frame owns all four, which is what stops twelve widgets inventing
twelve dialects.

Widgets compose the existing base elements and add nothing new:
`LedgerRegister` for anything tabular, `Derivation` for anything that sums,
`MetaChip` for age/deadline, `MoneyText` for every figure, `StatusBadge` for
every state, `EmptyState` via the frame.

Unknown key in `widgets/index.ts` → the grid skips it silently.

---

## Customisation

Phase 1 (now): layouts come from `config/dashboards.php` only. The table exists,
the resolver reads it, nothing writes to it yet.

Phase 2 (later): a "Customise" affordance writes the same JSON. Because the
contract is already in place, this is a form and a `PATCH` route — not a rewrite.

This ordering is deliberate. Shipping the frame with fixed defaults proves the
contract against real widgets before anyone can save a broken layout into it.
