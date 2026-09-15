# Opening Balances & Daily Close Card Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) Show card/bank sales as Money Out on the fuel Daily Close screens; (2) build a real Opening Balances feature in Accounting that posts native records against Opening Balance Equity and replaces the settings-JSON stub in fuel onboarding.

**Architecture:** Part 1 is a Vue-only relayout of `DailyClose/Create.vue` and `Show.vue`; the service and payload are untouched. Part 2 adds an `OpeningBalance` action family (Save / Lock / View) in the Accounting module registered on the CommandBus, one Inertia page, and a status-card hand-off from fuel onboarding. Every opening figure becomes a normal record (journal line, invoice, bill, amanat transaction, salary advance, partner transaction) with the counter-entry on account 3080.

**Tech Stack:** Laravel 11 (modules under `build/modules/*`), Pest feature tests, Inertia + Vue 3 `<script setup lang="ts">`, Shadcn components, CommandBus (`app(CommandBus::class)->dispatch('name', $params, $user)`), `GlPostingService::postBalancedTransaction`.

**Spec:** `docs/superpowers/specs/2026-09-15-opening-balances-and-daily-close-card-flow-design.md`

## Global Constraints

- All paths below are relative to the repo root; PHP/Vue code lives under `build/`. Run PHP commands from `build/` (`cd build && php artisan …`, `cd build && ./vendor/bin/pest …`).
- Never `$table->id()`; UUID primary keys, `protected $keyType = 'string'; public $incrementing = false;`.
- Schema-prefixed tables (`acct.`, `fuel.`, `auth.`). No `session('active_company_id')` — use `app(CurrentCompany::class)->get()` or `CompanyContext::requireCompany()`.
- Routes: `/{company}/…` with `['auth', 'identify.company']`. Controllers dispatch actions through `app(CommandBus::class)`; validation in FormRequests.
- Vue: Shadcn components only (`@/components/ui/*`), no raw `<input>`/`<button>`, no axios/fetch — Inertia `useForm`/`router`. Follow `docs/ledger-design-system.md`.
- Errors: validation inline, server errors via Sonner toast.
- Account codes used: `1050` cash on hand, `1000`/`subtype=bank` banks, `1100`/`subtype=accounts_receivable` AR, `1150` employee advances, `2100`/`subtype=accounts_payable` AP, `2200` amanat deposits, `2210` partner/investor deposits, `3080` Opening Balance Equity.
- Commit after each task with `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>` as the last line.

---

## Part 1 — Card / bank receipts are Money Out

### Task 1: Move payment channels to the Cash Out tab in `Create.vue`

**Files:**
- Modify: `build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Create.vue`
  - computed block around lines 1239–1289 (`totalNonCashReceipts`, `cashSales`, `totalMoneyIn`, `totalCardAndBank`, `totalMoneyOut`, `expectedClosingCash`)
  - Cash In tab template ~2341–2680 (channel entry blocks + Money In Summary)
  - Cash Out tab template ~2682–3075 (Money Out Summary)
  - Summary tab ~3095–3130

**Interfaces:**
- Consumes: `enabledChannels` (computed, `{code,label,type,bank_account_id?,clearing_account_id?}[]`), `getChannelTotal(code): number`, `form.payment_receipts`, `props.bankAccounts` (`{id,code,name}[]`).
- Produces: `channelOutRows` computed used by Task 2's `Show.vue` naming convention (`label → account name`).

- [ ] **Step 1: Rewrite the computed layer**

Replace the block from `const totalNonCashReceipts` through `const cashVariance` with:

```ts
// Total of all non-cash payment receipts (cards, transfers, fuel cards, wallets).
// These are sales that left the drawer for a bank/clearing account, so they are Money Out.
const totalNonCashReceipts = computed(() => {
  let total = 0
  for (const channelCode of Object.keys(form.payment_receipts)) {
    total += getChannelTotal(channelCode)
  }
  return total
})

const totalPartnerDepositsIn = computed(() => form.partner_deposits.reduce((s, d) => s + d.amount, 0))

// Money In = opening cash + every cash deposit + TOTAL sales (cash, card, transfer — all of it)
const totalMoneyIn = computed(() => {
  return form.opening_cash + totalPartnerDeposits.value + totalAmanatDeposits.value + totalOtherDeposits.value + totalSales.value
})

// One row per enabled non-cash channel with a value, labelled "channel → destination account"
const channelOutRows = computed(() => {
  return enabledChannels.value
    .filter(ch => ch.type !== 'cash' && getChannelTotal(ch.code) > 0)
    .map(ch => {
      const accountId = ch.clearing_account_id || ch.bank_account_id
      const account = props.bankAccounts.find(a => a.id === accountId)
      return {
        code: ch.code,
        label: account ? `${ch.label} → ${account.name}` : ch.label,
        amount: getChannelTotal(ch.code),
      }
    })
})

// Money Out = everything that left the drawer, including sales that went straight to bank/card accounts
const totalMoneyOut = computed(() => {
  const bankDeposits = form.bank_deposits.reduce((sum, d) => sum + d.amount, 0)
  const partnerWithdrawals = form.partner_withdrawals.reduce((sum, w) => sum + w.amount, 0)
  const employeeAdvances = form.employee_advances.reduce((sum, a) => sum + a.amount, 0)
  const payrollPayouts = form.payroll_payouts.reduce((sum, payout) => sum + payout.amount, 0)
  const cashBillPayments = totalCashBillPayments.value
  const amanat = form.amanat_disbursements.reduce((sum, a) => sum + a.amount, 0)
  const expenses = form.expenses.reduce((sum, e) => sum + e.amount, 0)

  return totalNonCashReceipts.value + bankDeposits + partnerWithdrawals + employeeAdvances + payrollPayouts + cashBillPayments + amanat + expenses
})

const expectedClosingCash = computed(() => totalMoneyIn.value - totalMoneyOut.value)

const cashVariance = computed(() => form.closing_cash - expectedClosingCash.value)
```

Delete `cashSales` and `totalCardAndBank`. Grep the file for both names and replace every remaining template use: `cashSales` → `totalSales`, `totalSales - totalCardAndBank` → `totalSales`. If `totalPartnerDeposits` / `totalAmanatDeposits` / `totalOtherDeposits` computeds do not already exist above this block, keep the inline `reduce` calls instead and drop `totalPartnerDepositsIn`.

Check the `bankAccounts` prop type includes `name`; if it is `{id, code, name}` nothing to do, otherwise extend the interface.

- [ ] **Step 2: Move the channel entry blocks**

In the Cash In tab (`<TabsContent value="money-in">`), cut the whole `<template v-for="channel in enabledChannels" …>` block that renders per-channel entry rows (the block containing the text `Credit/debit card swipes` and the `addPaymentReceipt`/`removePaymentReceipt` buttons — roughly lines 2540–2590). Paste it as the **first** section inside `<CardContent>` of the Cash Out tab (`<TabsContent value="money-out">`), wrapped as:

```vue
<!-- Sales that went to bank / card accounts (Money Out: they never reached the drawer) -->
<div class="space-y-4">
  <div>
    <h4 class="font-semibold text-sm">Sales that went to bank / card accounts</h4>
    <p class="text-xs text-muted-foreground">Card swipes, transfers and fuel-card sales are already inside Total Sales. Enter them here so they are taken out of expected cash.</p>
  </div>
  <!-- pasted channel blocks -->
</div>
<Separator />
```

- [ ] **Step 3: Rewrite the Money In Summary**

Replace the `<!-- Money In Summary -->` block with:

```vue
<!-- Money In Summary -->
<div class="p-4 rounded-lg bg-muted/30 space-y-3">
  <h4 class="font-semibold text-sm">Money In Summary</h4>
  <div class="space-y-2">
    <div class="flex justify-between text-sm">
      <span>Opening Cash</span>
      <span class="font-medium"><MoneyText :amount="form.opening_cash" :currency="currencyCode" :fraction-digits="0" /></span>
    </div>
    <div v-if="totalPartnerDeposits > 0" class="flex justify-between text-sm">
      <span>Partner Deposits</span>
      <span class="font-medium"><MoneyText :amount="totalPartnerDeposits" :currency="currencyCode" :fraction-digits="0" /></span>
    </div>
    <div v-if="totalAmanatDeposits > 0" class="flex justify-between text-sm">
      <span>Amanat Deposits</span>
      <span class="font-medium"><MoneyText :amount="totalAmanatDeposits" :currency="currencyCode" :fraction-digits="0" /></span>
    </div>
    <div v-if="totalOtherDeposits > 0" class="flex justify-between text-sm">
      <span>Other Cash In</span>
      <span class="font-medium"><MoneyText :amount="totalOtherDeposits" :currency="currencyCode" :fraction-digits="0" /></span>
    </div>
    <div class="flex justify-between text-sm">
      <span>Total Sales</span>
      <span class="font-medium"><MoneyText :amount="totalSales" :currency="currencyCode" :fraction-digits="0" /></span>
    </div>
    <Separator />
    <div class="flex justify-between text-base font-semibold">
      <span>Total Money In</span>
      <span><MoneyText :amount="totalMoneyIn" :currency="currencyCode" :fraction-digits="0" /></span>
    </div>
  </div>
</div>
```

- [ ] **Step 4: Add channel rows to the Money Out Summary**

Inside `<!-- Money Out Summary -->`, insert as the first rows (before `Bank Deposits (Vendor Payments)`):

```vue
<div v-for="row in channelOutRows" :key="'out-' + row.code" class="flex justify-between text-sm">
  <span>{{ row.label }}</span>
  <span class="font-medium text-destructive"><MoneyText :amount="row.amount" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
```

Change the empty-state condition from `totalMoneyOut === 0` (still correct) — no change needed. Update `saveMoneyOut`'s toast to use `totalMoneyOut.value`.

- [ ] **Step 5: Rewrite the Summary tab cash block**

Replace the rows from `+ Partner Deposits` through `Expected Closing` with:

```vue
<div class="flex justify-between">
  <span>Opening Cash</span>
  <span><MoneyText :amount="form.opening_cash" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
<div v-if="totalPartnerDeposits > 0" class="flex justify-between">
  <span>+ Partner Deposits</span>
  <span><MoneyText :amount="totalPartnerDeposits" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
<div v-if="totalAmanatDeposits > 0" class="flex justify-between">
  <span>+ Amanat Deposits</span>
  <span><MoneyText :amount="totalAmanatDeposits" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
<div v-if="totalOtherDeposits > 0" class="flex justify-between">
  <span>+ Other Cash In</span>
  <span><MoneyText :amount="totalOtherDeposits" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
<div class="flex justify-between">
  <span>+ Total Sales</span>
  <span><MoneyText :amount="totalSales" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
<Separator />
<div class="flex justify-between font-medium">
  <span>Total Money In</span>
  <span><MoneyText :amount="totalMoneyIn" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
<div class="flex justify-between text-destructive">
  <span>− Money Out (incl. card / bank sales)</span>
  <span><MoneyText :amount="totalMoneyOut" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
<Separator />
<div class="flex justify-between font-semibold text-lg">
  <span>Expected Closing</span>
  <span><MoneyText :amount="expectedClosingCash" :currency="currencyCode" :fraction-digits="0" /></span>
</div>
```

