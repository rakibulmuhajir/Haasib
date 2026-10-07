# Value trails

Fuel Profit is the first screen to offer a connected explanation of a number. Hover or focus a dotted figure for its immediate explanation; click or tap it to open **How this was calculated**. Follow the contributing values in one panel, use Back or the breadcrumbs to return, and open the original record when permitted. Escape closes the panel.

## Personal preference

**Personal settings → Appearance → Show where values come from** is saved in `auth.users.settings.show_value_trails`. Missing means enabled. It applies across companies and devices. Turning it off renders plain figures, hides existing `Hint` affordances, and prevents loading the explanation graph. Glossary `Explain` entries remain available. Saving preserves unrelated personal preferences and only changes the authenticated user.

## Current coverage

- Trial Balance: net debit/credit balances and their totals into signed journal entries, preserving the side on which each account's net balance falls.
- Receivables/payables ageing: total unpaid, overdue, buckets and party amounts into saved invoice/bill balances. Due-date fallback, bucket boundaries, excluded statuses and the report's current-balance semantics remain unchanged. The selected date controls ageing and document cutoff; it does not reconstruct past payment balances.

- Accounting Profit & Loss: headline income, expenses and profit, and account amounts into their contributing journal entries. Period/source breakdowns retain plain figures.
- Balance Sheet: assets, liabilities, equity, difference and account amounts, including retained earnings into all signed income/cost entries through the as-of date.
- Bank/cash account statements: opening balance into prior postings and closing balance into opening plus the exact visible signed movements. The statement's reversed-entry filter is preserved.
- Customer/supplier statements: opening and closing balances into the exact saved subsidiary documents (invoices/bills, payments and credits). Combined/category/selected-party balances follow each included party; document links require their individual view permission. No ledger totals replace the customer/vendor attribution of these statements.
- Employee statements: opening/closing into the existing saved payroll movements, without double-counting advance recovery. Expected salary before a payslip exists remains explicitly estimated, including combined balances. Saved payslip links require payslip-view permission; movements without navigable evidence remain unavailable.
- Amanat statements: opening/closing into saved deposits, payouts and fuel purchases using the existing journal-date fallback and void filtering. Source journals require journal-view permission; unlinked movements have no invented source link.
- Partner and expense statements: opening and closing balances into their own signed ledger contributions. Partner capital/drawings use credit minus debit, including negative withdrawals; expense statements retain each account's normal balance. Combined/selected statements and reversed-pair filtering follow the existing report.

Accounting graphs use optional evidence collected by the same ledger queries as the reported amounts, preserving each report's existing status/date filters. Report permission and the personal preference gate loading; journal-view permission gates source links. The graph response refreshes figures and filters together.

- Total, product and period sales, cost of sales, gross profit, sold quantity and purchased quantity.
- Daily close sources: saved nozzle meters, returns and historical price segments when those segments reconcile to recorded amounts; other-sale quantities and saved prices.
- Direct deliveries: invoice line totals and historical unit prices; bill direct quantities and the allocated bill cost.
- Live fuel cost correction journals and month-end stock valuation journals.
- Estimated other-sale costs are marked on their unit cost, cost and profit, through product, period and total levels.

- Book profit follows sales, opening and closing stock account postings, and stock-statement purchases. Shared stock accounts retain the report's unavailable book-profit behavior.
- Margins and average prices follow the applicable profit, sales, cost and quantity roots. Product margins use book profit when available; period margins use gross profit, matching the displayed report.
- Stock variance follows saved tank readings and reading corrections. Valuation using today's average cost is explicitly estimated; correction snapshots supply historical costs when available.
- Rate snapshots follow saved old/new prices and split quantities. Their estimated effect remains separate from recorded sales.
- Fuel Home's Today and History profit statements follow signed ledger entries through sales, costs, dip loss, expenses, salaries, other income/costs, gross profit and net profit. Discounts and reversals retain their ledger signs.
- Payroll's monthly saved-payslip figures and payslip totals follow gross pay, deductions and net pay into saved lines. Quantity × rate is exposed only when the saved inputs reconcile. Draft estimates, employee profiles, attendance, leave, setup and travel voucher workflows receive no new trails. Payroll evidence requires `payslip.view`, honours the personal switch, and preserves each payslip's currency. Missing or inconsistent historical details are shown as saved amounts rather than fabricated formulas.

