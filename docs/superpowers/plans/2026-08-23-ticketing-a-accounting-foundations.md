# Ticketing A — Accounting Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Accounting everything a ticket posting needs — four accounts, four posting-template doc types, three new template roles, currency fields on credit notes, and a posting service that turns ticket money into balanced journal entries.

**Architecture:** Nothing here knows what a booking is. `TicketPostingService` takes plain amounts (already in base currency) and an `acct` document, resolves every account from a `PostingTemplate` role, and writes a balanced `Transaction` through the existing engine. Plan B supplies the amounts from real ticket rows. This plan is finished and testable without a single ticket table existing.

**Tech Stack:** Laravel 12, PostgreSQL (schema `acct`), Pest.

**Spec:** `docs/superpowers/specs/2026-08-22-umrah-ticketing-design.md`

## Global Constraints

- Run everything from `D:\projects\haasib\build`.
- UUID keys: `$table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'))`. Models set `protected $keyType = 'string'; public $incrementing = false;`.
- Every new `acct` table gets RLS. Copy the pattern from `modules/Umrah/Database/Migrations/2026_08_21_000001_create_umrah_refunds.php:74-77`.
- Money columns: `decimal(15, 2)` for base amounts, `decimal(18, 6)` for currency amounts, `decimal(18, 8)` for exchange rates. From `docs/contracts/multicurrency-rules.md`, which is **LOCKED** — comply, never amend.
- Revenue, COGS and expense accounts are **base-currency only** (`currency` NULL). Only balance-sheet accounts may carry a foreign currency.
- Never use `$request->validate()`. Use a FormRequest.
- `sed -i` silently no-ops on these files (CRLF). Use the Edit tool.
- Pest/artisan output carries ANSI escapes. Pipe through `sed 's/\x1b\[[0-9;]*m//g'` when grepping it.
- **Test baseline is 185 passed / 937 assertions**, about 215 seconds. Run the full suite (`php artisan test`) before every commit; it must never drop below baseline.
- Commit after every task. Never `--no-verify`.

---

### Task 1: Contract documents

Contracts before migrations, per `CLAUDE.md`. Docs only — no code, no tests.

**Files:**
- Modify: `docs/contracts/coa-schema.md`
- Modify: `docs/contracts/accounting-invoicing-contract.md`

- [ ] **Step 1: Add the ticket accounts to the COA contract**

In `docs/contracts/coa-schema.md`, add to the Umrah/travel pack section:

| Code | Name | Type | Subtype | Normal balance |
|---|---|---|---|---|
| `2350` | Ticket Supplier Clearing | liability | other_current_liability | credit |
| `4130` | Ticket Commission Revenue | revenue | revenue | credit |
| `4140` | Ticket Service Fee Revenue | revenue | revenue | credit |
| `4150` | Ticket Discount | revenue | revenue | debit (contra) |
| `4160` | Ticket Cancellation Adjustments | revenue | revenue | debit |
| `9900` | Rounding Differences | expense | expense | debit |

Note under the table, in prose: `2350` is base-denominated and must return to
exactly zero per booking; `4150` is a contra-revenue account (`is_contra = true`,
`normal_balance = 'debit'`); `4130`, `4140`, `4150` and `4160` carry `currency = NULL`
because the multicurrency contract forbids foreign currency on revenue accounts.

- [ ] **Step 2: Document the ticket posting doc types**

In `docs/contracts/accounting-invoicing-contract.md`, add a section "Ticket
postings" stating: ticket invoices and bills are ordinary subledger documents for
AR, AP, aging and allocation, but they are **not** posted by `postInvoice()` /
`postBill()`. They are posted by `TicketPostingService`, which takes every account
from a template role and reads no line account. Record the four doc types and
their roles:

| Doc type | Roles |
|---|---|
| `TICKET_INVOICE` | `AR`, `CLEARING`, `REVENUE`, `SERVICE_FEE`, `DISCOUNT_GIVEN`, `ROUNDING` |
| `TICKET_BILL` | `AP`, `CLEARING` |
| `TICKET_CREDIT_NOTE` | `AR`, `CANCELLATION_ADJUSTMENT` |
| `TICKET_VENDOR_CREDIT` | `AP`, `CANCELLATION_ADJUSTMENT` |

