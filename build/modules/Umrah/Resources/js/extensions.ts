/**
 * Umrah's UI additions to core. A dashboard shows only the widgets in its
 * active tab's layout, so each widget is an async chunk loaded on first use.
 */
import { registerWidget } from '@/components/dashboard/widgets';
import { defineAsyncComponent } from 'vue';

registerWidget(
    'umrah.departures',
    defineAsyncComponent(() => import('./dashboard/widgets/DeparturesWidget.vue')),
);
registerWidget(
    'umrah.cash_book',
    defineAsyncComponent(() => import('./dashboard/widgets/CashBookWidget.vue')),
);
registerWidget(
    'umrah.cash_position',
    defineAsyncComponent(() => import('./dashboard/widgets/CashPositionWidget.vue')),
);
registerWidget(
    'umrah.agent_balances',
    defineAsyncComponent(() => import('./dashboard/widgets/AgentBalancesWidget.vue')),
);
registerWidget(
    'umrah.vendor_balances',
    defineAsyncComponent(() => import('./dashboard/widgets/VendorBalancesWidget.vue')),
);
registerWidget(
    'umrah.needs_attention',
    defineAsyncComponent(() => import('./dashboard/widgets/NeedsAttentionWidget.vue')),
);
registerWidget(
    'umrah.transport_readiness',
    defineAsyncComponent(() => import('./dashboard/widgets/TransportReadinessWidget.vue')),
);
registerWidget(
    'umrah.refunds_awaiting_decision',
    defineAsyncComponent(() => import('./dashboard/widgets/RefundsAwaitingDecisionWidget.vue')),
);
registerWidget(
    'umrah.operations_summary',
    defineAsyncComponent(() => import('./dashboard/widgets/OperationsSummaryWidget.vue')),
);