(Keep the existing `Opening Cash` row if one already precedes this block; do not duplicate it.)

- [ ] **Step 6: Move channel validation errors to the Cash Out tab**

Find where `tabsSaved.moneyIn` / the money-in tab computes "has errors" (search `payment_receipts.` in the error helpers ~line 663 and any `hasMoneyInErrors` style computed). Ensure `payment_receipts.*` errors count toward the Cash Out tab's error badge, not Cash In. If errors are only rendered inline per field (no tab badge), nothing to do.

- [ ] **Step 7: Type-check and run the dev build**

Run: `cd build && npx vue-tsc --noEmit -p tsconfig.json 2>&1 | grep DailyClose/Create.vue`
Expected: no lines (no type errors in this file). If `vue-tsc` is not configured, run `cd build && npm run build 2>&1 | tail -20` and expect a successful build.

- [ ] **Step 8: Manual check**

With `php artisan octane:start --server=frankenphp --port=9001 --watch` and `npm run dev` running, open `/{company}/fuel/daily-close/create`, enter sales 100,000, card 30,000 (Cash Out tab), bank deposit 20,000, opening 10,000. Expect Money In 110,000; Money Out 50,000; Expected Closing 60,000; Cash In summary shows no channel rows.

- [ ] **Step 9: Commit**

```bash
git add build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Create.vue
git commit -m "Show card and bank sales as Money Out in daily close

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 2: Mirror the grouping in `Show.vue`

**Files:**
- Modify: `build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Show.vue` (Cash Summary card ~lines 345–410)

**Interfaces:**
- Consumes: `metadata.payment_receipt_postings: {channel_code, channel_label, channel_type, account_id, amount}[]`, `metadata.total_revenue`, `metadata.opening_cash`, `metadata.partner_deposits`, `metadata.amanat_deposits`, `metadata.other_deposits`, `metadata.bank_deposits`, `metadata.partner_withdrawals`, `metadata.employee_advances`, `metadata.payroll_payouts`, `metadata.cash_bill_payments`, `metadata.amanat_disbursements`, `metadata.expenses`, `metadata.expected_closing`, `metadata.closing_cash`, `metadata.variance`.

- [ ] **Step 1: Add computed rows**

In `<script setup>` add:

```ts
const channelOutRows = computed(() => {
  const postings = (props.transaction?.metadata?.payment_receipt_postings ?? []) as Array<{ channel_code: string; channel_label: string; amount: number }>
  return postings.filter(p => Number(p.amount) > 0)
})
const totalChannelOut = computed(() => channelOutRows.value.reduce((s, p) => s + Number(p.amount), 0))
const totalMoneyIn = computed(() => {
  const m = metadata.value
  return Number(m.opening_cash || 0) + Number(m.partner_deposits || 0) + Number(m.amanat_deposits || 0) + Number(m.other_deposits || 0) + Number(m.total_revenue || 0)
})
const totalMoneyOut = computed(() => {
  const m = metadata.value
  return totalChannelOut.value + Number(m.bank_deposits || 0) + Number(m.partner_withdrawals || 0) + Number(m.employee_advances || 0)
    + Number(m.payroll_payouts || 0) + Number(m.cash_bill_payments || 0) + Number(m.amanat_disbursements || 0) + Number(m.expenses || 0)
})
```

Adjust `metadata.value` / `props.transaction` to whatever names the file already uses for the metadata object.

- [ ] **Step 2: Update the Cash Summary template**

In the Cash In block add after `Partner Deposits` (same pattern, `v-if` on value): `Amanat Deposits` (`metadata.amanat_deposits`), `Other Cash In` (`metadata.other_deposits`), then an unconditional `Total Sales` row (`metadata.total_revenue`) and a `Total Money In` row bound to `totalMoneyIn` in `font-semibold`.

In the Cash Out block insert first:

```vue
<div v-for="row in channelOutRows" :key="row.channel_code" class="flex justify-between items-center py-2">
  <span>{{ row.channel_label }} → bank / card account</span>
  <span class="font-semibold text-status-critical">-<MoneyText :amount="row.amount" :currency="currency" :fraction-digits="0" /></span>
</div>
```

and append rows for `Supplier Bill Payments (station cash)` (`metadata.cash_bill_payments`) and `Amanat Disbursements` (`metadata.amanat_disbursements`) if not already present, then a `Total Money Out` row bound to `totalMoneyOut`.

- [ ] **Step 3: Build check and commit**

Run: `cd build && npm run build 2>&1 | tail -5` → expect success.

```bash
git add build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Show.vue
git commit -m "Group card and bank sales under Money Out on daily close detail

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Part 2 — Opening balances

### Task 3: Permissions, command-bus registration, routes, schema contract note

**Files:**
- Modify: `build/app/Constants/Permissions.php` (after `JOURNAL_VIEW`, line ~97)
- Modify: `build/config/role-permissions.php` (every role block that lists `'journal.create'`: `owner` if enumerated, `manager`, `accountant`)
- Modify: `build/config/command-bus.php`
- Modify: `build/routes/web.php` (after the Manual Journals block, ~line 239)
- Modify: `docs/contracts/gl-core-schema.md`

**Interfaces:**
- Produces: `Permissions::OPENING_BALANCE_VIEW = 'opening_balance.view'`, `Permissions::OPENING_BALANCE_MANAGE = 'opening_balance.manage'`; command names `opening_balance.save`, `opening_balance.lock`, `opening_balance.view`; route names `accounting.opening-balances.show|store|lock`.

- [ ] **Step 1: Add constants**

```php
    public const OPENING_BALANCE_VIEW = 'opening_balance.view';

    public const OPENING_BALANCE_MANAGE = 'opening_balance.manage';
```

- [ ] **Step 2: Add to roles**

In `config/role-permissions.php`, directly after each `'journal.view',` line inside `manager` and `accountant` (and `owner` if it enumerates permissions rather than `*`), add:

```php
        'opening_balance.view',
        'opening_balance.manage',
```

- [ ] **Step 3: Register commands**

In `config/command-bus.php` next to the `journal.create` entry:

```php
    'opening_balance.view' => \App\Modules\Accounting\Actions\OpeningBalance\ViewAction::class,
    'opening_balance.save' => \App\Modules\Accounting\Actions\OpeningBalance\SaveAction::class,
    'opening_balance.lock' => \App\Modules\Accounting\Actions\OpeningBalance\LockAction::class,
```

- [ ] **Step 4: Routes**

```php
        // Opening balances
        Route::get('/{company}/accounting/opening-balances', [OpeningBalanceController::class, 'show'])->name('accounting.opening-balances.show');
        Route::post('/{company}/accounting/opening-balances', [OpeningBalanceController::class, 'store'])->name('accounting.opening-balances.store');
        Route::post('/{company}/accounting/opening-balances/lock', [OpeningBalanceController::class, 'lock'])->name('accounting.opening-balances.lock');
```

Add `use App\Modules\Accounting\Http\Controllers\OpeningBalanceController;` at the top of `web.php`.

- [ ] **Step 5: Schema contract**

Append to `docs/contracts/gl-core-schema.md`:

```markdown
## Opening balances

- Opening figures are ordinary records dated `as_of_date` with the counter-entry on account `3080 Opening Balance Equity` (created on demand: type `equity`, subtype `equity`, normal balance credit).
- The cash/bank/amanat/advance/partner journal is one `acct.transactions` row: `transaction_type = 'opening_balance'`, `reference_type = 'acct.opening_balances'`, `reference_id = null`. Its reversal (on re-save) is `transaction_type = 'opening_balance_reversal'`.
- Opening receivables are `acct.invoices` with a single line whose `income_account_id = 3080`, `internal_notes = 'OPENING'`. Opening payables are `acct.bills` with a single line whose `expense_account_id = 3080`, `internal_notes = 'OPENING'`.
- `fuel.amanat_transactions`, `payroll.salary_advances` and `auth.partner_transactions` created as opening rows carry `reference = 'OPENING'` and point at the opening journal via `transaction_id` / `journal_entry_id`.
- `company.settings.opening_balances = { as_of_date, locked_at, locked_by_user_id }`. Nothing else is stored in settings.
- `as_of_date` must be strictly earlier than the earliest posted non-opening transaction for the company.
```

- [ ] **Step 6: Sync and commit**

Run: `cd build && php artisan rbac:sync-permissions && php artisan rbac:sync-role-permissions`
Expected: both report success with the two new permissions.

```bash
git add build/app/Constants/Permissions.php build/config/role-permissions.php build/config/command-bus.php build/routes/web.php docs/contracts/gl-core-schema.md
git commit -m "Register opening balance permissions, commands and routes

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

Note: `web.php` now references a controller that does not exist until Task 7; `php artisan route:list` will fail until then. That is acceptable within this plan; do not run the app between Task 3 and Task 7.

### Task 4: `OpeningBalanceAccounts` resolver + `SaveAction` — cash & banks journal

**Files:**
- Create: `build/modules/Accounting/Services/OpeningBalanceAccounts.php`
- Create: `build/modules/Accounting/Actions/OpeningBalance/SaveAction.php`
- Create: `build/tests/Feature/Accounting/OpeningBalancesTest.php`

**Interfaces:**
- Produces:
  - `OpeningBalanceAccounts::resolve(string $companyId): array{cash:?string, ar:?string, ap:?string, amanat:?string, partner_deposits:?string, employee_advances:?string, equity:string}` — `equity` is always present (created if missing).
  - `SaveAction::handle(array $params): array{message:string, data:array{journal_id:?string, invoice_ids:string[], bill_ids:string[]}}`. Params shape:

    ```php
    [
      'as_of_date' => '2026-08-31',
      'cash' => ['amount' => 150000],
      'banks' => [['account_id' => uuid, 'amount' => 900000]],
      'credit_customers' => [['customer_id' => uuid, 'amount' => 42000]],
      'employees' => [['employee_id' => uuid, 'amount' => 5000]],
      'amanat' => [['customer_id' => uuid, 'amount' => 30000]],
      'suppliers' => [['vendor_id' => uuid, 'amount' => 250000]],
      'partners' => [['partner_id' => uuid, 'amount' => 1000000]],
    ]
    ```

- [ ] **Step 1: Write the fixture and first failing test**

`build/tests/Feature/Accounting/OpeningBalancesTest.php`:

```php
<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function openingBalanceFixture(): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Opening Balance Test',
        'slug' => 'opening-balance-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $fy = FiscalYear::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);
    foreach ([8 => ['2026-08-01', '2026-08-31'], 9 => ['2026-09-01', '2026-09-30']] as $n => [$start, $end]) {
        AccountingPeriod::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fy->id,
            'name' => "P{$n} 2026",
            'period_number' => $n,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    $mk = fn (string $code, string $name, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'subtype' => $subtype,
        'normal_balance' => $normal,
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    $accounts = [
        'cash' => $mk('1050', 'Cash on Hand', 'asset', 'cash', 'debit'),
        'bank' => $mk('1000', 'HBL Current', 'asset', 'bank', 'debit'),
        'bank2' => $mk('1010', 'UBL Card Settlement', 'asset', 'bank', 'debit'),
        'ar' => $mk('1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'),
        'advances' => $mk('1150', 'Employee Advances', 'asset', 'receivable', 'debit'),
        'ap' => $mk('2100', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'),
        'amanat' => $mk('2200', 'Customer Amanat Deposits', 'liability', 'other_current_liability', 'credit'),
        'partner' => $mk('2210', 'Investor Deposits', 'liability', 'other_current_liability', 'credit'),
    ];

    return compact('company', 'user', 'accounts');
}

function dispatchOpeningBalance(array $fixture, array $params): array
{
    return app(CompanyContextService::class)->withContext($fixture['company'], function () use ($fixture, $params) {
        return app(CommandBus::class)->dispatch('opening_balance.save', $params, $fixture['user'], true);
    });
}

function ledgerBalance(Account $account): float
{
    $rows = DB::table('acct.journal_entries')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit_amount),0) as d, COALESCE(SUM(credit_amount),0) as c')->first();
    return round((float) $rows->d - (float) $rows->c, 2);
}

