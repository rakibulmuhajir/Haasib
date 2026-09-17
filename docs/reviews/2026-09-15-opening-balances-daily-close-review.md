# Opening balances and daily close review

Reviewed `feature/opening-balances-daily-close` at `6ce75c39` against `main` and the user's original request.

## Verdict

Not ready to merge as a completed implementation of the requested workflow. The card presentation change is consistent with the intended register arithmetic. Opening balances have correctness and usability gaps, and the relationship between daily close and other forms was explicitly excluded from the implementation spec.

## Findings

### P1 — Concurrent saves can duplicate opening balances

`build/modules/Accounting/Actions/OpeningBalance/SaveAction.php:77–82`

The action reads company settings and checks the lock before entering its database transaction. It never locks and reloads the company row. Two requests that have already loaded the same settings can both treat their save as the first generation. The second overwrites the tracking IDs while leaving the first journal posted.

Reproduction: load two Company instances before either save; save cash 100 through one, then cash 200 through the other. Actual ledger balance: 300. Required replacement balance: 200. This deterministically reproduces the stale-state interleaving without relying on thread timing. The same design permits a save with stale state to overwrite a concurrent lock.

Fix: serialize save and lock operations using the same company-row lock; reload settings and perform guards within that transaction.

### P1 — Saved opening cash does not reach the first daily close

Integration gap between `build/modules/Accounting/Actions/OpeningBalance/SaveAction.php:304` and `build/modules/FuelStation/Services/DailyCloseService.php:87–105`.

The new feature posts opening cash to the ledger, but daily close only looks for a previous `fuel_daily_close`. With no earlier close it returns zero; Create.vue uses that result and tells the user to enter opening cash manually.

Reproduction: save Rs 150,000 opening cash dated 31 August; request previous closing for 1 September. Actual cash: 0; opening balance: 150,000. Leaving the default creates a false cash variance when the manager enters the actual drawer count. Manually re-entering the correct cash is a workaround, not integration.

Fix: derive the first close's opening cash from the saved opening balance, with an appropriate date boundary and treatment of intervening activity.

### P2 — Saved customer, depositor and supplier names do not reload

`build/modules/Accounting/Resources/js/pages/opening-balances/Index.vue:246`, `:328`, `:370`.

These EntitySearch instances receive only the entity ID, not `initialEntity`. On initial load the component has no recent/search results, so it fetches the ordinary customer/vendor detail route. Those routes return Inertia pages rather than the entity JSON it expects. Saved rows therefore render without their names. Locked rows are especially problematic because the disabled picker cannot be used to recover the display.

The server already provides the names in `opening.rows` and the option lists. Pass the corresponding `{ id, name }` as `initialEntity`, and keep it synchronized when rows change.

### P2 — Quick-add customer/supplier controls have no handler

Same EntitySearch instances as above. `allowQuickAdd` defaults to true, but the page never handles `quick-add-click`. Selecting Add new customer/vendor merely closes the picker. It creates no record and opens no form.

Wire the quick-add flow and select the newly created entity. Bank and employee pickers also require pre-existing records; the page currently supplies no creation handoff for those.

### P2 — Lock does not freeze the underlying opening records

`build/modules/Accounting/Actions/OpeningBalance/LockAction.php:36–41` and ordinary invoice/bill void actions.

Lock only writes settings checked by opening-balance SaveAction. Ordinary document actions do not consult that lock.

Reproduction: create an opening invoice, lock opening balances, invoke ordinary `invoice.void`. The action succeeds and the invoice becomes void. ViewAction then excludes it, so a supposedly locked opening receivable disappears from the opening page, which remains locked against correction there.

If full freezing is intended, enforce it at underlying mutation paths while still permitting ordinary payments. If the intended policy is only to prevent editing from this particular page, describe that narrower guarantee and provide a coherent correction/history workflow; the completion claim that Lock freezes balances is too strong.

### P2 — Opening Amanat history displays the entry date rather than the as-of date

`build/modules/Accounting/Actions/OpeningBalance/SaveAction.php:386–396`.

The opening Amanat row has no business date; creation uses today's timestamp. AmanatController orders history by `created_at`, and its Show.vue displays that timestamp as the transaction date.

Reproduction: save opening Amanat as of 31 August on 15 September. The journal is dated 31 August, but the depositor's history reports 15 September.

Fix: expose a reliable transaction/business date in Amanat history, sourced from the linked journal or a contracted date field, while preserving the actual creation timestamp for audit purposes.

## Original requirements and completion claims

- **Card/bank sales presentation:** the changed Create.vue arithmetic is consistent: opening 10,000 + sales 100,000 = Money In 110,000; cards 30,000 + deposit 20,000 = Money Out 50,000; expected cash 60,000. Show.vue mirrors the principal grouping. No browser verification was performed.
- **Morning dip:** the documented convention is the D+1 morning dip closing day D. The branch mostly changes wording/default date and adds a baseline guard. The three added tests verify baseline lookup; they do not establish the full purchase, adjustment, backdating and daily-close cycle.
- **Forms and daily close:** the design spec explicitly marks credit-sale entry and the one-way/two-way question out of scope. Thus it does not complete the user's wider request to decide and implement how purchases, bills, deposits, stock adjustments and daily close relate. Existing integrations should not be confused with delivery of that design.
- **Amanat double posting claim:** current AmanatController deposit and withdrawal actions redirect to Daily Close. The reported separate-page double-posting path is not active in these endpoints. AmanatService's unused code is a separate concern.
- **Partner capital:** the disclosed omission of updating Partner.total_invested matters to UI consistency: DailyCloseController reads that field and net_capital. Journal/sub-record existence alone does not prove partner summaries are correct.

## Verification

- Ran the branch's OpeningBalancesTest and DailyCloseTankBaselineTest: **17 passed, 114 assertions**.
- Added temporary checks for the four runtime reproductions above: **all four failed on the expected behavioral assertions** (0 vs 150,000 cash; 300 vs 200 ledger balance; locked invoice became void; 15 September vs 31 August Amanat date).
- The temporary test file was removed after execution. No application code was modified.
- UI findings are based on tracing component props/events and controller response types, not browser interaction.
- Did not independently rerun the reported complete 89-test suite or production frontend build. Their reported success would not cover these additional scenarios.
