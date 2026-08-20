/**
 * The dashboard widget registry — key → component.
 *
 * A dashboard shows only the widgets in its active tab's layout, so loading
 * every widget component up front would ship code that may never render.
 * `defineAsyncComponent` means a dashboard only pulls in the chunks for the
 * widgets it actually places.
 *
 * An unknown key is a DashboardGrid concern, not this file's — this map
 * simply may not have an entry for it, and the grid skips it silently.
 */
import { defineAsyncComponent, type Component } from 'vue'

export const widgetMap: Record<string, Component> = {
    'umrah.departures': defineAsyncComponent(
        () => import('@/components/dashboard/widgets/DeparturesWidget.vue'),
    ),
    'umrah.cash_book': defineAsyncComponent(
        () => import('@/components/dashboard/widgets/CashBookWidget.vue'),
    ),
    'umrah.agent_balances': defineAsyncComponent(
        () => import('@/components/dashboard/widgets/AgentBalancesWidget.vue'),
    ),
    'umrah.vendor_balances': defineAsyncComponent(
        () => import('@/components/dashboard/widgets/VendorBalancesWidget.vue'),
    ),
    'umrah.needs_attention': defineAsyncComponent(
        () => import('@/components/dashboard/widgets/NeedsAttentionWidget.vue'),
    ),
    'umrah.transport_readiness': defineAsyncComponent(
        () => import('@/components/dashboard/widgets/TransportReadinessWidget.vue'),
    ),
}