test('saving cash and bank opening balances posts one balanced journal against opening balance equity', function () {
    $f = openingBalanceFixture();

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 150000],
        'banks' => [
            ['account_id' => $f['accounts']['bank']->id, 'amount' => 900000],
            ['account_id' => $f['accounts']['bank2']->id, 'amount' => 50000],
        ],
    ]);

    $journal = Transaction::find($result['data']['journal_id']);
    expect($journal)->not->toBeNull()
        ->and($journal->transaction_type)->toBe('opening_balance')
        ->and($journal->reference_type)->toBe('acct.opening_balances')
        ->and($journal->transaction_date->toDateString())->toBe('2026-08-31');

    $entries = $journal->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));

    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();
    expect($equity)->not->toBeNull()->and($equity->type)->toBe('equity');

    expect(ledgerBalance($f['accounts']['cash']))->toBe(150000.0)
        ->and(ledgerBalance($f['accounts']['bank']))->toBe(900000.0)
        ->and(ledgerBalance($f['accounts']['bank2']))->toBe(50000.0)
        ->and(ledgerBalance($equity))->toBe(-1100000.0);

    $settings = $f['company']->fresh()->settings;
    expect($settings['opening_balances']['as_of_date'])->toBe('2026-08-31')
        ->and($settings['opening_balances']['locked_at'])->toBeNull();
});
```

- [ ] **Step 2: Run it to see it fail**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php`
Expected: FAIL — command `opening_balance.save` class not found.

- [ ] **Step 3: Account resolver**

`build/modules/Accounting/Services/OpeningBalanceAccounts.php`:

```php
<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;

/**
 * Resolves the control accounts an opening-balance save needs.
 * Opening Balance Equity (3080) is created on demand; the rest are reported
 * as null so the caller can raise a precise "set up account X" error.
 */
class OpeningBalanceAccounts
{
    public const EQUITY_CODE = '3080';

    public function resolve(string $companyId): array
    {
        $active = fn () => Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true);
        $byCode = fn (string $code) => (clone $active())->where('code', $code)->value('id');
        $bySubtype = fn (string $subtype) => (clone $active())->where('subtype', $subtype)->orderBy('code')->value('id');

        return [
            'cash' => $byCode('1050') ?? $bySubtype('cash'),
            'ar' => $byCode('1100') ?? $bySubtype('accounts_receivable'),
            'ap' => $byCode('2100') ?? $bySubtype('accounts_payable'),
            'amanat' => $byCode('2200'),
            'partner_deposits' => $byCode('2210'),
            'employee_advances' => $byCode('1150'),
            'equity' => $this->ensureEquity($companyId),
        ];
    }

    private function ensureEquity(string $companyId): string
    {
        $existing = Account::where('company_id', $companyId)->where('code', self::EQUITY_CODE)->first();
        if ($existing) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }
            return $existing->id;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => self::EQUITY_CODE,
            'name' => 'Opening Balance Equity',
            'type' => 'equity',
            'subtype' => 'equity',
            'normal_balance' => 'credit',
            'description' => 'Counter-entry for opening balances',
            'is_active' => true,
        ])->id;
    }
}
```

Check `Account::$fillable` includes `description` and `is_active`; drop keys it does not accept.

- [ ] **Step 4: SaveAction — cash & banks only**

`build/modules/Accounting/Actions/OpeningBalance/SaveAction.php`:

```php
<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAction implements PaletteAction
{
    public const JOURNAL_TYPE = 'opening_balance';
    public const REVERSAL_TYPE = 'opening_balance_reversal';
    public const REFERENCE_TYPE = 'acct.opening_balances';
    public const MARK = 'OPENING';

    public function __construct(
        private readonly GlPostingService $posting,
        private readonly OpeningBalanceAccounts $accounts,
    ) {}

    public function rules(): array
    {
        return [
            'as_of_date' => 'required|date',
            'cash' => 'nullable|array',
            'cash.amount' => 'nullable|numeric|min:0',
            'banks' => 'nullable|array',
            'banks.*.account_id' => 'required|uuid|exists:acct.accounts,id',
            'banks.*.amount' => 'required|numeric|min:0',
            'credit_customers' => 'nullable|array',
            'credit_customers.*.customer_id' => 'required|uuid|exists:acct.customers,id',
            'credit_customers.*.amount' => 'required|numeric|gt:0',
            'employees' => 'nullable|array',
            'employees.*.employee_id' => 'required|uuid|exists:payroll.employees,id',
            'employees.*.amount' => 'required|numeric|gt:0',
            'amanat' => 'nullable|array',
            'amanat.*.customer_id' => 'required|uuid|exists:acct.customers,id',
            'amanat.*.amount' => 'required|numeric|gt:0',
            'suppliers' => 'nullable|array',
            'suppliers.*.vendor_id' => 'required|uuid|exists:acct.vendors,id',
            'suppliers.*.amount' => 'required|numeric|gt:0',
            'partners' => 'nullable|array',
            'partners.*.partner_id' => 'required|uuid|exists:auth.partners,id',
            'partners.*.amount' => 'required|numeric|gt:0',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_MANAGE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $asOf = $params['as_of_date'];

        $this->guardNotLocked($company);
        $this->guardDate($company->id, $asOf);

        return DB::transaction(function () use ($company, $params, $asOf) {
            $accounts = $this->accounts->resolve($company->id);
            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));

            $lines = [];   // journal entries: ['account_id','type','amount','description']
            $debits = 0.0; // running totals to compute the 3080 line

            $this->addCashAndBankLines($company->id, $params, $accounts, $lines);

            $journalId = $this->postJournal($company->id, $currency, $asOf, $accounts['equity'], $lines);

            $settings = $company->settings ?? [];
            $settings['opening_balances'] = [
                'as_of_date' => $asOf,
                'locked_at' => null,
                'locked_by_user_id' => null,
            ];
            $company->settings = $settings;
            $company->save();

            return [
                'message' => 'Opening balances saved as of '.$asOf,
                'data' => ['journal_id' => $journalId, 'invoice_ids' => [], 'bill_ids' => []],
            ];
        });
    }

    private function guardNotLocked($company): void
    {
        if (! empty(($company->settings['opening_balances'] ?? [])['locked_at'])) {
            throw ValidationException::withMessages(['as_of_date' => 'Opening balances are locked.']);
        }
    }

    private function guardDate(string $companyId, string $asOf): void
    {
        $earliest = Transaction::where('company_id', $companyId)
            ->whereNotIn('transaction_type', [self::JOURNAL_TYPE, self::REVERSAL_TYPE])
            ->where(function ($q) {
                $q->whereNull('reference_type')->orWhere('reference_type', '!=', self::REFERENCE_TYPE);
            })
            ->whereIn('status', ['posted', 'locked'])
            ->min('transaction_date');

        if ($earliest && $asOf >= substr((string) $earliest, 0, 10)) {
            throw ValidationException::withMessages([
                'as_of_date' => "Opening balances must be dated before the first posted transaction ({$earliest}).",
            ]);
        }
    }

    private function addCashAndBankLines(string $companyId, array $params, array $accounts, array &$lines): void
    {
        $cash = (float) ($params['cash']['amount'] ?? 0);
        if ($cash > 0) {
            if (! $accounts['cash']) {
                throw ValidationException::withMessages(['cash.amount' => 'Set up account 1050 (Cash on Hand) first.']);
            }
            $lines[] = ['account_id' => $accounts['cash'], 'type' => 'debit', 'amount' => $cash, 'description' => 'Opening cash on hand'];
        }

        foreach ($params['banks'] ?? [] as $i => $bank) {
            $amount = (float) $bank['amount'];
            if ($amount <= 0) {
                continue;
            }
            $account = Account::where('company_id', $companyId)->where('id', $bank['account_id'])->where('subtype', 'bank')->first();
            if (! $account) {
                throw ValidationException::withMessages(["banks.{$i}.account_id" => 'Not a bank account of this company.']);
            }
            $lines[] = ['account_id' => $account->id, 'type' => 'debit', 'amount' => $amount, 'description' => "Opening balance — {$account->name}"];
        }
    }

    /** Posts the lines plus a single 3080 line that makes them balance. Returns null if there are no lines. */
    private function postJournal(string $companyId, string $currency, string $asOf, string $equityId, array $lines): ?string
    {
        if (empty($lines)) {
            return null;
        }

        $debits = 0.0;
        $credits = 0.0;
        foreach ($lines as $line) {
            $line['type'] === 'debit' ? $debits += $line['amount'] : $credits += $line['amount'];
        }
        $net = round($debits - $credits, 2);
        if ($net > 0) {
            $lines[] = ['account_id' => $equityId, 'type' => 'credit', 'amount' => $net, 'description' => 'Opening balance equity'];
        } elseif ($net < 0) {
            $lines[] = ['account_id' => $equityId, 'type' => 'debit', 'amount' => abs($net), 'description' => 'Opening balance equity'];
        }

        $transaction = $this->posting->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => self::JOURNAL_TYPE,
            'date' => $asOf,
            'currency' => $currency,
            'base_currency' => $currency,
            'description' => 'Opening balances as of '.$asOf,
            'reference_type' => self::REFERENCE_TYPE,
            'reference_id' => null,
        ], $lines);

        return $transaction->id;
    }
}
```

Read `GlPostingService::postBalancedTransaction` (lines 40–85) and confirm the header keys (`date`, `currency`, `base_currency`, `description`, `reference_type`, `reference_id`, `transaction_type`) and entry keys (`account_id`, `type`, `amount`, `description`) match — they are the same ones `AmanatService::deposit` passes. If `status` must be set explicitly for the transaction to count as posted, pass `'status' => 'posted'`.

- [ ] **Step 5: Run the test**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add build/modules/Accounting/Services/OpeningBalanceAccounts.php build/modules/Accounting/Actions/OpeningBalance/SaveAction.php build/tests/Feature/Accounting/OpeningBalancesTest.php
git commit -m "Post cash and bank opening balances against opening balance equity

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 5: `SaveAction` — amanat, employee advances, partner capital (journal lines + sub-records)

**Files:**
- Modify: `build/modules/Accounting/Actions/OpeningBalance/SaveAction.php`
- Modify: `build/tests/Feature/Accounting/OpeningBalancesTest.php`