State explicitly that a ticket invoice carries **one** line item, and that
supplier cost and commission must never appear on it.

- [ ] **Step 3: Commit**

```bash
git add docs/contracts/
git commit -m "Document the ticket accounts and posting doc types"
```

---

### Task 2: The six accounts

**Files:**
- Modify: `database/seeders/IndustryCoaPackSeeder.php` (the `seedUmrah` method)
- Create: `database/migrations/2026_08_23_000001_add_ticket_accounts_to_existing_companies.php`
- Test: `tests/Feature/Accounting/TicketAccountsTest.php`

**Interfaces:**
- Produces: accounts with codes `2350`, `4130`, `4140`, `4150`, `4160`, `9900` on every company whose COA came from the umrah or travel pack.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;

/**
 * The COA pack applies at company creation, so a template-only change
 * leaves every existing company without these accounts and every ticket
 * posting failing on a missing role mapping.
 */
it('gives an existing umrah company the six ticket accounts', function () {
    $company = Company::factory()->create();

    foreach (['2350', '4130', '4140', '4150', '4160', '9900'] as $code) {
        expect(Account::where('company_id', $company->id)->where('code', $code)->exists())
            ->toBeTrue("account {$code} is missing");
    }
});

it('makes the ticket discount account a contra-revenue account', function () {
    $company = Company::factory()->create();
    $discount = Account::where('company_id', $company->id)->where('code', '4150')->first();

    expect($discount->type)->toBe('revenue')
        ->and($discount->normal_balance)->toBe('debit')
        ->and($discount->is_contra)->toBeTrue();
});

