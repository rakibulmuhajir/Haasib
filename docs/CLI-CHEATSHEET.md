# Haasib CLI Commands Cheatsheet

## 📊 Journal Entries

### Create Manual Journal Entry
```bash
php artisan journal:entry:create \
  --company-id="uuid" \
  --description="Description" \
  --date="2024-01-15" \
  --debit-account="account-uuid" \
  --credit-account="account-uuid" \
  --amount="1000.00"
```

### List Journal Entries
```bash
# All entries
php artisan journal:entry:list --company-id="uuid"

# Filter by status
php artisan journal:entry:list --company-id="uuid" --status="draft"
php artisan journal:entry:list --company-id="uuid" --status="posted"

# Filter by date range
php artisan journal:entry:list --company-id="uuid" --from="2024-01-01" --to="2024-01-31"
```

### Journal Entry Workflow
```bash
# Submit for approval
php artisan journal:entry:submit entry-uuid

# Approve entry
php artisan journal:entry:approve entry-uuid

# Post to ledger
php artisan journal:entry:post entry-uuid

# Reverse entry
php artisan journal:entry:reverse entry-uuid --reason="Correction"

# Void entry
php artisan journal:entry:void entry-uuid --reason="Error"
```

## 📦 Journal Batches

### Create Batch
```bash
php artisan journal:batch:create \
  --name="Batch Name" \
  --description="Description" \
  --company-id="uuid"
```

### List Batches
```bash
# All batches
php artisan journal:batch:list --company-id="uuid"

# Filter by status
php artisan journal:batch:list --company-id="uuid" --status="pending"
php artisan journal:batch:list --company-id="uuid" --status="posted"
```

### Batch Workflow
```bash
# Approve batch
php artisan journal:batch:approve batch-uuid

# Post batch
php artisan journal:batch:post batch-uuid

# Schedule batch processing
php artisan journal:batch:schedule batch-uuid --schedule="0 9 * * 1"
```

## 🔄 Recurring Templates

### Create Template
```bash
php artisan journal:template:create \
  --name="Template Name" \
  --description="Description" \
  --schedule="0 9 1 * *" \
  --debit-account="account-uuid" \
  --credit-account="account-uuid" \
  --amount="500.00"
```

### Template Management
```bash
# List templates
php artisan journal:template:list --company-id="uuid"

# Update template
php artisan journal:template:update template-uuid --amount="600.00"

# Activate/Deactivate
php artisan journal:template:activate template-uuid
php artisan journal:template:deactivate template-uuid

# Preview next generation
php artisan journal:template:preview template-uuid

# Generate entries now
php artisan journal:template:generate --company-id="uuid"

# Delete template
php artisan journal:template:delete template-uuid
```

## 📈 Trial Balance & Reports

### Generate Trial Balance
```bash
# Current date
php artisan trial-balance:generate --company-id="uuid"

# Specific date
php artisan trial-balance:generate --company-id="uuid" --date="2024-01-31"

# Date range
php artisan trial-balance:generate --company-id="uuid" --from="2024-01-01" --to="2024-01-31"
```

### Export Trial Balance
```bash
# Export to CSV
php artisan trial-balance:export \
  --company-id="uuid" \
  --date="2024-01-31" \
  --output="trial-balance.csv"

# Export specific accounts
php artisan trial-balance:export \
  --company-id="uuid" \
  --account-ids="uuid1,uuid2" \
  --output="accounts-trial-balance.csv"
```

## 👥 Customer Management

### Create Customer
```bash
php artisan customer:create \
  --company-id="uuid" \
  --name="Customer Name" \
  --email="customer@example.com" \
  --phone="555-1234"
```

### Customer Operations
```bash
# List customers
php artisan customer:list --company-id="uuid"

# Update customer
php artisan customer:update customer-uuid --name="New Name"

# Change status
php artisan customer:status customer-uuid --status="active"

# Delete customer
php artisan customer:delete customer-uuid

# Add contact
php artisan customer:contact:add customer-uuid --email="contact@example.com"

# Add address
php artisan customer:address:add customer-uuid --address="123 Main St"

# Update aging
php artisan customer:aging:update --company-id="uuid"
```

## 🏢 System Commands

### Company Management
```bash
# List companies
php artisan company:list

# Switch company context
php artisan company:switch company-uuid
```

### User Management
```bash
# List users
php artisan user:list

# Switch user context
php artisan user:switch user-uuid
```

### Module Management
```bash
# List available modules
php artisan module:list

# Enable module
php artisan module:enable accounting
```

## 📝 Usage Tips

### Company/User Context
Most commands require `--company-id` parameter. Set default context:
```bash
php artisan company:switch your-company-uuid
```

### Common Patterns
```bash
# Chain operations
ENTRY=$(php artisan journal:entry:create ...)
php artisan journal:entry:submit $ENTRY
php artisan journal:entry:approve $ENTRY
php artisan journal:entry:post $ENTRY

# Batch processing
BATCH=$(php artisan journal:batch:create ...)
# Add entries via API/UI
php artisan journal:batch:approve $BATCH
php artisan journal:batch:post $BATCH
```

### Getting Help
```bash
# List all available commands
php artisan list | grep journal

# Get help for specific command
php artisan help journal:entry:create
```

### Cron Scheduling Examples
```bash
# Daily at 9 AM
0 9 * * *

# Weekly on Monday at 9 AM
0 9 * * 1

# Monthly on 1st at 9 AM
0 9 1 * *

# Every weekday at 9 AM
0 9 * * 1-5
```