A saved price proves the price used for a sale, not who originally configured the product price. Missing historical evidence is explicitly described rather than reconstructed from today's price. A source document's creator is shown only when readable, and does not imply that they originally set the price.

Payslips also show Remaining to pay. Its trail uses the saved whole-payment state: net pay less the recorded payment. Undoing payment restores the amount outstanding; directly referenced reversed payroll payment journals appear as zero-effect evidence, subject to journal-view permission. Draft amounts remain explicitly unapproved; voided/cancelled payslips have no amount payable. No partial-payment allocation is inferred from a larger daily-close journal.

## Data and request contract

Core exploration sends `X-Value-Trail-Node`, an offset and the previous graph version through an Inertia partial reload. Responses contain the requested node and at most 40 immediate child summaries. Original source details and deeper contributions arrive when that child is opened; Show more fetches the next 40. The client merges batches without losing breadcrumbs or cached detail. Changed evidence invalidates the version instead of mixing old and new contributions. Preferences and source permissions are checked on every request. Report calculations and server graph construction still run against the full contributing data; batching bounds response payloads, not database work. Consumers without these headers retain the full graph contract.

`ProductProfitabilityReportService::run(..., includeTrail: true)` collects contributions where the report actually adds their amounts. Other report consumers do not collect a graph. `ProfitValueTrail` returns `nodes` keyed by node ID, `roots` keyed by `total:{field}`, `product:{key}:{field}` or `period:{key}:{field}`, and `context` containing the applied report filters. Nodes carry their value, unit, formula, explanation, estimate flag, child IDs and optional original-record evidence.

The page requests the optional Inertia `valueTrail` prop only when a figure is opened. The same response refreshes displayed report figures, so a newly recorded sale cannot leave the screen showing one total and its explanation showing another. Filter changes cancel pending requests and clear cached evidence. Network or server failures show an inline retry state and a Sonner error.

The report FormRequest requires `Permissions::REPORT_VIEW` and valid company/RLS context. Every query retains its company and date filters. `ProfitValueTrailPresenter` requires the corresponding document-view permission before returning a source reference, actor or link; otherwise it returns a restricted-source marker. The account preference also blocks graph collection server-side. Graphs and formulas are read-only; no accounting data or posting logic changes.

## Reuse

`Hint` is the common entry point for every module: hover/focus for a short explanation, click/tap to follow a supplied trail. `ExplanationTrigger` supplies the same dotted border for `Hint` and glossary `Explain`, including nested `MoneyText` amounts whose inline-flex layout prevents inherited text decoration. Existing `Hint` content slots remain compatible. `ValueTrailTrigger` is only a compatibility wrapper; do not add new usages.

`useValueTrail`, `ValueTrailPanel`, `useValueTrails` and the `ValueTrail` TypeScript contract live in core shared resources. Context is module-defined, with an optional label or date range; Fuel-specific filters are not required. The composable owns lazy loading, request cancellation, stale-response protection, cache invalidation, loading/error/retry states and the user preference. A module supplies its current context, displayed-data snapshot and the report props to refresh together with evidence.

Example module integration:

```vue
<script setup lang="ts">
import Hint from '@/components/Hint.vue'
import ValueTrailPanel from '@/components/ValueTrailPanel.vue'
import { useValueTrail } from '@/composables/useValueTrail'

const props = defineProps<{ balance: number; customerId: string; currency: string }>()
const { open, loading, error, trail, root, load } = useValueTrail({
  refresh: ['balance'],
  context: () => props.customerId,
  snapshot: () => props.balance,
  snapshotFromPage: (page) => page.balance,
})
</script>

<template>
  <Hint trail="customer:balance" preview="Invoices less payments and credits">{{ balance }}</Hint>
  <ValueTrailPanel v-model:open="open" :loading="loading" :error="error" :trail="trail"
    :root="root" :currency="currency" @retry="load" />
</template>
```

The module must provide an optional Inertia `valueTrail` graph with the declared root; the example is an integration pattern. All existing contextual hints across modules inherit the shared affordance immediately. Detailed evidence covers the financial reports listed above, including Fuel, Payroll and Accounting statements. Each module must collect its own evidence beside its actual calculations. Do not calculate accounting formulas again in the frontend or create a separate query that silently uses different filters.
