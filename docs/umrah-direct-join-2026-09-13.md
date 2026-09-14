# Direct joining before the next service — 13 September 2026

Status: implemented locally, not deployed. Extends the [travelling-party feature set](umrah-cross-agent-voucher-feature-set-2026-09-09.md).

## Agreed scope

A passenger bought visa/transport in import group A, but will buy hotels with party B. Company staff open B's ordinary draft voucher and choose **Add existing passenger**. Search by passenger name, passport or original group number, select and add. No source voucher, identity re-entry, cancellation, refund or historical debt transfer is required.

Both group detail and group accounting show a notice above balances/calculations:

- A: passenger name is travelling with group B.
- B: passenger name joined from group A.

The import group remains the purchase reference. Its visa/transport count and bills are not reduced; B's original visa/transport count is not increased. New company hotel bookings use B's purchasing agent/rates and actual rooms/beds/nights. Adding a passenger does not silently reserve another bed or change an already purchased service. Staff must review accommodation before approval.

## Guardrails

Only same-company staff with voucher-update permission can search/add. Agent logins cannot search across agents or perform this join. Group references in notices retain existing agent privacy restrictions. Already assigned, unavailable, deleted or cancelled-source passengers are rejected atomically. Approved, amendment and shared-billing destinations are excluded. Duplicate requests do not duplicate membership or audit records. Both original and receiving groups have audit entries; no accounting entry is created by the join.

## Automated verification

- CrossAgentVoucherTransferTest: **62 passed / 639 assertions**, including no-source-voucher joins, both group/accounting notices, unchanged purchase/payment snapshots, duplicate/invalid input, already assigned passengers, agent and tenant isolation, and hotel approval using the destination agent's rate through both direct joining and existing voucher transfer.
- GroupAccountingServiceTest, OperationsTest and VoucherDraftEditingTest: **44 passed / 375 assertions**.
- Targeted ESLint and PHP Pint passed. Production build passed; existing font-resolution warnings remain.

## Local browser fixture

Created synthetic records only in `demo-babalsalam-travel` with prefix `QA-JOIN-0913`; no existing customer records were changed. Group A has two purchase passengers and no source voucher. Group B initially has one purchase passenger and a draft voucher. A1 was selected through the new picker and added to B; B displayed two travelling passengers. Both group-detail notices displayed the passenger and opposite group number. Purchase receivables remained A: PKR 2,400 and B: PKR 1,200.

Browser checking caught a stale embedded print preview after membership changed. The preview revision now includes passenger IDs, so pivot-only membership changes invalidate the displayed copy.

Retest passed: adding synthetic A2 through the Search button and checkbox updated both the header and embedded printed PAX count to **3 without reloading**. A1 no longer appeared in the unassigned-passenger search, and the empty-results explanation was displayed. Both group-accounting pages were checked in the browser: notices appeared before Used Services and original receivables stayed unchanged. Final fixture has both A passengers travelling on B's draft; A still retains its two original purchase passengers, and B retains its one original purchase passenger. No hotel was approved in the demo for this check; hotel posting behavior is covered by the automated tests above.

Fixture IDs:

- Original group A: `01a09ad7-86f4-7378-9f99-ba5fbfd44940`.
- Receiving group B: `01a09ad7-8a24-7380-b5c9-ac260acd27aa`.
- Draft voucher B: `01a09ad7-8b35-711e-be8b-2cdc3c439d92`.

No production deployment or live verification is claimed. User acceptance is the next release gate.
