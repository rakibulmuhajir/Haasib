# Groups and Vouchers search

13 September 2026. Implemented locally; not deployed. No migration or accounting changes.

## Behavior

- Existing list search supports partial, case-insensitive passenger names/passports, group names/numbers and agent names. Voucher list also supports voucher number/title. No universal passenger search.
- Group search now includes passengers joining its current travelling vouchers, as well as original purchase members. Staff may therefore find both the original and receiving group; an agent only finds their own authorized groups.
- Incoming matches require voucher-view permission and a visible current draft/approved voucher. Cancelled, superseded, deleted, pending amendment and released membership do not add receiving-group matches.
- Voucher passenger search follows actual, non-deleted voucher membership, not everyone in the purchase group. Searching a moved passenger no longer finds an emptied source draft through that passenger.
- Original group numbers remain purchase references. Voucher group-number search matches its displayed owning group, not hidden cross-agent source groups.
- Fixed stale agent-name queries: agent names now come from the linked accounting customer. The old Voucher query could fail with a missing-column error.
- Percent, underscore and backslash are literal search text, not SQL wildcard controls. Existing trim, Enter/Search, Clear, loading state and pagination behavior are retained.

## Verification

Full CrossAgentVoucherTransferTest regression: **70 tests passed / 1,017 assertions**. Eight new search cases cover names (including Urdu), case-insensitive passport matches, group/voucher/agent references, blank/no-match/wildcard searches, both agent views after a move, five inactive-membership states, another company's matching records and unchanged accounting snapshots.

Production build, targeted ESLint and PHP formatting passed. Browser smoke checks used existing synthetic demo records: Enter-to-search returned voucher QA-JOIN-0913-B; searching passport QA-JOIN-0913-A1 on Groups returned both its original A and receiving B groups with purchase counts and balances unchanged. No booking or accounting records were changed during browser checks. User acceptance and explicit deployment authorization remain required.