**Interfaces:**
- Consumes: `CustomerProfile::getOrCreateForCustomer(string $companyId, string $customerId): CustomerProfile`, `CustomerProfile::adjustAmanatBalance(float)`, `AmanatTransaction::TYPE_DEPOSIT`, `SalaryAdvance` (`status = 'pending'`), `PartnerTransaction` (`transaction_type = 'investment'`).
- Produces: sub-records tagged `reference = 'OPENING'` and linked to the journal by `transaction_id` (amanat) / `journal_entry_id` (advance, partner).

- [ ] **Step 1: Failing test**

Append to the test file (add the `use` lines at the top: `App\Modules\Accounting\Models\Customer`, `App\Modules\FuelStation\Models\AmanatTransaction`, `App\Modules\FuelStation\Models\CustomerProfile`, `App\Modules\Payroll\Models\Employee`, `App\Modules\Payroll\Models\SalaryAdvance`, `App\Models\Partner`, `App\Models\PartnerTransaction`):

```php
function openingCustomer(array $f, string $name): Customer
{
    return Customer::create([
        'company_id' => $f['company']->id,
        'customer_number' => 'CUST-'.str()->upper(str()->random(5)),
        'name' => $name,
        'customer_type' => 'business',
        'base_currency' => 'PKR',
        'ar_account_id' => $f['accounts']['ar']->id,
    ]);
}

test('amanat, employee advance and partner capital openings create sub-records linked to the journal', function () {
    $f = openingBalanceFixture();
    $depositor = openingCustomer($f, 'Haji Saab');
    $employee = Employee::create([
        'company_id' => $f['company']->id,
        'employee_number' => 'EMP-001',
        'first_name' => 'Ali',
        'last_name' => 'Khan',
        'hire_date' => '2025-01-01',
    ]);
    $partner = Partner::create([
        'company_id' => $f['company']->id,
        'name' => 'Owner One',
        'profit_share_percentage' => 100,
    ]);

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'amanat' => [['customer_id' => $depositor->id, 'amount' => 30000]],
        'employees' => [['employee_id' => $employee->id, 'amount' => 5000]],
        'partners' => [['partner_id' => $partner->id, 'amount' => 1000000]],
    ]);

    $journalId = $result['data']['journal_id'];

    $amanat = AmanatTransaction::where('customer_id', $depositor->id)->first();
    expect($amanat)->not->toBeNull()
        ->and($amanat->transaction_type)->toBe(AmanatTransaction::TYPE_DEPOSIT)
        ->and((float) $amanat->amount)->toBe(30000.0)
        ->and($amanat->reference)->toBe('OPENING')
        ->and($amanat->transaction_id)->toBe($journalId);
    expect((float) CustomerProfile::where('customer_id', $depositor->id)->first()->amanat_balance)->toBe(30000.0);

    $advance = SalaryAdvance::where('employee_id', $employee->id)->first();
    expect($advance)->not->toBeNull()
        ->and((float) $advance->amount_outstanding)->toBe(5000.0)
        ->and($advance->status)->toBe('pending')
        ->and($advance->reference)->toBe('OPENING')
        ->and($advance->journal_entry_id)->toBe($journalId);

    $capital = PartnerTransaction::where('partner_id', $partner->id)->first();
    expect($capital)->not->toBeNull()
        ->and($capital->transaction_type)->toBe('investment')
        ->and((float) $capital->amount)->toBe(1000000.0)
        ->and($capital->journal_entry_id)->toBe($journalId);

    expect(ledgerBalance($f['accounts']['amanat']))->toBe(-30000.0)
        ->and(ledgerBalance($f['accounts']['advances']))->toBe(5000.0)
        ->and(ledgerBalance($f['accounts']['partner']))->toBe(-1000000.0);

    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();
    // assets 5000 − liabilities 1,030,000 = −1,025,000 → 3080 carries a debit of 1,025,000
    expect(ledgerBalance($equity))->toBe(1025000.0);
});
```

Fill in any extra required columns the `Employee` / `Partner` / `Customer` factories or NOT NULL constraints demand — run the test, read the SQL error, add the column.

- [ ] **Step 2: Run to see it fail**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php --filter=sub-records`
Expected: FAIL — no amanat transaction.

- [ ] **Step 3: Implement**

In `SaveAction`, add the imports:

```php
use App\Models\PartnerTransaction;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Payroll\Models\SalaryAdvance;
use Illuminate\Support\Facades\Auth;
```

Change `handle()` so the journal is posted first and then the sub-records reference it. Replace the body between `$lines = [];` and the settings write with:

```php
            $this->addCashAndBankLines($company->id, $params, $accounts, $lines);
            $pending = [];   // closures run after the journal exists, receiving its id
            $this->addAmanatLines($company->id, $params, $accounts, $lines, $pending);
            $this->addEmployeeAdvanceLines($company->id, $params, $accounts, $asOf, $lines, $pending);
            $this->addPartnerLines($company->id, $params, $accounts, $asOf, $lines, $pending);

            $journalId = $this->postJournal($company->id, $currency, $asOf, $accounts['equity'], $lines);
            foreach ($pending as $create) {
                $create($journalId);
            }
```

Add the three methods:

```php
    private function addAmanatLines(string $companyId, array $params, array $accounts, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['amanat'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['amanat']) {
            throw ValidationException::withMessages(['amanat' => 'Set up account 2200 (Customer Amanat Deposits) first.']);
        }
        foreach ($rows as $row) {
            $customer = Customer::where('company_id', $companyId)->findOrFail($row['customer_id']);
            $amount = round((float) $row['amount'], 2);
            $lines[] = ['account_id' => $accounts['amanat'], 'type' => 'credit', 'amount' => $amount, 'description' => "Opening amanat — {$customer->name}"];
            $pending[] = function (string $journalId) use ($companyId, $customer, $amount) {
                $profile = CustomerProfile::getOrCreateForCustomer($companyId, $customer->id);
                if (! $profile->is_amanat_holder) {
                    $profile->update(['is_amanat_holder' => true]);
                }
                AmanatTransaction::create([
                    'company_id' => $companyId,
                    'customer_id' => $customer->id,
                    'transaction_type' => AmanatTransaction::TYPE_DEPOSIT,
                    'amount' => $amount,
                    'reference' => self::MARK,
                    'notes' => 'Opening balance',
                    'recorded_by_user_id' => Auth::id(),
                    'transaction_id' => $journalId,
                ]);
                $profile->adjustAmanatBalance($amount);
            };
        }
    }

    private function addEmployeeAdvanceLines(string $companyId, array $params, array $accounts, string $asOf, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['employees'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['employee_advances']) {
            throw ValidationException::withMessages(['employees' => 'Set up account 1150 (Employee Advances) first.']);
        }
        foreach ($rows as $row) {
            $amount = round((float) $row['amount'], 2);
            $employeeId = $row['employee_id'];
            $lines[] = ['account_id' => $accounts['employee_advances'], 'type' => 'debit', 'amount' => $amount, 'description' => 'Opening employee advance'];
            $pending[] = function (string $journalId) use ($companyId, $employeeId, $amount, $asOf, $accounts) {
                SalaryAdvance::create([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'advance_date' => $asOf,
                    'amount' => $amount,
                    'amount_recovered' => 0,
                    'amount_outstanding' => $amount,
                    'reason' => 'Opening balance',
                    'status' => 'pending',
                    'payment_method' => 'cash',
                    'reference' => self::MARK,
                    'journal_entry_id' => $journalId,
                    'advance_account_id' => $accounts['employee_advances'],
                    'recorded_by_user_id' => Auth::id(),
                ]);
            };
        }
    }

    private function addPartnerLines(string $companyId, array $params, array $accounts, string $asOf, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['partners'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['partner_deposits']) {
            throw ValidationException::withMessages(['partners' => 'Set up account 2210 (Investor Deposits) first.']);
        }
        foreach ($rows as $row) {
            $amount = round((float) $row['amount'], 2);
            $partnerId = $row['partner_id'];
            $lines[] = ['account_id' => $accounts['partner_deposits'], 'type' => 'credit', 'amount' => $amount, 'description' => 'Opening partner capital'];
            $pending[] = function (string $journalId) use ($companyId, $partnerId, $amount, $asOf) {
                PartnerTransaction::create([
                    'company_id' => $companyId,
                    'partner_id' => $partnerId,
                    'transaction_date' => $asOf,
                    'transaction_type' => 'investment',
                    'amount' => $amount,
                    'description' => 'Opening balance',
                    'reference' => self::MARK,
                    'payment_method' => 'cash',
                    'journal_entry_id' => $journalId,
                    'recorded_by_user_id' => Auth::id(),
                ]);
            };
        }
    }
```

If `SalaryAdvance` has a `status` enum without `pending`, use the value its `scopePending` checks (line ~109 of the model).

- [ ] **Step 4: Run tests**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php`
Expected: 2 PASS.

- [ ] **Step 5: Commit**

```bash
git add build/modules/Accounting/Actions/OpeningBalance/SaveAction.php build/tests/Feature/Accounting/OpeningBalancesTest.php
git commit -m "Record amanat, advance and partner opening balances with linked sub-records

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 6: `SaveAction` — credit customers (invoices) and suppliers (bills)

**Files:**
- Modify: `build/modules/Accounting/Actions/OpeningBalance/SaveAction.php`
- Modify: `build/tests/Feature/Accounting/OpeningBalancesTest.php`

**Interfaces:**
- Consumes: CommandBus `invoice.create` (`App\Modules\Accounting\Actions\Invoice\CreateAction`, params `customer, currency, date, due, line_items[{description, quantity, unit_price, tax_rate, income_account_id}], internal_notes, send_immediately`) and `bill.create` (`Bill\CreateAction`, params `vendor_id, bill_date, due_date, status:'received', currency, base_currency, internal_notes, line_items[{description, quantity, unit_price, tax_rate, expense_account_id}]`). Confirm the exact command names in `config/command-bus.php`.
- Produces: `data.invoice_ids`, `data.bill_ids` in the result.

- [ ] **Step 1: Failing test**

Append (`use App\Modules\Accounting\Models\Invoice; use App\Modules\Accounting\Models\Bill; use App\Modules\Accounting\Models\Vendor;`):

```php
test('credit customer and supplier openings become posted invoices and bills against opening balance equity', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Truck Company');
    $vendor = Vendor::create([
        'company_id' => $f['company']->id,
        'vendor_number' => 'VEND-0001',
        'name' => 'PSO Depot',
        'base_currency' => 'PKR',
        'is_active' => true,
        'ap_account_id' => $f['accounts']['ap']->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]],
        'suppliers' => [['vendor_id' => $vendor->id, 'amount' => 250000]],
    ]);

    expect($result['data']['journal_id'])->toBeNull();

    $invoice = Invoice::find($result['data']['invoice_ids'][0]);
    expect($invoice->customer_id)->toBe($customer->id)
        ->and((float) $invoice->total_amount)->toBe(42000.0)
        ->and((float) $invoice->balance)->toBe(42000.0)
        ->and($invoice->internal_notes)->toBe('OPENING')
        ->and($invoice->transaction_id)->not->toBeNull()
        ->and($invoice->invoice_date->toDateString())->toBe('2026-08-31');
    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();
    expect($invoice->lineItems->first()->income_account_id)->toBe($equity->id);

    $bill = Bill::find($result['data']['bill_ids'][0]);
    expect($bill->vendor_id)->toBe($vendor->id)
        ->and((float) $bill->balance)->toBe(250000.0)
        ->and($bill->internal_notes)->toBe('OPENING')
        ->and($bill->transaction_id)->not->toBeNull()
        ->and($bill->lineItems->first()->expense_account_id)->toBe($equity->id);

    expect(ledgerBalance($f['accounts']['ar']))->toBe(42000.0)
        ->and(ledgerBalance($f['accounts']['ap']))->toBe(-250000.0)
        ->and(ledgerBalance($equity))->toBe(208000.0);
});
```

- [ ] **Step 2: Run to see it fail**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php --filter=invoices`
Expected: FAIL — `invoice_ids[0]` undefined.

