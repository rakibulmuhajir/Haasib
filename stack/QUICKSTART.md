# Haasib Ledger Quickstart Guide

Welcome to Haasib, a comprehensive double-entry accounting system built on Laravel 12 with Vue 3 and Inertia.js.

## 🚀 Quickstart

### Prerequisites

- PHP 8.3+
- PostgreSQL 16+
- Node.js 18+
- Composer
- NPM or Yarn

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd haasib/stack
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   # Configure PostgreSQL in .env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=haasib
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # Run migrations
   php artisan migrate
   ```

5. **Seed data**
   ```bash
   php artisan db:seed
   ```

6. **Build frontend**
   ```bash
   npm run build
   ```

7. **Start development server**
   ```bash
   php artisan serve
   ```

## 📊 Core Features

### 1. Double-Entry Accounting
- **Manual Journal Entries**: Create journal entries with automatic debit/credit validation
- **Journal Entry Lifecycle**: Draft → Submit → Approve → Post
- **Audit Trail**: Complete audit history for all accounting transactions

### 2. Chart of Accounts
- **Account Management**: Create, update, and organize accounts
- **Account Types**: Assets, Liabilities, Equity, Revenue, Expenses
- **Account Hierarchy**: Parent-child relationships for rollup reporting

### 3. Trial Balance & Reporting
- **Trial Balance**: Generate real-time trial balances
- **Balance Sheet**: Financial position reporting
- **Income Statement**: Performance reporting
- **General Ledger**: Detailed transaction history

### 4. Recurring Templates
- **Automated Entries**: Schedule recurring journal entries
- **Template Management**: Create and manage recurring templates
- **Cron Scheduling**: Flexible scheduling with cron expressions

### 5. Batch Processing
- **Batch Management**: Group journal entries for approval
- **Workflow Control**: Batch approval and posting workflows
- **Bulk Operations**: Process multiple entries simultaneously

## 🎯 Getting Started

### Via Web UI

1. **Access the application**: Open `http://localhost:8000` in your browser
2. **Create a company**: Set up your company profile
3. **Set up accounts**: Create your chart of accounts
4. **Create journal entries**: Start recording transactions

### Via CLI

Haasib provides powerful CLI commands for accounting operations:

#### Journal Entry Management
```bash
# Create a manual journal entry
php artisan journal:entry:create \
  --company-id="your-company-id" \
  --description="Rent payment for office" \
  --date="2024-01-15" \
  --debit-account="10000" \
  --credit-account="50000" \
  --amount="2500.00"

# List journal entries
php artisan journal:entry:list --company-id="your-company-id"

# Approve and post entries
php artisan journal:entry:approve entry-id
php artisan journal:entry:post entry-id
```

#### Batch Processing
```bash
# Create a batch
php artisan journal:batch:create \
  --name="Monthly closing" \
  --description="January 2024 closing entries"

# List batches
php artisan journal:batch:list

# Approve and post batches
php artisan journal:batch:approve batch-id
php artisan journal:batch:post batch-id
```

#### Recurring Templates
```bash
# Create a recurring template
php artisan journal:template:create \
  --name="Monthly rent" \
  --description="Monthly office rent payment" \
  --schedule="0 9 1 * *" \
  --debit-account="10000" \
  --credit-account="50000" \
  --amount="2500.00"

# Generate recurring entries
php artisan journal:template:generate
```

#### Trial Balance
```bash
# Generate trial balance
php artisan trial-balance:generate \
  --company-id="your-company-id" \
  --date="2024-01-31"

# Export trial balance to CSV
php artisan trial-balance:export \
  --company-id="your-company-id" \
  --date="2024-01-31" \
  --output="trial-balance.csv"
```

## 🔄 Typical Workflows

### 1. Manual Journal Entry
```bash
# 1. Create journal entry (draft status)
php artisan journal:entry:create --description="Office supplies" --debit-account="58000" --credit-account="10000" --amount="150.00"

# 2. Submit for approval
php artisan journal:entry:submit entry-id

# 3. Approve the entry
php artisan journal:entry:approve entry-id

# 4. Post to ledger
php artisan journal:entry:post entry-id
```

### 2. Batch Processing
```bash
# 1. Create multiple entries (they start as draft)
# 2. Create a batch
php artisan journal:batch:create --name="Month-end closing"

# 3. Add entries to batch
# (Done via web UI or API)

# 4. Approve batch
php artisan journal:batch:approve batch-id

# 5. Post batch
php artisan journal:batch:post batch-id
```

### 3. Recurring Entries
```bash
# 1. Create template
php artisan journal:template:create --name="Monthly insurance" --schedule="0 0 15 * *"

# 2. Generate entries (automatically scheduled or manual)
php artisan journal:template:generate

# 3. Review and post generated entries
php artisan journal:entry:list --status="approved"
php artisan journal:entry:post generated-entry-id
```

## 🎨 Web Interface

### Navigation
- **Dashboard**: Overview of financial metrics
- **Journal Entries**: Manage journal entries and batches
- **Accounts**: Chart of accounts management
- **Reports**: Trial balance, balance sheet, income statement
- **Customers**: Customer management and aging reports
- **Invoices**: Invoice creation and management
- **Payments**: Payment processing and allocation

### Key Features
- **Real-time Validation**: Debit/credit validation during entry
- **Search & Filter**: Advanced filtering for all lists
- **Export**: CSV export for reports and data
- **Audit Trail**: Complete history of changes
- **Bulk Actions**: Batch operations for efficiency

## 📝 API Access

The system provides RESTful APIs for all operations:

```bash
# Authentication
curl -H "Authorization: Bearer your-token" https://your-domain.com/api/ledger/journal-entries

# Create journal entry
curl -X POST https://your-domain.com/api/ledger/journal-entries \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token" \
  -d '{"description":"Test entry","lines":[{"account_id":"uuid","debit_credit":"debit","amount":"100.00"}]}'
```

## 🔧 Configuration

### Queue Configuration
```bash
# Configure queue for background processing
QUEUE_CONNECTION=database
```

### Cron Jobs
```bash
# Add to your crontab for recurring entries
* * * * * cd /path-to-haasib && php artisan schedule:run
```

## 📚 Resources

### Documentation
- **API Documentation**: Available at `/docs` when running
- **CLI Help**: `php artisan help journal:entry:create`
- **Feature Guides**: Check the `docs/` directory

### Support
- **Troubleshooting**: Check logs in `storage/logs/laravel.log`
- **Performance**: Use Laravel Telescope for debugging
- **Testing**: Run `php artisan test` for test suite

## 🎉 Next Steps

1. **Explore the UI**: Navigate through the web interface
2. **Create Test Data**: Use CLI commands to create sample data
3. **Run Reports**: Generate trial balance and financial statements
4. **Set Up Recurring**: Configure recurring templates for regular entries
5. **Customize**: Adapt the system to your specific accounting needs

---

*Built with Laravel 12, Vue 3, PostgreSQL 16, and PrimeVue 4*