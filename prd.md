**Product Requirements Document (PRD)**

**Product Name**: LedgerFly (working title) **Product Type**: Hybrid Accounting Platform (CLI + GUI) **Target Audience**: SMEs, Freelancers, and Accountants **Primary Differentiator**: CLI-first speed with GUI-backed accessibility, focused on simplicity

---

## 1. **Executive Summary**

LedgerFly is a hybrid accounting app designed for SMEs that merges the power and speed of a Command Line Interface (CLI) with the intuitive accessibility of a Graphical User Interface (GUI). Built on a modular and multi-tenant architecture, the system supports critical accounting functions such as general ledger, accounts receivable/payable, bank reconciliation, tax, and inventory. The core philosophy is "Simplicity Over Complexity," making high-accuracy accounting easy, fast, and habit-forming.

---

## 2. **Goals and Objectives**

* Create the fastest accounting app experience for technical and non-technical users.
* Provide CLI for power users who want automation, speed, and scripting.
* Ensure the GUI is as intuitive as Wave, with no jargon or clutter.
* Support a modular architecture for future extensions.
* Provide high data integrity with compliance-grade audit trails.

---

## 3. **User Personas**

1. **Tech-savvy Founders / Freelancers**: Want speed, automation, and control.
2. **Accountants**: Need auditability, compliance, and detailed reporting.
3. **Non-technical SME Owners**: Seek ease of use, simplicity, and mobile access.

---

## 4. **Core Features**

### 4.1 CLI-first Architecture

* **Design Philosophy**: Unique commands, zero hierarchy — no need for module prefixes.
* Faster typing and easier memorization with single-word verbs.
* Natural language support for conversational CLI.
* Dedicated hotkey (Ctrl+\`) to open CLI overlay (slides up halfway on screen).
* Smart autocomplete and contextual suggestions.
* History, favorites, templates, and replay support.
* Natural-language date parsing (e.g., `due in 15d`, `March 15`).
* CLI accessible via web UI, desktop, and terminal.
* CLI feels conversational, not like programming.

### 4.2 GUI-First Simplicity

* Minimalist design (Wave-style)
* Primary workflows with 2-click access:

  * Create invoice/bill
  * Log payment/receipt
  * View cash flow
* Contextual overlays and inline help
* Visual CLI console in GUI (shows real-time command equivalent)

### 4.3 Multi-Tenant Support

* Schema-based separation (`company_id` scoped)
* Tenant-specific branding, timezone, currencies

### 4.4 Modules

* **Core**: Companies, users, permissions, currencies
* **Accounting**: GL, Chart of Accounts, Fiscal Years, Transactions, Journal Entries
* **AR/AP**: Invoices, Bills, Payments, Allocations
* **Banking**: Accounts, Transactions, Reconciliation
* **Tax**: Rates, Jurisdictions, Registrations, Exemptions
* **Inventory (Optional)**: Items, Stock levels, Movements

### 4.5 Posting Engine

* Auto-post via status triggers
* Templates for AR/AP
* COGS triggers for inventory

---

## 5. **Advanced Features**

* CLI-first onboarding (`setup` wizard)
* Scheduled CLI jobs (`post all invoices due today`)
* Intelligent prompts for missing flags
* Auto-validations and CLI confirmations (e.g. warn if period is closed)
* Modular loading (turn off unused modules per tenant)

---

## 6. **Technical Architecture**

* PostgreSQL backend (modular schemas)
* API-first backend
* CLI service (Node.js or Go-based command processor)
* React + Tailwind GUI frontend
* Redis or Kafka (for CLI job queues)
* Websockets (for CLI status updates in GUI)

---

## 7. **UX/UI Principles**

* "Zero-friction first time use"
* Onboarding via CLI wizard or GUI tour
* Realtime CLI/GU sync view
* Dashboard with widgets for pending invoices, due bills, bank balances

---

## 8. **Security & Compliance**

* Role-based permissions
* Audit trail for all changes (created\_by, updated\_by, timestamps)
* Encrypted credentials
* Optional 2FA

---

## 9. **Milestones & Roadmap**

* M1: CLI core engine with GL + Company setup
* M2: GUI MVP with Invoice, Bill, Payments
* M3: CLI-GUI unified workspace
* M4: Advanced Posting + Tax + Inventory

---

## 10. **Open Questions**

* Should CLI allow destructive commands by default?
* Which scripting language to embed (bash, js, custom DSL)?
* Real-time CLI collaboration (multi-user in same workspace)?
* Offline CLI sync support?

---

## 11. **CLI Command Structure (Flat Syntax)**

### 11.1 Core Commands

```bash
setup       # Company setup wizard
company     # Show/edit company info
users       # Manage users and roles
switch      # Switch active company
```

### 11.2 Money In (AR)

```bash
invoice         # Create/send invoice
bill-customer   # Alt. natural command for invoices
payment         # Record customer payment
customers       # Manage customers
aging           # AR aging report
```

### 11.3 Money Out (AP)

```bash
bill        # Vendor bill
expense     # Quick expense
pay         # Pay vendor bill
vendors     # Manage vendors
owed        # AP aging report
```

### 11.4 Banking

```bash
accounts    # Bank accounts list
import      # Import bank transactions
reconcile   # Reconcile bank account
transfer    # Transfer between accounts
balance     # Show balances
```

### 11.5 Reporting

```bash
dashboard       # Overview dashboard
profit          # Profit & Loss
balance-sheet   # Balance Sheet
cash-flow       # Cash flow report
taxes           # Tax reports
```

### 11.6 System

```bash
help        # Context help
history     # Command history
templates   # Saved templates
schedule    # Recurring jobs
export      # Export data
```

---

## 12. **Natural Language & Smart Prompts**

* Commands accept natural phrases (e.g., `send invoice`, `log payment`, `got paid`).
* CLI prompts user when details missing (e.g., customer, due date, amount).
* Context-aware suggestions (e.g., show unpaid invoices when logging payment).

---

## 13. **Autocomplete & Intelligence**

* Tab completion with contextual suggestions (`inv<TAB>` → `invoice`, `invoices`).
* Smart search within prompts (partial customer/vendor/invoice match).
* Natural queries supported (e.g., `who owes me money?`).

---

## 14. **Conversation-Style CLI**

* Support for natural questions:

```bash
> What do I owe?     # shows bills due
> Who owes me money? # shows AR aging
> How much cash?     # shows bank balances
```

* Status queries: `what's overdue?`, `show me last month`, `tax time`.

---

## 15. **Templates & Shortcuts**

* Save frequently used commands as templates.
* Recall with natural triggers (`monthly rent`, `utilities`).
* One-liner shortcut: `invoice acme 1500` → full invoice creation.

---

## 16. **Error Handling & Safety**

* Smart spellcheck (`invocie` → suggest `invoice`).
* Warnings on unusual values (`payment 5000` much higher than usual).
* Guided disambiguation (`pay bill` → list unpaid bills).

---

## 17. **Multi-Company Support**

* `switch` to change context.
* `companies` to list all accessible companies.
* Context indicator always visible in CLI/GUI.

---

## 18. **Integration Commands**

* `import bank` — import bank transactions
* `import receipts` — import receipt photos
* `sync quickbooks` — sync with QuickBooks
* `export taxes` — export tax data

---

**Appendices**

* Schema reference (see merged\_output.txt)
* CLI command map (Sections 11–18)

---

**End of Document**
