# Opening Balances & Daily Close Card Flow — Design

Date: 2026-09-15
Status: approved in conversation, awaiting implementation plan
Scope: two independent pieces that came out of a session with the petrol pump manager.

1. **Daily close — card / bank receipts shown as Money Out.** Presentation-only change to the Daily Close screens.
2. **Opening balances.** A real Accounting feature replacing the settings-JSON stub in fuel onboarding.

Out of scope (tracked separately): credit-sale (udhaar) entry in daily close; the one-way vs two-way question between daily close and the other forms; morning-vs-evening dip timing.

---

## Part 1 — Card / bank receipts are Money Out

### Problem

The manager's register works like this:

```
Money In  = opening cash + deposits + TOTAL sales (cash, card, transfer — all of it)
Money Out = bank deposits + expenses + salaries + … + card swipes (they went to the bank)
Expected closing cash = Money In − Money Out
```

`DailyCloseService` already computes exactly that (`cashFromSales = totalRevenue − nonCashReceipts`, service lines 923–930), and the GL posting (full revenue credited, card clearing / bank debited) is correct. Only the **screen** disagrees: payment-channel entries live under the *Cash In* tab, the Cash In summary lists them as "Non-Cash Receipts", and "Total Money In (All Sources)" stacks them on top of sales.

### Change

`build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Create.vue`

- Move the per-channel entry blocks (card POS, bank transfer, fuel card, mobile wallet — everything with `type !== 'cash'`) from the `money-in` tab to the `money-out` tab, in a section titled **"Sales that went to bank / card accounts"** placed first in that tab. Entry rows, add/remove, last-four, references: unchanged.
- **Cash In summary**: Opening Cash, Partner / Amanat / Other deposits, **Total Sales**, Total Money In. Remove the Non-Cash block and "Total Cash Available".
- **Cash Out summary**: one row per enabled non-cash channel with a non-zero total, labelled with the channel label and its destination account name (e.g. "HBL POS → HBL Card Account"), followed by the existing rows. Total Money Out includes them.
- **Summary tab**: same layout — `+ Total Sales`, then `− Money Out`, `= Expected Closing`. Drop the intermediate "Total Cash Available".
- Computed values: `totalMoneyIn` becomes `opening + deposits + totalSales`; `totalMoneyOut` adds `totalNonCashReceipts`; `expectedClosingCash` is unchanged in value (it already subtracted the channels) and is re-expressed as `totalMoneyIn − totalMoneyOut` so the three numbers on screen add up.
- Tab labels stay "Cash In" / "Cash Out". Toast on save of Cash Out reports the new total.
- `saveMoneyIn` / `saveMoneyOut` tab-saved state: channel validation errors (`payment_receipts.*`) now surface on the Cash Out tab.

`build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Show.vue` and `AmendmentChain.vue` (if it renders money in/out): mirror the same grouping from the stored metadata (`payment_receipt_postings` carries channel label, type, account and amount, so no metadata change is needed).

### Unchanged

- Request payload shape (`payment_receipts[channel].entries[]`), `StoreDailyCloseRequest` rules, `DailyCloseService`, GL posting, amendment pre-fill, metadata keys.

### Testing

- Vitest component test on `Create.vue`'s computed layer: given sales 100k, card 30k, bank deposit 20k, opening 10k → Money In 110k, Money Out 50k, Expected Closing 60k; and the Cash In summary contains no channel rows.
- Existing `DailyCloseService` feature tests keep passing untouched (proves the backend is not affected).

---

## Part 2 — Opening balances

### Problem

Today:

- `FuelStationOnboardingController::setupOpeningCash` writes cash and bank figures into `company.settings['opening_balances']`. Nothing is posted; the ledger starts at zero.
- `CompanyOnboardingService::setupOpeningBalances` only logs.
- Credit customer balance is hard-coded `0` (`CreditCustomerController.php:35`).
- Amanat balances live in `fuel.customer_profiles.amanat_balance` and can only be built up by deposits after go-live.
- Nothing exists for supplier payables, employee advances, or partner capital at go-live.

The station goes live with balances **as of 31 Aug 2026** and daily entries from 1 Sep.

### Approach (chosen): native records posted against Opening Balance Equity

Every opening figure becomes the same kind of record the system already understands, dated `as_of_date`, with the counter-entry on **3080 Opening Balance Equity**. Sub-ledgers (invoices, bills, amanat, advances, partner ledger) then work with zero special-casing.

