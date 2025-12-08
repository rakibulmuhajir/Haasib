# Integration Plan — GL Foundation

**Created**: 2025-12-07
**Purpose**: Roadmap for connecting AR/AP to General Ledger

---

## Current State Analysis

### What Exists ✅

| Component | Status | Notes |
|-----------|--------|-------|
| Chart of Accounts (`acct.accounts`) | ✅ | Has type, subtype (bank, cash, AR, AP), currency |
| Customers (`acct.customers`) | ✅ | AR master data |
| Vendors (`acct.vendors`) | ✅ | AP master data |
| Invoices (`acct.invoices`) | ✅ | Sales invoices with line items |
| Payments (`acct.payments`) | ✅ | Customer payments with allocations |
| Bills (`acct.bills`) | ✅ | Purchase invoices with line items |
| Bill Payments (`acct.bill_payments`) | ✅ | Vendor payments with allocations |
| Credit Notes / Vendor Credits | ✅ | Adjustments |

### What's Missing ❌

| Component | Gap | Impact |
|-----------|-----|--------|
| **deposit_account_id on Payment** | Can't select bank account | Money goes nowhere |
| **payment_account_id on BillPayment** | Can't select payment source | Money comes from nowhere |
| **income_account_id on InvoiceLineItem** | Can't post revenue | No P&L data |
| **expense_account_id on BillLineItem** | Can't post expenses | No P&L data |
| **Fiscal Years & Periods** | No period close | Can't lock periods |
| **Transactions & Journal Entries** | No GL posting | No ledger balances |
| **Posting service** | No auto-post logic | Manual journal entries |

---

## The Integration Gap Visualized

```
CURRENT STATE (Disconnected):
┌─────────────────┐     ┌─────────────────┐
│     AR Side     │     │    AP Side      │
├─────────────────┤     ├─────────────────┤
│ Customer        │     │ Vendor          │
│    ↓            │     │    ↓            │
│ Invoice ──────? │     │ Bill ────────?  │  ← No expense account
│    ↓            │     │    ↓            │
│ Payment ──────? │     │ BillPayment ──? │  ← No bank account
└─────────────────┘     └─────────────────┘
         ↓                      ↓
         ?                      ?
         ↓                      ↓
┌───────────────────────────────────────────┐
│              Chart of Accounts            │
│  (exists but nothing links to it)         │
└───────────────────────────────────────────┘
         ↓
         ?
         ↓
┌───────────────────────────────────────────┐
│           General Ledger                  │
│         (doesn't exist yet)               │
└───────────────────────────────────────────┘


TARGET STATE (Connected):
┌─────────────────┐     ┌─────────────────┐
│     AR Side     │     │    AP Side      │
├─────────────────┤     ├─────────────────┤
│ Customer        │     │ Vendor          │
│    ↓            │     │    ↓            │
│ Invoice ────────┼─────┼─ Bill           │
│ (line.income_   │     │ (line.expense_  │
│  account_id)    │     │  account_id)    │
│    ↓            │     │    ↓            │
│ Payment ────────┼─────┼─ BillPayment    │
│ (deposit_       │     │ (payment_       │
│  account_id)    │     │  account_id)    │
└────────┬────────┘     └────────┬────────┘
         │                       │
         ▼                       ▼
┌───────────────────────────────────────────┐
│              Posting Service              │
│  (creates journal entries automatically)  │
└───────────────────────────────────────────┘
         │
         ▼
┌───────────────────────────────────────────┐
│        acct.transactions                  │
│        acct.journal_entries               │
│        acct.journal_entry_lines           │
│  (balanced double-entry, links to COA)    │
└───────────────────────────────────────────┘
         │
         ▼
┌───────────────────────────────────────────┐
│     Reports (Trial Balance, P&L, BS)      │
│   (queries journal_entry_lines + COA)     │
└───────────────────────────────────────────┘
```

---

## Implementation Phases

### Phase 1: GL Foundation Tables (REQUIRED FIRST)
**Contract**: `gl-core-schema.md`

Create these tables in order:

1. **acct.fiscal_years**
   - Company's accounting years
   - Required before anything can post

2. **acct.accounting_periods**
   - Monthly/quarterly periods within fiscal year
   - Status: open, closed, locked
   - Required for period close

3. **acct.transactions**
   - Parent record for any GL posting
   - Links source (invoice, payment, manual) to journal

4. **acct.journal_entries**
   - The actual journal (JV-00001)
   - Status: draft, posted, void

5. **acct.journal_entry_lines**
   - Individual debit/credit lines
   - Links to `acct.accounts` (COA)
   - Trigger enforces balanced entries

**Migration file**: `2025_12_XX_000000_create_gl_core_tables.php`

---

### Phase 2: AR/AP Account Linking
**Modify existing tables**

