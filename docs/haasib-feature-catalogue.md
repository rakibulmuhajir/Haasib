# Haasib feature catalogue

Reviewed: 14 September 2026. Companion: [prioritized gap list](haasib-feature-gaps.md).

## Purpose and evidence rules

This is the planning source for industry-selection previews, homepage copy, and the next development slices. It records product capabilities, not every field, endpoint, or action. No application behavior or public claims were changed by this review.

Evidence: current source inspection, the recorded acceptance/release documents linked below, and the navigation regression results from this task. This is not a fresh end-to-end audit or production inspection. Tests found on disk are coverage leads, not newly passing results. A file or route proves implementation presence, not workflow completeness.

- **Present:** a relevant implementation was inspected; complete acceptance is not established.
- **Partial:** foundations exist with a known missing part or incomplete coverage.
- **Planned:** proposed work; not included in the available product.
- **Recorded verified:** linked records report scoped successful checks, subject to their exclusions.
- **Released:** release evidence explicitly includes the capability. Git push/merge is not deployment.
- **Unknown release:** no precise deployment evidence was established in this review; does not mean unavailable.

Public-copy decision: **Candidate** means wording is drafted but must pass the linked gap/release check before publication. **Scoped** means explicit release evidence supports only the stated scope; retain noted acceptance limitations. **Hold** means do not list as an included capability. Public visibility never grants access or enables a module.

## Industry profiles

Current configured selections: Petrol Pump (`fuel_station`), Travel (`travel`), Other (`other`). Older `umrah` values are still supported in application logic. Proposed display rename: Other -> General Business; retain its code.

| Profile | Promise | Scope boundary |
|---|---|---|
| Petrol Pump | Keep daily sales, fuel stock, customer balances and station accounts connected. | Fuel operations plus the relevant shared capabilities; investors and payment channels depend on station settings. |
| Travel / Umrah | Organize issued passenger lists, bookings, vouchers, stays, movements and their accounts. | Current specialization is post-visa Umrah operations. Do not imply flight inventory, ticket issuance, visa applications or Nusuk processing. |
| General Business | Manage sales, purchases, money and stock from one business workspace. | Shared business features, with inventory/payroll only where enabled. No implied specialization for manufacturing, hospitals or hotels. |

Sources: [industry configuration](../build/config/company-industries.php), [company bootstrap](../build/app/Services/CompanyBootstrapService.php), [Umrah scope](umrah-prioritized-feature-backlog.md).

## Shared and General Business capabilities

Applicable to General Business and specialist companies only where enabled and exposed by their workflow and permissions. A shared backend does not mean every specialist interface exposes every shared screen.

| ID | Capability / proposed customer wording | State and evidence | Release / public copy | Main gap |
|---|---|---|---|---|
| C01 | Create and manage separate companies | Present: company creation/controller and switcher [S1] | Unknown / Candidate | G01: verify all creation paths and context switches |
| C02 | Choose your business currency; add another when needed | Present; currency creation test exists [S1,S2] | Unknown / Candidate | G02: confirm manual-rate scope and enabled-currency behavior |
| C03 | Give staff appropriate access | Present: permission context, company roles, permission presenter [S3] | Unknown / Candidate | G03: verify navigation and actions for actual roles |
| C04 | Record sales and customer invoices | Present: accounting routes/nav [S4] | Unknown / Candidate | G04: complete sale-to-collection acceptance |
| C05 | Track customer receipts and outstanding balances | Present: accounting payment/customer surfaces [S4] | Unknown / Candidate | G04: partial allocation, overpayment and correction cases |
| C06 | Manage credit notes | Present: navigation and currency test [S4,S2] | Unknown / Candidate | G04: verify full credit/refund lifecycle |
| C07 | Record purchases, supplier bills and payments | Present: bill/payment routes; posting test exists [S4,S2] | Unknown / Candidate | G05: full and partial payment, credits and source links |
| C08 | Manage supplier credits | Present: accounting navigation [S4] | Unknown / Candidate | G05: acceptance beyond screen availability |
| C09 | Record expenses | Present: accounting routes; specialist expense surfaces [S4,U1,F1] | Unknown / Candidate | G06: common expense workflow and report reconciliation |
| C10 | Manage products, warehouses and stock movements | Present: inventory navigation [S5] | Unknown / Candidate; optional module | G07: stock/value reconciliation and enable/disable behavior |
| C11 | View bank accounts and reconcile transactions | Present: banking routes/nav [S4] | Unknown / Candidate | G08: distinguish reconciliation/feed UI from live bank connectivity |
| C12 | Manage employees, payslips and salary advances | Present: payroll navigation; multicurrency test exists [S6,S2] | Unknown / Candidate; optional module | G09: approval/payment/recovery acceptance |
| C13 | Review profit and detailed accounting records | Present: reports, journals, accounts and fiscal-year links [S4] | Unknown / Candidate | G06: figures, source links and plain-language presentation |
| C14 | Work in one consistent business workspace | Partial: General Business home and module navigation exist [S7] | Unknown / Candidate only for existing functions | G10: task-oriented home/navigation and guided setup |
| C15 | Configure tax treatment | Implementation lead only: Tax module exists but its navigation is empty [S8] | Unknown / Hold broad tax claims | G11: establish usable scope; no filing/compliance promises |

