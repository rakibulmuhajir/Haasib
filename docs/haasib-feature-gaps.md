# Haasib prioritized feature gaps

Reviewed: 14 September 2026. Companion and evidence index: [feature catalogue](haasib-feature-catalogue.md).

## How to use this list

This is a proposed delivery order, not a declaration that every workflow below is broken or absent. **Verify** means existing implementation needs acceptance evidence. **Complete** means known foundations need an identified extension. **Build** means a new agreed capability. Priorities are recommended; effort sizes are relative, not delivery promises.

- **P0:** Resolve evidence, release or correctness uncertainty before publishing the affected claim or expanding that workflow. Not every P0 is a confirmed production defect.
- **P1:** Next useful product work after its dependencies are satisfied.
- **P2:** Later extension; keep out of current promises.
- **P3:** Validate demand before scheduling.

All work retains route-based company context, permissions/RLS, module boundaries, UUIDs, source-linked accounting, plain-language copy and full success/error/loading behavior. Existing request/business authorization remains authoritative; hiding a menu is not security enforcement.

## First delivery sequence

1. **Finish evidence and publication decisions:** G01-G03 plus the release/acceptance parts of G19-G21. Record the exact deployed version and restrictions for any feature we intend to describe publicly. This need not wait for every P2 idea.
2. **Build the industry feature preview:** G01, using catalogue IDs and only eligible copy. Cover both current company-creation paths. This can proceed while unrelated operational acceptance continues.
3. **Close specialist acceptance gaps:** prioritize G13/G14/G16 and G19/G20/G21 by client impact. Create one bounded implementation slice only when a concrete failure or missing step is established.
4. **Improve General Business:** G10, then complete the G04-G09 journeys through the new task-oriented workspace. Reuse the existing services rather than constructing another accounting system.
5. **Stabilize Quick Commands:** G25-G27 before G28. Discovery and read-only actions can progress before financial-command expansion.
6. **Publish homepage features:** select eligible catalogue rows, verify screenshots against the deployed release, and publish the industry narratives. Later roadmap items stay separate.

## Shared product and onboarding

| Gap | Priority / kind / effort | Applies to | Concrete completion criteria |
|---|---|---|---|
| G01 Industry feature preview and creation consistency | P1 / Build + verify / M | C01; all profiles | Dedicated and inline creation share the same profile content. Desktop form-left/preview-right; mobile preview below selection. Selection updates title, benefit, included/optional features and next setup steps without clearing values. Blank/unknown industry has a safe shared fallback. Other is labelled General Business without changing its code. Verify creation succeeds with minimum details and presents loading, inline errors and success feedback. |
| G02 Currency scope and setup parity | P0 before currency claims / Verify / S-M | C02 | Test one currency, optional second currency, rejected invalid rates, manual rate entry, rounding and allowed invoice/payment currency combinations against the contract. Compare screen and company.create command requirements. Document limitations in onboarding; do not claim automatic exchange-rate feeds. |
| G03 Company/role/module navigation acceptance | P0 / Verify / M | C03,F11,U08,Q05 | Test standard users as well as god-mode; company switching, legacy industry identifiers, disabled modules, direct URLs, desktop and mobile. Confirm the deferred fuel context fix on actual fuel and travel routes. Validate current permission mapping rather than assume every capability belongs to a broad accounting permission. No unrelated-company commands/data after switching. |
| G04 Complete sell-to-collect journey | P1 / Verify then complete / M | C04-C06 | Starting from a fresh General Business company, create/select customer, record invoice/sale, collect full/partial payment, view remaining balance and source-linked journal, and process a supported correction/credit. Verify retry/duplicate behavior and no ledger/account selection in the normal path. Record any missing step before coding it. |
| G05 Complete buy-to-pay journey | P1 / Verify then complete / M | C07-C08 | Bill -> stock receipt where applicable -> full/partial payment -> supplier balance -> credit/correction. Verify allocation, multi-source payment where supported, source links and cash/bank effects. Screens and totals agree; unsupported combinations are explained before submission. |
| G06 Expenses and understandable financial reports | P1 / Verify then complete / M | C09,C13 | Common expenses can be entered without chart-of-accounts expertise. Date filters, report totals and drilldowns reconcile to source records, including reversals/credits. Define which draft versus posted records count. Avoid invented charts or aggregate figures with no drilldown. |
| G07 General stock workflow | P1 / Verify then complete / M | C10 | Receipt, sale, adjustment and movement history reconcile in quantity and value. Warehouse/product selection, negative-stock policy and module-disabled behavior are explicit. Separate stock operations from product setup and preserve source links. |
| G08 Banking availability boundary | P0 before integration claims / Verify / S-M | C11 | Establish which feed/import/reconciliation workflows actually work. Verify import duplicates and statement matching where present. Document supported sources. A Bank Feed screen must not be marketed as an automatic bank connection without provider-backed evidence. |
| G09 Payroll and advances completion | P1 / Verify then complete / M | C12,F09 | Employee advance -> payroll approval -> recovery -> payment -> remaining balance. Verify currencies, partial payments and links from station close. Draft payroll does not silently appear as posted expense; disabled payroll disappears consistently. |
| G10 General Business workspace improvement | P1 / Design + complete / L, split into slices | C14 | First agree task groups: Today, Sales, Purchases, Stock, Money, People, Reports, Settings as a candidate, not a fixed eight-item header. Home answers what needs attention, who owes money, what must be paid, and stock concerns when enabled. Prioritize normal actions and contextual next steps. Reuse specialist patterns for guided setup, plain-language feedback and source links. Keep advanced accounting reachable. |
| G11 Establish actual tax product scope | P2 / Audit / M | C15 | Inventory usable tax configuration/calculation screens, rules, country support and tests. Produce a bounded supported-scope statement. Empty Tax navigation is a lead, not proof the module has no functionality. Do not promise statutory returns, filing or compliance certification. |

