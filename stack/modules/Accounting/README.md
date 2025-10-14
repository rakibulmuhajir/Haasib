# Accounting Module

## Description
This module handles all accounting functionality for the Haasib application.

## Structure
- **Domain/**: Business logic organized by subdomains
  - **Ledger/**: Chart of accounts, journal entries, trial balance
  - **Invoicing/**: Invoices, customers, invoice items
  - **Payments/**: Payment processing, reconciliation
- **CLI/**: Command-line interface components
- **Http/**: HTTP layer components
- **Database/**: Database-related files
- **Providers/**: Service providers
- **Routes/**: Route definitions
- **Resources/**: Frontend assets
- **Tests/**: Unit and feature tests

## Schema
This module uses the `acct` schema for database tables.