## Petrol Pump capabilities

| ID | Capability / proposed customer wording | State and evidence | Release / public copy | Main gap |
|---|---|---|---|---|
| F01 | Set up station products, tanks, pumps and opening information | Present: station onboarding and settings [F1] | Unknown / Candidate | G12: finish the complete fresh-station setup acceptance |
| F02 | Close the day with sales, readings and cash reconciliation | Present: DailyCloseService and create screen [F1] | Unknown / Candidate | G13: representative balanced and mismatched closes |
| F03 | Review close history and make traceable corrections | Present: amendment chain, reversal and lock methods [F2] | Unknown / Candidate | G13: duplicate-day, correction, locks and posting integrity |
| F04 | Track fuel stock, deliveries and tank differences | Present: stock, receipts and tank-reading surfaces [F1,S5] | Unknown / Candidate | G14: delivered/sold/measured stock and journal reconciliation |
| F05 | Maintain fuel prices | Present: rate service and routes [F1] | Unknown / Candidate | G14: price changes within a close and historical prices |
| F06 | Track credit customers and Amanat deposits | Present: customer/amanat services and routes [F1] | Unknown / Candidate | G15: collections, limits, deposit receipt/use/withdrawal |
| F07 | Settle card and clearing balances | Present: vendor-card and clearing settlement methods include fee handling [F3] | Unknown / Candidate | G16: partial settlement versus fees; prevent duplicate settlement |
| F08 | Track partner and optional investor balances | Present: partner routes and investor service; investor feature flag [F1,F4] | Unknown / Candidate; optional investor tracking | G15: lot/entitlement/settlement acceptance and role visibility |
| F09 | Connect staff advances with station cash and payroll | Present foundations: daily-close/payroll integration [F1,S6] | Unknown / Candidate | G09: reconcile advances and recovery across modules |
| F10 | Review station performance, product profit and stock variance | Present: replacement report routes/services [F5] | Unknown / Candidate | G17: reports, filters, source records and real export delivery |
| F11 | Reach daily close, stock, purchases and people quickly | Implemented; navigation smoke checks and four tenant-sharing regression tests passed in this task [F4] | Pushed to main, deployment unknown / Candidate | G03: authenticated desktop/mobile and restricted-role QA |

Do not use the unchecked [handover checklist](petrol-pump-module-handover-checklist.md) as proof that features are missing. The older [reporting audit](petrol-pump-reporting-audit.md) is historical: current sales/shrinkage routes redirect to replacement reports. An export-shaped legacy URL redirect is not evidence of a working export.

## Travel / Umrah capabilities

