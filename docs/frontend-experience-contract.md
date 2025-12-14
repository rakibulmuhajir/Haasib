# Frontend Experience Contract

**Last Updated**: 2025-12-11
**Purpose**: Defines the end-user experience, information architecture, and UX philosophy
**Audience**: Product Managers, UX Designers, Frontend Developers
**Related**: `docs/ui-screen-specifications.md` (technical field specs)

---

## Table of Contents

1. [Core Philosophy](#1-core-philosophy)
2. [User Modes](#2-user-modes)
3. [Information Architecture](#3-information-architecture)
4. [The Resolution Engine](#4-the-resolution-engine)
5. [Navigation & Layout](#5-navigation--layout)
6. [Dashboard Experience](#6-dashboard-experience)
7. [Transaction Interfaces](#7-transaction-interfaces)
8. [Reporting Experience](#8-reporting-experience)
9. [Safety Nets & Error Recovery](#9-safety-nets--error-recovery)
10. [Onboarding Experience](#10-onboarding-experience)
11. [Mobile Strategy](#11-mobile-strategy)
12. [Permissions & Role-Based UX](#12-permissions--role-based-ux)
13. [Interaction Patterns](#13-interaction-patterns)
14. [Language & Terminology](#14-language--terminology)
15. [Visual Design Principles](#15-visual-design-principles)

---

## 1. Core Philosophy

### The Promise
> "So easy you can't mess it up. So rigorous it survives a tax audit."

### The Reality
A strict double-entry accounting engine concealed behind a consumer-grade, task-based interface.

### Design Principles

| Principle | Description | Implementation |
|-----------|-------------|----------------|
| **Hide the Plumbing** | Never show journals, debits, credits unless explicitly requested | Owner Mode hides all GL terminology |
| **Task-Based, Not Module-Based** | Users think in actions, not accounting concepts | "Record a sale" not "Create invoice and post to AR" |
| **Progressive Disclosure** | Show complexity only when needed | Accountant Mode toggle reveals advanced features |
| **Guardrails, Not Locks** | Prevent errors through design, not error messages | Smart defaults, validation at entry, not submission |
| **Explain, Don't Blame** | When things don't balance, show why | Balance Explainer widget, not "Error: Unbalanced" |

### The Two-Brain Architecture

The system serves two distinct mental models:

```
┌─────────────────────────────────────────────────────────────┐
│                    HAASIB ACCOUNTING                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────────┐          ┌──────────────────┐        │
│  │   OWNER MODE     │          │ ACCOUNTANT MODE  │        │
│  │   (Ease Layer)   │          │  (Rigor Layer)   │        │
│  │                  │          │                  │        │
│  │  "Money In"      │  ←───→   │  "Revenue"       │        │
│  │  "Money Out"     │  Toggle  │  "Expenses"      │        │
│  │  "Categories"    │          │  "GL Accounts"   │        │
│  │  "Unpaid"        │          │  "AR Aging"      │        │
│  └──────────────────┘          └──────────────────┘        │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              CODEX ENGINE (Immutable Ledger)        │   │
│  │         Double-Entry · Period Locking · Audit       │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. User Modes

### Mode Toggle
- **Location:** Header, next to user profile
- **Persistence:** Remembered per user per device
- **Transition:** Instant, no page reload

### A. Owner Mode (Default)

**Target User:** Business owners, non-accountants, daily operators

**Goal:** Cash flow visibility and tax compliance

**Characteristics:**

| Aspect | Owner Mode |
|--------|------------|
| **Language** | Plain English |
| **Density** | Low (large cards, whitespace) |
| **Navigation** | Task-based ("Record Sale", "Pay Bill") |
| **Reports** | Question-based ("How much did I make?") |
| **Hidden Elements** | Journal IDs, DR/CR, GL codes, Trial Balance |
| **Visible Elements** | Money In/Out, Categories, Balances |

**UI Patterns:**
- Large action cards on dashboard
- Simplified forms with smart defaults
- Progress indicators and task completion
- Natural language confirmations ("You recorded $500 in sales")

**Hidden from Owner Mode:**
- Journal entry numbers
- Debit/Credit columns
- General Ledger codes
- Chart of Accounts management
- Period management
- Reconciliation details
- Tax code configuration

### B. Accountant Mode (Professional)

**Target User:** Accountants, bookkeepers, auditors, financial controllers

**Goal:** Audit trail, reconciliation, adjustments, compliance

**Characteristics:**

| Aspect | Accountant Mode |
|--------|-----------------|
| **Language** | Accounting terminology |
| **Density** | High (compact grids, data tables) |
| **Navigation** | Module-based (GL, AR, AP, Reports) |
| **Reports** | Standard GAAP/IFRS names |
| **Visible Elements** | Full COA, Journal IDs, Tax Codes, Depreciation |
| **Shortcuts** | Keyboard navigation, bulk actions |

**UI Patterns:**
- High-density data grids
- Keyboard shortcuts (Ctrl+J for Journal Entry)
- Batch operations
- Drill-down everywhere
- Export everything

**Exclusive to Accountant Mode:**
- Direct Journal Entry creation
- Chart of Accounts editing
- Period close/lock
- Reconciliation workflow
- Tax code management
- Depreciation schedules
- Audit log access

### Mode Switching Behavior

```typescript
interface UserMode {
  mode: 'owner' | 'accountant';
  preferences: {
    defaultMode: 'owner' | 'accountant';
    rememberChoice: boolean;
  };
}

// Switching modes updates:
// 1. Navigation menu items
// 2. Column visibility in grids
// 3. Report names and groupings
// 4. Available actions on entities
// 5. Terminology throughout UI
```

---

## 3. Information Architecture

### Owner Mode Navigation

```
Dashboard
├── Money In
│   ├── Record Sale
│   ├── Send Invoice
│   ├── View Unpaid (AR Aging)
│   └── Customer List
├── Money Out
│   ├── Record Expense
│   ├── Enter Bill
│   ├── Pay Bills
│   ├── View Unpaid (AP Aging)
│   └── Vendor List
├── Bank
│   ├── Review Transactions (Resolution Engine)
│   ├── Connect Account
│   └── Balance Overview
├── Reports
│   ├── How Much Did I Make? (P&L)
│   ├── Who Owes Me Money? (AR)
│   ├── Who Do I Owe? (AP)
│   ├── Where Did My Money Go? (Expenses)
│   └── Cash Flow Forecast
└── Settings
    ├── Company Info
    ├── Tax Settings (simplified)
    └── Team & Permissions
```

### Accountant Mode Navigation

```
Dashboard
├── Accounting
│   ├── Chart of Accounts
│   ├── Journal Entries
│   ├── General Ledger
│   ├── Trial Balance
│   └── Period Management
├── Receivables (AR)
│   ├── Customers
│   ├── Invoices
│   ├── Payments
│   ├── Credit Notes
│   └── AR Aging Report
├── Payables (AP)
│   ├── Vendors
│   ├── Bills
│   ├── Bill Payments
│   ├── Vendor Credits
│   └── AP Aging Report
├── Banking
│   ├── Bank Accounts
│   ├── Transactions
│   ├── Reconciliation
│   ├── Bank Rules
│   └── Bank Transfers
├── Tax
│   ├── Tax Codes
│   ├── Tax Rates
│   ├── Tax Reports
│   └── Tax Settings
├── Reports
│   ├── Financial Statements
│   │   ├── Income Statement
│   │   ├── Balance Sheet
│   │   └── Cash Flow Statement
│   ├── Ledger Reports
│   │   ├── General Ledger
│   │   ├── Trial Balance
│   │   └── Journal Report
│   ├── Aging Reports
│   ├── Tax Reports
│   └── Custom Reports
└── Settings
    ├── Company Settings
    ├── Fiscal Years
    ├── Currencies & FX
    ├── Tax Configuration
    ├── Users & Permissions
    └── Integrations
```

---

## 4. The Resolution Engine

**The Primary Interface for Bank Transaction Processing**

This replaces the standard "Transactions List" and is the heart of the daily workflow.

### Concept

```
┌─────────────────────────────────────────────────────────────┐
│  INPUT: Immutable Bank Feed (API/CSV)                       │
│                      ↓                                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │            RESOLUTION ENGINE                         │   │
│  │                                                      │   │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐       │   │
│  │  │ MATCH  │ │ CREATE │ │TRANSFER│ │  PARK  │       │   │
│  │  └────────┘ └────────┘ └────────┘ └────────┘       │   │
│  │                                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                      ↓                                      │
│  OUTPUT: Posted Journal Entries                             │
└─────────────────────────────────────────────────────────────┘
```

### The 4 Resolution Modes

#### Mode 1: MATCH (Pairing)

**Purpose:** Link bank transaction to existing invoice/bill

**Trigger:** System detects open Invoice/Bill with similar Amount/Date

**UI Experience:**

```
┌─────────────────────────────────────────────────────────────┐
│  💳 PAYMENT RECEIVED                              $500.00   │
│  Dec 10, 2025 · First National Bank                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ✨ We found a match!                                       │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  📄 Invoice #1024                                     │ │
│  │  Customer: Acme Corp                                  │ │
│  │  Amount: $500.00 · Due: Dec 15, 2025                 │ │
│  │  Status: Unpaid                                       │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌────────────────┐  ┌────────────────┐                    │
│  │ ✓ Confirm Match│  │ ✗ Not a Match  │                    │
│  └────────────────┘  └────────────────┘                    │
└─────────────────────────────────────────────────────────────┘
```

**Ledger Impact (hidden from Owner):**
```
DR Accounts Receivable (reduction)  $500.00
   CR Bank Account                  $500.00
```

**Edge Cases:**
- **Partial match:** Bank amount < Invoice amount → Create partial payment
- **Overpayment:** Bank amount > Invoice amount → Offer to apply to other invoices or create credit
- **Multiple matches:** Show list, allow selection

#### Mode 2: CREATE (Categorize)

**Purpose:** Create new expense/income from bank transaction

**Trigger:** No matching invoice/bill found

**UI Experience:**

```
┌─────────────────────────────────────────────────────────────┐
│  💳 PAYMENT MADE                                  -$150.00  │
│  Dec 10, 2025 · OFFICE DEPOT #1234                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Category:    [ Office Supplies          ▼ ]                │
│                                                             │
│  Tax:         [ Standard (17%)           ▼ ]                │
│               ○ Tax Inclusive  ● Tax Exclusive              │
│                                                             │
│  Description: [ Printer paper and ink cartridges    ]       │
│                                                             │
│  [ + Split this transaction ]                               │
│                                                             │
│  ┌────────────────┐  ┌────────────────┐                    │
│  │ ✓ Save         │  │ ⊕ Park for Later│                   │
│  └────────────────┘  └────────────────┘                    │
└─────────────────────────────────────────────────────────────┘
```

**Special Behaviors:**

**Splits:**
```
┌─────────────────────────────────────────────────────────────┐
│  Split Transaction                            Total: $150.00│
├─────────────────────────────────────────────────────────────┤
│  Line 1: [ Office Supplies      ▼ ] [ $100.00 ]   [🗑]     │
│  Line 2: [ Computer Equipment   ▼ ] [  $50.00 ]   [🗑]     │
│                                      ──────────            │
│                           Remaining: $0.00 ✓               │
│                                                             │
│  [ + Add another line ]                                     │
└─────────────────────────────────────────────────────────────┘
```

**Asset Recognition:**
- If category = Fixed Asset, trigger Asset Details Modal:

```
┌─────────────────────────────────────────────────────────────┐
│  📦 New Asset Details                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Asset Name:     [ HP LaserJet Pro Printer         ]        │
│                                                             │
│  Asset Category: [ Office Equipment                ▼ ]      │
│                                                             │
│  Depreciation:   [ Straight-line · 5 years         ▼ ]      │
│                                                             │
│  Purchase Date:  [ Dec 10, 2025                    📅 ]     │
│                                                             │
│  ┌────────────────┐  ┌────────────────┐                    │
│  │ ✓ Create Asset │  │ Cancel         │                    │
│  └────────────────┘  └────────────────┘                    │
└─────────────────────────────────────────────────────────────┘
```

**Ledger Impact (hidden from Owner):**
```
DR Office Supplies Expense    $128.21
DR Tax Receivable (17%)        $21.79
   CR Bank Account            $150.00
```

#### Mode 3: TRANSFER (Internal Movement)

**Purpose:** Move money between accounts (not expense)

**Trigger:** User selects "Transfer" or system detects matching opposite transaction

**UI Experience:**

```
┌─────────────────────────────────────────────────────────────┐
│  💳 TRANSFER                                     -$1,000.00 │
│  Dec 10, 2025 · SAVINGS ACCOUNT TRANSFER                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  This is a transfer to:                                     │
│                                                             │
│  [ Business Savings Account - 4521       ▼ ]                │
│                                                             │
│  ℹ️ No tax codes apply to internal transfers                │
│                                                             │
│  ┌────────────────┐  ┌────────────────┐                    │
│  │ ✓ Confirm      │  │ ✗ Not a Transfer│                   │
│  └────────────────┘  └────────────────┘                    │
└─────────────────────────────────────────────────────────────┘
```

**Constraints:**
- Destination must be Asset or Liability account
- No tax codes allowed
- System matches opposite transaction if found

**Ledger Impact:**
```
DR Savings Account    $1,000.00
   CR Checking Account $1,000.00
```

#### Mode 4: PARK (Safety Valve)

**Purpose:** Defer decision, get help, avoid forced categorization

**Trigger:** User clicks "Ask Accountant" or "Not Sure"

**UI Experience:**

```
┌─────────────────────────────────────────────────────────────┐
│  💳 UNKNOWN CHARGE                                 -$75.00  │
│  Dec 10, 2025 · POS DEBIT 8834729                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🤔 Not sure what this is?                                  │
│                                                             │
│  Add a note for your accountant:                            │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ I don't recognize this charge. Might be a           │   │
│  │ subscription I forgot about?                        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌────────────────┐                                        │
│  │ ⊕ Park & Ask   │                                        │
│  └────────────────┘                                        │
└─────────────────────────────────────────────────────────────┘
```

**Result:**
- Removed from main feed
- Moved to "Clarification Queue"
- No ledger impact
- Notification sent to Accountant role
- Appears in "Needs Attention" widget

### View Modes

**Card View (Default for Owner):**
- One transaction per card
- Large touch targets
- Swipe gestures (mobile)
- AI suggestions prominent

**Grid View (Default for Accountant):**
- High-density data table
- Multi-select for batch operations
- Keyboard navigation
- Inline editing

**Batch Operations (Grid View):**
```
┌─────────────────────────────────────────────────────────────┐
│  ☑ 5 transactions selected                                 │
│                                                             │
│  [ Bulk Categorize ▼ ]  [ Accept All Matches ]  [ Park All ]│
└─────────────────────────────────────────────────────────────┘
```

### Smart Suggestions (AI/Rules)

The system learns from:
1. **Vendor recognition:** "STARBUCKS" → "Meals & Entertainment"
2. **Amount patterns:** Recurring $50 charges → Suggest "Subscription"
3. **User history:** How this user categorized similar items
4. **Bank rules:** User-created rules for automatic categorization

```
┌─────────────────────────────────────────────────────────────┐
│  💡 Smart Suggestion                                        │
│                                                             │
│  Based on your history, this looks like:                    │
│  📁 Office Supplies (85% confidence)                        │
│                                                             │
│  [ ✓ Accept ]  [ Change Category ]                         │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Navigation & Layout

### Global Layout Structure

```
┌─────────────────────────────────────────────────────────────┐
│  HEADER                                                     │
│  [Logo] [Company ▼] [Search] [Notifications] [Mode] [User] │
├──────────────┬──────────────────────────────────────────────┤
│              │                                              │
│  SIDEBAR     │  MAIN CONTENT                                │
│              │                                              │
│  Navigation  │  ┌──────────────────────────────────────┐   │
│  Menu        │  │  Page Header                         │   │
│              │  │  [Title] [Actions]                   │   │
│  ──────────  │  ├──────────────────────────────────────┤   │
│              │  │                                      │   │
│  Quick       │  │  Page Content                        │   │
│  Actions     │  │                                      │   │
│              │  │                                      │   │
│  ──────────  │  │                                      │   │
│              │  │                                      │   │
│  Context     │  │                                      │   │
│  Info        │  │                                      │   │
│              │  └──────────────────────────────────────┘   │
│              │                                              │
├──────────────┴──────────────────────────────────────────────┤
│  FOOTER (minimal)                                           │
│  [Help] [Keyboard Shortcuts] [Status]                       │
└─────────────────────────────────────────────────────────────┘
```

### Sidebar Behavior

- **Collapsible:** Toggle to icon-only mode
- **Contextual:** Shows relevant quick actions based on current page
- **Persistent:** Scroll state maintained
- **Responsive:** Becomes overlay on mobile

### Header Components

| Component | Behavior |
|-----------|----------|
| **Company Switcher** | Dropdown for multi-company users |
| **Global Search** | Search across all entities (Cmd/Ctrl+K) |
| **Notifications** | Bell icon with badge, dropdown panel |
| **Mode Toggle** | Owner/Accountant switch |
| **User Menu** | Profile, settings, logout |

### Breadcrumbs

```
Dashboard > Invoices > INV-1024 > Edit
```

- Always visible below header
- Clickable navigation
- Current page not clickable

---

## 6. Dashboard Experience

### Owner Mode Dashboard

**Layout:**

```
┌─────────────────────────────────────────────────────────────┐
│  Good morning, Ahmed!                     Dec 11, 2025      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  CASH POSITION                                       │   │
│  │  ══════════════════════════════════════════════════ │   │
│  │                                                      │   │
│  │  💰 Total Cash: PKR 1,234,567                       │   │
│  │                                                      │   │
│  │  ┌────────────┐ ┌────────────┐ ┌────────────┐      │   │
│  │  │ Checking   │ │ Savings    │ │ Petty Cash │      │   │
│  │  │ 856,789    │ │ 375,000    │ │ 2,778      │      │   │
│  │  └────────────┘ └────────────┘ └────────────┘      │   │
│  │                                                      │   │
│  │  [⚠️ 12 transactions to review]                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌────────────────────────┐ ┌────────────────────────┐     │
│  │  MONEY COMING IN       │ │  MONEY GOING OUT       │     │
│  │  ════════════════════  │ │  ════════════════════  │     │
│  │                        │ │                        │     │
│  │  This Month: 450,000   │ │  This Month: 320,000   │     │
│  │  ▲ 12% vs last month   │ │  ▼ 5% vs last month    │     │
│  │                        │ │                        │     │
│  │  [📄 3 unpaid invoices]│ │  [📄 5 bills due soon] │     │
│  │  PKR 125,000 overdue   │ │  PKR 89,000 due        │     │
│  └────────────────────────┘ └────────────────────────┘     │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  QUICK ACTIONS                                       │   │
│  │                                                      │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │   │
│  │  │ 📝       │ │ 💰       │ │ 📄       │ │ 📊     │ │   │
│  │  │ Create   │ │ Record   │ │ Enter    │ │ View   │ │   │
│  │  │ Invoice  │ │ Payment  │ │ Expense  │ │ Reports│ │   │
│  │  └──────────┘ └──────────┘ └──────────┘ └────────┘ │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  NEEDS ATTENTION                                     │   │
│  │                                                      │   │
│  │  🔴 Invoice #1021 is 15 days overdue (PKR 45,000)   │   │
│  │  🟡 Bill from ABC Supplier due in 3 days            │   │
│  │  🔵 12 bank transactions to categorize              │   │
│  │  🟢 Tax payment due Dec 15                          │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Accountant Mode Dashboard

**Layout:**

```
┌─────────────────────────────────────────────────────────────┐
│  Company: ABC Trading Co.                    Dec 11, 2025   │
│  Fiscal Year: FY 2025 (Jul 2024 - Jun 2025)                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐        │
│  │ TRIAL BALANCE│ │ UNRECONCILED │ │ PERIOD STATUS│        │
│  │ ✓ Balanced   │ │ 23 items     │ │ Dec: Open    │        │
│  │ DR=CR        │ │ PKR 156,000  │ │ Nov: Closed  │        │
│  └──────────────┘ └──────────────┘ └──────────────┘        │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  KEY METRICS                                         │   │
│  │                                                      │   │
│  │  Revenue YTD    Expenses YTD    Net Income           │   │
│  │  5,234,567      3,456,789       1,777,778            │   │
│  │                                                      │   │
│  │  AR Outstanding AP Outstanding  Cash Position        │   │
│  │  456,789        234,567         1,234,567            │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌────────────────────────┐ ┌────────────────────────┐     │
│  │  RECENT ACTIVITY       │ │  PENDING ITEMS         │     │
│  │                        │ │                        │     │
│  │  • JE-2345 posted      │ │  • 5 parked items      │     │
│  │  • INV-1024 paid       │ │  • 2 draft invoices    │     │
│  │  • Bill-456 entered    │ │  • Bank rec pending    │     │
│  │  • Period Nov locked   │ │  • 3 approvals needed  │     │
│  └────────────────────────┘ └────────────────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

### Dashboard Widgets

| Widget | Owner Mode | Accountant Mode |
|--------|------------|-----------------|
| Cash Position | ✓ (simplified) | ✓ (with bank details) |
| Money In/Out Summary | ✓ | — |
| Revenue/Expense YTD | — | ✓ |
| Unpaid Invoices | ✓ "People who owe you" | ✓ "AR Aging Summary" |
| Bills Due | ✓ "Bills to pay" | ✓ "AP Aging Summary" |
| Bank Transactions | ✓ "Transactions to review" | ✓ "Unreconciled items" |
| Trial Balance Status | — | ✓ |
| Period Status | — | ✓ |
| Quick Actions | ✓ (large buttons) | ✓ (compact) |
| Needs Attention | ✓ (friendly) | ✓ (technical) |

---

## 7. Transaction Interfaces

### Invoice Creation (Owner Mode)

**Progressive Form:**

```
Step 1: Who is this for?
┌─────────────────────────────────────────────────────────────┐
│  Customer: [ 🔍 Search or add new...              ▼ ]       │
└─────────────────────────────────────────────────────────────┘

Step 2: What did you sell?
┌─────────────────────────────────────────────────────────────┐
│  ┌────────────────────────────────────────────────────────┐│
│  │ Item/Service  │ Quantity │  Price   │   Amount        ││
│  ├───────────────┼──────────┼──────────┼─────────────────┤│
│  │ [Web Design ▼]│  [ 1  ]  │ [50,000] │ PKR 50,000      ││
│  │ [+ Add line]  │          │          │                 ││
│  └───────────────┴──────────┴──────────┴─────────────────┘│
│                                                            │
│  Subtotal: PKR 50,000                                      │
│  Tax (17%): PKR 8,500                                      │
│  ─────────────────────                                     │
│  Total: PKR 58,500                                         │
└─────────────────────────────────────────────────────────────┘

Step 3: When is it due?
┌─────────────────────────────────────────────────────────────┐
│  Invoice Date: [ Dec 11, 2025  📅 ]                         │
│  Due Date:     [ Dec 26, 2025  📅 ]  (Net 15)              │
└─────────────────────────────────────────────────────────────┘

[Save as Draft]  [Send Invoice]
```

### Invoice Creation (Accountant Mode)

**Full Form with GL Details:**

```
┌─────────────────────────────────────────────────────────────┐
│  New Invoice                              [Save] [Post]     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Customer:     [ Acme Corp                    ▼ ]           │
│  Invoice #:    [ INV-1025          ] (auto)                │
│  Invoice Date: [ 2025-12-11        📅 ]                     │
│  Due Date:     [ 2025-12-26        📅 ]                     │
│  Terms:        [ Net 15                      ▼ ]            │
│  Currency:     [ PKR                         ▼ ]            │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Item │ Description │ Qty │ Rate │ Account │Tax│Amount│  │
│  ├──────┼─────────────┼─────┼──────┼─────────┼───┼──────┤  │
│  │ ▼    │ Web Design  │  1  │50000 │ 4-1010  │17%│50000 │  │
│  │ ▼    │ Hosting     │ 12  │ 1000 │ 4-1020  │17%│12000 │  │
│  │ +    │             │     │      │         │   │      │  │
│  └──────┴─────────────┴─────┴──────┴─────────┴───┴──────┘  │
│                                                             │
│                              Subtotal: PKR 62,000           │
│                              Tax (17%): PKR 10,540          │
│                              ─────────────────────          │
│                              Total:     PKR 72,540          │
│                                                             │
│  Reference: [________________]  Notes: [________________]   │
│                                                             │
│  Preview Journal Entry:                                     │
│  ┌────────────────────────────────────────────────────┐    │
│  │ DR  1-1200 Accounts Receivable      PKR 72,540    │    │
│  │     CR 4-1010 Consulting Revenue    PKR 50,000    │    │
│  │     CR 4-1020 Hosting Revenue       PKR 12,000    │    │
│  │     CR 2-1100 Sales Tax Payable     PKR 10,540    │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

### Common Patterns Across Transactions

**Status Badges:**

```vue
<Badge variant="draft">Draft</Badge>       <!-- Gray -->
<Badge variant="pending">Pending</Badge>   <!-- Yellow -->
<Badge variant="approved">Approved</Badge> <!-- Green -->
<Badge variant="paid">Paid</Badge>         <!-- Blue -->
<Badge variant="overdue">Overdue</Badge>   <!-- Red -->
<Badge variant="voided">Voided</Badge>     <!-- Strikethrough -->
```

**Amount Display:**

- **Positive (Income):** Green, no sign → `PKR 50,000`
- **Negative (Expense):** Red, with minus → `-PKR 50,000`
- **Zero:** Gray → `PKR 0`
- **Foreign Currency:** Show both → `USD 500 (PKR 139,000)`

**Date Display:**

- **Relative for recent:** "Today", "Yesterday", "3 days ago"
- **Absolute for older:** "Dec 11, 2025"
- **Overdue:** Red with days count → "15 days overdue"

---

## 8. Reporting Experience

### Owner Mode Reports (Question-Based)

| User Question | Report Name | Visualization |
|---------------|-------------|---------------|
| "How much money did I make?" | Profit Summary | Waterfall chart |
| "Who owes me money?" | Unpaid Invoices | Customer list with amounts |
| "Who do I owe money to?" | Unpaid Bills | Vendor list with amounts |
| "Where did my money go?" | Spending by Category | Donut chart with drill-down |
| "Can I pay my bills?" | Cash Forecast | Line chart (30-day projection) |
| "How is my business doing?" | Business Snapshot | Combined metrics card |

**Example: "How much money did I make?"**

```
┌─────────────────────────────────────────────────────────────┐
│  How Much Money Did You Make?                               │
│  December 2025                                   [▼ Month]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│                    WATERFALL CHART                          │
│                                                             │
│  Money In ████████████████████████████ PKR 450,000         │
│           │                                                 │
│  Cost of  │████████ -150,000                               │
│  Goods    │                                                 │
│           │                                                 │
│  Expenses │██████████ -180,000                             │
│           │                                                 │
│           ▼                                                 │
│  Profit   ████████ PKR 120,000                             │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  📈 You made 15% more than last month!                      │
│                                                             │
│  Top Income Sources:              Top Expenses:             │
│  • Web Design: PKR 200,000       • Salaries: PKR 80,000    │
│  • Consulting: PKR 150,000       • Rent: PKR 50,000        │
│  • Hosting: PKR 100,000          • Software: PKR 30,000    │
└─────────────────────────────────────────────────────────────┘
```

### Accountant Mode Reports (Standard Names)

**Financial Statements:**
- Income Statement (P&L)
- Balance Sheet
- Cash Flow Statement
- Statement of Changes in Equity

**Ledger Reports:**
- General Ledger
- Trial Balance
- Journal Report
- Account Transactions

**Aging Reports:**
- AR Aging Summary
- AR Aging Detail
- AP Aging Summary
- AP Aging Detail

**Tax Reports:**
- Tax Summary by Code
- Tax Liability Report
- Input Tax Report
- Tax Filing Report

**Report Features (Accountant Mode):**
- Date range picker
- Comparison periods (YoY, MoM)
- Account filtering
- Drill-down to transactions
- Export (PDF, Excel, CSV)
- Scheduled reports (email)
- Custom report builder

---

## 9. Safety Nets & Error Recovery

### The Balance Explainer Widget

**Location:** Dashboard, next to bank balance

**States:**

**Balanced (Green):**
```
┌─────────────────────────────────────────────────────────────┐
│  ✓ Bank Balance Matches                                     │
│  System: PKR 1,234,567                                      │
│  Bank:   PKR 1,234,567                                      │
└─────────────────────────────────────────────────────────────┘
```

**Unbalanced (Red/Warning):**
```
┌─────────────────────────────────────────────────────────────┐
│  ⚠️ Balance Difference: PKR 500                             │
│  ───────────────────────────────────                        │
│                                                             │
│  System Ledger:  PKR 1,234,567                              │
│  Bank Feed:      PKR 1,235,067                              │
│  Difference:     PKR 500                                    │
│                                                             │
│  Why the difference?                                        │
│                                                             │
│  📌 2 Unreviewed transactions    PKR 200                   │
│  📌 1 Future-dated payment       PKR 300                   │
│                                                             │
│  [Review Transactions]                                      │
└─────────────────────────────────────────────────────────────┘
```

### Version History (Error Recovery)

**Trigger:** Click "Edited [Date]" badge on any transaction

**UI:**

```
┌─────────────────────────────────────────────────────────────┐
│  Version History: Invoice #1024                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ○ Current Version                          Dec 11, 2025    │
│  │ Amount: PKR 58,500                                       │
│  │ Customer: Acme Corp                                      │
│  │ Changed by: Ahmed                                        │
│  │                                                          │
│  ○ Previous Version (Voided)                Dec 10, 2025    │
│  │ Amount: PKR 50,000 ← Changed                            │
│  │ Customer: Acme Corp                                      │
│  │ Changed by: Ahmed                                        │
│  │ Reason: "Forgot to add hosting charges"                  │
│  │                                                          │
│  ○ Original Version                         Dec 9, 2025     │
│    Created by: Ahmed                                        │
│                                                             │
│  [View Journal Entries]  (Accountant Mode only)             │
└─────────────────────────────────────────────────────────────┘
```

**Key Principles:**
- Never show reversal entries in main transaction lists
- Always explain why something changed
- Show who made changes and when
- Allow comparison between versions
- In Accountant Mode, show underlying journal entries

### Undo & Confirmation Patterns

**Destructive Actions:**

```
┌─────────────────────────────────────────────────────────────┐
│  ⚠️ Void Invoice #1024?                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  This will:                                                 │
│  • Mark the invoice as voided                               │
│  • Create a reversing entry in the ledger                   │
│  • Cannot be undone                                         │
│                                                             │
│  Amount: PKR 58,500                                         │
│  Customer: Acme Corp                                        │
│                                                             │
│  Type "VOID" to confirm: [____________]                     │
│                                                             │
│  [Cancel]  [Void Invoice]                                   │
└─────────────────────────────────────────────────────────────┘
```

**Soft Confirmation (Sonner Toast with Undo):**

```
┌─────────────────────────────────────────────────────────────┐
│  ✓ Invoice sent to ahmed@acme.com              [Undo] [×]   │
└─────────────────────────────────────────────────────────────┘
```

### Period Lock Protection

**Attempting action in locked period:**

```
┌─────────────────────────────────────────────────────────────┐
│  🔒 Period Locked                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Cannot post to November 2025                               │
│  This period was locked on Dec 1, 2025                      │
│                                                             │
│  Options:                                                   │
│  • Change the date to December 2025                         │
│  • Ask an admin to unlock the period                        │
│                                                             │
│  [Change Date]  [Cancel]                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 10. Onboarding Experience

### Philosophy
> "Don't ask for Chart of Accounts. Ask about their business."

### Onboarding Flow

**Step 1: Business Type**

```
┌─────────────────────────────────────────────────────────────┐
│  What does your business do?                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌────────────────┐  ┌────────────────┐                    │
│  │  🛠️            │  │  📦            │                    │
│  │  Services      │  │  Products      │                    │
│  │                │  │                │                    │
│  │  Consulting,   │  │  Retail,       │                    │
│  │  freelancing,  │  │  wholesale,    │                    │
│  │  professional  │  │  manufacturing │                    │
│  └────────────────┘  └────────────────┘                    │
│                                                             │
│  ┌────────────────┐                                        │
│  │  🔄            │                                        │
│  │  Both          │                                        │
│  │                │                                        │
│  │  Mix of        │                                        │
│  │  services and  │                                        │
│  │  products      │                                        │
│  └────────────────┘                                        │
└─────────────────────────────────────────────────────────────┘
```

**Impact:**
- Services → Hides Inventory, COGS
- Products → Enables Inventory tracking
- Both → Full feature set

**Step 2: Tax Setup**

```
┌─────────────────────────────────────────────────────────────┐
│  Do you collect sales tax?                                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ○ Yes, I'm registered for sales tax                        │
│    → Which jurisdiction? [Pakistan - Federal ▼]             │
│    → Tax Registration #: [_______________]                  │
│                                                             │
│  ○ No, I'm not required to collect tax                      │
│                                                             │
│  ○ I'm not sure                                             │
│    → We'll set up tax codes, you can configure later        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Impact:**
- Auto-provisions appropriate tax codes
- Sets up tax reports
- Configures invoice tax display

**Step 3: Connect Bank (Critical)**

```
┌─────────────────────────────────────────────────────────────┐
│  Connect your bank account                                  │
│  This is where the magic happens ✨                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │  🏦 Connect via Plaid                               │    │
│  │  Automatic, secure bank sync                        │    │
│  └────────────────────────────────────────────────────┘    │
│                                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │  📄 Upload bank statement                           │    │
│  │  CSV or PDF from your bank                          │    │
│  └────────────────────────────────────────────────────┘    │
│                                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │  ⏭️ Skip for now                                    │    │
│  │  You can connect later                              │    │
│  └────────────────────────────────────────────────────┘    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Step 4: Fiscal Year**

```
┌─────────────────────────────────────────────────────────────┐
│  When does your fiscal year end?                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ○ December 31 (Calendar year)                              │
│  ○ June 30 (Pakistan standard)                              │
│  ○ Other: [___________]                                     │
│                                                             │
│  Current fiscal year: July 1, 2024 - June 30, 2025         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Step 5: Welcome Dashboard**

```
┌─────────────────────────────────────────────────────────────┐
│  🎉 You're all set!                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Here's what to do next:                                    │
│                                                             │
│  ☐ Review your first bank transactions                     │
│  ☐ Create your first invoice                               │
│  ☐ Add your team members                                   │
│  ☐ Set up your tax rates                                   │
│                                                             │
│  [Go to Dashboard]                                          │
│                                                             │
│  💡 Tip: The app learns from you! The more you categorize, │
│     the smarter the suggestions become.                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Post-Onboarding Guidance

**Empty States:**

```
┌─────────────────────────────────────────────────────────────┐
│  No invoices yet                                            │
│                                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │       📝                                            │    │
│  │                                                     │    │
│  │  Create your first invoice                          │    │
│  │  Get paid faster with professional invoices         │    │
│  │                                                     │    │
│  │  [Create Invoice]                                   │    │
│  └────────────────────────────────────────────────────┘    │
│                                                             │
│  💡 Tip: You can also send invoices from your phone!       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 11. Mobile Strategy

### Philosophy
> "Triage and Capture, not Full Accounting"

Mobile is a companion app, not a replacement for desktop.

### Core Mobile Features

**1. Swipe-to-Categorize (Bank Feed)**

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  ← PARK                                      CONFIRM →      │
│                                                             │
│         ┌─────────────────────────────────────┐            │
│         │                                     │            │
│         │  OFFICE DEPOT                       │            │
│         │  -$150.00                           │            │
│         │  Dec 10, 2025                       │            │
│         │                                     │            │
│         │  💡 Office Supplies (85%)           │            │
│         │                                     │            │
│         │  ↑ Tap for details                  │            │
│         │                                     │            │
│         └─────────────────────────────────────┘            │
│                                                             │
│  ◀──────────────────────────────────────────────────────▶  │
│                                                             │
│  12 remaining                                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Gestures:**
- **Swipe Right:** Accept suggestion / Confirm match
- **Swipe Left:** Park for later
- **Swipe Up / Tap:** Expand details, change category
- **Swipe Down:** Skip (next card)

**2. Receipt Snap**

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │             [ Camera Viewfinder ]                   │   │
│  │                                                     │   │
│  │           Position receipt in frame                 │   │
│  │                                                     │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│                        [ 📸 ]                               │
│                                                             │
│  💡 We'll extract the amount and vendor automatically       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**After Capture:**

```
┌─────────────────────────────────────────────────────────────┐
│  Receipt Captured ✓                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📄 [Receipt Image Thumbnail]                               │
│                                                             │
│  Detected:                                                  │
│  Vendor: Office Depot                                       │
│  Amount: $150.00                                            │
│  Date: Dec 10, 2025                                         │
│                                                             │
│  Category: [ Office Supplies           ▼ ]                  │
│                                                             │
│  [Save as Pending]  [Match to Bank Transaction]            │
│                                                             │
│  ℹ️ We'll match this to your bank feed when it arrives     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**3. Quick Invoice**

```
┌─────────────────────────────────────────────────────────────┐
│  Quick Invoice                                    [ × ]     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Customer: [ 🔍 Select customer...              ]           │
│                                                             │
│  Amount:   [ PKR 50,000                         ]           │
│                                                             │
│  For:      [ Web Design Services                ]           │
│                                                             │
│  Due:      [ Net 15                             ▼ ]         │
│                                                             │
│  [Preview]  [Send Invoice]                                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**4. Dashboard Glance**

```
┌─────────────────────────────────────────────────────────────┐
│  Good morning, Ahmed                                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  💰 Cash: PKR 1,234,567                                    │
│                                                             │
│  ┌────────────────────┐ ┌────────────────────┐             │
│  │ 📥 Money In        │ │ 📤 Money Out       │             │
│  │ PKR 450,000       │ │ PKR 320,000       │             │
│  │ This month        │ │ This month        │             │
│  └────────────────────┘ └────────────────────┘             │
│                                                             │
│  ⚠️ 3 items need attention                                 │
│  • 12 transactions to review                               │
│  • Invoice #1021 overdue                                   │
│  • Bill due tomorrow                                       │
│                                                             │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐                      │
│  │ 📝   │ │ 📸   │ │ 💳   │ │ 📊   │                      │
│  │Invoice│ │Receipt│ │ Feed │ │Reports│                     │
│  └──────┘ └──────┘ └──────┘ └──────┘                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Mobile-Specific Patterns

**Touch Targets:**
- Minimum 44x44 pixels
- Adequate spacing between targets
- Large buttons for primary actions

**Offline Capability:**
- Receipt capture works offline
- Syncs when connection restored
- Clear offline indicator

**Push Notifications:**
- Invoice paid
- Bill due soon
- Bank transactions need review
- Payment received

---

## 12. Permissions & Role-Based UX

### Role Definitions

| Role | Description | Primary Use Case |
|------|-------------|------------------|
| **Owner** | Full business access | Daily operations, oversight |
| **Admin** | Full access + settings | Configuration, user management |
| **Accountant** | Full ledger access | Reconciliation, adjustments, tax |
| **Contributor** | Create-only access | Data entry, invoicing |
| **Viewer** | Read-only access | Stakeholders, investors |

### Permission Matrix

| Action | Owner | Admin | Accountant | Contributor | Viewer |
|--------|:-----:|:-----:|:----------:|:-----------:|:------:|
| View Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ |
| View Reports | ✓ | ✓ | ✓ | — | ✓ |
| Create Invoice | ✓ | ✓ | ✓ | ✓ | — |
| Approve Invoice | ✓ | ✓ | ✓ | — | — |
| View Bank Feed | ✓ | ✓ | ✓ | — | — |
| Categorize Transactions | ✓ | ✓ | ✓ | — | — |
| Create Journal Entry | — | ✓ | ✓ | — | — |
| Reconcile Bank | — | ✓ | ✓ | — | — |
| Close Period | — | ✓ | ✓ | — | — |
| Unlock Period | — | ✓ | — | — | — |
| Manage Users | — | ✓ | — | — | — |
| Company Settings | ✓ | ✓ | — | — | — |
| Delete Company | — | ✓ | — | — | — |

### Role-Based UI Adaptations

**Hidden Elements:**
- Menu items the user can't access are hidden, not disabled
- Actions they can't perform don't appear
- Sensitive data (bank details) hidden from Contributors

**Graceful Degradation:**
- If user lacks permission for an action, show explanation
- Suggest who can help (e.g., "Ask an Admin to...")

**Mode Restrictions:**
- Contributors see Owner Mode only (no toggle)
- Accountant Mode available to: Owner, Admin, Accountant
- Viewer sees simplified read-only version

---

## 13. Interaction Patterns

### Forms

**Progressive Disclosure:**
- Show required fields first
- "Show more options" for advanced settings
- Inline help text, not modals

**Validation:**
- Real-time validation as user types
- Clear error messages next to fields
- Block submission until valid

**Smart Defaults:**
- Pre-fill from customer/vendor defaults
- Remember last-used values
- Calculate dates from terms

### Tables & Grids

**Sorting:**
- Click column header to sort
- Visual indicator for sort direction
- Remember sort preference

**Filtering:**
- Filter bar above table
- Quick filters (status badges)
- Advanced filters in slide-out panel

**Pagination:**
- Show item count
- Page size selector (25, 50, 100)
- Keyboard navigation

**Selection:**
- Checkbox column for bulk actions
- "Select all" header checkbox
- Bulk action bar when items selected

### Modals & Dialogs

**Usage:**
- Confirmation dialogs for destructive actions
- Quick-add forms (new customer inline)
- Detail views that don't warrant full page

**Behavior:**
- Click outside to close (non-destructive only)
- Escape key to close
- Focus trap within modal
- Return focus on close

### Keyboard Shortcuts (Accountant Mode)

| Shortcut | Action |
|----------|--------|
| `Cmd/Ctrl + K` | Global search |
| `Cmd/Ctrl + N` | New (context-aware) |
| `Cmd/Ctrl + J` | New Journal Entry |
| `Cmd/Ctrl + I` | New Invoice |
| `Cmd/Ctrl + B` | New Bill |
| `Cmd/Ctrl + S` | Save |
| `Cmd/Ctrl + Enter` | Save & Close |
| `Escape` | Cancel / Close |
| `?` | Show shortcuts help |

### Loading States

**Skeleton Screens:**
- Show layout structure while loading
- Animate shimmer effect
- Avoid spinners for < 300ms

**Progress Indicators:**
- Determinate for known duration (file upload)
- Indeterminate for unknown duration (API call)
- Show what's happening ("Saving invoice...")

### Empty States

**First-Time:**
- Illustration + explanation
- Clear call-to-action
- Help link

**No Results:**
- "No matches found"
- Suggestion to broaden search
- Clear filters button

**No Data:**
- Explain what would appear here
- How to add data
- Example if helpful

---

## 14. Language & Terminology

### Mode-Specific Terminology

| Concept | Owner Mode | Accountant Mode |
|---------|------------|-----------------|
| Income | Money In | Revenue |
| Expenses | Money Out | Expenses |
| Categories | Categories | GL Accounts |
| Unpaid Invoices | People who owe you | AR Outstanding |
| Unpaid Bills | Bills to pay | AP Outstanding |
| Bank Feed | Transactions to review | Unreconciled items |
| Profit | Money you made | Net Income |
| Cash | Cash on hand | Cash & Equivalents |

### Tone & Voice

**Owner Mode:**
- Conversational, friendly
- First person ("Your cash balance")
- Encouraging ("Great job keeping up!")
- Action-oriented ("Record a sale")

**Accountant Mode:**
- Professional, precise
- Third person ("Company cash balance")
- Neutral, factual
- Technical accuracy prioritized

### Error Messages

**Do:**
- Explain what went wrong
- Suggest how to fix it
- Use plain language

**Don't:**
- Show error codes
- Blame the user
- Use technical jargon

**Examples:**

Bad: "Error 422: Validation failed for field invoice_date"

Good: "The invoice date can't be in a closed period. Change the date to December 2025 or later."

Bad: "Transaction failed"

Good: "Couldn't save the invoice. Check your internet connection and try again."

### Number Formatting

**Currency:**
- Use company locale settings
- Thousands separator: 1,234,567
- Decimal: .00
- Symbol placement: PKR 1,234.00

**Dates:**
- Short: Dec 11, 2025
- Long: December 11, 2025
- Relative: Today, Yesterday, 3 days ago

**Percentages:**
- One decimal: 17.5%
- No decimal for whole: 17%

### Implementation: useLexicon Composable

**All mode-specific terminology MUST use the lexicon system.** Never hardcode mode-specific strings.

**Files:**
- Dictionary: `resources/js/lib/lexicon.ts`
- Composable: `resources/js/composables/useLexicon.ts`

**Basic Usage:**

```vue
<script setup lang="ts">
import { useLexicon } from '@/composables/useLexicon'

const { t, tpl } = useLexicon()
</script>

<template>
  <!-- Simple term -->
  <h1>{{ t('moneyIn') }}</h1>
  <!-- Owner: "Money In", Accountant: "Revenue" -->

  <!-- Templated term with interpolation -->
  <p>{{ tpl('transactionsToReviewCount', { count: 12 }) }}</p>
  <!-- Owner: "12 transactions to review", Accountant: "12 unreconciled transactions" -->

  <!-- Categories/Accounts -->
  <label>{{ t('category') }}</label>
  <!-- Owner: "Category", Accountant: "Account" -->
</template>
```

**API Reference:**

```typescript
const {
  t,                // (key, overrideMode?) => string
  tpl,              // (key, params, overrideMode?) => string
  both,             // (key) => { owner, accountant } | null
  has,              // (key) => boolean
  currentMode,      // Ref<'owner' | 'accountant'>
  isAccountantMode, // ComputedRef<boolean>
} = useLexicon()
```

**Available Term Categories:**

| Category | Keys | Example |
|----------|------|---------|
| `coreTerms` | moneyIn, moneyOut, profit, cash, category | General financial concepts |
| `receivablesTerms` | unpaidInvoices, whoOwesYou, arAging | AR/Customer terms |
| `payablesTerms` | unpaidBills, whoYouOwe, apAging | AP/Vendor terms |
| `bankingTerms` | bankFeed, transactionsToReview, reconcile | Banking terms |
| `reportTerms` | profitAndLoss, balanceSheet, cashFlow | Report names |
| `navigationTerms` | dashboard, accounting, receivables | Nav items |
| `statusTerms` | draft, approved, posted, voided | Status badges |
| `dashboardTerms` | cashPosition, needsAttention | Dashboard widgets |
| `emptyStateTerms` | noInvoices, noTransactions | Empty state text |
| `helpTerms` | invoiceDateHelp, categoryHelp | Tooltips/help |
| `templateTerms` | transactionsToReviewCount, invoicePaid | Interpolated messages |

**Adding New Terms:**

```typescript
// In resources/js/lib/lexicon.ts

// 1. Add to appropriate category
export const receivablesTerms: TermDictionary = {
  // ... existing terms

  // Add new term
  creditNoteApplied: {
    owner: 'Credit applied to invoice',
    accountant: 'Credit Note Application',
  },
}

// 2. For templated terms, add to templateTerms
export const templateTerms: TermDictionary = {
  // ... existing terms

  creditAppliedAmount: {
    owner: '{amount} credit applied',
    accountant: 'Credit applied: {amount}',
  },
}
```

**Component Examples:**

```vue
<!-- Navigation Item -->
<SidebarItem :label="t('receivables')" icon="ArrowDownLeft" />

<!-- Page Heading -->
<h1>{{ t('unpaidInvoices') }}</h1>
<p class="text-muted-foreground">{{ t('whoOwesYou') }}</p>

<!-- Empty State -->
<EmptyState
  :title="t('noTransactions')"
  :description="t('noTransactionsDesc')"
/>

<!-- Status Badge -->
<Badge>{{ t(invoice.status) }}</Badge>

<!-- Dashboard Widget -->
<Card>
  <CardHeader>
    <CardTitle>{{ t('cashPosition') }}</CardTitle>
  </CardHeader>
  <CardContent>
    <p>{{ tpl('profitThisMonth', { amount: formatCurrency(profit) }) }}</p>
  </CardContent>
</Card>

<!-- Tooltip/Help Text -->
<Label>
  {{ t('category') }}
  <TooltipProvider>
    <Tooltip>
      <TooltipTrigger><InfoIcon /></TooltipTrigger>
      <TooltipContent>{{ t('categoryHelp') }}</TooltipContent>
    </Tooltip>
  </TooltipProvider>
</Label>
```

**Guidelines:**

1. **Always use `t()` for mode-varying text** - Even if it seems like a one-off, add it to the lexicon
2. **Use `tpl()` for dynamic content** - Messages with counts, amounts, names
3. **Check `has(key)` for dynamic keys** - When key comes from data, verify it exists
4. **Never hardcode mode checks** - Don't do `isAccountantMode ? 'Revenue' : 'Money In'`
5. **Keep terms concise** - Max 60 chars for toasts, labels
6. **Document new terms** - Update this section when adding categories

**Where NOT to use lexicon:**

- Static content that doesn't vary by mode (company name, page titles like "Settings")
- Technical identifiers shown to all users (invoice numbers, dates)
- Data from backend (customer names, amounts)

---

## 15. Visual Design Principles

### Design System Foundation

**Based on:** Shadcn/Vue components
**Theme:** Light/Dark mode support
**Spacing:** 4px base unit (4, 8, 12, 16, 24, 32, 48, 64)

### Color Usage

**Semantic Colors:**

| Purpose | Color | Usage |
|---------|-------|-------|
| Primary | Brand Blue | Primary actions, links |
| Success | Green | Positive amounts, confirmations |
| Danger | Red | Negative amounts, errors, destructive |
| Warning | Yellow/Orange | Attention needed, overdue |
| Info | Light Blue | Informational, tips |
| Neutral | Gray | Borders, disabled, secondary |

**Amount Colors:**
- Income/Positive: Green (`text-green-600`)
- Expense/Negative: Red (`text-red-600`)
- Zero/Neutral: Gray (`text-gray-500`)

### Typography

**Font Family:** System fonts (Inter fallback)

**Scale:**
- Display: 36px / 44px line-height
- H1: 30px / 36px
- H2: 24px / 32px
- H3: 20px / 28px
- H4: 16px / 24px
- Body: 14px / 20px
- Small: 12px / 16px

**Weights:**
- Regular (400): Body text
- Medium (500): Labels, small headings
- Semibold (600): Headings, emphasis
- Bold (700): Key metrics, totals

### Component Patterns

**Cards:**
- Used for grouped content
- Subtle shadow, rounded corners
- Hover state for interactive cards

**Badges:**
- Status indicators
- Pill shape, small text
- Color-coded by status

**Buttons:**
- Primary: Filled, brand color
- Secondary: Outlined
- Ghost: Text only
- Destructive: Red fill/outline

**Inputs:**
- Clear labels above
- Placeholder text for hints
- Error state with red border + message
- Focus ring on active

### Responsive Breakpoints

| Breakpoint | Width | Layout |
|------------|-------|--------|
| Mobile | < 640px | Single column, bottom nav |
| Tablet | 640-1024px | Collapsible sidebar |
| Desktop | 1024-1440px | Full sidebar, standard |
| Wide | > 1440px | Full sidebar, extra spacing |

### Animation & Motion

**Principles:**
- Subtle, purposeful
- 150-300ms duration
- Ease-out for entrances
- Ease-in for exits

**Usage:**
- Page transitions: Fade + slide
- Modal: Fade + scale
- Dropdown: Fade + slide down
- Toast: Slide in from right
- Skeleton: Shimmer effect

---

## Appendix A: Screen Inventory

### Owner Mode Screens

| Screen | Purpose | Key Components |
|--------|---------|----------------|
| Dashboard | Overview & quick actions | Cash widget, Money In/Out, Actions, Alerts |
| Bank Feed | Transaction resolution | Card/Grid view, Resolution modes |
| Invoices List | View all invoices | Table, filters, status badges |
| Invoice Create | Create new invoice | Progressive form, line items |
| Invoice View | View invoice details | Header, lines, actions, history |
| Customers List | View all customers | Table, search, quick add |
| Customer View | Customer details | Info card, transactions, balance |
| Bills List | View all bills | Table, filters, status |
| Bill Create | Enter vendor bill | Form, line items |
| Expenses | Quick expense entry | Simplified form |
| Reports | Question-based reports | Report cards, visualizations |

### Accountant Mode Screens

| Screen | Purpose | Key Components |
|--------|---------|----------------|
| Dashboard | Metrics & status | Trial balance, period status, alerts |
| Chart of Accounts | Manage COA | Tree view, account types |
| Journal Entries | Manual entries | Balanced entry form, DR/CR |
| General Ledger | Ledger report | Account transactions, drill-down |
| Bank Reconciliation | Reconcile accounts | Matching interface, adjustments |
| Period Management | Fiscal periods | Open/close, lock |
| Tax Configuration | Tax setup | Codes, rates, jurisdictions |
| All AR/AP Screens | Full ledger access | Extended columns, journal links |

---

## Appendix B: Implementation Checklist

### Phase 1: Core Engine (Backend)
- [ ] Double-entry ledger with immutability
- [ ] Chart of Accounts management
- [ ] Period locking system
- [ ] Multi-currency support with FX
- [ ] Audit logging

### Phase 2: Resolution Engine (Backend + API)
- [ ] Bank feed ingestion (CSV, API)
- [ ] Match detection algorithm
- [ ] Categorization rules engine
- [ ] Park/queue system
- [ ] Split transaction support

### Phase 3: Owner UI (Frontend)
- [x] Dashboard with widgets
- [x] Bank feed card view
- [x] Resolution interface (Match/Create/Transfer/Park)
- [x] Invoice creation (simplified)
- [x] Bill entry (simplified)
- [ ] Question-based reports

### Phase 4: Safety Nets
- [x] Balance Explainer widget
- [ ] Version history UI
- [ ] Period lock warnings
- [ ] Undo/confirmation patterns
- [ ] Error recovery flows

### Phase 5: Accountant Mode
- [x] Mode toggle
- [ ] High-density grids
- [ ] Journal entry screen
- [ ] Bank reconciliation workflow
- [ ] Full COA management
- [ ] Period management
- [ ] Standard financial reports

### Phase 6: Mobile
- [ ] Swipe-to-categorize
- [ ] Receipt capture + OCR
- [ ] Quick invoice
- [ ] Dashboard glance
- [ ] Push notifications

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-11 | AI-Assisted | Initial version |

---

## Related Documents

- `docs/ui-screen-specifications.md` - Technical field specs for each screen
- `docs/contracts/acct-schema.md` - Accounting schema contract
- `AI_PROMPTS/toast.md` - Toast notification implementation
- `AI_PROMPTS/FRONTEND_REMEDIATION.md` - Vue component standards
- `CLAUDE.md` - Development standards hub

---

**End of Frontend Experience Contract**
