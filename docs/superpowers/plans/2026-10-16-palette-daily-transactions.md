# Daily Transactions in the Palette Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Let people record the day's ordinary transactions from the command palette in one line. Examples: `deposit 50000 meezan`, `withdraw 20000`, `transfer 100000 meezan to hbl`, `bank charge 500 meezan`, `received 15000 from al noor`, `paid 8000 to karachi office`, `expense 2500 tea`, `sale 12000`. Before posting, the palette shows exactly what will be booked; Enter posts it.

**Why:** These are the most frequent entries a business makes, and today each one needs a page and a form. The bookings already exist as CommandBus actions:
- `bank_transaction.create` (kind: deposit, withdrawal, transfer or charge)
- `payment.create`
- `bill_payment.create`
- `expense.create`

They can be typed today, but only with raw flags. Cash sale exists only as a page (`SaleController::store`), not as a command.

**Architecture:**
1. **A quick-entry grammar** in the palette frontend turns plain verbs into a command key plus params. It fills defaults (today's company date, the default cash account, the bank by name) and resolves names (customer, vendor, account) through the existing `/api/palette/flag-values` search sources.
2. **A server preview endpoint** dry-runs the command inside a DB transaction that is always rolled back. It returns the journal lines it *would* post and the affected balances after posting, so the preview comes from the real action, not a re-implementation.
3. **Enter posts** through the existing `/api/palette/execute`. The result line offers "Open" and, where a void command exists, "Undo", which runs it.

All validation, permissions, period locks and daily-close locks stay in the actions themselves.

**Paths** are relative to `build/`.

## Global Constraints

- **Core independence:** core must not name FuelStation or Umrah, and `tests/Feature/Modules/CoreIndependenceTest.php` stays green.
- **Commands only:** no `$request->validate` in controllers, use FormRequests, and Bus dispatch through CommandBus.
- **Company date:** "today" comes from `app(\App\Support\CompanyClock::class)->today()`.
- **Data safety:** never run `migrate:fresh` or anything with `--env=testing`. Run one test process at a time. UI copy stays short.
- **Preview never persists:** the transaction is always rolled back, even on success. Actions that do work outside the DB (mail, queued jobs, broadcasts) must be suppressed or deferred during preview. Grep each wrapped action and its services for `Mail::`, `dispatch(`, `event(`, `Notification`, `Http::`. If any appear, gate them behind a `PalettePreview::active()` flag, or exclude that action from preview.

## Review Focus

1. **Preview is side-effect free.** Running preview twice leaves no rows. It doesn't bump document numbers (number sequences stored in the DB are rolled back with the transaction; check for any cache-based counters). It sends nothing.
2. **Ambiguous names never guess.** `received 15000 from al` with two matching customers shows both to pick from, instead of choosing one.
3. **A locked or closed date is refused at preview.** The action's own message is shown (e.g. a daily-close lock or a closed period), so the user learns it before pressing Enter.
4. **Permissions are checked at preview, not only at execute.** A cashier without `bill_payment.create` sees "You can't record vendor payments", not a preview.
5. **Amount parsing covers local habits:** `50,000`, `50000`, `50k` and `1.5 lakh`, which is 150,000. Anything else is refused, never coerced to a different number.

---

## Task 1: Cash sale as a command

**Files:**
- Create `modules/Accounting/Services/CashSaleService.php`, holding the logic now in `SaleController::store`: the cash-sales customer, the invoice and the payment.
- Create `modules/Accounting/Actions/Sale/CreateAction.php`. It implements `PaletteAction` and `DescribesPalette`. Rules:
  - `date`, defaulting to the company date;
  - `amount`;
  - `description`, defaulting to "Cash sale";
  - `income_account_id`, nullable;
  - `deposit_account_id`, defaulting to the company's default cash account, the way `sales/Create.vue` defaults it.

  Its permission is the one the sales page uses (check `SaleController`'s route middleware or FormRequest `authorize`).
- Register `sale.create` in `config/command-bus.php`.
- Make `SaleController::store` call the service, so there's one write path. Its behaviour must stay identical.

**Test:** `tests/Feature/Accounting/CashSaleCommandTest.php`:
- `sale.create` posts the same journal as the sales page for the same input;
- the page still works (existing sales tests pass);
- permission is enforced.

- [ ] Write the test, implement, run `php artisan test tests/Feature/Accounting --filter="Sale|sale"`, then commit.

## Task 2: Preview endpoint

**Files:**
- Create `app/Support/Palette/PalettePreview.php`. `PalettePreview::run(string $key, array $params, User $user): array` does the following:
  1. `DB::beginTransaction()`.
  2. Set a static `active` flag.
  3. Dispatch through `CommandBus` with the same permission rules as `CommandController`. Reuse its authorise logic; don't copy it. If needed, extract a small `CommandAuthorizer` used by both.
  4. Capture what was posted. The action results return ids, so read the `acct.transactions` and `acct.journal_entries` (or whatever posting writes; check `PostingService` and `GlPostingService`) created since the start of the transaction, plus the post-balances of the accounts touched.
  5. `DB::rollBack()` in `finally`.
  6. Return `{ok, lines: [{side, account_code, account_name, amount}], balances: [{account_name, before, after}], summary}`.

  Validation and authorisation errors return `{ok:false, errors}` with the action's own messages.
- Create `app/Http/Controllers/Api/PalettePreviewController.php`, with `POST /api/palette/preview` and the same middleware as `/api/palette/execute`.
- Gate any outside side effects found in the Global Constraints grep.

**Test:** `tests/Feature/Palette/PalettePreviewTest.php`:
- preview of a deposit returns debit bank and credit cash with the right amounts, and **no rows remain** (count transactions before and after);
- preview twice leaves the counts unchanged;
- a user without permission gets the permission error;
- a date in a closed period returns the period error;
- `payment.create` preview shows the AR credit;
- `sale.create` preview works.

- [ ] Write the test, implement, run `php artisan test tests/Feature/Palette`, then commit.

## Task 3: Quick-entry grammar (frontend)

**Files:**
- Create `resources/js/palette/quick-entry.ts`. Export `parseQuickEntry(text): QuickEntry | null` and `resolveQuickEntry(entry, lookups): Promise<Resolved>`.
  - **Verbs and their commands:**

    | Verb | Command |
    |---|---|
    | `deposit <amount> [into] <bank>` | bank_transaction kind=deposit |
    | `withdraw <amount> [from] <bank>` | kind=withdrawal |
    | `transfer <amount> <bank> to <bank>` | kind=transfer |
    | `bank charge <amount> <bank> [note]` | kind=charge, `expense_account_id` = the company's bank-charges account (find how BankTransaction charge picks it) |
    | `received <amount> from <customer>` | payment.create, method from the deposit account's subtype as `PaymentController::store` does |
    | `paid <amount> to <vendor> [from <bank>]` | bill_payment.create |
    | `expense <amount> <what> [from <account>]` | expense.create; `account_id` resolved by name against expense accounts (search "tea" → Office supplies or Meals). If it isn't confident, show choices. |
    | `sale <amount> [note]` | sale.create |

  - **Defaults:**
    - the bank omitted → the company's default bank account;
    - the cash account → the default cash account;
    - the date → the company date.

    Read these from shared Inertia props, or add a small `/api/palette/defaults` endpoint. Check what's already shared first.
  - **Amounts:** `50000`, `50,000`, `50k`, `1.5 lakh` / `2 lac`. Anything else returns null.
  - **Names:** resolved through `/api/palette/flag-values?entity=customer|vendor|account&q=`. 0 matches gives an error. 1 match resolves. Several matches return them as choices.
- Modify `resources/js/composables/palette/useCommandPalette.ts` and `resources/js/components/palette/CommandPalette.vue`.
  - When the input parses as a quick entry, show the **preview panel**: the debit/credit lines from `/api/palette/preview`, the balances (before → after), any error, and choices if a name is ambiguous.
  - Debounce the preview by 300ms after the user stops typing.
  - Enter executes through `/api/palette/execute` with the resolved params. The result line shows the summary plus "Open" (`router.visit`) and "Undo" where a void command exists for that document (payment.void, bill_payment.void; check what bank_transaction and expense support. If there's no void, offer no Undo).
  - Plain commands keep working exactly as now.
- Help: `help` lists the quick-entry verbs with one example each.

**Tests:** `tests/js/palette/quick-entry.spec.ts` covers parse cases for every verb and every amount form, rejects garbage, and checks ambiguity output. Then run `npx vitest run` and `npx vue-tsc --noEmit -p tsconfig.json` (expect 0 errors; if the errors are only in generated `resources/js/actions/**`, run `php artisan wayfinder:generate --with-form`).

**Browser check:** use Playwright headless, as `build/scripts/smoke-crawl.cjs` launches it, on dev company `walkthrough-general-co` (demo@haasib.app / demo-password, from `database/seeders/Demo/DemoSupport.php`). Each of these must show the right preview lines:
- `deposit 5000`
- `received 1000 from al noor`
- `paid 500 to karachi office`
- `expense 250 tea`
- `sale 1200`

Post one deposit, then confirm it appears in Banking with its journal. Undo it if Undo exists. Take screenshots.

- [ ] Write the test, implement, run the checks, then commit.

## Task 4: Docs

Add a short "Typing transactions" section to the palette help copy and to `cli-palette.md`, listing the verbs.

- [ ] Commit.
