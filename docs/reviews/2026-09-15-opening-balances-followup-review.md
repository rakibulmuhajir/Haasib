# Opening balances follow-up review

Reviewed the uncommitted working-tree fixes on top of `6ce75c39` on 15 September 2026. No application code changed during this review.

## Remaining findings

### P1 — Lock still allows ordinary invoice/bill amount edits

The new guards in `Accounting/Actions/Invoice/VoidAction.php:36` and `Accounting/Actions/Bill/VoidAction.php:34` block voiding only. `Accounting/Actions/Invoice/UpdateAction.php:61` and `Accounting/Actions/Bill/UpdateAction.php:43` do not check the opening lock.

Reproduced through the command bus: save an opening invoice for Rs 100, lock opening balances, call `invoice.update` with a line amount of Rs 200. The action succeeds and the invoice total becomes Rs 200. These update actions also do not repost the original journal, so changing a posted opening document can disagree with its ledger entry.

Enforce the opening-record lock on financial edits as well as voids, covering both invoices and bills. Ordinary payment allocation should remain possible. The current error message also tells users to unlock opening balances even though this feature provides no unlock action.

### P2 — Amanat history sorts only within each already-selected page

`build/modules/FuelStation/Http/Controllers/AmanatController.php:114–132`.

The database still selects pages by `created_at`. Only afterward does PHP sort those 50 rows by the derived transaction date. This cannot produce correctly ordered history across pages.

Reproduced: 50 ordinary transactions dated 1 September, plus a newly entered opening deposit whose journal date is 31 August. Page 1 contains the August opening and only 49 September entries; the remaining September entry is pushed onto page 2. The displayed dates are now correct, but chronological pagination is not.

Derive and sort by the effective business date in SQL before `paginate(50)`, using a stable secondary order such as creation timestamp and ID. Preserve the creation timestamp for audit display.

## Earlier findings addressed

- Save and Lock now lock/reload the company row within a transaction. The stale-company duplicate-save regression passes.
- The first daily close now reads prior cash ledger balance when no earlier close exists. The Rs 150,000 opening-cash regression passes.
- Customer/vendor/depositor pickers receive `initialEntity` from existing server data. Code inspection confirms the missing-name cause is addressed; no browser interaction was performed.
- The dead quick-add option has been disabled. This removes the misleading control; it does not implement creation from the opening-balances page.
- Ordinary opening-invoice/bill void actions reject locked records. The edit bypass above remains.
- Amanat history displays the linked journal's business date and retains the recorded timestamp as hover text. Single-entry date regression passes; cross-page ordering remains broken.

## Verification

- OpeningBalancesTest, DailyCloseOpeningCashTest, AmanatHistoryTest, DailyCloseTankBaselineTest: **21 passed, 127 assertions**.
- Two temporary additional checks reproduced both remaining findings with failed behavioral assertions.
- `git diff --check` passed.
- Temporary test file removed after execution. Production build and browser interaction were not run.

The original broader forms ↔ daily-close / credit-sales design remains outside these fixes. These changes are still uncommitted in the reviewed working tree.