## Petrol Pump

| Gap | Priority / kind / effort | Applies to | Concrete completion criteria |
|---|---|---|---|
| G12 Fresh station setup | P1 / Verify then complete / M | F01 | Create a usable station with products, tanks, pumps/nozzles, staff, vendors, opening prices/stock/cash and enabled channels. Confirm one normal Products You Sell path and sensible accounting defaults. Setup remains resumable; ordinary operators are not asked to map ledger accounts. |
| G13 Daily close and corrections | P0 before close reliability claims / Verify / M | F02-F03 | Exercise normal close, missing/invalid readings, cash mismatch, mid-day rate change, duplicate submit/day, read-only posted close, reversal/amendment chain and month locks. Check source-linked balanced postings and effective-close totals. Fix failures as separate scoped tasks. |
| G14 Physical stock and fuel purchasing reconciliation | P0 / Verify / M | F04-F05 | Reconcile starting stock + receipts - sales +/- approved variance to ending stock and inventory value. Cover multiple tanks/products, shortages/gains and price history. No duplicate receipt or silent negative stock. Delivery, bill and journal link to one another where applicable. |
| G15 Customer, Amanat, partner and investor lifecycle | P1 / Verify then complete / M-L | F06,F08 | Validate credit sale/collection/limits; Amanat receipt/use/withdrawal; partner investment/drawing; and optional investor lots/entitlements/settlements separately. Publish only the cases accepted. Investors hidden when disabled. Do not infer complete investor accounting from a list/detail page. |
| G16 Settlement correctness and discoverability | P0 / Verify / M | F07 | Test vendor-card and generic clearing paths separately: no fee, explicit fee, partial receipt, retained outstanding balance and repeat submission. Determine when a receipt shortfall is a fee versus an unsettled balance; never let the UI silently make that choice. Link settlement, bank receipt and original sales. |
| G17 Current station reports and export delivery | P1 / Verify then complete / M | F10 | Test the current performance/profitability/expense/variance services, not the obsolete report assumptions. Confirm date/product filters, source drilldowns and GL/stock agreement. Actually receive and open any advertised CSV/PDF export. Legacy /export routes that redirect do not meet this acceptance. Distinguish variance reporting from a full claims-resolution workflow. |

There was no focused fuel posting/settlement suite found by the filename search in this review. This is not proof that no coverage exists under other names. Locate coverage before adding tests, and record the concrete cases still missing.

## Travel / Umrah

