/**
 * Shared shape every dashboard widget component is built against.
 *
 * A widget receives exactly `{ data, options }` per the contract
 * (docs/contracts/dashboard-widgets.md) — nothing else, and never resolves
 * its own query. `data` is whatever the backend `DashboardWidget::resolve()`
 * returned for this placement; another agent owns that shape and it may
 * shift by a field name here and there before it settles, so every widget
 * keeps `data` loose (`Record<string, any>` rows) and reads fields with
 * fallbacks rather than asserting a strict interface. A small backend naming
 * difference should be a one-line fix in one widget file, never a rewrite.
 */
export interface DashboardWidgetProps<T = Record<string, any>> {
    data: T | null | undefined
    options: Record<string, unknown> | null | undefined
}

/** A row of largely-unknown shape, as every widget payload row is until the
 *  backend's exact field names are confirmed. */
export type LooseRow = Record<string, any>