| ID | Capability / proposed customer wording | State and evidence | Release / public copy | Main gap |
|---|---|---|---|---|
| U01 | Import and preview issued passenger lists | Recorded verified: row validation, exclusions, same-booking duplicate checks and retry protection [U2] | Included in Sep 14 release [U3] / Scoped | G18: representative Nusuk fields; no complete booking-import claim |
| U02 | Book the services your company actually sells | Recorded verified: service-selective Quick Booking and commercial pricing [U4] | Exact production version not established in tracker / Candidate | G19: verify deployed version and representative booking types |
| U03 | Maintain dated prices and agent-specific rates | Recorded verified for visa/transport/hotel scope [U4] | Exact production version not established / Candidate | G19: do not imply add-on/package coverage |
| U04 | Prepare vouchers with your branding and contact details | Recorded verified; user-confirmed release Sep 9 [U3,U5] | Released as recorded / Scoped | G20: real PDF/download delivery and permitted data |
| U05 | Manage travelling parties across purchase groups | Recorded verified: transfers/direct joins preserve original purchases [U3,U6] | Included in Sep 14 release / Scoped | G19: retain documented excluded transfer cases |
| U06 | Track arrivals, stays and transport movements | Present and recorded verification in Operations documents [U1,U3] | Prior release recorded; exact original version unspecified / Candidate | G20: dispatch/actual execution boundaries |
| U07 | Track agent/customer receipts, supplier payments and booking accounts | Recorded verified for scoped booking/payment workflows [U3,U7] | Allocation safeguards included in Sep 14 release / Scoped | G19: preserve service ownership and reconciliation rules |
| U08 | Search within Groups and Vouchers | Recorded verified: scoped search and agent isolation [U3,U8] | Included in Sep 14 release / Scoped | G03: regression with roles and cross-agent records |
| U09 | Save personal Operations views and export movement data | Recorded implementation/tests [U3,U9] | Included in Sep 14 release / Scoped for saved views; hold export reliability claim | G20: CSV/PDF download acceptance remains unverified |
| U10 | Track hotel/transport supplier confirmation and booking readiness | Recorded verified locally, with explicit exclusions [U10] | Not deployed per latest release tracker / Hold until accepted/released | G21: release acceptance and required migrations |
| U11 | Reuse complete Umrah package templates | Missing per canonical backlog [U1] | Planned / Hold | G22: optional templates with booking snapshots |
| U12 | Sell configurable ziyarat and add-on services | Missing per canonical backlog [U1] | Planned / Hold | G23: pricing, fulfillment and accounting design |
| U13 | Track actual transport execution and room allotments | Later ideas in backlog; existing dispatch is not execution tracking [U1] | Proposed / Hold | G24: validate operator need before scheduling |

## Quick Commands

Proposed public name: **Quick Commands**. Technical/help name: command palette. This is an optional way to use the same product, not a paid tier or an owner/accountant mode switch. This naming is a planning recommendation, not a change to existing product copy.

| ID | Capability | State and evidence | Release / public copy | Main gap |
|---|---|---|---|---|
| Q01 | Open commands from the header or keyboard | Present: header trigger, shared visibility state, palette component [Q1] | Unknown / Candidate after keyboard QA | G25 |
| Q02 | Choose commands with suggestions and guided fields | Present: parser, schemas, autocomplete, field chips and entity pickers [Q1] | Unknown / Candidate after workflow QA | G25 |
| Q03 | Reuse recent commands and act on results | Present: history/frecency, output tables and quick actions [Q1] | Unknown / Candidate after scope/privacy QA | G25 |
| Q04 | Manage selected company/customer/invoice/payment/user/role tasks | Partial: 25 declared frontend command schemas across six entities [Q2] | End-to-end coverage unverified / Hold blanket management claim | G26 |
| Q05 | Apply the same permissions and company context as screens | Backend checks exist; full parity unverified [Q3] | Unknown / Hold parity claim | G26 |
| Q06 | Safely retry commands without duplicate financial writes | Partial: backend has optional idempotency-key handling; inspected client has no X-Idempotency-Key emission [Q1,Q3] | Unknown / Hold reliability/automatic-retry claim | G27 |
| Q07 | Manage purchases, stock, station and travel operations through commands | Not declared in inspected palette schemas; backend actions may exist [Q2,Q3] | Planned expansion / Hold | G28 |

Declared schema inventory (not a promise that every command works): company create/list/view/switch/delete; customer create/list/view/delete; invoice create/list/view/send/void/duplicate; payment create/list/view/void; user create/list/view; role create/list/view. Total: 25.

Old CLI documents disagree on custom Vue versus terminal libraries and contain aspirational GUI-parity/offline claims. Retain the existing Vue implementation and reconcile the specification before expansion. AI/free-form natural-language understanding, bulk financial execution, arbitrary shell commands and mobile terminal support are not included features.

## Industry-preview and homepage copy candidates

These are editorial selections of catalogue IDs, not automatic publication authorization. Show only capabilities verified for the deployed version; optional flags and plan entitlements must agree with the list.

| Profile | Suggested first six capabilities |
|---|---|
| Petrol Pump | Daily close (F02), tanks/stock/deliveries (F04), prices (F05), customers/Amanat (F06), supplier bills/payments (C07), station reporting (F10) |
| Travel / Umrah | Passenger import (U01), service-based booking (U02), vouchers (U04), travelling parties (U05), Operations (U06), receipts/payments/accounts (U07) |
| General Business | Sales/invoices (C04), collections (C05), purchases/payments (C07), expenses (C09), optional stock (C10), reports (C13) |

Before selection: explain shared company records, connected operations and controlled staff access. After selection: title, one-sentence benefit, six capabilities, optional features, and what setup comes next. Desktop: form left, preview right. Mobile: preview after industry selection. Both creation paths must agree. Planned features belong in a separate roadmap, never mixed into included features.

