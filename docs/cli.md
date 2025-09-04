# LedgerFly CLI - Simplified Command Syntax

## Design Philosophy: Unique Commands, Zero Hierarchy

**Why No Modules/Prefixes?**
- ✅ Faster typing: `invoice` vs `ar.invoice.create`
- ✅ Natural language: matches how people think
- ✅ Easier memorization: unique verbs are intuitive
- ✅ Better autocomplete: no namespace confusion
- ✅ CLI feels like conversation, not programming

**CLI Activation**: Dedicated hotkey (e.g., Ctrl+`) expands CLI halfway up screen

## Mini-grammar & Parsing

**Grammar (v1):** `<verb> [subject] [amount] [date] [flags]`
- `verb`: canonical action id (invoice | payment | bill | pay | transfer | …)
- `subject`: fuzzy-matched entity by name/code (customer, invoice, vendor, account)
- `amount`: numbers with optional currency; parse locale
- `date`: natural date (“net30”, “today”, “2025-09-30”)
- `flags`: `--customer/-c`, `--vendor/-v`, `--amount/-a`, `--date/-d`, `--draft`, `--ref`, etc.

**EBNF sketch**
- command = verb , { SP , (subject | amount | date | flag) } ;
- verb = "invoice" | "payment" | "bill" | "pay" | "transfer" | "dashboard" | … ;
- subject = QUOTED | WORD+ ;
- amount = CURRENCY? , NUMBER ;
- date = "today" | "tomorrow" | "net" , NUMBER | ISO_DATE ;
- flag = "--" WORD [ "=" VALUE ] | "-" ALIAS VALUE ;


**Synonym map (client)**
- `invoice` ⇆ `bill-customer` ⇆ `send invoice` (already established in the [functional spec](clie-v2.md#synonyms-that-map-to-actions)).
- `payment` ⇆ `record payment` ⇆ `got paid` (also covered in the [functional spec](clie-v2.md#synonyms-that-map-to-actions)).
- `bill` ⇆ `expense` ⇆ `create bill` (see the [functional spec](clie-v2.md#synonyms-that-map-to-actions)).

**Parser behavior**
- Tokenize with quotes. Map synonyms to a canonical `action`.
- Use Fuse to fuzzy-match `subject` against customers/vendors/invoices.
- Heuristics: first number → `amount`, `netXX`/date token → `date`.
- Output `{ action, params, missing, idemKey }` where `idemKey` is a client-generated UUID for idempotency.

## Palette UI: placement & controls

- **Placement:** a thin **status-bar dock** at the bottom when idle; on `Ctrl+`` it **expands to half screen**. You already documented half-screen; this cements the bottom-dock default (see [palette spec](clie-v2.md#1-shell--interaction-model)).
- **Controls (right side):** `▢` expand-to-half, `—` hide to dock, `⛶` full screen.
- **Focus trap**, `aria-live` for results, ESC to close.
- **Hotkeys:** `Ctrl+`` toggle palette; `Tab` cycles suggestions; `Enter` runs; Up/Down navigate history.


---

## 1. Core Command Set

### 🏢 Company & Setup
```bash
setup           # Launch interactive company setup wizard with guided prompts for
                # basic info, chart of accounts, tax settings, and bank connections

company         # Display current company details or edit company information
                # including name, address, fiscal year, and currency settings

users           # List all users in current company or add/edit user permissions,
                # roles, and access levels for multi-user environments

switch          # Switch context between different companies in multi-tenant setup
                # with visual confirmation of active company
```

### 💰 Money In (AR)
```bash
invoice         # Create new customer invoice with line items, tax calculations,
                # and automatic GL posting. Supports recurring invoice templates

bill-customer   # Alternative command for creating invoices - more natural for
                # service-based businesses who "bill" rather than "invoice"

payment         # Record customer payment against specific invoices with automatic
                # AR allocation, bank deposit tracking, and payment method logging

customers       # Manage customer database including contact info, payment terms,
                # tax settings, and credit limits with search and filtering

aging           # Generate AR aging report showing outstanding customer balances
                # by age buckets (current, 30, 60, 90+ days) with drill-down detail
```

### 💸 Money Out (AP)
```bash
bill            # Create vendor bill with expense coding, approval workflow,
                # and automatic AP posting with due date tracking

expense         # Quick expense entry for immediate costs like meals, gas, supplies
                # with receipt attachment and expense category assignment

pay             # Pay outstanding bills with check printing, ACH transfer, or
                # credit card processing integration and automatic AP allocation

vendors         # Manage vendor database with contact info, payment terms, tax IDs,
                # and 1099 tracking capabilities with vendor performance metrics

owed            # Generate AP aging report showing outstanding vendor balances
                # by due date with cash flow impact analysis
```

### 🏦 Banking
```bash
accounts        # List all bank accounts with current balances, account types,
                # and reconciliation status with visual balance trends

import          # Import bank transactions from CSV, OFX, or direct bank feeds
                # with duplicate detection and intelligent transaction matching

reconcile       # Interactive bank reconciliation with outstanding item tracking,
                # auto-matching suggestions, and variance analysis

transfer        # Record transfers between bank accounts with automatic journal
                # entries and inter-account balance validation

balance         # Show real-time account balances with pending transaction effects
                # and available vs book balance differentiation
```

### 📊 Reporting
```bash
dashboard       # Main overview with cash position, AR/AP summaries, recent
                # transactions, and key performance indicators with trend charts

profit          # Generate Profit & Loss statement for any date range with
                # comparison periods, budget variance, and drill-down capability

balance-sheet   # Generate Balance Sheet with assets, liabilities, and equity
                # with comparative periods and ratio analysis

cash-flow       # Cash flow statement showing operating, investing, and financing
                # activities with cash position forecasting

taxes           # Tax reports including sales tax liability, 1099 preparation,
                # and tax code analysis with compliance checking
```

### ⚙️ System
```bash
help            # Context-sensitive help system that shows relevant commands
                # based on current screen/workflow with examples and shortcuts

history         # Command history with search, favorites, and replay functionality
                # including frequency analysis and usage patterns

templates       # Create, edit, and execute saved command templates for recurring
                # transactions like monthly rent, payroll, or utility bills

schedule        # Set up recurring commands for automated posting, report generation,
                # and reminder notifications with cron-like scheduling

export          # Export data to various formats (CSV, Excel, PDF, QuickBooks)
                # with custom field mapping and scheduled export capabilities
```

---

## 2. Natural Language & Smart Prompts

### Natural Phrases That Work
```bash
# These all create invoices:
invoice
create invoice
bill customer
send invoice
invoice client

# These all record payments:
payment
log payment
record payment
got paid
customer paid

# These all create expenses:
bill
expense
create bill
pay for
spent on
```

### Smart Prompt Examples

**Incomplete Invoice:**
```bash
> invoice
Who's the customer? (type to search or create new)
> Acme Corp
How much?
> 1500
When is it due? (e.g., "30 days", "March 15", "net 30")
> 30 days
Description? (optional)
> Consulting services
```

**Context-Aware Payment:**
```bash
> payment
Which invoice? (showing unpaid invoices for quick selection)
  [1] INV-001: Acme Corp - $1,500 (due in 5 days)
  [2] INV-002: Beta LLC - $800 (overdue 2 days)
> 1
How much did they pay? (default: $1,500)
> 1500
Payment method? (check/bank/cash/card)
> bank
```

---

## 3. Flag System (Optional Enhancement)

### Universal Flags
```bash
--amount, -a     # Dollar amount
--date, -d       # Any date (natural language)
--customer, -c   # Customer name/ID
--vendor, -v     # Vendor name/ID
--account        # Account name/code
--ref            # Reference number
--notes          # Description/notes
--draft          # Save without posting
--help, -h       # Command help
```

### Speed Examples
```bash
# Power users can skip prompts:
invoice -c "Acme Corp" -a 1500 -d "30 days" --ref "Consulting Q1"
payment -a 1500 --ref INV-001 --method bank
bill -v "Office Depot" -a 89.50 --account supplies
```

---

## 4. Command Examples by Workflow

### 📝 Creating Things
```bash
invoice          # New customer invoice
bill             # New vendor bill
expense          # Quick expense
customer         # Add customer
vendor           # Add vendor
account          # Add bank/GL account
```

### 💵 Money Movement
```bash
payment          # Customer paid me
pay              # I paid vendor/bill
transfer         # Move money between accounts
deposit          # Record deposit
withdrawal       # Record withdrawal
```

### 📊 Viewing Data
```bash
dashboard        # Main overview
customers        # Customer list
vendors          # Vendor list
invoices         # Invoice list
bills            # Bill list
transactions     # Recent transactions
balance          # Account balances
```

### 🔍 Reports & Analysis
```bash
aging            # AR aging (who owes me)
owed             # AP aging (what I owe)
profit           # P&L statement
balance-sheet    # Balance sheet
cash-flow        # Cash flow
taxes            # Tax summary
```

---

## 5. Autocomplete Intelligence

### Contextual Suggestions
```bash
> inv<TAB>
invoice    invoices

> pay<TAB>
payment    pay

> cus<TAB>
customer   customers

> show<TAB>
# Shows available things to show:
dashboard  profit  balance-sheet  customers  invoices  bills
```

### Smart Search Within Commands
```bash
> invoice
Customer: acme<TAB>
  Acme Corp
  Acme Industries
  Acme Services

> payment
Invoice: inv<TAB>
  INV-001: Acme Corp - $1,500
  INV-002: Beta LLC - $800
```

---

## 6. Conversation-Style Interactions

### Natural Question Flow
```bash
> What do I owe?
Showing bills due soon:
- Office Rent: $2,000 (due Mar 15)
- Electric Bill: $150 (due Mar 20)
Total: $2,150

> Who owes me money?
Outstanding invoices:
- Acme Corp: $1,500 (due in 5 days)
- Beta LLC: $800 (overdue by 2 days)
Total: $2,300

> How much cash do I have?
Bank Balances:
- Business Checking: $15,450
- Business Savings: $25,000
Total Available: $40,450
```

### Status Queries
```bash
> How are we doing?        # Dashboard overview
> What's overdue?         # Overdue invoices/bills
> What's coming up?       # Upcoming due dates
> Show me last month      # Previous month P&L
> Tax time               # Tax-related items
```

---

## 7. Templates & Shortcuts

### Common Shortcuts
```bash
> monthly rent           # Runs saved template
> utilities             # Runs utility bill template
> retainer invoice      # Runs retainer template
```

### Template Creation
```bash
> save template "monthly rent"
# Saves last command as template
> templates
# Shows all saved templates
```

---

## 8. Smart Error Handling

### Helpful Corrections
```bash
> invocie
Did you mean: invoice? (y/n)

> payment 5000
Warning: That's much higher than usual payments. Continue? (y/n)

> pay bill
Which bill? Here are recent unpaid bills:
  [1] Electric - $150
  [2] Office Rent - $2,000
```

---

## 9. Multi-Company Support

### Simple Context Switching
```bash
> switch                 # Shows company list
> switch "Subsidiary"    # Switches to subsidiary
> companies             # Lists all accessible companies
```

---

## 10. Integration Commands

### Simple Data Flow
```bash
> import bank           # Import bank transactions
> import receipts       # Import receipt photos
> sync quickbooks       # Sync with QuickBooks
> export taxes          # Export for tax prep
```

---

## 11. Structured Command API (server)

Single endpoint powers palette (and later, xterm):
POST /api/v1/commands
Headers: X-Idempotency-Key: <uuid>
Body: { "action": "invoice.create", "params": { ... } }


**Contract**
- On success: `{ ok: true, message, data, redirect? }`
- On validation: `422 { ok:false, errors }`
- On missing params: `200 { status:"prompt", fields:[{name,label,type,required,options?}], defaults? }`

**Notes**
- Wrap mutating actions in `DB::transaction`.
 - Responses follow API v1 conventions (snake_case, ISO-8601, errors envelope) as detailed in the [dev plan](dev-plan.md#6-api-v1-design).

## 12. Idempotency & Audit

 - Require `X-Idempotency-Key` for POST/PUT; persist keys 24h per user+tenant. This mirrors the idempotency guidance in the [dev plan](dev-plan.md#6-api-v1-design).
- Audit table stores: raw command string, parsed params, user, company, results (model ids), latency.

## 13. Auth, RBAC & Tenancy

- Enforce policies per action; verbs map to abilities (*ledger.post*, *invoice.create*, *payment.create*).
 - Tenant scoping from the active company; palette calls piggyback on your existing RLS/tenant context (see [dev plan](dev-plan.md#example-migration-schema--table--rls)).
- Failure modes: 422 for missing context, 403 for not-a-member, align with your middleware rules.

## 14. Superadmin Console (xterm) — separate

- Hidden route `/super/console` gated by `system_role=superadmin` (global). Different from company roles.
- Console speaks the **same** `{action, params}` API so logic lives in one place. Admin-only ops (queues, imports, reindex) go here, not the palette.

## 15. UX & Perf Tests

- Parser golden tests: string → `{action, params}`.
- Contract tests per action: params → domain result + GL rows (transactional).
- UX probes: keystroke→suggestions < 60 ms; enter→posted < 300 ms p50 (log spans).
- A11y: role="dialog", focus trap, `aria-activedescendant` on list, `aria-live="polite"`.


## Why This Approach Wins

**🎯 Cognitive Load**: One word = one action
**⚡ Speed**: No typing hierarchy prefixes
**🗣️ Natural**: Matches how people think about accounting
**📚 Learnable**: Unique verbs are memorable
**🔧 Powerful**: Flags available when needed
**💬 Conversational**: CLI feels like talking to an expert

**The Only Module Hierarchy Argument**:
If you had 100+ commands, grouping might help discoverability. But with smart autocomplete, natural language, and contextual help, even 50 unique commands are easily manageable. Users learn faster with distinct verbs than namespaced actions.

**Hotkey Experience**:
- Press Ctrl+` → CLI slides up halfway
- Type naturally: "invoice acme 1500"
- Hit Enter → Done
- CLI slides down, shows success message in GUI

This creates the "fastest accounting app experience" you're targeting while keeping it approachable for non-technical users.