- [ ] **Step 3: Implement**

Add `use App\Services\CommandBus;` and `use App\Modules\Accounting\Models\Vendor;`. In `handle()` after the `$pending` loop:

```php
            $invoiceIds = $this->createOpeningInvoices($company, $params, $accounts, $asOf, $currency);
            $billIds = $this->createOpeningBills($company, $params, $accounts, $asOf, $currency);
```

and return them in `data`. Methods:

```php
    private function createOpeningInvoices($company, array $params, array $accounts, string $asOf, string $currency): array
    {
        $rows = array_filter($params['credit_customers'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return [];
        }
        if (! $accounts['ar']) {
            throw ValidationException::withMessages(['credit_customers' => 'Set up account 1100 (Accounts Receivable) first.']);
        }
        $bus = app(CommandBus::class);
        $ids = [];
        foreach ($rows as $row) {
            $customer = Customer::where('company_id', $company->id)->findOrFail($row['customer_id']);
            $result = $bus->dispatch('invoice.create', [
                'customer' => $customer->id,
                'currency' => $currency,
                'date' => $asOf,
                'due' => $asOf,
                'send_immediately' => true,
                'internal_notes' => self::MARK,
                'description' => 'Opening balance as of '.$asOf,
                'line_items' => [[
                    'description' => 'Opening balance as of '.$asOf,
                    'quantity' => 1,
                    'unit_price' => round((float) $row['amount'], 2),
                    'tax_rate' => 0,
                    'income_account_id' => $accounts['equity'],
                ]],
            ], Auth::user(), true);
            $ids[] = $result['data']['id'];
        }
        return $ids;
    }

    private function createOpeningBills($company, array $params, array $accounts, string $asOf, string $currency): array
    {
        $rows = array_filter($params['suppliers'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return [];
        }
        if (! $accounts['ap']) {
            throw ValidationException::withMessages(['suppliers' => 'Set up account 2100 (Accounts Payable) first.']);
        }
        $bus = app(CommandBus::class);
        $ids = [];
        foreach ($rows as $row) {
            Vendor::where('company_id', $company->id)->findOrFail($row['vendor_id']);
            $result = $bus->dispatch('bill.create', [
                'vendor_id' => $row['vendor_id'],
                'bill_date' => $asOf,
                'due_date' => $asOf,
                'status' => 'received',
                'currency' => $currency,
                'base_currency' => $currency,
                'internal_notes' => self::MARK,
                'line_items' => [[
                    'description' => 'Opening balance as of '.$asOf,
                    'quantity' => 1,
                    'unit_price' => round((float) $row['amount'], 2),
                    'tax_rate' => 0,
                    'expense_account_id' => $accounts['equity'],
                ]],
            ], Auth::user(), true);
            $ids[] = $result['data']['id'];
        }
        return $ids;
    }
```

Check `Invoice\CreateAction::handle` return shape (`data.id` vs `data.invoice.id`) at lines ~102–115 and adjust the `$result['data']['id']` access. If the dispatch's 4th argument (`true` = skip permission re-check) is not supported by `CommandBus::dispatch`, drop it — the test helper passes it already, so the signature exists.

If the invoice action's tax/discount defaults or the posting template inject tax lines even with `tax_rate => 0`, the AR total will still equal the amount; the test asserts `total_amount` so it will surface.

- [ ] **Step 4: Run tests**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php`
Expected: 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add build/modules/Accounting/Actions/OpeningBalance/SaveAction.php build/tests/Feature/Accounting/OpeningBalancesTest.php
git commit -m "Create opening invoices and bills for customer and supplier balances

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 7: Re-save (replace), date guard, lock, and `ViewAction`

**Files:**
- Modify: `build/modules/Accounting/Actions/OpeningBalance/SaveAction.php`
- Create: `build/modules/Accounting/Actions/OpeningBalance/LockAction.php`
- Create: `build/modules/Accounting/Actions/OpeningBalance/ViewAction.php`
- Modify: `build/tests/Feature/Accounting/OpeningBalancesTest.php`

**Interfaces:**
- Consumes: CommandBus `invoice.void` (`{id, reason}`), `bill.void` (`{id, reason}`) — confirm names in `config/command-bus.php`.
- Produces:
  - `LockAction::handle([]): array{message}` — sets `settings.opening_balances.locked_at/locked_by_user_id`.
  - `ViewAction::handle([]): array` with shape:

    ```php
    [
      'as_of_date' => ?string, 'locked_at' => ?string, 'locked_by' => ?string,
      'earliest_transaction_date' => ?string,
      'rows' => [
        'cash' => ['amount' => float],
        'banks' => [['account_id','account_name','amount']],
        'credit_customers' => [['customer_id','customer_name','amount','invoice_id','paid_amount']],
        'employees' => [['employee_id','employee_name','amount','recovered']],
        'amanat' => [['customer_id','customer_name','amount']],
        'suppliers' => [['vendor_id','vendor_name','amount','bill_id','paid_amount']],
        'partners' => [['partner_id','partner_name','amount']],
      ],
      'totals' => ['assets' => float, 'liabilities' => float, 'equity' => float],
      'options' => [
        'bank_accounts' => [['id','code','name']], 'customers' => [['id','name']], 'vendors' => [['id','name']],
        'employees' => [['id','name']], 'partners' => [['id','name']],
      ],
    ]
    ```

- [ ] **Step 1: Failing tests**

```php
test('re-saving replaces the previous opening records without duplicating balances', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Truck Company');

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 150000],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]],
    ]);
    $second = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 120000],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 40000]],
    ]);

    expect(ledgerBalance($f['accounts']['cash']))->toBe(120000.0)
        ->and(ledgerBalance($f['accounts']['ar']))->toBe(40000.0);
    expect(Invoice::where('company_id', $f['company']->id)->where('status', '!=', 'void')->count())->toBe(1);
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'opening_balance_reversal')->count())->toBe(1);
    $view = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true));
    expect($view['rows']['cash']['amount'])->toBe(120000.0)
        ->and($view['rows']['credit_customers'][0]['amount'])->toBe(40000.0)
        ->and($view['totals']['assets'])->toBe(160000.0);
});

