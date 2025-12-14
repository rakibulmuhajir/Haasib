# Haasib CLI Commands Cheat Sheet

Quick reference for common invoice management CLI commands.

## Invoice Operations

```bash
# Create invoice
php artisan invoice:create --customer=CUST-001 --items="Service:1:1000.00" --send

# List invoices
php artisan invoice:list --status=unpaid

# Show invoice details
php artisan invoice:show INV-2024-001

# Send invoice
php artisan invoice:send INV-2024-001

# Post to ledger
php artisan invoice:post INV-2024-001

# Generate PDF
php artisan invoice:pdf INV-2024-001 --save
```

## Template Operations

```bash
# Create template
php artisan invoice:template:create "Monthly Services" --customer=CUST-001 --items="Hosting:1:100.00"

# List templates
php artisan invoice:template:list

# Apply template
php artisan invoice:template:apply TPL-001 --customer=CUST-002

# Create from invoice
php artisan invoice:template:create "Consulting" --from-invoice=INV-2024-001
```

## Credit Notes

```bash
# Create credit note
php artisan creditnote:create INV-2024-001 250.00 --reason="Partial refund"

# List credit notes
php artisan creditnote:list

# Post credit note
php artisan creditnote:post CN-2024-001
```

## Payment Allocation

```bash
# Auto-allocate (FIFO)
php artisan payment:allocate PAY-001 --strategy=fifo

# Manual allocation
php artisan payment:allocate PAY-001 --invoices=INV-001,INV-002 --amounts=100,200

# Preview allocation
php artisan payment:allocate PAY-001 --strategy=proportional --dry-run

# List allocations
php artisan payment:allocation:list PAY-001
```

## Natural Language Examples

```bash
# Invoice creation
php artisan invoice:create --natural="create invoice for ACME Corp for $5000 due in 30 days send"

# Template creation
php artisan invoice:template:create --natural="create monthly hosting template for customer ABC-123"

# Complex request
php artisan invoice:create --natural="create invoice for Global Tech for consulting $3000 plus maintenance $500 due in 45 days"
```

## Common Options

```bash
--company=COMP-001     # Specify company
--format=json          # JSON output
--dry-run              # Preview without executing
--interactive          # Interactive mode
--send                 # Send immediately
--post                 # Post to ledger
--quiet                # Suppress output
```

## Quick Troubleshooting

```bash
# Check system status
php artisan setup:status

# Get command help
php artisan invoice:create --help

# Check permissions
php artisan invoice:list --company=COMP-001
```

## Output Formats

```bash
--format=table         # Table (default)
--format=json          # JSON
--format=csv           # CSV
--format=text          # Plain text
```

## Common Workflows

```bash
# Monthly recurring invoice workflow
php artisan invoice:template:apply TPL-MONTHLY --customer=CUST-001 --send

# Payment processing workflow
php artisan payment:allocate PAY-001 --strategy=fifo --auto

# Customer statement workflow
php artisan invoice:list --customer=CUST-001 --status=unpaid --format=json
```