Homepage order: core promise -> shared capabilities -> industry profiles -> connected workflow -> Quick Commands once verified -> support/trust -> FAQ and demo invitation. Do not imply bank integrations, statutory tax filing, automatic exchange rates, general visa processing, universal passenger search, AI commands or fully automated reconciliation without separate evidence.

## Source index

- S1: [company creation](../build/resources/js/pages/companies/Create.vue), [inline company creation](../build/resources/js/pages/companies/Index.vue), [controller](../build/app/Http/Controllers/CompanyController.php).
- S2: [company currency test](../build/tests/Feature/CompanyCreationCurrencyTest.php), [bill posting test](../build/tests/Feature/Accounting/BillPaymentPostingTest.php), [credit-note currency test](../build/tests/Feature/Accounting/CreditNoteCurrencyTest.php), [payroll currency test](../build/tests/Feature/Payroll/PayrollMulticurrencyTest.php), [currency contract](contracts/multicurrency-rules.md).
- S3: [company middleware](../build/app/Http/Middleware/IdentifyCompany.php), [permission presenter](../build/app/Services/UserPermissionPresenter.php), [permission constants](../build/app/Constants/Permissions.php).
- S4: [accounting navigation](../build/modules/Accounting/Resources/js/nav.ts), [web routes](../build/routes/web.php).
- S5: [inventory navigation](../build/modules/Inventory/Resources/js/nav.ts).
- S6: [payroll navigation](../build/modules/Payroll/Resources/js/nav.ts).
- S7: [company home](../build/resources/js/pages/company/Show.vue), [navigation registry](../build/resources/js/navigation/registry.ts).
- S8: [Tax navigation](../build/modules/Tax/Resources/js/nav.ts).
- F1: [fuel routes](../build/modules/FuelStation/Routes/fuel.php), [fuel services](../build/modules/FuelStation/Services), [handover checklist](petrol-pump-module-handover-checklist.md).
- F2: [close amendments](../build/modules/FuelStation/Services/DailyCloseAmendmentService.php).
- F3: [settlements](../build/modules/FuelStation/Services/VendorCardSettlementService.php).
- F4: [navigation plan/status](petrol-pump-navigation.md), [access resolver](../build/modules/FuelStation/Services/FuelNavigationAccess.php), [tenant regression](../build/tests/Unit/FuelNavigationSharingTest.php). The later stdClass fix is commit `4d71452a`; it supersedes the initial middleware implementation in `1afd7197`.
- F5: [performance](../build/modules/FuelStation/Services/StationPerformanceReportService.php), [profitability](../build/modules/FuelStation/Services/ProductProfitabilityReportService.php), [variance](../build/modules/FuelStation/Services/StockVarianceReportService.php), plus F1 routes.
- U1: [canonical Umrah backlog](umrah-prioritized-feature-backlog.md).
- U2: [import evidence](umrah-import-preview-feature-set.md).
- U3: [release tracker](umrah-release-tracker.md). Sep 14 release `3bec8835d2495fb53bd14c24f7e4ea51628b75f3` supersedes older not-deployed notes for its explicitly included scope, not later hotel/readiness work.
- U4: [commercial pricing evidence](umrah-commercial-pricing-e2e-2026-09-07.md).
- U5: [voucher presentation evidence](umrah-voucher-print-feature-set-2026-09-07.md).
- U6: [travelling parties](umrah-cross-agent-voucher-feature-set-2026-09-09.md), [direct joins](umrah-direct-join-2026-09-13.md).
- U7: [payment evidence](umrah-payment-browser-test-2026-09-11.md).
- U8: [scoped search](umrah-scoped-search-feature-set.md).
- U9: [saved views](umrah-saved-operations-views.md), [CSV](umrah-operations-csv-feature-set.md), [browser acceptance limitations](umrah-browser-acceptance-2026-09-14.md).
- U10: [hotel confirmations](umrah-hotel-confirmations-feature-set.md), [booking readiness](umrah-booking-readiness-feature-set.md).
- Q1: [palette component](../build/resources/js/components/palette/CommandPalette.vue), [palette composable](../build/resources/js/composables/palette/useCommandPalette.ts), [palette sources](../build/resources/js/palette).
- Q2: [frontend command schemas](../build/resources/js/palette/schemas.ts).
- Q3: [command controller](../build/app/Http/Controllers/CommandController.php), [API routes](../build/routes/api.php), [bus map](../build/config/command-bus.php), [bus-map test](../build/tests/Feature/CommandBusMapTest.php).
- Historical proposals, not availability evidence: [CLI specification](../cli-specs.md), [palette specification](../cli-palette.md), [old feature briefs](next-feature-briefs.md).
