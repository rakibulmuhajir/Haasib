# Hotel confirmations — first release slice

14 September 2026. Implemented locally; not committed or deployed. Local migration applied. Awaiting user acceptance.

## Operator workflow

Open a voucher → **Internal details & history** → **Hotel confirmations**. Each stay has its own row and Update action. Choose Pending or Confirmed; optionally enter a BRN, a separate hotel confirmation number, and an internal note. History records each update, staff name and timestamp.

Operations → Hotel check-ins → Needs attention includes pending bookings and stays needing reconfirmation. Its Update hotel confirmation action opens the internal voucher tab. Checkouts are not flagged merely because a confirmation is pending.

## Rules and boundaries

- Existing untouched stays show **Not recorded**. They are not silently declared confirmed or added to a new pending queue.
- New company stays begin **Pending**. Externally arranged stays show **Agent-arranged**, with no company confirmation action.
- Hotel identity/name/city, supplier, dates, room type/count and bed count changes invalidate the old confirmation. Reverting the change does not resurrect it. Notes-only changes and reordering preserve it.
- Confirmation is for one stay, not all hotels on the voucher. Stable server-owned UUIDs distinguish repeated stays.
- Amendments and separated vouchers do not copy confirmation metadata into the new voucher. An amendment starts with a fresh pending company-hotel workflow; the original retains its history.
- Company staff with voucher-update permission can maintain confirmations. Agents can read their own voucher references/status, but cannot write confirmations or receive internal notes/history. Unknown, cancelled, superseded and foreign-company targets are protected.
- Saving uses a database transaction, voucher row lock and matching stay-revision/version tokens. Stale forms must reload instead of overwriting newer work.
- Confirmed requires hotel, dates and room arrangement. References remain optional; no fake reference is required to record a real confirmation.
- Hotel confirmation metadata is stored separately from pricing snapshots. Saving it does not reprice, post, move payments or alter the passenger print layout.
- No new approval block, inventory/allotments, supplier messages, cancellation accounting, meal-plan restoration or hotel repricing is included.

## Competitor influence

- First CRM: hotel-confirmation operational reporting, as recorded in `research/eumrah-demo/`.
- Second CRM: distinct BRN and confirmation references in the hotel workflow; see the 37:15 entry in `research/etravel-crm-one-session/timestamped-feature-inventory.md`.
- Haasib-specific additions: per-stay identity/reconfirmation, protected audit history, stale-save checks, agent privacy and accounting/print isolation. These are our safeguards, not claims about competitor internals.

## Verification

- **197 backend tests / 2,015 assertions passed** across HotelConfirmations, Operations, VoucherDraftEditing, VoucherAmendmentAccounting, VoucherAmendmentAndApproval, CrossAgentVoucherTransfer and VoucherPrintProfiles.
- Final focused rerun: **26 confirmation tests / 138 assertions passed**, after fixing an additional legacy edge case where filtering blank hotel rows could shift a visible stay's identity. That case now has a request-level regression test.
- The confirmation tests cover success/history, independent stays, stale versions/revisions, invalid fields, incomplete stays, repeat hotel identities, notes/reordering, nine material-change types and reversal, legacy read-only projection, external stays, cancellation, agent read/write/privacy, company isolation, Operations staff, amendments and unexpected-storage-error feedback.
- Production Vite build, targeted ESLint, PHP formatting and `git diff --check` passed. Build reports existing unresolved-font warnings. `layout:validate` is unavailable in this checkout; no composer quality-check script or validate-migration.sh was found.
- Built-in browser: used existing local QA voucher **QA-PAY-0911-B**, not production data. Verified Not recorded → Pending → Confirmed, separate references, visible internal note and history, and an unchanged displayed hotel sale/cost of 400/240. The private QA note was absent from the passenger print-preview tab. Pending status appeared in the Operations custom-date hotel-check-in queue.
- Browser Operations shortcut opened the correct internal tab. Confirming there cleared the warning; the same filtered queue went from **1 matching movement to 0**. QA voucher left Confirmed with clearly marked test references/note and its update history retained.
- Browser checks are not an exhaustive mobile/accessibility or multi-user concurrency run. Request-level tests cover role/privacy and stale-write behavior. PDF/CSV download delivery is not part of this slice's acceptance claim.

## User acceptance

1. On a voucher with multiple stays, confirm only the first hotel. Check that the others do not become confirmed.
2. Try both optional references and an internal note; reload and inspect history.
3. Set a company stay Pending. In Operations, select its dates, Hotel check-ins and Needs attention; use Update hotel confirmation.
4. Confirm the stay and verify the pending issue clears. Other unrelated readiness issues may remain.
5. Open a legacy voucher and an agent-arranged stay; check the distinct labels.
6. As an agent, check own-voucher references and the absence of staff notes/edit controls.
7. Verify the passenger print copy and group/voucher accounting remain unchanged.

## Release

Deploy only after acceptance and explicit release authorization. Requires `2026_09_14_120000_add_hotel_confirmations_to_vouchers.php`; it adds a JSONB column to the existing RLS-protected voucher table. No backfill, rate changes or permission sync is needed for this slice. Keep the column on an application rollback so recorded confirmation history is not discarded.
