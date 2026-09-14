# Umrah release tracker

Last reconciled: 14 September 2026.

## Current local feature set — hotel confirmations

**14 September — Implemented and tested locally; not deployed.** Per-stay Pending/Confirmed, separate optional BRN and confirmation number, internal notes/history, reconfirmation after material changes, legacy Not recorded and Agent-arranged states. Operations flags pending check-ins and links to the internal voucher tab. No pricing/accounting or passenger-print changes. **197 tests / 2,015 assertions**, build and targeted lint passed; built-in-browser QA save and queue checks completed. Requires one additive voucher-metadata migration (applied locally). See [scope, verification and acceptance checklist](umrah-hotel-confirmations-feature-set.md). User acceptance and explicit deployment authorization remain outstanding.

## Production release — 14 September 2026

The new hotel-confirmation slice above is **not** included in this already-completed production release. Final local follow-up: 26 focused tests / 138 assertions passed, including legacy blank-row identity; browser Operations action and pending-warning clearance verified.

**Deployed successfully:** application commit `3bec8835d2495fb53bd14c24f7e4ea51628b75f3` at 05:01 UTC (10:01 Asia/Karachi), using the documented server deploy.sh. This release includes the accumulated work authorized below, including commits `c884189f` and `77fdf24f`. Earlier "not deployed" entries below are historical and superseded for these included features; outstanding acceptance limitations are not erased.

- Fresh pre-release regression: 159 tests / 1,841 assertions passed; 16 payment-entry frontend tests passed; targeted ESLint and production build passed.
- Production PostgreSQL backup created with private file permissions and archive listing checked before deployment (path below).
- Group-leader and personal Operations-view migrations ran in production batch 27.
- Permission/role sync, production cache rebuild and queue restart completed; maintenance mode exited.
- Server HEAD confirmed as the application release above, with clean worktree. Live homepage, login and built JavaScript asset returned HTTP 200.
- No authenticated production booking/payment mutations were performed for smoke testing. CSV/PDF browser download delivery remains unverified; do not call this exhaustive live acceptance.

## 14 September release authorization

User explicitly authorized committing and deploying the accumulated work. Release includes payment-allocation safeguards, travelling-party vouchers/direct joins and group notices, Mutamer import preview/retry protection, scoped list search, Operations CSV and personal saved views. Production preflight found a clean main checkout at `138b825ad1aae5ea9c9ee10e1de43d9e31caef22`. Private PostgreSQL backup created and archive listing validated at `/home/ubuntu/haasib-backups/pre-umrah-20260914.dump` before deployment. Deployment outcome is recorded above. CSV/PDF browser download delivery remains an explicitly unverified acceptance item; earlier automated results are not a substitute.

## Sources of truth

- Product scope and priority: [prioritized backlog](umrah-prioritized-feature-backlog.md), especially its post-visa product boundary.
- Implementation: `AGENTS.md`, relevant schema contracts and frontend specifications.
- Acceptance evidence: the scoped feature/test reports linked below.
- Deployment status: this tracker. Local test success is not production verification.

## Recorded delivery

| Feature set | Implementation/test evidence | Release evidence |
| --- | --- | --- |
| Operations and role-appropriate home views | Earlier development history; maintained in current application | Previously discussed releases; exact version not recorded here |
| Commercial pricing, category/agent overrides and Quick Booking | [7 September verification](umrah-commercial-pricing-e2e-2026-09-07.md) | Tested implementation confirmed; precise production version not independently established |
| Voucher contacts/footer, logos, print-style view, passenger age/location table and linked stay nights/dates | [Voucher feature set](umrah-voucher-print-feature-set-2026-09-07.md), plus subsequent scoped test results in the development conversation | **Released — user confirmed on 9 September 2026.** Deployment commit/version and live smoke-test results were not supplied; do not infer them from local HEAD |

## Release procedure for each next feature set

1. Agree one bounded feature set and its exclusions in the backlog.
2. Inspect existing behavior before declaring features missing; update relevant contracts before schema changes.
3. Record acceptance cases including success, invalid input, permissions/tenant isolation, existing records and accounting effects where applicable.
4. Implement and run scoped automated tests plus browser checks; document remaining manual checks.
5. User acceptance, then explicit release authorization. Record backup/migration/build requirements.
6. Record the deployed commit/version, deployment date and who confirmed it. Do not label deployment independently verified without evidence.
7. Record live smoke checks without creating unintended approved/accounting transactions.

## Next feature set

**14 September — Hotel/transport cancellation and owner booking readiness implemented locally; not deployed.** Per-booking Pending/Confirmed/Needs reconfirmation/Cancelled states now record supplier context without changing accounting or automatically issuing refunds. Owner/staff Groups lists show separate green/orange/red/grey readiness based on the current travelling vouchers, original service ownership and the 72-hour arrival threshold. Transport provider confirmation remains separate from vehicle/driver dispatch. **209 tests / 2,104 assertions** passed across the focused regression; production build and targeted lint passed. See [scope and verification](umrah-booking-readiness-feature-set.md). User acceptance and explicit deployment authorization remain outstanding.