| Section | Record created | Posting | Notes |
|---|---|---|---|
| Cash on hand | line in the opening journal | Dr 1050, Cr 3080 | |
| Each bank / card settlement account (`subtype = bank`) | line in the opening journal | Dr bank, Cr 3080 | |
| Amanat depositor | `AmanatTransaction` (type deposit) via `AmanatService`, new `source = 'opening'` | Dr 3080, Cr 2200 | `AmanatService::deposit` gains an optional `date` and `counter_account_id`; profile `amanat_balance` is adjusted as today |
| Credit customer (udhaar) | `Invoice` via `Invoice\CreateAction`: one line "Opening balance as of {date}", `income_account_id = 3080`, no tax, `status = posted`/sent, `due_date = as_of_date` | Dr AR, Cr 3080 | Customer payments settle it through the existing Payments module |
| Supplier | `Bill` via `Bill\CreateAction`: one line, `expense_account_id = 3080`, no tax | Dr 3080, Cr AP | Bill payments settle it as usual |
| Employee advance outstanding | `SalaryAdvance` with `amount = amount_outstanding = X`, `amount_recovered = 0`, `status = approved`, `advance_date = as_of_date`, `advance_account_id = 1150` | Dr 1150, Cr 3080 | Payroll recovery works unchanged |
| Partner capital (optional) | `PartnerTransaction` type contribution, `transaction_date = as_of_date` | Dr 3080, Cr 2210 (partner deposits account per `resolveAccounts`) | |

3080 nets to the owner's opening equity. Accountants may later reclassify it to retained earnings with a normal journal; we do not automate that.

Rejected: (B) a single journal with `dimension_1` = customer/vendor id — sub-ledger pages would not see it, `amanat_balance` still needs patching. (C) keep settings-JSON and make reports add it in — the present non-implementation.

### Data

No new tables. Identification and reload:

