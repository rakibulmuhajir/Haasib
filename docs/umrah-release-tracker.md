# Umrah release tracker

Last reconciled: 11 September 2026.

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

**Selected: travelling-party vouchers across import groups and agents. In progress locally; not release-ready.**

11 September payment acceptance: normal browser transfer, hotel approval, separate agent receipts, supplier payment and statements reconciled correctly. **Two discovered payment defects are now fixed locally:** negative/malformed allocations are rejected rather than saved as credit, and both payment-entry forms use the local calendar day. Browser retesting confirmed rejected attempts create no receipt and valid partial allocation/remaining credit still works. Regression rerun: **85 backend tests / 712 assertions**, plus **20 frontend tests**; build and targeted lint passed. See [payment browser test evidence](umrah-payment-browser-test-2026-09-11.md) for exact records, amounts and remaining scope. These two blockers are cleared; user acceptance and the remaining feature-set gates still apply. Not deployed.

10 September browser correction: fixed a long-label dialog overflow that dismissed Move without submitting. A real browser round trip now persists and writes both audit records. Both group pages show current travelling-voucher assignments and a separate incoming-passenger table; visa-status controls removed from group detail/add/correct. Agent privacy and inactive assignment coverage included. Latest scoped regression: **127 passed, 1,037 assertions**; frontend build required and verified locally. This does not constitute a production release or completion of unrelated acceptance gates below.

Governing rule: previous purchases remain with the purchasing agent and supplier. Subsequent services are billed to the agent buying those services. A change of travelling companions does not move historical balances, payments or supplier costs.

Local implementation now supports company-authorized cross-agent draft and approved transfers, original-provider print/PDF and Operations attribution, destination-agent hotel accounting and explicit group-leader checkboxes. Approved transfers retain existing purchases and record versioned manifest history; pending amendments and shared hotel billing are blocked. Staff approved-to-approved transfer, leader interaction and recipient-agent read/access controls have browser coverage. New-payment ownership/rollback, contact isolation and different-date cohorts have HTTP/service acceptance coverage. A discovered full-import-batch pickup-manifest defect has been corrected; ambiguous same-day vehicle allocation is flagged, not guessed. User acceptance (including the complete clerk payment screen and the documented exclusions) remains required before release. The leader UUID migration has been applied locally only. See [feature-set scope and verification](umrah-cross-agent-voucher-feature-set-2026-09-09.md).