test('as_of_date on or after the first posted transaction is rejected', function () {
    $f = openingBalanceFixture();
    Transaction::create([
        'company_id' => $f['company']->id,
        'transaction_number' => 'JE-0001',
        'transaction_type' => 'journal',
        'transaction_date' => '2026-09-01',
        'posting_date' => '2026-09-01',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'status' => 'posted',
        'description' => 'first close',
    ]);

    expect(fn () => dispatchOpeningBalance($f, ['as_of_date' => '2026-09-01', 'cash' => ['amount' => 1]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('locking prevents further saves', function () {
    $f = openingBalanceFixture();
    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 10]]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true));

    expect($f['company']->fresh()->settings['opening_balances']['locked_at'])->not->toBeNull();
    expect(fn () => dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 20]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
```

Add missing NOT NULL columns to the `Transaction::create` in the guard test if the DB complains (`fiscal_year_id`, `period_id` — take them from the fixture's period).

- [ ] **Step 2: Run to see them fail**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php`
Expected: the three new tests FAIL (duplicated balances / `opening_balance.view` unknown / lock unknown).

- [ ] **Step 3: Replace-previous in SaveAction**

At the start of the `DB::transaction` closure in `handle()`, before resolving accounts, call `$this->reversePrevious($company);`:

```php
    /**
     * Opening balances are re-entered as a whole: every earlier opening record is
     * reversed/voided first. Refuses when any opening record has been used since.
     */
    private function reversePrevious($company): void
    {
        $companyId = $company->id;
        $bus = app(CommandBus::class);
        $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        $invoices = Invoice::where('company_id', $companyId)->where('internal_notes', self::MARK)->where('status', '!=', 'void')->get();
        foreach ($invoices as $invoice) {
            if ((float) $invoice->paid_amount > 0) {
                throw ValidationException::withMessages(['credit_customers' => "Opening invoice {$invoice->invoice_number} already has payments; cannot re-enter opening balances."]);
            }
            $bus->dispatch('invoice.void', ['id' => $invoice->id, 'reason' => 'Opening balances re-entered'], Auth::user(), true);
        }

        $bills = Bill::where('company_id', $companyId)->where('internal_notes', self::MARK)->where('status', '!=', 'void')->get();
        foreach ($bills as $bill) {
            if ((float) $bill->paid_amount > 0) {
                throw ValidationException::withMessages(['suppliers' => "Opening bill {$bill->bill_number} already has payments; cannot re-enter opening balances."]);
            }
            $bus->dispatch('bill.void', ['id' => $bill->id, 'reason' => 'Opening balances re-entered'], Auth::user(), true);
        }

        $journals = Transaction::where('company_id', $companyId)
            ->where('transaction_type', self::JOURNAL_TYPE)
            ->whereNull('reversed_by_transaction_id')
            ->with('journalEntries')
            ->get();
        foreach ($journals as $journal) {
            foreach (AmanatTransaction::where('transaction_id', $journal->id)->get() as $amanat) {
                $profile = CustomerProfile::getOrCreateForCustomer($companyId, $amanat->customer_id);
                if ((float) $profile->amanat_balance < (float) $amanat->amount) {
                    throw ValidationException::withMessages(['amanat' => 'An opening amanat balance has already been drawn down; cannot re-enter opening balances.']);
                }
                $profile->adjustAmanatBalance(-(float) $amanat->amount);
                $amanat->delete();
            }
            foreach (SalaryAdvance::where('journal_entry_id', $journal->id)->get() as $advance) {
                if ((float) $advance->amount_recovered > 0) {
                    throw ValidationException::withMessages(['employees' => 'An opening employee advance has already been partly recovered; cannot re-enter opening balances.']);
                }
                $advance->delete();
            }
            PartnerTransaction::where('journal_entry_id', $journal->id)->delete();

            $reversal = [];
            foreach ($journal->journalEntries as $entry) {
                $debit = (float) $entry->debit_amount;
                $credit = (float) $entry->credit_amount;
                $reversal[] = [
                    'account_id' => $entry->account_id,
                    'type' => $debit > 0 ? 'credit' : 'debit',
                    'amount' => $debit > 0 ? $debit : $credit,
                    'description' => 'Reversal of opening balances',
                ];
            }
            $reversalTx = $this->posting->postBalancedTransaction([
                'company_id' => $companyId,
                'transaction_type' => self::REVERSAL_TYPE,
                'date' => $journal->transaction_date->toDateString(),
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => 'Reversal of opening balances '.$journal->transaction_number,
                'reference_type' => self::REFERENCE_TYPE,
                'reference_id' => $journal->id,
            ], $reversal);
            $journal->reversed_by_transaction_id = $reversalTx->id;
            $journal->save();
        }
    }
```

Check `acct.transactions` has a `reversed_by_transaction_id` column (`grep reversed build/modules/Accounting/Models/Transaction.php` / `docs/contracts/gl-core-schema.md`). If it does not, replace the `whereNull('reversed_by_transaction_id')` filter with `whereDoesntHave` on reversals by `reference_id` (`Transaction::where('transaction_type', REVERSAL_TYPE)->where('reference_id', $journal->id)->exists()`), and drop the two lines that set it. Add `use App\Modules\Accounting\Models\Invoice; use App\Modules\Accounting\Models\Bill;`.

- [ ] **Step 4: LockAction**

```php
<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LockAction implements PaletteAction
{
    public function rules(): array
    {
        return [];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_MANAGE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $settings = $company->settings ?? [];
        $opening = $settings['opening_balances'] ?? null;

        if (! $opening || empty($opening['as_of_date'])) {
            throw ValidationException::withMessages(['as_of_date' => 'Save opening balances before locking them.']);
        }
        if (! empty($opening['locked_at'])) {
            return ['message' => 'Opening balances were already locked.'];
        }

        $opening['locked_at'] = now()->toIso8601String();
        $opening['locked_by_user_id'] = Auth::id();
        $settings['opening_balances'] = $opening;
        $company->settings = $settings;
        $company->save();

        return ['message' => 'Opening balances locked as of '.$opening['as_of_date']];
    }
}
```

- [ ] **Step 5: ViewAction**

```php
<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;

class ViewAction implements PaletteAction
{
    public function __construct(private readonly OpeningBalanceAccounts $accounts) {}

    public function rules(): array
    {
        return [];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_VIEW;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $companyId = $company->id;
        $opening = ($company->settings ?? [])['opening_balances'] ?? [];
        $accounts = $this->accounts->resolve($companyId);

        $journal = Transaction::where('company_id', $companyId)
            ->where('transaction_type', SaveAction::JOURNAL_TYPE)
            ->whereDoesntHave('reversals')
            ->with('journalEntries.account')
            ->latest('created_at')
            ->first();

        $cash = 0.0;
        $banks = [];
        if ($journal) {
            foreach ($journal->journalEntries as $entry) {
                if ($entry->account_id === $accounts['cash']) {
                    $cash += (float) $entry->debit_amount;
                } elseif ($entry->account?->subtype === 'bank') {
                    $banks[] = ['account_id' => $entry->account_id, 'account_name' => $entry->account->name, 'amount' => (float) $entry->debit_amount];
                }
            }
        }

        $amanat = $journal ? AmanatTransaction::where('transaction_id', $journal->id)->with('customer:id,name')->get()
            ->map(fn ($t) => ['customer_id' => $t->customer_id, 'customer_name' => $t->customer?->name, 'amount' => (float) $t->amount])->values()->all() : [];
        $employees = $journal ? SalaryAdvance::where('journal_entry_id', $journal->id)->with('employee:id,first_name,last_name')->get()
            ->map(fn ($a) => ['employee_id' => $a->employee_id, 'employee_name' => trim(($a->employee?->first_name ?? '').' '.($a->employee?->last_name ?? '')), 'amount' => (float) $a->amount, 'recovered' => (float) $a->amount_recovered])->values()->all() : [];
        $partners = $journal ? PartnerTransaction::where('journal_entry_id', $journal->id)->with('partner:id,name')->get()
            ->map(fn ($p) => ['partner_id' => $p->partner_id, 'partner_name' => $p->partner?->name, 'amount' => (float) $p->amount])->values()->all() : [];

        $creditCustomers = Invoice::where('company_id', $companyId)->where('internal_notes', SaveAction::MARK)->where('status', '!=', 'void')
            ->with('customer:id,name')->get()
            ->map(fn ($i) => ['customer_id' => $i->customer_id, 'customer_name' => $i->customer?->name, 'amount' => (float) $i->total_amount, 'invoice_id' => $i->id, 'paid_amount' => (float) $i->paid_amount])->values()->all();
        $suppliers = Bill::where('company_id', $companyId)->where('internal_notes', SaveAction::MARK)->where('status', '!=', 'void')
            ->with('vendor:id,name')->get()
            ->map(fn ($b) => ['vendor_id' => $b->vendor_id, 'vendor_name' => $b->vendor?->name, 'amount' => (float) $b->total_amount, 'bill_id' => $b->id, 'paid_amount' => (float) $b->paid_amount])->values()->all();

        $assets = $cash + array_sum(array_column($banks, 'amount')) + array_sum(array_column($creditCustomers, 'amount')) + array_sum(array_column($employees, 'amount'));
        $liabilities = array_sum(array_column($amanat, 'amount')) + array_sum(array_column($suppliers, 'amount')) + array_sum(array_column($partners, 'amount'));

        $earliest = Transaction::where('company_id', $companyId)
            ->whereNotIn('transaction_type', [SaveAction::JOURNAL_TYPE, SaveAction::REVERSAL_TYPE])
            ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', '!=', SaveAction::REFERENCE_TYPE))
            ->whereIn('status', ['posted', 'locked'])
            ->min('transaction_date');

        $lockedBy = ! empty($opening['locked_by_user_id']) ? User::find($opening['locked_by_user_id'])?->name : null;

        return [
            'as_of_date' => $opening['as_of_date'] ?? null,
            'locked_at' => $opening['locked_at'] ?? null,
            'locked_by' => $lockedBy,
            'earliest_transaction_date' => $earliest ? substr((string) $earliest, 0, 10) : null,
            'rows' => [
                'cash' => ['amount' => round($cash, 2)],
                'banks' => $banks,
                'credit_customers' => $creditCustomers,
                'employees' => $employees,
                'amanat' => $amanat,
                'suppliers' => $suppliers,
                'partners' => $partners,
            ],
            'totals' => [
                'assets' => round($assets, 2),
                'liabilities' => round($liabilities, 2),
                'equity' => round($assets - $liabilities, 2),
            ],
            'options' => [
                'bank_accounts' => Account::where('company_id', $companyId)->where('is_active', true)->where('subtype', 'bank')->orderBy('code')->get(['id', 'code', 'name'])->toArray(),
                'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->toArray(),
                'vendors' => Vendor::where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->toArray(),
                'employees' => Employee::where('company_id', $companyId)->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                    ->map(fn ($e) => ['id' => $e->id, 'name' => trim($e->first_name.' '.$e->last_name)])->values()->all(),
                'partners' => Partner::where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->toArray(),
            ],
        ];
    }
}
```

`whereDoesntHave('reversals')` needs a relation; if `Transaction` has no `reversals()` relation, replace with the same "not reversed" filter used in Task 7 Step 3 (`whereNull('reversed_by_transaction_id')` or a `whereNotExists` sub-query on `reference_id`). If `AmanatTransaction`/`SalaryAdvance`/`PartnerTransaction` lack `customer()`/`employee()`/`partner()` relations, load names with a keyed `pluck` instead.

- [ ] **Step 6: Run all tests**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php`
Expected: 6 PASS.

- [ ] **Step 7: Commit**

```bash
git add build/modules/Accounting/Actions/OpeningBalance build/tests/Feature/Accounting/OpeningBalancesTest.php
git commit -m "Add opening balance re-entry, date guard, lock and view actions

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 8: FormRequest, controller, permission test

**Files:**
- Create: `build/modules/Accounting/Http/Requests/StoreOpeningBalancesRequest.php`
- Create: `build/modules/Accounting/Http/Controllers/OpeningBalanceController.php`
- Modify: `build/tests/Feature/Accounting/OpeningBalancesTest.php`

**Interfaces:**
- Consumes: Task 3 routes; Task 4–7 actions.
- Produces: Inertia page `accounting/opening-balances/Index` with props `{ company: {id,name,slug}, opening: <ViewAction result> }`.

- [ ] **Step 1: Failing HTTP test**

```php
test('the opening balances page requires the view permission and store requires manage', function () {
    $f = openingBalanceFixture();
    $slug = $f['company']->slug;

    $this->actingAs($f['user'])
        ->get("/{$slug}/accounting/opening-balances")
        ->assertOk();

    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->post("/{$slug}/accounting/opening-balances", ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 5]])
        ->assertForbidden();

    $this->actingAs($f['user'])
        ->post("/{$slug}/accounting/opening-balances", ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 5]])
        ->assertRedirect("/{$slug}/accounting/opening-balances");

    expect(ledgerBalance($f['accounts']['cash']))->toBe(5.0);
});
```

Look at `build/tests/Feature/Accounting/CreateVendorTest.php` for how an owner user gets company permissions in HTTP tests (role assignment / `CompanyRoleAccessTest`) and mirror that setup for `$f['user']` if `Company::create` alone does not grant the owner role.

- [ ] **Step 2: Run to see it fail**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php --filter=permission`
Expected: FAIL — controller class missing.

- [ ] **Step 3: FormRequest**

```php
<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Accounting\Actions\OpeningBalance\SaveAction;
use Illuminate\Foundation\Http\FormRequest;

class StoreOpeningBalancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return array_merge(app(SaveAction::class)->rules(), [
            'banks.*.account_id' => 'required|uuid|distinct',
            'credit_customers.*.customer_id' => 'required|uuid|distinct',
            'employees.*.employee_id' => 'required|uuid|distinct',
            'amanat.*.customer_id' => 'required|uuid|distinct',
            'suppliers.*.vendor_id' => 'required|uuid|distinct',
            'partners.*.partner_id' => 'required|uuid|distinct',
        ]);
    }
}
```

`hasCompanyPermission` / `validateRlsContext` come from whatever base `StoreJournalRequest` extends — copy its `extends`/`use` lines.

- [ ] **Step 4: Controller**

```php
<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreOpeningBalancesRequest;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpeningBalanceController extends Controller
{
    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();
        abort_unless($request->user()->hasCompanyPermission($company, Permissions::OPENING_BALANCE_VIEW), 403);

        $opening = app(CommandBus::class)->dispatch('opening_balance.view', [], $request->user());

        return Inertia::render('accounting/opening-balances/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
            'opening' => $opening,
            'canManage' => $request->user()->hasCompanyPermission($company, Permissions::OPENING_BALANCE_MANAGE),
        ]);
    }

    public function store(StoreOpeningBalancesRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $result = app(CommandBus::class)->dispatch('opening_balance.save', $request->validated(), $request->user());

        return redirect()
            ->route('accounting.opening-balances.show', ['company' => $company->slug])
            ->with('success', $result['message']);
    }

    public function lock(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        abort_unless($request->user()->hasCompanyPermission($company, Permissions::OPENING_BALANCE_MANAGE), 403);
        $result = app(CommandBus::class)->dispatch('opening_balance.lock', [], $request->user());

        return redirect()
            ->route('accounting.opening-balances.show', ['company' => $company->slug])
            ->with('success', $result['message']);
    }
}
```

Match the permission-check helper the `JournalController` (or `BillController`) uses for `index` — if it relies on the CommandBus permission check rather than an explicit `abort_unless`, do the same.

- [ ] **Step 5: Run tests and route list**

Run: `cd build && php artisan route:list --path=opening-balances && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php`
Expected: 3 routes listed; 7 PASS.

- [ ] **Step 6: Commit**

```bash
git add build/modules/Accounting/Http/Requests/StoreOpeningBalancesRequest.php build/modules/Accounting/Http/Controllers/OpeningBalanceController.php build/tests/Feature/Accounting/OpeningBalancesTest.php
git commit -m "Add opening balances controller and form request

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 9: Opening balances page (`Index.vue`)