#### 2a. Add columns to `acct.payments`
```sql
ALTER TABLE acct.payments
  ADD COLUMN deposit_account_id uuid REFERENCES acct.accounts(id),
  ADD COLUMN transaction_id uuid REFERENCES acct.transactions(id);
```

**Validation**: `deposit_account_id` must be an account with `subtype IN ('bank', 'cash')`

**UI Change**: Payment form gets "Deposit To" dropdown showing bank/cash accounts

#### 2b. Add columns to `acct.bill_payments`
```sql
ALTER TABLE acct.bill_payments
  ADD COLUMN payment_account_id uuid REFERENCES acct.accounts(id),
  ADD COLUMN transaction_id uuid REFERENCES acct.transactions(id);
```

**Validation**: `payment_account_id` must be an account with `subtype IN ('bank', 'cash', 'credit_card')`

**UI Change**: Bill Payment form gets "Pay From" dropdown

#### 2c. Add columns to `acct.invoice_line_items`
```sql
ALTER TABLE acct.invoice_line_items
  ADD COLUMN income_account_id uuid REFERENCES acct.accounts(id);
```

**Validation**: `income_account_id` must be an account with `type = 'revenue'`

**Default**: Company can set default income account in settings

#### 2d. Add columns to `acct.bill_line_items`
```sql
ALTER TABLE acct.bill_line_items
  ADD COLUMN expense_account_id uuid REFERENCES acct.accounts(id);
```

**Validation**: `expense_account_id` must be an account with `type IN ('expense', 'cogs', 'asset')`

**Migration file**: `2025_12_XX_000001_add_gl_links_to_ar_ap.php`

---

### Phase 3: Posting Service
**New service class**

```php
// App\Services\PostingService
class PostingService
{
    public function postInvoice(Invoice $invoice): Transaction;
    public function postPayment(Payment $payment): Transaction;
    public function postBill(Bill $bill): Transaction;
    public function postBillPayment(BillPayment $billPayment): Transaction;
    public function reverseTransaction(Transaction $transaction): Transaction;
}
```

#### Posting Logic Examples

**Invoice Posted** (when status changes to 'sent' or 'approved'):
```
Debit:  Accounts Receivable (customer's AR account)     $1,000
Credit: Sales Revenue (from line item income_account)   $1,000
```

**Payment Received** (on create):
```
Debit:  Bank Account (deposit_account_id)               $1,000
Credit: Accounts Receivable (customer's AR account)     $1,000
```

**Bill Posted** (when status changes to 'approved'):
```
Debit:  Expense Account (from line item expense_account) $500
Credit: Accounts Payable (vendor's AP account)           $500
```

**Bill Payment Made** (on create):
```
Debit:  Accounts Payable (vendor's AP account)          $500
Credit: Bank Account (payment_account_id)               $500
```

---

### Phase 4: Default Account Settings
**New company settings**

Add to company settings or create `acct.account_defaults`:

| Setting | Purpose | Account Type |
|---------|---------|--------------|
| `default_ar_account_id` | Customer receivables | subtype = 'accounts_receivable' |
| `default_ap_account_id` | Vendor payables | subtype = 'accounts_payable' |
| `default_income_account_id` | Sales revenue | type = 'revenue' |
| `default_expense_account_id` | General expense | type = 'expense' |
| `default_bank_account_id` | Primary bank | subtype = 'bank' |
| `retained_earnings_account_id` | Year-end close | subtype = 'retained_earnings' |

**UI**: Settings > Accounting > Default Accounts

---

## Detailed Task Breakdown

### Week 1: GL Core Tables

| Task | File | Dependencies |
|------|------|--------------|
| Create fiscal_years migration | `create_gl_core_tables.php` | None |
| Create accounting_periods migration | Same file | fiscal_years |
| Create transactions migration | Same file | accounting_periods |
| Create journal_entries migration | Same file | transactions |
| Create journal_entry_lines migration | Same file | journal_entries, accounts |
| Add balance trigger | Same file | journal_entry_lines |
| Create FiscalYear model | `FiscalYear.php` | Migration |
| Create AccountingPeriod model | `AccountingPeriod.php` | Migration |
| Create Transaction model | `Transaction.php` | Migration |
| Create JournalEntry model | `JournalEntry.php` | Migration |
| Create JournalEntryLine model | `JournalEntryLine.php` | Migration |

### Week 2: AR/AP Integration

| Task | File | Dependencies |
|------|------|--------------|
| Add deposit_account_id to payments | `add_gl_links.php` | GL tables exist |
| Add payment_account_id to bill_payments | Same file | GL tables exist |
| Add income_account_id to invoice_line_items | Same file | GL tables exist |
| Add expense_account_id to bill_line_items | Same file | GL tables exist |
| Update Payment model $fillable | `Payment.php` | Migration |
| Update BillPayment model $fillable | `BillPayment.php` | Migration |
| Update InvoiceLineItem model $fillable | `InvoiceLineItem.php` | Migration |
| Update BillLineItem model $fillable | `BillLineItem.php` | Migration |
| Add account relationship to models | All above | Migration |