**14 September browser acceptance update:** user checked import and partial search. Built-in browser checks passed scoped search examples, seeded-agent isolation, saved-view replacement/persistence/privacy/removal, and screen/print report agreement. **CSV delivery remains unverified:** clicks produced no new observable Downloads file. Do not mark the full browser checklist complete. See [exact evidence and remaining checks](umrah-browser-acceptance-2026-09-14.md). Nothing deployed.

**13 September — Personal saved Operations views implemented locally, not deployed.** Save/open/remove and same-name replacement; rolling periods versus fixed custom ranges; current user/company isolation. Browser checks, Operations regression, build and targeted lint passed. Requires the new operation_views migration, applied locally only. See [scope and release notes](umrah-saved-operations-views.md). No specific city-direction filter or shared views in this slice.

**13 September — Scoped Groups/Vouchers search corrected and tested locally; not deployed.** Existing name/passport/group-number search retained; Group search now includes permitted current incoming passengers. Fixed stale agent-name lookup that could fail Voucher searches; wildcard characters are literal. **70 travelling-party/search tests / 1,017 assertions** passed. No accounting changes or migration. See [scope and evidence](umrah-scoped-search-feature-set.md).

**13 September — Mutamer import preview and reliability implemented locally and tested; awaiting user acceptance, not deployed.** Create Group now previews rows before adding them, shows row errors and same-file/current-form duplicate passports, allows explicit exclusions, and paginates 50 rows with a 500-passenger limit. Malformed/oversized workbooks are rejected; save retries preserve the original group and charges. Search remains scoped to Groups/Vouchers; no universal passenger search. **35 tests / 186 assertions**, targeted lint, PHP formatting, production build and synthetic-workbook browser checks passed. Tests include a posted visa purchase replay with unchanged transaction IDs and balances. No migration required. Issued visa/MOFA mapping remains separate pending a representative Nusuk workbook. See [scope and evidence](umrah-import-preview-feature-set.md).

## Other locally tested feature set awaiting acceptance

**13 September — Operations CSV export implemented locally and tested; awaiting user acceptance, not deployed.** This is the next bounded feature set authorized after the direct passenger-join work. Export CSV on Operations and its printable report reuses the same authorized projection, labels date-window summaries separately from filtered movement totals, retains local clocks and protects spreadsheet cells from formula injection. Applied filters are used rather than unsaved filter edits. **45 Operations tests / 533 assertions**, targeted lint, PHP formatting, production build and real browser download checks passed. See [scope and evidence](umrah-operations-csv-feature-set.md). Saved views, other reports and packages remain separate.

## Previous feature set awaiting release acceptance

**Selected: travelling-party vouchers across import groups and agents. In progress locally; not release-ready.**

13 September clarification: the primary workflow is joining another travelling party **before buying its hotel service**, not cancelling a previously purchased hotel. Added direct selection of existing unassigned passengers on an ordinary draft voucher; a source voucher is not required. Both purchase-group detail and service-accounting pages now show outgoing/incoming passenger notices above the calculations. Original visa/transport purchase membership, charges and payments stay unchanged; later hotels use the receiving voucher's agent and booked rooms/beds/nights. No new migration. See [direct-join verification](umrah-direct-join-2026-09-13.md). Not deployed; user acceptance remains required.

11 September payment acceptance: normal browser transfer, hotel approval, separate agent receipts, supplier payment and statements reconciled correctly. **Two discovered payment defects are now fixed locally:** negative/malformed allocations are rejected rather than saved as credit, and both payment-entry forms use the local calendar day. Browser retesting confirmed rejected attempts create no receipt and valid partial allocation/remaining credit still works. Regression rerun: **85 backend tests / 712 assertions**, plus **20 frontend tests**; build and targeted lint passed. See [payment browser test evidence](umrah-payment-browser-test-2026-09-11.md) for exact records, amounts and remaining scope. These two blockers are cleared; user acceptance and the remaining feature-set gates still apply. Not deployed.

10 September browser correction: fixed a long-label dialog overflow that dismissed Move without submitting. A real browser round trip now persists and writes both audit records. Both group pages show current travelling-voucher assignments and a separate incoming-passenger table; visa-status controls removed from group detail/add/correct. Agent privacy and inactive assignment coverage included. Latest scoped regression: **127 passed, 1,037 assertions**; frontend build required and verified locally. This does not constitute a production release or completion of unrelated acceptance gates below.

Governing rule: previous purchases remain with the purchasing agent and supplier. Subsequent services are billed to the agent buying those services. A change of travelling companions does not move historical balances, payments or supplier costs.

Local implementation now supports company-authorized cross-agent draft and approved transfers, original-provider print/PDF and Operations attribution, destination-agent hotel accounting and explicit group-leader checkboxes. Approved transfers retain existing purchases and record versioned manifest history; pending amendments and shared hotel billing are blocked. Staff approved-to-approved transfer, leader interaction and recipient-agent read/access controls have browser coverage. New-payment ownership/rollback, contact isolation and different-date cohorts have HTTP/service acceptance coverage. A discovered full-import-batch pickup-manifest defect has been corrected; ambiguous same-day vehicle allocation is flagged, not guessed. User acceptance (including the complete clerk payment screen and the documented exclusions) remains required before release. The leader UUID migration has been applied locally only. See [feature-set scope and verification](umrah-cross-agent-voucher-feature-set-2026-09-09.md).
