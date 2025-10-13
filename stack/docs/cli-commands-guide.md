# Haasib CLI Commands Guide

Comprehensive documentation for all invoice management CLI commands in the Haasib system.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Invoice Commands](#invoice-commands)
3. [Invoice Template Commands](#invoice-template-commands)
4. [Credit Note Commands](#credit-note-commands)
5. [Payment Allocation Commands](#payment-allocation-commands)
6. [Company Management Commands](#company-management-commands)
7. [Setup Commands](#setup-commands)
8. [Natural Language Processing](#natural-language-processing)
9. [Output Formats](#output-formats)
10. [Common Options](#common-options)
11. [Best Practices](#best-practices)
12. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Prerequisites

All CLI commands require:
- **Authentication**: User must be authenticated in the system
- **Company Context**: Most commands require company context (use `--company=<id>` or set default company)
- **Permissions**: User must have appropriate role-based permissions

### Basic Command Structure

```bash
php artisan <command>:<action> [arguments] [options]
```

### Authentication Setup

```bash
# Set up your authentication context
php artisan setup:status

# Initialize system if needed
php artisan setup:initialize
```

---

## Invoice Commands

### invoice:create

Create a new invoice with line items, customer information, and various delivery options.

#### Signature
```bash
php artisan invoice:create [options]
```

#### Key Options
- `--customer=`: Customer ID or name (required)
- `--items=`: Line items in JSON or comma-separated format
- `--issue-date=`: Issue date (Y-m-d, defaults to today)
- `--due-date=`: Due date (Y-m-d)
- `--currency=USD`: Currency code
- `--send`: Send invoice immediately after creation
- `--post`: Post invoice to ledger immediately
- `--draft`: Create as draft (default)
- `--natural=`: Natural language input
- `--format=table`: Output format

#### Examples

**Basic Invoice Creation:**
```bash
php artisan invoice:create --customer=acme-corp --items="Web Design:1:2500.00"
```

**Multi-line Items:**
```bash
php artisan invoice:create \
  --customer=CUST-001 \
  --items="Web Development:40:150.00,SEO Services:10:200.00,Hosting:12:50.00" \
  --due-date=2024-02-15
```

**JSON Line Items:**
```bash
php artisan invoice:create \
  --customer=123 \
  --items='[
    {"description": "Website Design", "quantity": 1, "unit_price": 2500, "tax_rate": 10},
    {"description": "Maintenance", "quantity": 12, "unit_price": 200, "tax_rate": 0}
  ]' \
  --send
```

**Natural Language:**
```bash
php artisan invoice:create \
  --natural="create invoice for ACME Corp for website design $2500 due in 30 days send"
```

**Interactive Mode:**
```bash
php artisan invoice:create --customer=CUST-001
# Will prompt for line items interactively
```

### invoice:list

List invoices with filtering and sorting options.

#### Signature
```bash
php artisan invoice:list [options]
```

#### Key Options
- `--status=`: Filter by status (draft, sent, posted, paid, cancelled)
- `--customer=`: Filter by customer ID or name
- `--date-from=`: Filter by issue date from
- `--date-to=`: Filter by issue date to
- `--overdue`: Show only overdue invoices
- `--limit=50`: Number of results to show
- `--format=table`: Output format

#### Examples

**List All Invoices:**
```bash
php artisan invoice:list
```

**Filter by Status:**
```bash
php artisan invoice:list --status=unpaid --format=table
```

**Overdue Invoices:**
```bash
php artisan invoice:list --overdue --format=json
```

**Date Range:**
```bash
php artisan invoice:list --date-from=2024-01-01 --date-to=2024-01-31
```

### invoice:show

Display detailed information about a specific invoice.

#### Signature
```bash
php artisan invoice:show {id}
```

#### Examples

**Show by Invoice Number:**
```bash
php artisan invoice:show INV-2024-001
```

**Show by UUID:**
```bash
php artisan invoice:show 123e4567-e89b-12d3-a456-426614174000
```

**JSON Output:**
```bash
php artisan invoice:show INV-2024-001 --format=json
```

### invoice:update

Update an existing invoice.

#### Signature
```bash
php artisan invoice:update {id} [options]
```

#### Key Options
- `--customer=`: Update customer
- `--items=`: Update line items
- `--issue-date=`: Update issue date
- `--due-date=`: Update due date
- `--notes=`: Update notes
- `--terms=`: Update payment terms

#### Examples

**Update Customer:**
```bash
php artisan invoice:update INV-2024-001 --customer=CUST-002
```

**Update Line Items:**
```bash
php artisan invoice:update INV-2024-001 --items="Updated Service:1:3000.00"
```

### invoice:send

Send an invoice to the customer.

#### Signature
```bash
php artisan invoice:send {id}
```

#### Examples

```bash
php artisan invoice:send INV-2024-001
```

### invoice:post

Post an invoice to the accounting ledger.

#### Signature
```bash
php artisan invoice:post {id}
```

#### Examples

```bash
php artisan invoice:post INV-2024-001
```

### invoice:cancel

Cancel an invoice.

#### Signature
```bash
php artisan invoice:cancel {id} --reason=`
```

#### Examples

```bash
php artisan invoice:cancel INV-2024-001 --reason="Customer request"
```

### invoice:duplicate

Create a duplicate of an existing invoice.

#### Signature
```bash
php artisan invoice:duplicate {id} [options]
```

#### Key Options
- `--customer=`: New customer ID (optional)
- `--date=`: New issue date (optional)

#### Examples

```bash
php artisan invoice:duplicate INV-2024-001 --customer=CUST-002
```

### invoice:pdf

Generate PDF for an invoice.

#### Signature
```bash
php artisan invoice:pdf {id} [options]
```

#### Key Options
- `--save`: Save PDF to file system
- `--preview`: Preview PDF generation

#### Examples

```bash
php artisan invoice:pdf INV-2024-001 --save
```

---

## Invoice Template Commands

### invoice:template:create

Create a new invoice template for recurring billing scenarios.

#### Signature
```bash
php artisan invoice:template:create {name} [options]
```

#### Key Options
- `--customer=`: Customer ID for customer-specific templates
- `--from-invoice=`: Create template from existing invoice
- `--description=`: Template description
- `--currency=`: Template currency
- `--items=`: Default line items
- `--payment-terms=30`: Default payment terms in days
- `--interactive`: Interactive mode for line items

#### Examples

**Create Basic Template:**
```bash
php artisan invoice:template:create "Monthly Hosting" \
  --customer=CUST-001 \
  --items="Web Hosting:1:100.00,SSL Certificate:1:50.00" \
  --payment-terms=30
```

**From Existing Invoice:**
```bash
php artisan invoice:template:create "Consulting Template" \
  --from-invoice=INV-2024-001 \
  --description="Template for consulting services"
```

**Interactive Mode:**
```bash
php artisan invoice:template:create "Custom Template" --interactive
```

**Natural Language:**
```bash
php artisan invoice:template:create --natural="create template for monthly recurring services for customer ABC-123"
```

### invoice:template:list

List all invoice templates.

#### Signature
```bash
php artisan invoice:template:list [options]
```

#### Key Options
- `--customer=`: Filter by customer
- `--active-only`: Show only active templates

#### Examples

```bash
php artisan invoice:template:list --active-only
```

### invoice:template:show

Show detailed template information.

#### Signature
```bash
php artisan invoice:template:show {id}
```

#### Examples

```bash
php artisan invoice:template:show TPL-001
```

### invoice:template:apply

Apply a template to create a new invoice.

#### Signature
```bash
php artisan invoice:template:apply {id} [options]
```

#### Key Options
- `--customer=`: Override template customer
- `--overrides=`: JSON string of field overrides

#### Examples

```bash
php artisan invoice:template:apply TPL-001 --customer=CUST-002
```

### invoice:template:update

Update an existing template.

#### Signature
```bash
php artisan invoice:template:update {id} [options]
```

#### Examples

```bash
php artisan invoice:template:update TPL-001 --items="Updated Service:1:150.00"
```

### invoice:template:duplicate

Duplicate an existing template.

#### Signature
```bash
php artisan invoice:template:duplicate {id} {new-name}
```

#### Examples

```bash
php artisan invoice:template:duplicate TPL-001 "New Template Name"
```

### invoice:template:delete

Delete a template.

#### Signature
```bash
php artisan invoice:template:delete {id}
```

#### Examples

```bash
php artisan invoice:template:delete TPL-001
```

---

## Credit Note Commands

### creditnote:create

Create a credit note against an existing invoice.

#### Signature
```bash
php artisan creditnote:create {invoice} {amount} [options]
```

#### Key Options
- `--reason=`: Reason for the credit note
- `--currency=`: Currency code (defaults to invoice currency)
- `--tax=`: Tax rate or amount
- `--items=`: Credit note items
- `--interactive`: Interactive mode
- `--dry-run`: Preview without creating

#### Examples

**Basic Credit Note:**
```bash
php artisan creditnote:create INV-2024-001 250.00 --reason="Partial refund"
```

**With Tax:**
```bash
php artisan creditnote:create INV-2024-001 100.00 \
  --reason="Service adjustment" \
  --tax=10%
```

**Interactive Mode:**
```bash
php artisan creditnote:create INV-2024-001 150.00 --interactive
```

**Dry Run Preview:**
```bash
php artisan creditnote:create INV-2024-001 200.00 \
  --reason="Quality issues" \
  --dry-run
```

### creditnote:list

List credit notes.

#### Signature
```bash
php artisan creditnote:list [options]
```

#### Examples

```bash
php artisan creditnote:list --status=pending
```

### creditnote:show

Show credit note details.

#### Signature
```bash
php artisan creditnote:show {id}
```

#### Examples

```bash
php artisan creditnote:show CN-2024-001
```

### creditnote:post

Post credit note to ledger.

#### Signature
```bash
php artisan creditnote:post {id}
```

#### Examples

```bash
php artisan creditnote:post CN-2024-001
```

### creditnote:cancel

Cancel a credit note.

#### Signature
```bash
php artisan creditnote:cancel {id} --reason=`
```

#### Examples

```bash
php artisan creditnote:cancel CN-2024-001 --reason="Error in creation"
```

---

## Payment Allocation Commands

### payment:allocate

Allocate payment amounts across multiple invoices using various strategies.

#### Signature
```bash
php artisan payment:allocate {payment} [options]
```

#### Key Options
- `--strategy=`: Allocation strategy (fifo, proportional, overdue_first, largest_first)
- `--invoices=`: Comma-separated invoice IDs for manual allocation
- `--amounts=`: Comma-separated allocation amounts
- `--auto`: Enable automatic allocation
- `--force`: Force allocation despite warnings
- `--dry-run`: Preview allocation without executing

#### Allocation Strategies

**FIFO (First In, First Out):**
```bash
php artisan payment:allocate PAY-001 --strategy=fifo
```

**Proportional:**
```bash
php artisan payment:allocate PAY-001 --strategy=proportional
```

**Overdue Priority:**
```bash
php artisan payment:allocate PAY-001 --strategy=overdue_first
```

**Largest First:**
```bash
php artisan payment:allocate PAY-001 --strategy=largest_first
```

**Manual Allocation:**
```bash
php artisan payment:allocate PAY-001 \
  --invoices=INV-001,INV-002,INV-003 \
  --amounts=100,200,150
```

**Dry Run Preview:**
```bash
php artisan payment:allocate PAY-001 --strategy=fifo --dry-run
```

### payment:allocation:list

List payment allocations for a payment.

#### Signature
```bash
php artisan payment:allocation:list {payment}
```

#### Examples

```bash
php artisan payment:allocation:list PAY-001
```

### payment:allocation:reverse

Reverse a payment allocation.

#### Signature
```bash
php artisan payment:allocation:reverse {payment} {allocation} --reason=`
```

#### Examples

```bash
php artisan payment:allocation:reverse PAY-001 ALLOC-001 --reason="Customer request"
```

### payment:allocation:report

Generate payment allocation reports.

#### Signature
```bash
php artisan payment:allocation:report [options]
```

#### Key Options
- `--date-from=`: Report start date
- `--date-to=`: Report end date
- `--customer=`: Filter by customer
- `--format=table`: Output format

#### Examples

```bash
php artisan payment:allocation:report --date-from=2024-01-01 --date-to=2024-01-31
```

---

## Company Management Commands

### company:invite:user

Invite a user to join a company.

#### Signature
```bash
php artisan company:invite:user {companyId} {email} [options]
```

#### Key Options
- `--role=`: User role (owner, admin, accountant, viewer)
- `--send-email`: Send invitation email immediately

#### Examples

```bash
php artisan company:invite:user COMP-001 user@example.com --role=admin --send-email
```

---

## Setup Commands

### setup:status

Check system setup status and prerequisites.

#### Signature
```bash
php artisan setup:status
```

#### Examples

```bash
php artisan setup:status
```

### setup:initialize

Initialize the system for first-time use.

#### Signature
```bash
php artisan setup:initialize
```

#### Examples

```bash
php artisan setup:initialize
```

### setup:reset

Reset system configuration (development use only).

#### Signature
```bash
php artisan setup:reset --confirm
```

#### Examples

```bash
php artisan setup:reset --confirm
```

---

## Natural Language Processing

Many commands support natural language input for quick, intuitive operations:

### Supported Natural Language Patterns

**Invoice Creation:**
```bash
php artisan invoice:create --natural="create invoice for ACME Corp for website design $2500 due in 30 days send"
```

**Template Creation:**
```bash
php artisan invoice:template:create --natural="create monthly hosting template for customer ABC-123"
```

**Complex Instructions:**
```bash
php artisan invoice:create \
  --natural="create invoice for Global Tech Inc for consulting services $5000 plus maintenance $1000 due in 45 days with notes 'Payment via wire transfer'"
```

### Natural Language Features

- **Customer Recognition**: Automatically identifies customers by name or ID
- **Amount Parsing**: Understands currency amounts and calculations
- **Date Handling**: Interprets relative dates ("in 30 days", "next Friday")
- **Action Words**: Recognizes "send", "post", "draft", etc.
- **Item Parsing**: Extracts line items from descriptions

---

## Output Formats

All commands support multiple output formats:

### Table Format (Default)
```bash
php artisan invoice:list --format=table
```

### JSON Format
```bash
php artisan invoice:list --format=json
```

### CSV Format
```bash
php artisan invoice:list --format=csv > invoices.csv
```

### Text Format
```bash
php artisan invoice:show INV-001 --format=text
```

---

## Common Options

Available across most commands:

### Authentication & Context
- `--company=`: Specify company ID
- `--user=`: Specify user ID (admin only)

### Output Control
- `--format=`: Output format (table, json, csv, text)
- `--quiet`: Suppress non-error output
- `--no-interactive`: Disable interactive prompts

### Natural Language
- `--natural=`: Natural language input
- `--no-confirmation`: Skip confirmation prompts

### Other Options
- `--help`: Show command help
- `--dry-run`: Preview without executing (where supported)

---

## Best Practices

### 1. Company Context Management
```bash
# Set company for session operations
export HAASIB_COMPANY=COMP-001

# Or specify per command
php artisan invoice:list --company=COMP-001
```

### 2. Natural Language Usage
- Use quotes around natural language input
- Be specific about amounts and dates
- Include action words (send, post, draft)

### 3. Batch Operations
```bash
# Process multiple invoices
for invoice in INV-001 INV-002 INV-003; do
  php artisan invoice:send $invoice
done
```

### 4. Data Validation
- Always use `--dry-run` for financial operations
- Verify customer IDs before creating invoices
- Check payment allocations before processing

### 5. Error Handling
```bash
# Check return codes
if php artisan invoice:create ...; then
  echo "Invoice created successfully"
else
  echo "Invoice creation failed"
fi
```

### 6. Security Considerations
- Never expose sensitive data in command line arguments
- Use environment variables for credentials
- Validate user permissions before operations

### 7. Performance Optimization
- Use appropriate filtering for large lists
- Limit results with `--limit` for better performance
- Use JSON output for programmatic processing

---

## Troubleshooting

### Common Issues

#### Authentication Errors
```bash
Error: Authentication required
Solution: Ensure you're logged in and have proper permissions
```

#### Company Context Missing
```bash
Error: Company context required
Solution: Use --company=<id> or set default company
```

#### Customer Not Found
```bash
Error: Customer 'ABC Corp' not found
Solution: Verify customer name or use customer ID
```

#### Invalid Date Format
```bash
Error: Invalid date format
Solution: Use Y-m-d format (2024-01-15)
```

#### Permission Denied
```bash
Error: Your role (viewer) does not allow this action
Solution: Contact administrator for appropriate permissions
```

### Debug Mode
Enable verbose output for troubleshooting:

```bash
php artisan invoice:create --customer=CUST-001 --verbose
```

### Log Files
Check application logs for detailed error information:

```bash
tail -f storage/logs/laravel.log
```

### Getting Help
Each command has built-in help:

```bash
php artisan invoice:create --help
php artisan invoice:list --help
```

### System Status
Check overall system health:

```bash
php artisan setup:status
```

---

## Advanced Usage

### Shell Integration

**Bash Function for Invoice Creation:**
```bash
create_invoice() {
  customer=$1
  amount=$2
  description=$3
  
  php artisan invoice:create \
    --customer="$customer" \
    --items="$description:1:$amount" \
    --send
}

# Usage:
create_invoice "ACME Corp" 2500 "Web Design Services"
```

**Batch Processing Script:**
```bash
#!/bin/bash
# process_invoices.sh

for invoice in $(php artisan invoice:list --status=draft --format=json | jq -r '.[].id'); do
  echo "Processing invoice: $invoice"
  php artisan invoice:send "$invoice"
  sleep 1
done
```

### Integration with External Systems

**API Integration Example:**
```bash
# Create invoice from external system data
curl -X POST "https://api.external-system.com/invoices" \
  | php artisan invoice:create --natural="$(cat -)"
```

### Custom Aliases

```bash
# Add to ~/.bashrc or ~/.zshrc
alias invoices='php artisan invoice:list'
alias create-invoice='php artisan invoice:create'
alias payment-alloc='php artisan payment:allocate'
```

---

This comprehensive guide covers all CLI commands in the Haasib invoice management system. For additional help or specific use cases, refer to the built-in command help or contact system administrators.