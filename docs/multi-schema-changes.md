# Multi-Schema Database Implementation Changes

> **ARCHIVE NOTICE**: Multi-schema notes reflect the pre-`stack/` layout. Confirm current migrations and schema policies before using any instructions here.

This document tracks all changes made to implement the multi-schema database structure.

## Schema Distribution
- **auth**: Authentication, authorization, multi-tenant core
- **public**: General reference data, system infrastructure
- **hrm**: Human Resource Management entities
- **acct**: Accounting and financial data

## Completed Changes

### 1. Database Migrations
- Updated core tables migration to move users, sessions, password_reset_tokens to auth schema
- Updated reference data migration to move currencies, languages, locales to public schema
- Updated company relationships migration with correct schema references
- Updated idempotency keys migration to use public schema
- Updated HRM migrations (customers, contacts, vendors) to use hrm schema
- Updated ledger schema migration to use acct schema with proper foreign keys
- Updated permissions migration (already correctly uses auth schema)

### 2. Eloquent Models
All models have been updated with schema prefixes in their $table property:
- **Auth schema**: User, Company, CompanyInvitation, CompanySecondaryCurrency, UserSetting
- **Public schema**: Currency, Country, ExchangeRate
- **HRM schema**: Customer, Contact, Vendor, Interaction
- **Accounting schema**: AccountsReceivable, Invoice, InvoiceItem, InvoiceItemTax, Item, ItemCategory, JournalEntry, JournalLine, LedgerAccount, Payment, PaymentAllocation, StockMovement

### 3. Configuration Files
- Updated `config/auth.php`: password reset tokens table now uses auth.password_reset_tokens
- Updated `config/session.php`: sessions table now uses auth.sessions
- Permission config already correctly uses auth schema prefixes

### 4. Services and Traits
- Updated `CurrencyService`: audit_logs now uses acct.audit_logs
- Updated `LedgerService`: audit_logs now uses acct.audit_logs
- Updated `AuditLogging` trait: audit_logs now uses acct.audit_logs

### 5. Middleware
- Updated `EnsureIdempotency`: idempotency_keys now uses public.idempotency_keys

### 6. Trait for Schema Management
Created `HasSchemaTable` trait that can be used by models to automatically add schema prefixes based on table names.

## Completed Changes

### 1. Database Migrations ✅
- Updated core tables migration to move users, sessions, password_reset_tokens to auth schema
- Updated reference data migration to move currencies, languages, locales to public schema
- Updated company relationships migration with correct schema references
- Updated idempotency keys migration to use public schema
- Updated HRM migrations (customers, contacts, vendors) to use hrm schema
- Updated ledger schema migration to use acct schema with proper foreign keys
- Updated permissions migration (already correctly uses auth schema)
- Updated ALL remaining migration files to use correct schema prefixes

### 2. Eloquent Models ✅
All models have been updated with schema prefixes in their $table property:
- **Auth schema**: User, Company, CompanyInvitation, CompanySecondaryCurrency, UserSetting
- **Public schema**: Currency, Country, ExchangeRate
- **HRM schema**: Customer, Contact, Vendor, Interaction
- **Accounting schema**: AccountsReceivable, Invoice, InvoiceItem, InvoiceItemTax, Item, ItemCategory, JournalEntry, JournalLine, LedgerAccount, Payment, PaymentAllocation, StockMovement

### 3. Configuration Files ✅
- Updated `config/auth.php`: password reset tokens table now uses auth.password_reset_tokens
- Updated `config/session.php`: sessions table now uses auth.sessions
- Permission config already correctly uses auth schema prefixes

### 4. Services and Traits ✅
- Updated `CurrencyService`: audit_logs now uses acct.audit_logs
- Updated `LedgerService`: audit_logs now uses acct.audit_logs
- Updated `AuditLogging` trait: audit_logs now uses acct.audit_logs

### 5. Middleware ✅
- Updated `EnsureIdempotency`: idempotency_keys now uses public.idempotency_keys

### 6. Seeders ✅
- Updated `ReferenceDataSeeder`: all tables now use public schema prefixes
- Other seeders using Eloquent models automatically get schema prefixes from HasSchemaTable trait

### 7. Test Files ✅
- Updated all test files to use schema-qualified table names in DB::table() and assertDatabase*() calls

### 8. Trait for Schema Management ✅
Created `HasSchemaTable` trait that automatically adds schema prefixes based on table names.

## Current Issue 🚨

**Schema Migration Order Problem**: Some tables are in wrong schemas because migrations ran before we updated them with schema prefixes:

**Tables incorrectly in public schema (should be in other schemas):**
- **HRM schema expected**: customers, contacts, vendors
- **Acct schema expected**: accounting_periods, accounts_receivable, audit_logs, bill_items, bill_payment_allocations, bill_payments, bills, chart_of_accounts, fiscal_years, invoice_item_taxes, invoice_items, invoices, items, journal_entries, journal_lines, ledger_accounts, payment_allocations, payments, transactions, user_accounts, user_currency_exchange_rates, user_currency_preferences, user_settings
- **Auth schema expected**: users, password_reset_tokens, sessions

## Required Fix

The database needs to be reset and migrations re-run with the corrected schema prefixes:

1. Drop all tables from the database
2. Run `php artisan migrate:fresh` to recreate with correct schemas
3. Run seeders to populate reference data
4. Test that all cross-schema relationships work correctly

## Notes
- PostgreSQL supports cross-schema foreign keys and joins
- Laravel handles schema-qualified table names natively
- The HasSchemaTable trait provides a clean way to manage schemas automatically
- RLS policies should be applied per schema for security
- All migrations are now properly updated with schema prefixes

## Notes
- PostgreSQL supports cross-schema foreign keys and joins
- Laravel handles schema-qualified table names natively
- The HasSchemaTable trait provides a clean way to manage schemas automatically
- RLS policies should be applied per schema for security
