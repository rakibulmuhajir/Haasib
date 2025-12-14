# Multi-Currency Business Rules (LOCKED — Phase 1)
Version: 1.0  
Status: LOCKED  
Last Updated: 2024-01-28

## Scope (Phase 1)
- ✅ Multi-currency transactions (invoices/bills/payments) with manual rates.
- ✅ Foreign currency accounts (Bank, AR, AP, CC, Asset/Liability types allowed).
- ✅ Realized forex gains/losses on payments only.
- ✅ Dual amount display (transaction + base).
- ✅ Reports in base currency only.
- ❌ Automated rates, unrealized FX, cross-currency allocations (non-base), multi-currency reporting, base currency changes, FX bank recs.

## Core Principles (Immutable)
1) One base currency per company (`base_currency` char(3)).  
2) Dual recording: store `currency_amount` and `base_amount`.  
3) Exchange rates on posted transactions never change.  
4) Journals balance in base currency at numeric(15,2).  
5) Phase 1 only realized FX on payments (no revaluation).

## Precision Standards
- `currency_amount numeric(18,6)`
- `exchange_rate numeric(18,8)`
- `base_amount numeric(15,2)`
- `debit/credit numeric(15,2)`
- Balance invariant: `SUM(debit) = SUM(credit)` per journal entry at 15,2.

## Currency Enablement
- Base currency selected at company creation (must exist in `public.currencies`, active). Immutable after journal entries exist.
- Enabling currencies: add to `auth.company_currencies` (must be active in `public.currencies`). No duplicates.
- Disabling currency: blocked if base; blocked if accounts in that currency have non-zero balances or unpaid transactions.

## Account Currency Rules
Foreign currency allowed: Bank, AR, AP, Credit Card, Other Current Asset, Other Asset, Other Current Liability, Other Liability.  
Base-only (currency NULL): Revenue, COGS, Expense, Other Income, Other Expense, Equity.  
Account currency immutable after the first journal_entry_line. Account name should include currency if foreign (e.g., "Chase Bank (USD)").

## Transaction Rules
### Invoices/Bills
- Select currency from enabled list (defaults to base).  
- If `currency != base_currency`: `exchange_rate` required > 0; `base_amount = ROUND(total_amount * exchange_rate, 2)`.  
- If `currency = base_currency`: `exchange_rate` NULL; `base_amount = total_amount`.  
- Balance tracks in transaction currency; posting uses base amounts.

### Payments
- Payment currency must equal invoice currency OR company base currency (Phase 1 rule).  
- `base_amount = ROUND(amount * COALESCE(exchange_rate, 1.0), 2)`.  
- Allocate only under the rule above; otherwise reject.

### Journal Entry Lines
- Store `currency` (char(3)), `currency_amount numeric(18,6)`, `exchange_rate numeric(18,8)`, `debit/credit numeric(15,2)`.  
- `debit`/`credit` mutually exclusive.  
- If `currency = base_currency`: `exchange_rate = 1.0`, `currency_amount = debit/credit`.  
- If `currency != base_currency`: `debit/credit = ROUND(currency_amount * exchange_rate, 2)`.  
- Journals must balance in base at 15,2.

## Payment Allocation Rule (Phase 1)
```
payment.currency = invoice.currency
OR
payment.currency = company.base_currency
```
No other cross-currency allocations.

## Exchange Rates (Manual Only)
- Convention: store as `1 [foreign] = X [base]` (never inverted).  
- Required if transaction currency differs from base.  
- Precision up to 8 decimals, > 0.  
- Immutable after posting; void/reverse using original rate.

## Forex Gain/Loss (Realized Only)
- Trigger: payment rate differs from invoice rate.  
- Formula (per allocation):
```
invoice_base = allocation_amount * invoice.exchange_rate
payment_base = allocation_amount * payment.exchange_rate
fx = payment_base - invoice_base
```
- Gain → credit FX Gains (8010). Loss → debit FX Losses (9010).  
- Use same rate as original on reversals.

## Immutability Rules
- Cannot change after posting: transaction currency, exchange_rate, base_amount, account currency (after postings), company base_currency (after journals).  
- Drafts can change. Currency enablement can change if unused.

## Out-of-Scope (Phase 1)
- Automated rate fetch, daily rates, unrealized FX, cross-currency allocations (non-base), multi-currency reporting, base currency change, FX bank recs, hedging/derivatives, crypto.

## Error Messages (standard)
- `CURRENCY_NOT_ENABLED`: enable currency first.  
- `RATE_REQUIRED`: rate needed when currency != base.  
- `INVALID_RATE`: must be >0, <=8 dp.  
- `CURRENCY_MISMATCH`: payment currency must match invoice or base.  
- `IMMUTABLE_CURRENCY`: entity has transactions.  
- `CANNOT_DISABLE_BASE`: base currency cannot be disabled.  
- `CURRENCY_IN_USE`: currency has balances/open transactions.  
- `INVALID_ACCOUNT_TYPE`: this account type must use base currency.