**Files:**
- Create: `build/modules/Accounting/Resources/js/pages/opening-balances/Index.vue`

**Interfaces:**
- Consumes: props `company`, `opening` (ViewAction shape from Task 7), `canManage: boolean`; routes from Task 3; `EntitySearch` (`@/components/forms/EntitySearch.vue`, props `modelValue`, `entityType: 'customer'|'vendor'`, emits `update:modelValue`); `MoneyText` (`@/components/MoneyText.vue`); Shadcn `Card`, `Button`, `Input`, `Label`, `Select*`, `Separator`, `Collapsible*`, `AlertDialog*`; `useForm`, `router` from `@inertiajs/vue3`; `toast` from `vue-sonner`.

- [ ] **Step 1: Write the page**

Read `docs/ledger-design-system.md` first, then `build/modules/Accounting/Resources/js/pages/journals/Create.vue` for the layout wrapper (`AppLayout`/`PageHeader` names). Then create:

```vue
<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import AppLayout from '@/layouts/AppLayout.vue'
import MoneyText from '@/components/MoneyText.vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog'
import { ChevronDown, Lock, Plus, Trash2 } from 'lucide-vue-next'

interface Opt { id: string; name: string; code?: string }
interface Row { id: string; amount: number }
interface Opening {
  as_of_date: string | null
  locked_at: string | null
  locked_by: string | null
  earliest_transaction_date: string | null
  rows: {
    cash: { amount: number }
    banks: { account_id: string; account_name: string; amount: number }[]
    credit_customers: { customer_id: string; customer_name: string; amount: number }[]
    employees: { employee_id: string; employee_name: string; amount: number }[]
    amanat: { customer_id: string; customer_name: string; amount: number }[]
    suppliers: { vendor_id: string; vendor_name: string; amount: number }[]
    partners: { partner_id: string; partner_name: string; amount: number }[]
  }
  totals: { assets: number; liabilities: number; equity: number }
  options: { bank_accounts: Opt[]; customers: Opt[]; vendors: Opt[]; employees: Opt[]; partners: Opt[] }
}

const props = defineProps<{ company: { id: string; name: string; slug: string }; opening: Opening; canManage: boolean }>()

const locked = computed(() => !!props.opening.locked_at)
const editable = computed(() => props.canManage && !locked.value)
const currency = 'PKR'

const form = useForm({
  as_of_date: props.opening.as_of_date ?? '',
  cash: { amount: props.opening.rows.cash.amount ?? 0 },
  banks: props.opening.rows.banks.map(r => ({ account_id: r.account_id, amount: r.amount })),
  credit_customers: props.opening.rows.credit_customers.map(r => ({ customer_id: r.customer_id, amount: r.amount })),
  employees: props.opening.rows.employees.map(r => ({ employee_id: r.employee_id, amount: r.amount })),
  amanat: props.opening.rows.amanat.map(r => ({ customer_id: r.customer_id, amount: r.amount })),
  suppliers: props.opening.rows.suppliers.map(r => ({ vendor_id: r.vendor_id, amount: r.amount })),
  partners: props.opening.rows.partners.map(r => ({ partner_id: r.partner_id, amount: r.amount })),
})

const sum = (rows: { amount: number }[]) => rows.reduce((s, r) => s + Number(r.amount || 0), 0)
const assets = computed(() => Number(form.cash.amount || 0) + sum(form.banks) + sum(form.credit_customers) + sum(form.employees))
const liabilities = computed(() => sum(form.amanat) + sum(form.suppliers) + sum(form.partners))
const equity = computed(() => assets.value - liabilities.value)
const rowCount = computed(() => (Number(form.cash.amount) > 0 ? 1 : 0) + form.banks.length + form.credit_customers.length + form.employees.length + form.amanat.length + form.suppliers.length + form.partners.length)

const dateGuardMessage = computed(() => {
  const earliest = props.opening.earliest_transaction_date
  if (!earliest || !form.as_of_date) return null
  return form.as_of_date >= earliest ? `Must be before the first posted transaction (${earliest}).` : null
})
const canSave = computed(() => editable.value && rowCount.value > 0 && !!form.as_of_date && !dateGuardMessage.value && !form.processing)

const open = reactive({ cash: true, credit: true, employees: true, amanat: true, suppliers: true, partners: false })

const err = (key: string) => (form.errors as Record<string, string>)[key]

function submit() {
  form.post(`/${props.company.slug}/accounting/opening-balances`, {
    preserveScroll: true,
    onSuccess: () => toast.success('Opening balances saved'),
    onError: (errors) => {
      const first = Object.values(errors)[0]
      if (first && !Object.keys(errors).some(k => k.includes('.'))) toast.error(first as string)
    },
  })
}

function lock() {
  router.post(`/${props.company.slug}/accounting/opening-balances/lock`, {}, {
    preserveScroll: true,
    onSuccess: () => toast.success('Opening balances locked'),
    onError: (errors) => toast.error((Object.values(errors)[0] as string) || 'Could not lock'),
  })
}
</script>

<template>
  <Head title="Opening Balances" />
  <AppLayout title="Opening balances">
    <div class="space-y-6 pb-32">
      <Card>
        <CardHeader>
          <CardTitle>Opening balances</CardTitle>
          <CardDescription>
            What the business held and owed the day before entries start in Haasib. Every line is posted against Opening Balance Equity (3080).
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="locked" class="rounded-md border border-status-attention/40 bg-status-attention/10 p-3 text-sm">
            Locked on {{ opening.locked_at?.slice(0, 10) }}<span v-if="opening.locked_by"> by {{ opening.locked_by }}</span>. Balances are read-only.
          </div>
          <div class="grid gap-2 md:max-w-xs">
            <Label for="as_of_date">As of date</Label>
            <Input id="as_of_date" v-model="form.as_of_date" type="date" :disabled="!editable" />
            <p v-if="dateGuardMessage || err('as_of_date')" class="text-sm text-destructive">{{ err('as_of_date') || dateGuardMessage }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- Cash & banks -->
      <Card>
        <Collapsible v-model:open="open.cash">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Cash &amp; banks</CardTitle>
                <CardDescription>Drawer cash and each bank or card settlement account</CardDescription>
              </div>
              <span class="flex items-center gap-3 text-sm"><MoneyText :amount="Number(form.cash.amount || 0) + sum(form.banks)" :currency="currency" :fraction-digits="0" /><ChevronDown class="h-4 w-4" /></span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <div class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1"><Label>Cash on hand</Label><p class="text-sm text-muted-foreground">Account 1050</p></div>
                <Input v-model.number="form.cash.amount" type="number" min="0" step="1" :disabled="!editable" />
                <span />
              </div>
              <p v-if="err('cash.amount')" class="text-sm text-destructive">{{ err('cash.amount') }}</p>
              <div v-for="(row, i) in form.banks" :key="'bank-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label v-if="i === 0">Bank account</Label>
                  <Select v-model="row.account_id" :disabled="!editable">
                    <SelectTrigger><SelectValue placeholder="Choose bank account" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="a in opening.options.bank_accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</SelectItem>
                    </SelectContent>
                  </Select>
                  <p v-if="err(`banks.${i}.account_id`)" class="text-sm text-destructive">{{ err(`banks.${i}.account_id`) }}</p>
                </div>
                <div class="grid gap-1">
                  <Label v-if="i === 0">Balance</Label>
                  <Input v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <p v-if="err(`banks.${i}.amount`)" class="text-sm text-destructive">{{ err(`banks.${i}.amount`) }}</p>
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.banks.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.banks.push({ account_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add bank account</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Credit customers -->
      <Card>
        <Collapsible v-model:open="open.credit">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div><CardTitle>Credit customers</CardTitle><CardDescription>What each customer owes the business (udhaar). Becomes an opening invoice.</CardDescription></div>
              <span class="flex items-center gap-3 text-sm"><MoneyText :amount="sum(form.credit_customers)" :currency="currency" :fraction-digits="0" /><ChevronDown class="h-4 w-4" /></span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <p v-if="err('credit_customers')" class="text-sm text-destructive">{{ err('credit_customers') }}</p>
              <div v-for="(row, i) in form.credit_customers" :key="'cc-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label v-if="i === 0">Customer</Label>
                  <EntitySearch v-model="row.customer_id" entity-type="customer" :disabled="!editable" />
                  <p v-if="err(`credit_customers.${i}.customer_id`)" class="text-sm text-destructive">{{ err(`credit_customers.${i}.customer_id`) }}</p>
                </div>
                <div class="grid gap-1">
                  <Label v-if="i === 0">Owes</Label>
                  <Input v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <p v-if="err(`credit_customers.${i}.amount`)" class="text-sm text-destructive">{{ err(`credit_customers.${i}.amount`) }}</p>
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.credit_customers.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.credit_customers.push({ customer_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add customer</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Employee advances -->
      <Card>
        <Collapsible v-model:open="open.employees">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div><CardTitle>Employee advances</CardTitle><CardDescription>Salary advances not yet recovered</CardDescription></div>
              <span class="flex items-center gap-3 text-sm"><MoneyText :amount="sum(form.employees)" :currency="currency" :fraction-digits="0" /><ChevronDown class="h-4 w-4" /></span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <p v-if="err('employees')" class="text-sm text-destructive">{{ err('employees') }}</p>
              <div v-for="(row, i) in form.employees" :key="'emp-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label v-if="i === 0">Employee</Label>
                  <Select v-model="row.employee_id" :disabled="!editable">
                    <SelectTrigger><SelectValue placeholder="Choose employee" /></SelectTrigger>
                    <SelectContent><SelectItem v-for="e in opening.options.employees" :key="e.id" :value="e.id">{{ e.name }}</SelectItem></SelectContent>
                  </Select>
                </div>
                <div class="grid gap-1">
                  <Label v-if="i === 0">Outstanding</Label>
                  <Input v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.employees.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.employees.push({ employee_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add employee</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Amanat depositors -->
      <Card>
        <Collapsible v-model:open="open.amanat">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div><CardTitle>Amanat depositors</CardTitle><CardDescription>Money customers have left with the station</CardDescription></div>
              <span class="flex items-center gap-3 text-sm"><MoneyText :amount="sum(form.amanat)" :currency="currency" :fraction-digits="0" /><ChevronDown class="h-4 w-4" /></span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <p v-if="err('amanat')" class="text-sm text-destructive">{{ err('amanat') }}</p>
              <div v-for="(row, i) in form.amanat" :key="'am-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label v-if="i === 0">Depositor</Label>
                  <EntitySearch v-model="row.customer_id" entity-type="customer" :disabled="!editable" />
                </div>
                <div class="grid gap-1">
                  <Label v-if="i === 0">Held</Label>
                  <Input v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.amanat.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.amanat.push({ customer_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add depositor</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Suppliers -->
      <Card>
        <Collapsible v-model:open="open.suppliers">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div><CardTitle>Suppliers</CardTitle><CardDescription>Unpaid supplier balances. Becomes an opening bill.</CardDescription></div>
              <span class="flex items-center gap-3 text-sm"><MoneyText :amount="sum(form.suppliers)" :currency="currency" :fraction-digits="0" /><ChevronDown class="h-4 w-4" /></span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <p v-if="err('suppliers')" class="text-sm text-destructive">{{ err('suppliers') }}</p>
              <div v-for="(row, i) in form.suppliers" :key="'sup-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label v-if="i === 0">Supplier</Label>
                  <EntitySearch v-model="row.vendor_id" entity-type="vendor" :disabled="!editable" />
                </div>
                <div class="grid gap-1">
                  <Label v-if="i === 0">Owed</Label>
                  <Input v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.suppliers.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.suppliers.push({ vendor_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add supplier</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Partner capital (optional) -->
      <Card>
        <Collapsible v-model:open="open.partners">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div><CardTitle>Partner capital <span class="text-sm font-normal text-muted-foreground">(optional)</span></CardTitle><CardDescription>Capital each partner has put in</CardDescription></div>
              <span class="flex items-center gap-3 text-sm"><MoneyText :amount="sum(form.partners)" :currency="currency" :fraction-digits="0" /><ChevronDown class="h-4 w-4" /></span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <p v-if="err('partners')" class="text-sm text-destructive">{{ err('partners') }}</p>
              <div v-for="(row, i) in form.partners" :key="'pt-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label v-if="i === 0">Partner</Label>
                  <Select v-model="row.partner_id" :disabled="!editable">
                    <SelectTrigger><SelectValue placeholder="Choose partner" /></SelectTrigger>
                    <SelectContent><SelectItem v-for="p in opening.options.partners" :key="p.id" :value="p.id">{{ p.name }}</SelectItem></SelectContent>
                  </Select>
                </div>
                <div class="grid gap-1">
                  <Label v-if="i === 0">Capital</Label>
                  <Input v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.partners.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.partners.push({ partner_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add partner</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>
    </div>

    <!-- Sticky totals footer -->
    <div class="fixed inset-x-0 bottom-0 border-t bg-background/95 backdrop-blur">
      <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-3 text-sm">
        <div class="flex flex-wrap gap-6">
          <div><div class="text-xs text-muted-foreground">Assets</div><MoneyText :amount="assets" :currency="currency" :fraction-digits="0" class="font-medium" /></div>
          <div><div class="text-xs text-muted-foreground">Liabilities</div><MoneyText :amount="liabilities" :currency="currency" :fraction-digits="0" class="font-medium" /></div>
          <div><div class="text-xs text-muted-foreground">→ Opening Balance Equity</div><MoneyText :amount="equity" :currency="currency" :fraction-digits="0" class="font-semibold" /></div>
        </div>
        <div class="flex items-center gap-2">
          <AlertDialog v-if="canManage && !locked && opening.as_of_date">
            <AlertDialogTrigger as-child><Button variant="outline"><Lock class="mr-2 h-4 w-4" />Lock</Button></AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Lock opening balances?</AlertDialogTitle>
                <AlertDialogDescription>Once locked, opening balances cannot be changed from this page.</AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="lock">Lock</AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
          <Button v-if="editable" :disabled="!canSave" @click="submit">Save opening balances</Button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
```