it('leaves ticket revenue accounts on the base currency', function () {
    $company = Company::factory()->create();

    foreach (['4130', '4140', '4150', '4160'] as $code) {
        $account = Account::where('company_id', $company->id)->where('code', $code)->first();
        expect($account->currency)->toBeNull("account {$code} must be base-currency only");
    }
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Accounting/TicketAccountsTest.php
```

Expected: FAIL, "account 2350 is missing".

- [ ] **Step 3: Add the accounts to the pack**

In `database/seeders/IndustryCoaPackSeeder.php`, inside `seedUmrah()`, add to the
account array (place them in code order beside the existing `1170`, `2300`,
`4100`, `4110`, `4120` entries):

```php
['code' => '2350', 'name' => 'Ticket Supplier Clearing', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
['code' => '4130', 'name' => 'Ticket Commission Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
['code' => '4140', 'name' => 'Ticket Service Fee Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
['code' => '4150', 'name' => 'Ticket Discount', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
['code' => '4160', 'name' => 'Ticket Cancellation Adjustments', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
['code' => '9900', 'name' => 'Rounding Differences', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
```

Read the surrounding rows first and match whatever key set they use. If the
existing rows do not pass `is_contra`, check the pack's insert code — if it does
not carry the key through, add it there too, defaulting to `false`.

- [ ] **Step 4: Write the backfill migration**

Create `database/migrations/2026_08_23_000001_add_ticket_accounts_to_existing_companies.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The COA pack applies at company creation. Adding accounts to the pack
 * alone would leave every live company without them, and every ticket
 * posting failing on a missing role mapping at the moment of first use.
 */
return new class extends Migration
{
    private const ACCOUNTS = [
        ['code' => '2350', 'name' => 'Ticket Supplier Clearing', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'is_contra' => false],
        ['code' => '4130', 'name' => 'Ticket Commission Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_contra' => false],
        ['code' => '4140', 'name' => 'Ticket Service Fee Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_contra' => false],
        ['code' => '4150', 'name' => 'Ticket Discount', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
        ['code' => '4160', 'name' => 'Ticket Cancellation Adjustments', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
        ['code' => '9900', 'name' => 'Rounding Differences', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_contra' => false],
    ];

    public function up(): void
    {
        $now = now();

        // Every company, not only travel ones: a company can enable Umrah
        // later, and an account nobody uses costs nothing.
        $companyIds = DB::table('auth.companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            foreach (self::ACCOUNTS as $account) {
                $exists = DB::table('acct.accounts')
                    ->where('company_id', $companyId)
                    ->where('code', $account['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('acct.accounts')->insert(array_merge($account, [
                    'id' => DB::raw('public.gen_random_uuid()'),
                    'company_id' => $companyId,
                    'currency' => null,
                    'is_active' => true,
                    'is_system' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('acct.accounts')
            ->whereIn('code', array_column(self::ACCOUNTS, 'code'))
            ->where('is_system', false)
            ->delete();
    }
};
```

Before running it, open `acct.accounts` and confirm the column list. If the table
has NOT NULL columns this insert omits, add them with the same defaults the pack
seeder uses.

- [ ] **Step 5: Run the migration and the test**

```bash
php artisan migrate
php artisan test tests/Feature/Accounting/TicketAccountsTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 6: Run the full suite**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
```

Expected: at or above 185 passed.

- [ ] **Step 7: Commit**

```bash
git add database/ tests/Feature/Accounting/TicketAccountsTest.php
git commit -m "Add the six ticket accounts to the pack and to existing companies"
```

---

### Task 3: Posting template doc types and roles

Four doc types and three roles are rejected today by two database CHECK
constraints, a FormRequest and a validator. All four must change together or a
ticket template cannot be inserted at all.

**Files:**
- Create: `database/migrations/2026_08_23_000002_add_ticket_posting_template_types.php`
- Modify: `modules/Accounting/Http/Requests/StorePostingTemplateRequest.php`
- Modify: `modules/Accounting/Services/PostingTemplateValidator.php:78-89` (`requiredRoles`)
- Modify: `modules/Accounting/Services/PostingTemplateValidator.php:91-105` (`validateRoleAccountCompatibility`)
- Test: `tests/Feature/Accounting/TicketPostingTemplateTest.php`

**Interfaces:**
- Produces: doc types `TICKET_INVOICE`, `TICKET_BILL`, `TICKET_CREDIT_NOTE`, `TICKET_VENDOR_CREDIT`; roles `SERVICE_FEE`, `CANCELLATION_ADJUSTMENT`, `ROUNDING`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\PostingTemplate;
use Illuminate\Support\Facades\DB;

function ticketTemplateAccount(Company $company, string $code): Account
{
    return Account::where('company_id', $company->id)->where('code', $code)->firstOrFail();
}

it('accepts a TICKET_INVOICE template with all six roles', function () {
    $company = Company::factory()->create();

    $template = PostingTemplate::create([
        'company_id' => $company->id,
        'doc_type' => 'TICKET_INVOICE',
        'name' => 'Ticket sale',
        'is_active' => true,
        'is_default' => true,
        'effective_from' => '2026-01-01',
        'version' => 1,
    ]);

    $roles = [
        'AR' => '1100',
        'CLEARING' => '2350',
        'REVENUE' => '4130',
        'SERVICE_FEE' => '4140',
        'DISCOUNT_GIVEN' => '4150',
        'ROUNDING' => '9900',
    ];

    foreach ($roles as $role => $code) {
        DB::table('acct.posting_template_lines')->insert([
            'id' => DB::raw('public.gen_random_uuid()'),
            'company_id' => $company->id,
            'posting_template_id' => $template->id,
            'role' => $role,
            'account_id' => ticketTemplateAccount($company, $code)->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect($template->fresh()->lines)->toHaveCount(6);
});

it('rejects a doc type that is not on the list', function () {
    $company = Company::factory()->create();

    expect(fn () => PostingTemplate::create([
        'company_id' => $company->id,
        'doc_type' => 'TICKET_NONSENSE',
        'name' => 'Bad',
        'is_active' => true,
        'is_default' => true,
        'effective_from' => '2026-01-01',
        'version' => 1,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

Read `acct.posting_template_lines` and `acct.posting_templates` first and match
the real column names — the insert above assumes `posting_template_id` and
`role`. Fix the test to the actual schema before running it; do not change the
schema to match the test.

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Accounting/TicketPostingTemplateTest.php
```

Expected: FAIL on `posting_templates_doc_type_chk`.

- [ ] **Step 3: Widen the two CHECK constraints**

Create `database/migrations/2026_08_23_000002_add_ticket_posting_template_types.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ticket postings take every account from a template role rather than
 * from a line's income/expense account, which keeps the account-type
 * validators on invoice and bill lines exactly as strict as they are.
 * That only works if the roles and doc types exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE acct.posting_templates DROP CONSTRAINT posting_templates_doc_type_chk');
        DB::statement("
            ALTER TABLE acct.posting_templates
            ADD CONSTRAINT posting_templates_doc_type_chk
            CHECK (doc_type IN (
                'AR_INVOICE','AR_PAYMENT','AR_CREDIT_NOTE',
                'AP_BILL','AP_PAYMENT','AP_VENDOR_CREDIT',
                'BANK_TRANSFER','BANK_FEE','PAYROLL',
                'TICKET_INVOICE','TICKET_BILL','TICKET_CREDIT_NOTE','TICKET_VENDOR_CREDIT'
            ))
        ");

        DB::statement('ALTER TABLE acct.posting_template_lines DROP CONSTRAINT posting_template_lines_role_chk');
        DB::statement("
            ALTER TABLE acct.posting_template_lines
            ADD CONSTRAINT posting_template_lines_role_chk
            CHECK (role IN (
                'AR','AP','REVENUE','EXPENSE','TAX_PAYABLE','TAX_RECEIVABLE',
                'DISCOUNT_GIVEN','DISCOUNT_RECEIVED','SHIPPING',
                'BANK','CASH','CLEARING','RETAINED_EARNINGS','SUSPENSE',
                'SERVICE_FEE','CANCELLATION_ADJUSTMENT','ROUNDING'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM acct.posting_template_lines WHERE role IN ('SERVICE_FEE','CANCELLATION_ADJUSTMENT','ROUNDING')");
        DB::statement("DELETE FROM acct.posting_templates WHERE doc_type LIKE 'TICKET_%'");

        DB::statement('ALTER TABLE acct.posting_templates DROP CONSTRAINT posting_templates_doc_type_chk');
        DB::statement("
            ALTER TABLE acct.posting_templates
            ADD CONSTRAINT posting_templates_doc_type_chk
            CHECK (doc_type IN (
                'AR_INVOICE','AR_PAYMENT','AR_CREDIT_NOTE',
                'AP_BILL','AP_PAYMENT','AP_VENDOR_CREDIT',
                'BANK_TRANSFER','BANK_FEE','PAYROLL'
            ))
        ");

        DB::statement('ALTER TABLE acct.posting_template_lines DROP CONSTRAINT posting_template_lines_role_chk');
        DB::statement("
            ALTER TABLE acct.posting_template_lines
            ADD CONSTRAINT posting_template_lines_role_chk
            CHECK (role IN (
                'AR','AP','REVENUE','EXPENSE','TAX_PAYABLE','TAX_RECEIVABLE',
                'DISCOUNT_GIVEN','DISCOUNT_RECEIVED','SHIPPING',
                'BANK','CASH','CLEARING','RETAINED_EARNINGS','SUSPENSE'
            ))
        ");
    }
};
```

- [ ] **Step 4: Teach the validator the four doc types**

In `modules/Accounting/Services/PostingTemplateValidator.php`, extend
`requiredRoles()`. This matters more than it looks: an unlisted doc type falls to
`default => []` and requires nothing at all, so a ticket template missing its AR
mapping would validate clean and fail at posting time instead.

```php
'TICKET_INVOICE' => [['AR'], ['CLEARING'], ['REVENUE'], ['DISCOUNT_GIVEN']],
'TICKET_BILL' => [['AP'], ['CLEARING']],
'TICKET_CREDIT_NOTE' => [['AR'], ['CANCELLATION_ADJUSTMENT']],
'TICKET_VENDOR_CREDIT' => [['AP'], ['CANCELLATION_ADJUSTMENT']],
```

`SERVICE_FEE` and `ROUNDING` are deliberately not required — a booking with no
service fee and no rounding difference never touches them.

In `validateRoleAccountCompatibility()`, add three arms:

```php
'SERVICE_FEE' => $account->type === 'revenue',
'CANCELLATION_ADJUSTMENT' => $account->type === 'revenue',
'ROUNDING' => in_array($account->type, ['expense', 'other_expense', 'revenue', 'other_income'], true),
```

Leave `CLEARING` alone. It already falls through to `default => true`, which is
what lets `2350` sit there as a liability without widening anything.

- [ ] **Step 5: Add the doc types to the FormRequest**

In `modules/Accounting/Http/Requests/StorePostingTemplateRequest.php`, find the
`doc_type` rule and add the four `TICKET_*` values to its `in:` list. Read the
file first — if it derives the list from a constant, add them to the constant
instead of hardcoding a second copy.

- [ ] **Step 6: Run the tests**

```bash
php artisan migrate
php artisan test tests/Feature/Accounting/TicketPostingTemplateTest.php
```

Expected: PASS, 2 tests.

- [ ] **Step 7: Run the full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
git add database/ modules/Accounting/ tests/
git commit -m "Allow ticket posting template doc types and roles"
```

---

### Task 4: TicketPostingService — the sale

The heart of the plan. Takes base amounts, resolves accounts from template roles,
writes a balanced transaction.

**Files:**
- Create: `modules/Accounting/Services/TicketPostingService.php`
- Test: `tests/Feature/Accounting/TicketPostingServiceTest.php`

**Interfaces:**
- Consumes: the doc types and roles from Task 3; the accounts from Task 2.
- Produces, for Plan B:

```php
public function postTicketInvoice(Invoice $invoice, TicketSaleAmounts $amounts): Transaction
public function postTicketBill(Bill $bill, float $supplierCostBase): Transaction
```

```php
final class TicketSaleAmounts
{
    public function __construct(
        public readonly float $supplierCostBase,
        public readonly float $commissionBase,
        public readonly float $serviceFeeBase,
        public readonly float $discountBase,
    ) {}
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\TicketPostingService;
use App\Modules\Accounting\Services\TicketSaleAmounts;

/**
 * The worked example from the spec: gross fare 85,000, taxes 12,400,
 * supplier cost 91,000, discount 2,000, service fee 1,500. The buyer
 * pays 96,900 and the company keeps 5,900.
 */
it('posts the spec worked example and leaves clearing at zero', function () {
    [$company, $invoice] = ticketInvoiceFixture(saleTotal: 96_900, discount: 2_000);

    $transaction = app(TicketPostingService::class)->postTicketInvoice(
        $invoice,
        new TicketSaleAmounts(
            supplierCostBase: 91_000,
            commissionBase: 6_400,
            serviceFeeBase: 1_500,
            discountBase: 2_000,
        ),
    );

    expect(ticketBalance($transaction, '1100'))->toBe(96_900.0)   // Dr AR
        ->and(ticketBalance($transaction, '4150'))->toBe(2_000.0)  // Dr discount
        ->and(ticketBalance($transaction, '2350'))->toBe(-91_000.0) // Cr clearing
        ->and(ticketBalance($transaction, '4130'))->toBe(-6_400.0)  // Cr commission
        ->and(ticketBalance($transaction, '4140'))->toBe(-1_500.0); // Cr fee
});

it('returns clearing to exactly zero once the bill posts', function () {
    [$company, $invoice, $bill] = ticketInvoiceAndBillFixture();

    $service = app(TicketPostingService::class);
    $service->postTicketInvoice($invoice, new TicketSaleAmounts(91_000, 6_400, 1_500, 2_000));
    $service->postTicketBill($bill, 91_000);

    expect(ticketAccountBalance($company, '2350'))->toBe(0.0);
});

it('posts no discount entry when nothing was given away', function () {
    [$company, $invoice] = ticketInvoiceFixture(saleTotal: 98_900, discount: 0);

    $transaction = app(TicketPostingService::class)->postTicketInvoice(
        $invoice,
        new TicketSaleAmounts(91_000, 6_400, 1_500, 0),
    );

    expect(ticketBalance($transaction, '4150'))->toBe(0.0);
});

it('refuses to post when the amounts do not balance', function () {
    [$company, $invoice] = ticketInvoiceFixture(saleTotal: 96_900, discount: 2_000);

    // Commission is wrong by 100, so the entry cannot balance.
    expect(fn () => app(TicketPostingService::class)->postTicketInvoice(
        $invoice,
        new TicketSaleAmounts(91_000, 6_500, 1_500, 2_000),
    ))->toThrow(\RuntimeException::class);
});
```

Write the three helpers at the top of the file. `ticketInvoiceFixture()` creates a
company, ensures the `TICKET_INVOICE` template with all six role mappings exists,
and creates an `Invoice` with **one** line item at `saleTotal + discount` and
`discount_amount = $discount`. `ticketBalance(Transaction, string $code)` returns
debit minus credit for that account's journal lines, so a credit reads negative.
`ticketAccountBalance(Company, string $code)` sums the same across all
transactions. Read `tests/Feature/Umrah/RefundCreditAdvanceTest.php` for the
fixture style this codebase uses.

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Accounting/TicketPostingServiceTest.php
```

Expected: FAIL, class `TicketPostingService` not found.

- [ ] **Step 3: Write the service**

Create `modules/Accounting/Services/TicketPostingService.php`. Read
`PostingService::buildInvoiceEntries()` and `postInvoice()` first and follow their
shape — same `Transaction` creation, same `assertBalanced` discipline, same
period-lock check. Key points:

```php
namespace App\Modules\Accounting\Services;

/**
 * Ticket postings take every account from a template role. No ticket
 * posting reads income_account_id or expense_account_id, which is why
 * the account-type validators on invoice and bill lines stay exactly as
 * strict as they are -- the liability clearing account never goes near
 * a field called "income account".
 *
 * Amounts arrive already in base currency. Converting is the caller's
 * job, because only the caller knows the ticket's two exchange rates.
 */
final class TicketPostingService
{
    public function postTicketInvoice(Invoice $invoice, TicketSaleAmounts $amounts): Transaction
    {
        // 1. resolveTemplate(company, 'TICKET_INVOICE', $invoice->invoice_date)
        // 2. roleAccounts($template) -> ['AR' => id, 'CLEARING' => id, ...]
        // 3. entries:
        //      Dr AR        $invoice->base_amount
        //      Dr DISCOUNT_GIVEN  $amounts->discountBase   (skip when 0)
        //      Cr CLEARING        $amounts->supplierCostBase
        //      Cr REVENUE         $amounts->commissionBase
        //      Cr SERVICE_FEE     $amounts->serviceFeeBase (skip when 0)
        //      then the rounding leg, below
        // 4. assertBalanced, create the Transaction
    }
}
```

The rounding leg: sum the debits and the credits. If they differ by no more than
`0.01`, add a single balancing entry to the `ROUNDING` account and continue. If
they differ by more, throw — that is a defect, not a rounding difference, and the
fourth test above depends on it throwing.

`resolveTemplate()` and `roleAccounts()` are currently `private` on
`PostingService`. Change them to `protected` and have `TicketPostingService`
extend it, **or** extract them to a small shared trait. Prefer the trait: a
ticket posting is not a kind of invoice posting, and inheritance here would
suggest it is.

- [ ] **Step 4: Run the tests**

```bash
php artisan test tests/Feature/Accounting/TicketPostingServiceTest.php
```

Expected: PASS, 4 tests.

- [ ] **Step 5: Run the full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
git add modules/Accounting/Services/ tests/
git commit -m "Post a ticket sale through template roles"
```

---

### Task 5: Currency fields on credit notes

`acct.credit_notes` stores only `base_currency` — no `currency`, no
`exchange_rate` — so it cannot represent a credit against a foreign-currency
invoice. `CreditNoteItem` has no account column at all.

**Files:**
- Create: `database/migrations/2026_08_23_000003_add_currency_to_credit_notes.php`
- Modify: `modules/Accounting/Models/CreditNote.php:20-40` (`$fillable`)
- Modify: `modules/Accounting/Models/CreditNoteItem.php:19-32` (`$fillable`)
- Modify: `modules/Accounting/Models/VendorCreditItem.php` (`$fillable`)
- Test: `tests/Feature/Accounting/CreditNoteCurrencyTest.php`

**Interfaces:**
- Produces: `credit_notes.currency`, `credit_notes.exchange_rate`,
  `credit_notes.base_amount`, `credit_note_items.income_account_id`,
  `vendor_credit_items.expense_account_id`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Modules\Accounting\Models\CreditNote;
use Illuminate\Database\QueryException;

it('records a credit note in a foreign currency with its rate', function () {
    $company = Company::factory()->create();   // base PKR

    $note = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => creditNoteCustomer($company)->id,
        'credit_note_number' => 'CN-TEST-1',
        'credit_date' => '2026-09-01',
        'amount' => 325.00,
        'currency' => 'USD',
        'exchange_rate' => 280.00000000,
        'base_currency' => 'PKR',
        'base_amount' => 91_000.00,
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]);

    expect($note->fresh()->base_amount)->toEqual(91_000.00);
});

it('refuses a base-amount that does not match the rate', function () {
    $company = Company::factory()->create();

    expect(fn () => CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => creditNoteCustomer($company)->id,
        'credit_note_number' => 'CN-TEST-2',
        'credit_date' => '2026-09-01',
        'amount' => 325.00,
        'currency' => 'USD',
        'exchange_rate' => 280.00000000,
        'base_currency' => 'PKR',
        'base_amount' => 1.00,            // wrong on purpose
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]))->toThrow(QueryException::class);
});

it('keeps existing base-currency credit notes valid', function () {
    $company = Company::factory()->create();

    $note = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => creditNoteCustomer($company)->id,
        'credit_note_number' => 'CN-TEST-3',
        'credit_date' => '2026-09-01',
        'amount' => 5_000.00,
        'currency' => 'PKR',
        'exchange_rate' => null,
        'base_currency' => 'PKR',
        'base_amount' => 5_000.00,
        'reason' => 'Goodwill',
        'status' => 'draft',
    ]);

    expect($note->fresh()->exchange_rate)->toBeNull();
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Accounting/CreditNoteCurrencyTest.php
```

Expected: FAIL, unknown column `currency`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_23_000003_add_currency_to_credit_notes.php`.
Add `currency` (char 3), `exchange_rate` (decimal 18,8, nullable) and
`base_amount` (decimal 15,2) to `acct.credit_notes`, plus
`income_account_id` (uuid, nullable) to `acct.credit_note_items` and
`expense_account_id` (uuid, nullable) to `acct.vendor_credit_items`.

Backfill before adding the CHECK — existing rows are base-currency by definition:

```php
DB::statement('UPDATE acct.credit_notes SET currency = base_currency, base_amount = amount WHERE currency IS NULL');
```

Then the same dual-recording CHECK the refunds table uses, copied from
`modules/Umrah/Database/Migrations/2026_08_21_000001_create_umrah_refunds.php:73`:

```php
DB::statement('ALTER TABLE acct.credit_notes ADD CONSTRAINT credit_notes_exchange_rate_check CHECK ((currency = base_currency AND exchange_rate IS NULL AND base_amount = round(amount, 2)) OR (currency <> base_currency AND exchange_rate > 0 AND base_amount = round(amount * exchange_rate, 2)))');
```

Add the FKs on `currency` and `base_currency` to `public.currencies`, and on the
two account columns to `acct.accounts` with `nullOnDelete()`.

`down()` drops the constraint, the FKs, then the columns.

- [ ] **Step 4: Add the columns to the three models**

`CreditNote::$fillable` gains `currency`, `exchange_rate`, `base_amount`.
`CreditNoteItem::$fillable` gains `income_account_id`.
`VendorCreditItem::$fillable` gains `expense_account_id`.

Check each model's `$casts` and add `'base_amount' => 'decimal:2'` and
`'exchange_rate' => 'decimal:8'` if the neighbouring money fields are cast.

- [ ] **Step 5: Run the tests**

```bash
php artisan migrate
php artisan test tests/Feature/Accounting/CreditNoteCurrencyTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 6: Run the full suite and commit**

The credit-note tests already in the suite are the ones most likely to break
here. If any fail, the backfill is the first place to look.

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
git add database/ modules/Accounting/Models/ tests/
git commit -m "Give credit notes a transaction currency and their items an account"
```

---

### Task 6: TicketPostingService — the cancellation

**Files:**
- Modify: `modules/Accounting/Services/TicketPostingService.php`
- Test: `tests/Feature/Accounting/TicketCancellationPostingTest.php`

**Interfaces:**
- Consumes: Task 4's service, Task 5's currency fields.
- Produces, for Plan B:

```php
public function postTicketCreditNote(CreditNote $note, float $buyerReturnBase): Transaction
public function postTicketVendorCredit(VendorCredit $credit, float $supplierReturnBase): Transaction
```

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Modules\Accounting\Services\TicketPostingService;

/**
 * The buyer leg debits 4160 and the supplier leg credits it, so the net
 * debit left behind is buyer return minus supplier return -- the
 * cancellation cost, falling out of the ledger rather than being
 * computed beside it.
 */
it('leaves the cancellation cost as a net debit in 4160', function () {
    [$company, $note, $credit] = ticketCancellationFixture();

    $service = app(TicketPostingService::class);
    $service->postTicketCreditNote($note, 80_000);        // given back to the buyer
    $service->postTicketVendorCredit($credit, 85_000);    // got back from the supplier

    // Buyer 80,000 debit less supplier 85,000 credit = 5,000 credit,
    // i.e. the cancellation made 5,000 rather than costing anything.
    expect(ticketAccountBalance($company, '4160'))->toBe(-5_000.0);
});

it('shows a real cost when the buyer gets back more than the supplier returned', function () {
    [$company, $note, $credit] = ticketCancellationFixture();

    $service = app(TicketPostingService::class);
    $service->postTicketCreditNote($note, 85_000);
    $service->postTicketVendorCredit($credit, 80_000);

    expect(ticketAccountBalance($company, '4160'))->toBe(5_000.0);
});

it('posts one leg alone when the other returned nothing', function () {
    [$company, $note] = ticketCancellationFixture();

    app(TicketPostingService::class)->postTicketCreditNote($note, 80_000);

    expect(ticketAccountBalance($company, '4160'))->toBe(80_000.0)
        ->and(ticketAccountBalance($company, '1100'))->toBe(-80_000.0);
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Accounting/TicketCancellationPostingTest.php
```

Expected: FAIL, method `postTicketCreditNote` not found.

- [ ] **Step 3: Implement both methods**

```
postTicketCreditNote:
    Dr CANCELLATION_ADJUSTMENT   buyerReturnBase
    Cr AR                        buyerReturnBase

postTicketVendorCredit:
    Dr AP                        supplierReturnBase
    Cr CANCELLATION_ADJUSTMENT   supplierReturnBase
```

Resolve `TICKET_CREDIT_NOTE` and `TICKET_VENDOR_CREDIT` templates the same way
Task 4 resolves its two. Both throw when the amount is not greater than zero — a
zero leg raises no document at all, so a zero here means the caller has a bug.

- [ ] **Step 4: Run the tests**

```bash
php artisan test tests/Feature/Accounting/TicketCancellationPostingTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 5: Run the full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
git add modules/Accounting/ tests/
git commit -m "Post ticket cancellation credits against 4160"
```

---

## Done when

- `php artisan test` is at or above 185 passed.
- A `TICKET_INVOICE` template can be created, and posting through it leaves
  `2350` at exactly zero once the matching bill posts.
- A credit note can be recorded in USD against a PKR base with its rate.
- No account-type validator was widened. `CLEARING` still falls through to
  `default => true`; `income_account_id` still rejects a liability.

## Not in this plan

Ticket tables, bookings, cancellations, screens, permissions and reports are
Plan B and Plan C. Residual cash settlement (`acct.customer_refunds`,
`acct.vendor_refund_receipts`) is deferred to a follow-on by decision.