| Gap | Priority / kind / effort | Applies to | Concrete completion criteria |
|---|---|---|---|
| G18 Representative issued-list mapping | P1 / Complete after input evidence / M | U01 | Obtain a representative Nusuk workbook through an appropriate private test fixture process. Verify issued visa/MOFA mapping where required, without expanding into visa processing. Preserve existing preview and retry safeguards. Complete historical booking import is a separate scope. |
| G19 Booking/pricing/payment release acceptance | P0 / Verify / M | U02-U03,U05,U07 | Reconcile tracker with the deployed commit; run permitted booking-type, role and payment acceptance. Preserve original service purchase ownership when companions change. Keep known excluded amendment/shared-billing cases explicit. Do not rebuild already released joins, pricing or payment safeguards. |
| G20 Reports, downloads and operational boundaries | P0 for export claims / Verify then complete / M | U04,U06,U09 | Browser downloads must produce a readable file matching filters and role visibility. Resolve CSV/PDF delivery uncertainty recorded after automated tests. Verify saved-view isolation and rolling dates. Separate planned dispatch from actual pickup/completion tracking. Other report exports are additional scope. |
| G21 Hotel/transport confirmations and readiness release | P0 release gate / Accept + release / M | U10 | Review the existing scoped acceptance checklist, tenant permissions, reconfirmation after edits and cancelled/agent-arranged/legacy states. Verify additive migrations and deployment requirements, then follow the existing release process. Confirmation cancellation must not imply automatic supplier cancellation, refund or accounting reversal. Current main inclusion alone does not close this gap. |
| G22 Optional complete-package templates | P2 / Build / L | U11 | Validate demand; define reusable stays/transport/add-ons and approved pricing with immutable booking snapshots. Visa-only, transport-only and hotel-only companies can skip packages. Depends on stable service pricing and the accepted G23 boundary if add-ons are included. |
| G23 Configurable add-on services | P2 / Design + build / L | U12 | Define service, supplier, sale/cost, fulfillment and accounting ownership before implementing. Include voucher/Operations behavior only when relevant. Decide cancellation/refund semantics explicitly. |
| G24 Later specialist extensions | P3 / Discover / Unestimated | U13 | Validate room allotment inventory, actual transport execution and service-issue tracking with operators before selecting a bounded slice. No default commitment to all three. |

## Quick Commands

| Gap | Priority / kind / effort | Applies to | Concrete completion criteria |
|---|---|---|---|
| G25 Discovery, keyboard use and guided fields | P1 / Verify + refine / M | Q01-Q03 | Ctrl/Cmd+K opens consistently; Escape/focus restoration and keyboard selection work. Show current company prominently. Beginners choose an action and fill guided fields; experienced users use shortcuts. Filter suggestions by permissions/module. History and result state do not leak another company's information. Use the existing Vue palette. |
| G26 Command/GUI coverage and policy parity | P0 before command-management claims / Audit + fix / M-L | Q04-Q05 | Make a matrix of all 25 schemas: parser -> endpoint -> registered action -> validation -> permissions -> source transaction -> result/UI update. Verify aliases and quick actions too. One success and meaningful error/authorization case per supported workflow, including company.create consistency. Declared commands that fail stay unadvertised. |
| G27 Financial execution safety and retries | P0 before expansion / Complete + verify / M | Q06 | Issue a stable key per logical mutation and reuse it on an uncertain retry. Verify backend scoping by tenant/user/action as appropriate, payload mismatch handling and concurrent duplicate protection; optional key lookup alone is insufficient evidence. Preview consequential changes and handle validation/server errors. Do not automatically replay an unconfirmed financial action when connectivity returns. Use compensating reversal for posted financial corrections, not generic undo. Reconcile current fetch/global-endpoint code with the project's Inertia and route-context standards through a scoped design. |
| G28 Extend to ordinary and specialist workflows | P2 after G26-G27 / Build incrementally / L | Q07 | Start with vendors/bills and stock discovery, then guided draft creation, then accepted mutations. Add fuel/travel actions only through existing authorized business services. Select high-frequency actions from operator use; do not promise full GUI parity in one release. No raw shell execution, AI interpretation or batch financial automation is implied. |

## Homepage publication gate

For each included feature, record its catalogue ID, exact public wording, applicable industry/modules, deployed version, acceptance evidence and remaining limitations. Use two independent statuses: implementation/verification and deployment. Do not turn all source-present features green because this catalogue exists.

First version can use six concise capabilities per industry once those selected claims are eligible; it need not list every advanced control. Include Quick Commands only after its scoped supported-command list passes G25-G27. Future features can appear on a separately labelled roadmap without invented dates. Homepage changes are not implemented by these documents.

## Explicitly excluded or deferred

- Visa application/approval/passport handover queues and rebuilding Nusuk.
- Universal Umrah passenger search: retain the user-agreed Groups/Vouchers scope.
- Live bank feeds, statutory filing, AI commands or automatic exchange-rate feeds without separately established scope.
- A paid Pro tier or mandatory mode switch: Quick Commands is the recommended capability name, with packaging undecided.
- Automatic offline financial execution, generic undo of posted ledger entries, or separate accounting logic for commands.
- New vertical modules merely because they appear in old schema discussions.

## Maintenance and ownership

The product owner selects priorities and approves customer wording. The implementing engineer records exact implementation/test evidence. The release owner updates deployment status. These are responsibilities, not newly assigned people.

For each delivered slice: update its catalogue row and gap, link checks and known exclusions, and update the existing specialist release tracker when relevant. Retain history rather than overwriting a recorded release with an older planning note. Reassess effort after reproduction; no dates or staffing commitments are made here.