### Week 3: UI Updates

| Task | File | Dependencies |
|------|------|--------------|
| Payment form: add deposit_account_id select | `payments/Create.vue` | Model updated |
| BillPayment form: add payment_account_id select | `bill-payments/Create.vue` | Model updated |
| Invoice line item: add income_account_id select | `invoices/Create.vue` | Model updated |
| Bill line item: add expense_account_id select | `bills/Create.vue` | Model updated |
| Bank account selector component | `BankAccountSelect.vue` | None |
| Revenue account selector component | `RevenueAccountSelect.vue` | None |
| Expense account selector component | `ExpenseAccountSelect.vue` | None |

### Week 4: Posting Service

| Task | File | Dependencies |
|------|------|--------------|
| Create PostingService | `PostingService.php` | All models |
| Implement postInvoice() | Same file | Service class |
| Implement postPayment() | Same file | Service class |
| Implement postBill() | Same file | Service class |
| Implement postBillPayment() | Same file | Service class |
| Wire to existing actions via events | Event listeners | Service complete |

---

## Account Selector Specifications

### Bank Account Selector
Used in: Payment, BillPayment

```vue
<BankAccountSelect
  v-model="form.deposit_account_id"
  :company-id="company.id"
  label="Deposit To"
  placeholder="Select bank account"
/>
```

**Query**: `accounts WHERE company_id = ? AND subtype IN ('bank', 'cash') AND is_active = true`

### Revenue Account Selector
Used in: Invoice line items

```vue
<RevenueAccountSelect
  v-model="line.income_account_id"
  :company-id="company.id"
  :default-id="defaultIncomeAccountId"
/>
```

**Query**: `accounts WHERE company_id = ? AND type = 'revenue' AND is_active = true`

### Expense Account Selector
Used in: Bill line items

```vue
<ExpenseAccountSelect
  v-model="line.expense_account_id"
  :company-id="company.id"
  :default-id="defaultExpenseAccountId"
/>
```

**Query**: `accounts WHERE company_id = ? AND type IN ('expense', 'cogs', 'asset') AND is_active = true`

---

## Posting Triggers (When to Post)

| Document | Trigger Event | Journal Entry |
|----------|---------------|---------------|
| Invoice | Status → 'sent' or 'approved' | DR AR, CR Revenue |
| Payment | On create (always posted immediately) | DR Bank, CR AR |
| Credit Note | Status → 'approved' | DR Revenue, CR AR |
| Bill | Status → 'approved' | DR Expense, CR AP |
| Bill Payment | On create (always posted immediately) | DR AP, CR Bank |
| Vendor Credit | Status → 'approved' | DR AP, CR Expense |

---

## Backward Compatibility

### Existing Data Without Account Links

For existing payments/invoices without account IDs:
1. They remain valid (nullable foreign keys)
2. They won't appear in GL reports
3. Admin can "migrate" them by assigning accounts retroactively
4. New records require account selection (after Phase 2)

### Migration Script for Existing Data

```php
// Optional: Assign default accounts to existing records
Payment::whereNull('deposit_account_id')
    ->update(['deposit_account_id' => $company->default_bank_account_id]);
```

---

## Testing Checklist

### Unit Tests
- [ ] JournalEntry always balances (trigger test)
- [ ] PostingService creates correct entries for invoice
- [ ] PostingService creates correct entries for payment
- [ ] Void reverses entries correctly
- [ ] Period close prevents new postings

### Integration Tests
- [ ] Create invoice → post → verify GL balances
- [ ] Create payment → post → verify bank account balance
- [ ] Full cycle: invoice → payment → reconciliation

### UI Tests
- [ ] Bank account dropdown shows only bank/cash accounts
- [ ] Revenue account dropdown shows only revenue accounts
- [ ] Expense account dropdown shows expense/cogs/asset accounts
- [ ] Default account pre-selected when available

---

## Next Steps

1. **Approve this plan** - Review with stakeholders
2. **Start Phase 1** - Create GL core migration
3. **Seed test data** - Fiscal year + periods for dev company
4. **Phase 2** - Add account links to AR/AP
5. **Phase 3** - Update UI forms
6. **Phase 4** - Implement posting service

---

## Related Contracts

- [gl-core-schema.md](./gl-core-schema.md) - GL table specifications
- [coa-schema.md](./coa-schema.md) - Chart of Accounts (already implemented)
- [accounting-invoicing-contract.md](./accounting-invoicing-contract.md) - AR tables
- [ap-schema.md](./ap-schema.md) - AP tables
- [posting-schema.md](./posting-schema.md) - Auto-posting templates