- Every GL `Transaction` created by this feature: `transaction_type = 'opening_balance'`, `reference_type = 'acct.opening_balances'`.
- Invoices / bills / amanat / advances / partner transactions created here carry `reference = 'OPENING'` (or the model's equivalent free-text reference) **and** `metadata.opening_balance = true` where the model has a metadata column; where it does not, the link is via the GL transaction `reference_type` above.
- `company.settings['opening_balances']` is replaced by:
  ```json
  { "opening_balances": { "as_of_date": "2026-08-31", "locked_at": null, "locked_by_user_id": null } }
  ```
  The figures themselves are read back from the records; settings only hold the date and lock.

Schema contract: add an "Opening balances" section to `docs/contracts/gl-core-schema.md` describing the tagging above and the 3080 account requirement.

### Backend

`build/modules/Accounting/`

- `Actions/OpeningBalance/SaveAction.php` — dispatched via `Bus::dispatch()`. Input: validated array from `StoreOpeningBalancesRequest`. Runs in one DB transaction:
  1. Guard: `as_of_date` must be strictly before the earliest posted non-opening transaction for the company; refuse with a validation error otherwise. Refuse everything if `locked_at` is set.
  2. Resolve 3080 by code; create it (equity / `subtype = equity`, name "Opening Balance Equity") if the company's pack lacks it.
  3. Diff incoming rows against existing opening records (keyed by section + entity id / account id). Unchanged rows: skip. Changed or removed rows: void the previous record through the module's own void path (`Invoice\VoidAction`, `Bill\VoidAction`, reversing journal for GL-only lines, amanat withdrawal-reversal via `AmanatService`, delete unrecovered `SalaryAdvance`, reversing `PartnerTransaction`) and create fresh. New rows: create.
  4. Post the cash/bank journal as one `Transaction` with one line per account plus the 3080 balancing line, via `GlPostingService::postBalancedTransaction`.
  5. Persist `as_of_date` in settings.
- `Actions/OpeningBalance/LockAction.php` — sets `locked_at` / `locked_by_user_id`. Locking is a separate explicit step; unlocking is not offered in v1.
- `Actions/OpeningBalance/ViewAction.php` — loads the current state for the page: sections, rows with amounts, totals (assets, liabilities, equity, 3080 balance), lock state, earliest posted transaction date (for the guard message), plus pick-lists (cash/bank accounts, customers, vendors, employees, partners, amanat-holder customers).
- `Http/Requests/StoreOpeningBalancesRequest.php` — rules:
  - `as_of_date: required|date`
  - `cash.amount: nullable|numeric|min:0`
  - `banks[].account_id: uuid|exists acct.accounts (subtype bank, same company)`, `banks[].amount: numeric|min:0`
  - `amanat[].customer_id`, `credit_customers[].customer_id`, `suppliers[].vendor_id`, `employees[].employee_id`, `partners[].partner_id` — each `uuid`, same company, distinct within its section; `amount: required|numeric|gt:0`.
  - Authorization: `hasCompanyPermission(Permissions::OPENING_BALANCES_MANAGE)`.
- `Http/Controllers/OpeningBalanceController.php` — `show`, `store`, `lock`. Inertia page `accounting/opening-balances/Index`.
- Routes (`build/routes/web.php`, accounting group): `GET /{company}/accounting/opening-balances`, `POST …`, `POST …/lock`; middleware `['auth', 'identify.company']`.
- Permissions: add `OPENING_BALANCES_VIEW` / `OPENING_BALANCES_MANAGE` to `app/Constants/Permissions.php`, `config/role-permissions.php` (owner, admin, accountant), then `rbac:sync-permissions` and `rbac:sync-role-permissions`.
- `AmanatService::deposit` — accept optional `date` (defaults `now()`), `counter_account_id` (defaults cash) and `source`. No behaviour change for existing callers.

`build/modules/FuelStation/`

- `FuelStationOnboardingController::setupOpeningCash` and route `fuel.onboarding.opening-cash`: removed. The wizard's `opening_cash` step becomes a status card: "Opening balances — not set / N lines, total assets PKR X as of {date} / locked" with a link to the Accounting page. `ViewAction` above exposes a small `summary()` used here.
- `CreditCustomerController`: `current_balance` = sum of open invoice balances for the customer (this finally makes the page truthful; it also picks up the opening invoice). Keep the `TODO` note out.

### Frontend

`build/modules/Accounting/Resources/js/pages/opening-balances/Index.vue` — `<script setup lang="ts">`, Shadcn components, Inertia `useForm`. Follow `docs/ledger-design-system.md`.

Layout (single page, no wizard):

- Header: "Opening balances as of [date picker]". Guard message inline if the date is not before the earliest posting. Lock button (destructive-styled, confirm dialog) once saved.
- Six collapsible sections in this order — **Cash & banks**, **Credit customers (receivables)**, **Employee advances**, **Amanat depositors**, **Suppliers (payables)**, **Partner capital** (collapsed by default, marked optional). Each is a small table: entity picker (`EntitySearch` for customers/vendors, Select for accounts/employees/partners), amount, remove; "Add row" at the bottom; section subtotal on the right.
- Sticky footer: Assets total, Liabilities total, "→ Opening Balance Equity" (assets − liabilities, sign shown), Save button. Save is disabled when the form has no rows or the date guard fails.
- Locked state: read-only tables, no Save, banner "Locked on {date} by {user}".
- Errors: validation inline per row; server errors via Sonner toast (`AI_PROMPTS/toast.md`).

Fuel onboarding `Onboarding/Index.vue`: replace the `opening_cash` step body with the status card + link.

### Error handling

- Missing 3080 → created silently (logged).
- Missing 1050 / 2200 / 2210 / 1150 / AR / AP control accounts → validation error naming the code, same wording style as `DailyCloseService` ("Set up account 2200 …").
- Any failure inside `SaveAction` rolls back the whole transaction; nothing partial is left.
- Posting to a closed accounting period is rejected by `GlPostingService` already; surface its message.

### Testing

Feature tests (Pest, `build/tests/Feature/Accounting/OpeningBalancesTest.php`):

1. Full save creates: one `opening_balance` transaction with cash + 2 banks + 3080 line that balances; one invoice per credit customer with `income_account_id = 3080`; one bill per supplier; `AmanatTransaction` + profile balance per depositor; `SalaryAdvance` per employee; `PartnerTransaction` per partner. Trial balance after save: assets − liabilities = 3080 balance.
2. Re-save with one amount changed and one row removed → old records voided/reversed, new ones created, totals correct, no duplicates.
3. Guard: `as_of_date` on/after the first posted daily close → 422.
4. Locked → store returns 403-style validation error; lock endpoint sets `locked_at`.
5. Permissions: user without `OPENING_BALANCES_MANAGE` gets 403.
6. `CreditCustomerController` index shows the opening receivable as `current_balance`.
7. Fuel onboarding page no longer exposes `fuel.onboarding.opening-cash`; the summary card reflects the saved state.

Unit test for `AmanatService::deposit` with `counter_account_id` and `date` overrides.

---

## Delivery order

1. Part 1 (small, independent, frontend only).
2. Part 2 backend (actions, request, permissions, routes, AmanatService change, tests).
3. Part 2 frontend page.
4. Fuel onboarding hand-off + `CreditCustomerController` balance.