Replace `AppLayout` and the `title` prop with whatever wrapper `journals/Create.vue` uses. If `EntitySearch` has no `disabled` prop, wrap it in a `<div :class="{ 'pointer-events-none opacity-60': !editable }">` instead. Use the company's base currency if the layout exposes it (`usePage().props.company.base_currency`) instead of the hard-coded `'PKR'`.

- [ ] **Step 2: Build and manual check**

Run: `cd build && npm run build 2>&1 | tail -5` → success.
Open `/{company}/accounting/opening-balances`: add cash 150,000, one bank, one credit customer, one depositor; footer shows Assets / Liabilities / Equity; Save → toast + rows reload from server; Lock → banner, inputs disabled.

- [ ] **Step 3: Commit**

```bash
git add build/modules/Accounting/Resources/js/pages/opening-balances/Index.vue
git commit -m "Add opening balances page

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 10: Fuel onboarding hand-off and credit-customer balances

**Files:**
- Modify: `build/modules/FuelStation/Http/Controllers/FuelStationOnboardingController.php` (remove `setupOpeningCash` ~1513–1610; replace `openingBalances` prop ~339/388)
- Modify: `build/modules/FuelStation/Routes/fuel.php:53` (remove the `opening-cash` route)
- Modify: `build/modules/FuelStation/Resources/js/pages/FuelStation/Onboarding/Index.vue` (`opening_cash` step body ~2940–3010, `submitOpeningCash` ~1573, `openingCashForm` state ~1231–1260, `openingBalances` prop type ~216)
- Modify: `build/modules/FuelStation/Http/Controllers/CreditCustomerController.php:35,138`
- Modify: `build/tests/Feature/Accounting/OpeningBalancesTest.php`

**Interfaces:**
- Consumes: `opening_balance.view` command (Task 7).
- Produces: onboarding prop `openingBalances: { as_of_date, locked_at, row_count, assets, liabilities, url }`.

- [ ] **Step 1: Failing tests**

```php
test('credit customer index reports the opening receivable as current balance', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Truck Company');
    CustomerProfile::getOrCreateForCustomer($f['company']->id, $customer->id)->update(['is_credit_customer' => true]);
    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]]]);

    $response = $this->actingAs($f['user'])->get("/{$f['company']->slug}/fuel/credit-customers");
    $response->assertOk();
    $customers = $response->viewData('page')['props']['customers'];
    expect(collect($customers)->firstWhere('id', $customer->id)['current_balance'])->toBe(42000.0);
});

test('the fuel onboarding opening-cash route is gone', function () {
    $f = openingBalanceFixture();
    $this->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/onboarding/opening-cash", ['as_of_date' => '2026-08-31', 'cash_on_hand' => 1])
        ->assertNotFound();
});
```

Check the credit customers route path in `fuel.php` (`grep credit-customers build/modules/FuelStation/Routes/fuel.php`) and the prop name the index renders.

- [ ] **Step 2: Run to see them fail**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php --filter="credit customer index|opening-cash route"`
Expected: both FAIL.

- [ ] **Step 3: CreditCustomerController balance**

Replace both `'current_balance' => 0, // TODO: Calculate from transactions` lines with a real lookup. Above the mapping in `index()` (and `show()` if it maps the same way) add:

```php
        $openBalances = \App\Modules\Accounting\Models\Invoice::where('company_id', $company->id)
            ->whereNotIn('status', ['void', 'draft'])
            ->where('balance', '>', 0)
            ->selectRaw('customer_id, SUM(balance) as balance')
            ->groupBy('customer_id')
            ->pluck('balance', 'customer_id');
```

and use `'current_balance' => (float) ($openBalances[$c->customer_id ?? $c->id] ?? 0)` — pick whichever id the mapped row uses for the customer.

- [ ] **Step 4: Remove the onboarding stub**

- Delete `setupOpeningCash()` from `FuelStationOnboardingController` and the route on `fuel.php:53`.
- Where the controller builds `'openingBalances' => $openingBalances`, replace with:

```php
        $openingSummary = null;
        try {
            $view = app(\App\Services\CommandBus::class)->dispatch('opening_balance.view', [], $request->user());
            $rows = $view['rows'];
            $openingSummary = [
                'as_of_date' => $view['as_of_date'],
                'locked_at' => $view['locked_at'],
                'row_count' => ($rows['cash']['amount'] > 0 ? 1 : 0) + count($rows['banks']) + count($rows['credit_customers']) + count($rows['employees']) + count($rows['amanat']) + count($rows['suppliers']) + count($rows['partners']),
                'assets' => $view['totals']['assets'],
                'liabilities' => $view['totals']['liabilities'],
                'url' => route('accounting.opening-balances.show', ['company' => $company->slug]),
            ];
        } catch (\Throwable $e) {
            \Log::warning('Onboarding: could not load opening balance summary', ['error' => $e->getMessage()]);
        }
        …
        'openingBalances' => $openingSummary,
```

(`$request` must be available in that method's signature; add `Request $request` if it is not.)

- [ ] **Step 5: Replace the wizard step body**

In `Onboarding/Index.vue`: change the prop type to `openingBalances: { as_of_date: string | null; locked_at: string | null; row_count: number; assets: number; liabilities: number; url: string } | null`; delete `openingCashForm`, its watcher and `submitOpeningCash`; replace the `<div v-if="activeStepId === 'opening_cash'">` body with:

```vue
<div v-if="activeStepId === 'opening_cash'" class="space-y-6">
  <p class="text-sm text-text-secondary">
    Cash, bank balances, customer udhaar, amanat deposits, supplier dues and employee advances as of the day before entries start. These are entered once on the Accounting opening-balances page.
  </p>
  <Card>
    <CardContent class="flex flex-wrap items-center justify-between gap-4 p-4">
      <div v-if="openingBalances?.as_of_date" class="space-y-1 text-sm">
        <div class="font-medium">{{ openingBalances.row_count }} lines as of {{ openingBalances.as_of_date }}<span v-if="openingBalances.locked_at"> · locked</span></div>
        <div class="text-muted-foreground">Assets <MoneyText :amount="openingBalances.assets" :currency="currencyCode" :fraction-digits="0" /> · Liabilities <MoneyText :amount="openingBalances.liabilities" :currency="currencyCode" :fraction-digits="0" /></div>
      </div>
      <div v-else class="text-sm text-muted-foreground">Not set yet.</div>
      <Button as-child variant="outline"><Link :href="openingBalances?.url ?? `/${companySlug}/accounting/opening-balances`">{{ openingBalances?.as_of_date ? 'Review opening balances' : 'Enter opening balances' }}</Link></Button>
    </CardContent>
  </Card>
</div>
```

Import `Link` from `@inertiajs/vue3` and `MoneyText` if not already imported. If the wizard marks steps complete from server data, treat `openingBalances?.as_of_date` as the completion signal for this step.

- [ ] **Step 6: Run the full test file and build**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting/OpeningBalancesTest.php && npm run build 2>&1 | tail -3`
Expected: 9 PASS; build success.

- [ ] **Step 7: Commit**

```bash
git add build/modules/FuelStation build/tests/Feature/Accounting/OpeningBalancesTest.php
git commit -m "Hand fuel onboarding off to Accounting opening balances and show real credit balances

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 11: Full regression run

**Files:** none new.

- [ ] **Step 1: Run the Accounting and FuelStation suites**

Run: `cd build && ./vendor/bin/pest tests/Feature/Accounting tests/Unit --stop-on-failure` and `cd build && ./vendor/bin/pest --filter=DailyClose`
Expected: all PASS. Any failure in a daily-close test means Part 1 or Task 10 touched something it should not have — fix before finishing.

- [ ] **Step 2: Run the route smoke test**

Run: `cd build && ./vendor/bin/pest tests/Feature/RouteSmokeTest.php`
Expected: PASS (the removed `fuel.onboarding.opening-cash` route must not be referenced anywhere: `grep -rn "opening-cash" build/modules build/resources build/routes` returns nothing).

- [ ] **Step 3: Final commit if anything changed**

```bash
git status --short
# commit any fixups with a message describing them, ending with the Co-Authored-By line
```
