# Haasib Command System — Unified Specification v2

> **Philosophy**: The command palette is Robin to the GUI's Batman. It accelerates power users while gently teaching novices. Every keystroke should feel intentional, every response immediate.

---

## Design Decisions (Settled)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Locale** | Configurable per company | Date format (DD/MM vs MM/DD), number format (1,000.00 vs 1.000,00) |
| **Currency** | Multi-currency per company | Each company can have base currency + transaction currencies |
| **Offline/Network failure** | Toast notification + retry queue | "Connection lost. Command queued. Will retry when online." |
| **Mobile support** | No | Palette is desktop-only. Mobile users get GUI only. Hide Cmd+K hints on touch devices. |
| **Terminal library** | jQuery Terminal | Purpose-built for command interpreters, not real shell access. Batteries included: autocomplete, history, formatting. |
| **Palette architecture** | Hybrid | Collapsed: lightweight Vue input. Expanded: jQuery Terminal for full experience. Both share same parser/API. |

---

## Table of Contents

1. [Design Principles](#1-design-principles)
2. [Architecture Overview](#2-architecture-overview)
3. [Grammar Specification](#3-grammar-specification)
4. [MVP Scope & Phases](#4-mvp-scope--phases)
5. [Discoverability System](#5-discoverability-system)
6. [Parser & Autocomplete](#6-parser--autocomplete)
7. [Inline Help System](#7-inline-help-system)
8. [Undo & Confirmation](#8-undo--confirmation)
9. [Onboarding Strategy](#9-onboarding-strategy)
10. [UI/UX Specification](#10-uiux-specification)
11. [Backend Contract](#11-backend-contract)
12. [Performance Targets](#12-performance-targets)
13. [Testing Strategy](#13-testing-strategy)
14. [Rollout Milestones](#14-rollout-milestones)
15. [Command Reference](#15-command-reference)

---

## 1. Design Principles

### 1.1 Non-Negotiables

| Principle | Rationale |
|-----------|-----------|
| **GUI parity** | Every GUI feature reachable from palette. No hidden functionality. |
| **One grammar** | Single, learnable pattern. No exceptions. |
| **Forgiveness over precision** | Multiple inputs → same outcome. Never punish typos. |
| **Show, don't lecture** | Inline hints, not documentation walls. |
| **Speed is UX** | < 60ms to suggestions, < 300ms to commit. Non-negotiable. |
| **Safe by default** | Destructive actions require confirmation. Always. |

### 1.2 User Mental Model

```
"I type what I want. It figures out the rest."
```

The user should never:
- Memorize exact flag names
- Wonder if their input was understood
- Fear making mistakes
- Feel slower than clicking

### 1.3 The Addiction Formula

```
Discoverability → First Success → Habit Formation → Preference
      ↓                ↓               ↓                ↓
  GUI hints      Instant result    History/TAB     Time savings
```

Users don't become "addicted" by seeing the palette. They become addicted through:
1. **Accidental discovery** via GUI hints
2. **Immediate reward** from faster completion
3. **Muscle memory** from consistent grammar
4. **Social proof** from seeing others use it

---

## 2. Architecture Overview

### 2.1 Two Interfaces, One Brain

```
┌─────────────────────────────────────────────────────────────────┐
│                        HAASIB APP                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌──────────────┐              ┌──────────────┐                │
│   │     GUI      │              │   Command    │                │
│   │   (Vue/      │◄────────────►│   Palette    │                │
│   │   Inertia)   │   bidirectional  (xterm.js) │                │
│   └──────────────┘              └──────────────┘                │
│          │                             │                         │
│          │         Same Services       │                         │
│          ▼                             ▼                         │
│   ┌─────────────────────────────────────────────┐               │
│   │              Command Bus                     │               │
│   │   (Laravel Actions + Policies + Tenancy)    │               │
│   └─────────────────────────────────────────────┘               │
│                          │                                       │
│                          ▼                                       │
│   ┌─────────────────────────────────────────────┐               │
│   │              PostgreSQL                      │               │
│   │         (Multi-tenant Data)                 │               │
│   └─────────────────────────────────────────────┘               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Palette Placement

```
┌─────────────────────────────────────────────────────────────────┐
│  🏢 Acme Corp          Dashboard          yasir@acme.com    ⚙️  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│                                                                  │
│                      [ Main GUI Content ]                        │
│                                                                  │
│                                                                  │
│                                                                  │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│ ❯ invoice.create acme 1500                    [▭] [—] [⛶]      │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │ invoice.create  Create new invoice                      │   │
│   │ invoice.list    List invoices [--unpaid] [--overdue]    │   │
│   │ invoice.send    Email invoice to customer               │   │
│   └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘

Legend:
[▭] = Expand to half-screen
[—] = Collapse to thin strip
[⛶] = Fullscreen mode
```

### 2.3 State Persistence

| State | Storage | Lifetime |
|-------|---------|----------|
| Palette size preference | localStorage | Permanent |
| Command history | IndexedDB | 90 days |
| Recent entities | IndexedDB | 30 days |
| Favorites/pins | Server (user prefs) | Permanent |
| Templates | Server (per company) | Permanent |

---

## 3. Grammar Specification

### 3.1 Canonical Pattern

```
<entity>.<verb> [subject] [flags]
```

**This is the ONLY pattern.** Everything else is sugar that resolves to this.

### 3.2 Formal Grammar (BNF)

```bnf
<command>     ::= <entity> "." <verb> <arguments>?
                | <shortcut>
                | <natural>

<entity>      ::= "company" | "user" | "invoice" | "payment"
                | "bill" | "expense" | "customer" | "vendor"
                | "account" | "ledger" | "report"

<verb>        ::= "create" | "list" | "view" | "update" | "delete"
                | "send" | "void" | "assign" | "unassign"
                | "reconcile" | "export"

<arguments>   ::= <subject>? <flags>*

<subject>     ::= <identifier> | <quoted-string> | <number>

<flags>       ::= <long-flag> | <short-flag>
<long-flag>   ::= "--" <flag-name> ("=" <value>)?
<short-flag>  ::= "-" <letter> <value>?

<shortcut>    ::= "inv" | "pay" | "bill" | "exp" | "cust" | "vend"
                  (resolves to entity.list or contextual default)

<natural>     ::= free-form text parsed by intent classifier
```

### 3.3 Resolution Rules

User input is normalized through these steps:

```
Step 1: Shortcut expansion
  "inv"           → "invoice.list"
  "inv new"       → "invoice.create"
  "inv acme"      → "invoice.list --customer=acme"

Step 2: Synonym mapping
  "create invoice" → "invoice.create"
  "new invoice"    → "invoice.create"
  "bill customer"  → "invoice.create"

Step 3: Preposition extraction
  "to acme"       → --customer=acme
  "for 1500"      → --amount=1500
  "as admin"      → --role=admin
  "from jan"      → --from=jan

Step 4: Smart type inference
  "1500"          → --amount=1500 (if verb expects amount)
  "acme"          → --customer=acme (fuzzy match to entity)
  "jan 15"        → --date=2025-01-15 (date parsing)
  "net 30"        → --due=+30days

Step 5: Flag normalization
  "-c acme"       → --customer=acme
  "-a 1500"       → --amount=1500
  "--amt=1500"    → --amount=1500 (alias resolution)
```

### 3.4 Entity-Verb Matrix

| Entity | create | list | view | update | delete | send | void |
|--------|--------|------|------|--------|--------|------|------|
| company | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| user | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| invoice | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| payment | ✓ | ✓ | ✓ | — | ✓ | — | ✓ |
| bill | ✓ | ✓ | ✓ | ✓ | — | — | ✓ |
| expense | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| customer | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| vendor | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| account | ✓ | ✓ | ✓ | ✓ | — | — | — |
| ledger | post | ✓ | ✓ | — | — | — | reverse |
| report | — | ✓ | ✓ | — | — | export | — |

### 3.5 Universal Flags

These work across all applicable commands:

| Flag | Short | Type | Example |
|------|-------|------|---------|
| `--customer` | `-c` | string | `--customer="Acme Corp"` |
| `--vendor` | `-v` | string | `--vendor="Office Depot"` |
| `--amount` | `-a` | money | `--amount=1500` or `-a 1500$` |
| `--date` | `-d` | date | `--date=jan15` or `-d "Jan 15"` |
| `--from` | | date | `--from=2025-01-01` |
| `--to` | | date | `--to=2025-01-31` |
| `--status` | `-s` | enum | `--status=unpaid` |
| `--account` | | string | `--account="Cash"` |
| `--ref` | | string | `--ref=INV-001` |
| `--notes` | `-n` | string | `--notes="Q1 services"` |
| `--draft` | | bool | `--draft` (don't post to GL) |
| `--format` | `-f` | enum | `--format=json` or `csv` or `table` |
| `--help` | `-h` | bool | Show command help |

---

## 4. MVP Scope & Phases

### Phase 1: Foundations (Current)
**Goal**: Core infrastructure + DevOps commands

| Component | Status | Notes |
|-----------|--------|-------|
| Palette shell (expand/collapse/fullscreen) | ✓ | |
| Hotkey binding (Cmd/Ctrl+K) | ✓ | |
| Entity-first guided flow | ✓ | |
| Freeform parsing | ✓ | Basic |
| `company.create\|delete\|assign\|unassign` | ✓ | |
| `user.create\|delete\|update` | ✓ | |
| Idempotency | ✓ | |
| Audit logging | ✓ | |
| Error toasts | ✓ | |

### Phase 2: Accounting Core (Next)
**Goal**: Invoice/Payment/Bill + Reports

| Component | Target | Dependencies |
|-----------|--------|--------------|
| `customer.create\|list\|view\|update` | Week 1 | — |
| `vendor.create\|list\|view\|update` | Week 1 | — |
| `invoice.create\|list\|view\|send\|void` | Week 2-3 | customer |
| `payment.create\|list\|view\|void` | Week 3 | invoice |
| `bill.create\|list\|view\|void` | Week 4 | vendor |
| `expense.create\|list\|view` | Week 4 | account |
| `report.list\|view` (AR aging, AP aging, P&L) | Week 5 | All above |
| GUI discoverability hints | Week 5 | — |

### Phase 3: Power Features
**Goal**: Templates, history, workflows

| Component | Target |
|-----------|--------|
| Command history (↑/↓) | Week 6 |
| Favorites/pins | Week 6 |
| Templates (`template.save\|list\|run`) | Week 7 |
| Batch operations (`invoice.send --status=unpaid`) | Week 8 |
| Export modes (`--format=csv\|json`) | Week 8 |
| Workflows (`workflow.month-end`) | Week 9 |

### Phase 4: Intelligence
**Goal**: Natural language, learning

| Component | Target |
|-----------|--------|
| Natural language queries ("who owes me?") | Week 10 |
| Anomaly warnings | Week 11 |
| Personalized suggestions | Week 12 |

---

## 5. Discoverability System

### 5.1 GUI → Palette Hints

Every significant GUI action should hint at its command equivalent.

**Pattern A: Post-action toast**
```
┌─────────────────────────────────────────┐
│  ✓ Invoice #1042 created                │
│                                         │
│  ⚡ Tip: invoice.create acme 1500       │
│     does this in 2 seconds              │
└─────────────────────────────────────────┘
```

**Pattern B: Button tooltip**
```
┌──────────────────────┐
│  + New Invoice       │  ← hover
├──────────────────────┤
│ Shortcut:            │
│ invoice.create       │
│ or just: inv new     │
└──────────────────────┘
```

**Pattern C: Empty state nudge**
```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│              No unpaid invoices 🎉                          │
│                                                             │
│   Pro tip: Press Cmd+K and type "inv --unpaid"              │
│   to check this anytime                                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Pattern D: Keyboard shortcut badge**
```
┌────────────────────────────────────────┐
│  Invoices                    Cmd+K inv │
├────────────────────────────────────────┤
│  Invoice list here...                  │
└────────────────────────────────────────┘
```

### 5.2 Discovery Frequency Rules

| User Tenure | Hint Frequency | Types Shown |
|-------------|----------------|-------------|
| First week | Every action | All patterns |
| Week 2-4 | 1 in 3 actions | A, C only |
| Month 2+ | 1 in 10 actions | C only |
| Palette user | Never | None (they know) |

Detection: Track `palette_command_count` per user. If > 20, reduce hints.

### 5.3 Discoverability Database

Store hint mappings:

```typescript
// resources/js/discoverability/hints.ts
export const actionHints: Record<string, HintConfig> = {
  'gui.invoice.create': {
    command: 'invoice.create',
    shorthand: 'inv new',
    example: 'inv new acme 1500',
    benefit: 'Create invoices in 2 seconds'
  },
  'gui.invoice.list.unpaid': {
    command: 'invoice.list --unpaid',
    shorthand: 'inv --unpaid',
    benefit: 'Check unpaid invoices instantly'
  },
  'gui.report.aging': {
    command: 'report.aging',
    shorthand: 'aging',
    benefit: 'See who owes you in one command'
  }
}
```

---

## 6. Parser & Autocomplete

### 6.1 Parser Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Input: "inv acme 1500"                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Tokenizer                                                  │
│  ["inv", "acme", "1500"]                                    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Shortcut Expander                                          │
│  "inv" → entity: "invoice", verb: "create" (contextual)     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Entity Matcher (Fuse.js)                                   │
│  "acme" → customer: { id: 42, name: "Acme Corp" }           │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Type Inferencer                                            │
│  "1500" → amount: 1500 (verb expects amount)                │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Output: ParsedCommand                                      │
│  {                                                          │
│    action: "invoice.create",                                │
│    params: { customer_id: 42, amount: 1500 },               │
│    missing: ["due_date"],                                   │
│    confidence: 0.92,                                        │
│    idemKey: "sha256:..."                                    │
│  }                                                          │
└─────────────────────────────────────────────────────────────┘
```

### 6.2 Forgiving Parser Rules

**Rule 1: Order doesn't matter**
```
invoice.create acme 1500
invoice.create 1500 acme
inv acme 1500$
inv 1500 to acme
```
All → `invoice.create --customer=acme --amount=1500`

**Rule 2: Typos are corrected**
```
invocie.create  → invoice.create (Levenshtein ≤ 2)
incoice.craete  → invoice.create
inv creaet      → invoice.create
```

**Rule 3: Synonyms are understood**
```
bill customer acme     → invoice.create --customer=acme
send invoice to acme   → invoice.create --customer=acme
new inv acme           → invoice.create --customer=acme
```

**Rule 4: Amounts are flexible**
```
1500      → 1500.00
1500$     → 1500.00
$1,500    → 1500.00
1.5k      → 1500.00
1500.50   → 1500.50
```

**Rule 5: Dates are flexible**
```
jan 15       → 2025-01-15
jan15        → 2025-01-15
15/1         → 2025-01-15
15-jan       → 2025-01-15
tomorrow     → (computed)
next friday  → (computed)
net 30       → (today + 30 days)
30 days      → (today + 30 days)
eom          → (end of month)
```

### 6.3 Autocomplete Behavior

**Trigger**: After 1 character typed (instant)

**Ranking Algorithm**:
1. Exact prefix match (score: 1.0)
2. Recent commands (score: 0.9, decay by age)
3. Fuzzy match (score: Fuse.js score)
4. Context boost (+0.2 if related to current page)

**Visual Display**:
```
❯ inv
  ┌──────────────────────────────────────────────────────────┐
  │ invoice.create   Create new invoice           ⏱️ recent │
  │ invoice.list     List invoices                          │
  │ invoice.send     Send invoice by email                  │
  │ invoice.void     Void an invoice                        │
  ├──────────────────────────────────────────────────────────┤
  │ Recent: inv acme 2500                         ↵ repeat  │
  └──────────────────────────────────────────────────────────┘
```

**After entity selected**:
```
❯ invoice.create
  ┌──────────────────────────────────────────────────────────┐
  │ Customers:                                               │
  │   acme       Acme Corp            $4,200 outstanding    │
  │   beta       Beta LLC             $0 outstanding        │
  │   gamma      Gamma Industries     $850 outstanding      │
  ├──────────────────────────────────────────────────────────┤
  │ Type customer name or continue with flags               │
  └──────────────────────────────────────────────────────────┘
```

### 6.4 TAB Completion

| Context | TAB behavior |
|---------|--------------|
| Empty input | Show recent commands |
| Partial entity | Complete entity name |
| After entity | Show available verbs |
| After verb | Complete first required param |
| Partial flag | Complete flag name |
| Multiple matches | Cycle through options |
| Single match | Insert and advance cursor |

### 6.5 Autocomplete Data Sources

```typescript
// Built in Web Worker, refreshed on focus
interface AutocompleteIndex {
  commands: CommandDef[]           // Static, from registry
  customers: LightEntity[]         // From API, cached 5min
  vendors: LightEntity[]           // From API, cached 5min
  accounts: LightEntity[]          // From API, cached 5min
  invoices: LightEntity[]          // Recent 100, cached 1min
  history: HistoryEntry[]          // From IndexedDB
}

interface LightEntity {
  id: number
  name: string
  searchText: string               // Denormalized for Fuse
  meta?: Record<string, any>       // e.g., outstanding balance
}
```

---

## 7. Inline Help System

### 7.1 Help Triggers

| Trigger | Response |
|---------|----------|
| `help` or `?` | Show global help overview |
| `help invoice` | Show invoice commands |
| `invoice.create --help` | Show create invoice syntax |
| `invoice.create -h` | Same as above |
| Unknown command | "Did you mean...?" + help |

### 7.2 Help Display Format

**Global help** (`help`):
```
┌─────────────────────────────────────────────────────────────────┐
│ HAASIB COMMAND HELP                                    [?] [×] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ QUICK START                                                     │
│   inv new acme 1500        Create invoice for Acme, $1,500     │
│   inv --unpaid             List unpaid invoices                │
│   pay inv-1042 1500        Record payment for INV-1042         │
│                                                                 │
│ NAVIGATION                                                      │
│   Tab          Autocomplete                                    │
│   ↑/↓          History / suggestions                           │
│   Esc          Close palette                                   │
│   Cmd+Enter    Execute immediately                             │
│                                                                 │
│ MODULES                                                         │
│   invoice      Customer invoices (AR)                          │
│   payment      Received payments                               │
│   bill         Vendor bills (AP)                               │
│   expense      Quick expenses                                  │
│   report       Financial reports                               │
│                                                                 │
│ Type "help <module>" for details                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Module help** (`help invoice`):
```
┌─────────────────────────────────────────────────────────────────┐
│ INVOICE COMMANDS                                       [?] [×] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ invoice.create    Create new invoice                           │
│   inv new acme 1500                                            │
│   invoice.create -c "Acme" -a 1500 --due="net 30"              │
│                                                                 │
│ invoice.list      List invoices                                │
│   inv                     All invoices                         │
│   inv --unpaid            Unpaid only                          │
│   inv --overdue           Past due                             │
│   inv -c acme             For specific customer                │
│                                                                 │
│ invoice.view      View invoice details                         │
│   inv 1042                View INV-1042                        │
│                                                                 │
│ invoice.send      Email invoice                                │
│   inv send 1042           Send INV-1042                        │
│                                                                 │
│ invoice.void      Void invoice                                 │
│   inv void 1042           Void INV-1042 (requires confirm)     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Command help** (`invoice.create --help`):
```
┌─────────────────────────────────────────────────────────────────┐
│ invoice.create — Create new invoice                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ USAGE                                                           │
│   invoice.create <customer> <amount> [options]                 │
│   inv new <customer> <amount> [options]                        │
│                                                                 │
│ REQUIRED                                                        │
│   customer    Customer name or ID                              │
│   amount      Invoice amount                                   │
│                                                                 │
│ OPTIONS                                                         │
│   --due, -d       Due date (default: net 30)                   │
│   --ref           Reference number                             │
│   --notes, -n     Invoice notes                                │
│   --draft         Save without posting to GL                   │
│   --items         Line items (interactive if omitted)          │
│                                                                 │
│ EXAMPLES                                                        │
│   inv new acme 1500                                            │
│   inv new "Acme Corp" 1500 --due="jan 15"                      │
│   invoice.create -c acme -a 1500 -d "net 30" -n "Q1 retainer"  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 7.3 Contextual Hints

While typing, show parsing feedback:

```
❯ inv new acme 150
  ┌────────────────────────────────────────────────────────────┐
  │ invoice.create                                             │
  │   customer: Acme Corp ✓                                    │
  │   amount: $150.00 ✓                                        │
  │   due: (default: net 30)                                   │
  │                                                            │
  │ Press Enter to create, Tab to add options                  │
  └────────────────────────────────────────────────────────────┘
```

When missing required params:

```
❯ inv new acme
  ┌────────────────────────────────────────────────────────────┐
  │ invoice.create                                             │
  │   customer: Acme Corp ✓                                    │
  │   amount: _____ ← required                                 │
  │                                                            │
  │ Add amount: inv new acme 1500                              │
  └────────────────────────────────────────────────────────────┘
```

---

## 8. Undo & Confirmation

### 8.1 Action Classification

| Category | Examples | Behavior |
|----------|----------|----------|
| **Safe** | list, view, report | Execute immediately |
| **Reversible** | create, update | Execute, show undo option |
| **Destructive** | delete, void | Require confirmation |
| **Critical** | company.delete, bulk ops | Require typed confirmation |

### 8.2 Confirmation Flows

**Destructive action** (`invoice.void 1042`):
```
❯ invoice.void 1042
  ┌────────────────────────────────────────────────────────────┐
  │ ⚠️  VOID INVOICE                                           │
  │                                                            │
  │ Invoice: INV-1042                                          │
  │ Customer: Acme Corp                                        │
  │ Amount: $1,500.00                                          │
  │ Status: Unpaid                                             │
  │                                                            │
  │ This will:                                                 │
  │   • Mark invoice as void                                   │
  │   • Reverse GL entries                                     │
  │   • Send void notification to customer                     │
  │                                                            │
  │ [y] Confirm    [n] Cancel    [Enter = Cancel]              │
  └────────────────────────────────────────────────────────────┘
```

**Critical action** (`company.delete acme`):
```
❯ company.delete acme
  ┌────────────────────────────────────────────────────────────┐
  │ 🔴 DELETE COMPANY                                          │
  │                                                            │
  │ Company: Acme Corp                                         │
  │ Data: 1,247 invoices, 892 payments, 3 years of records     │
  │                                                            │
  │ ⚠️  This action is IRREVERSIBLE.                           │
  │ All data for this company will be permanently deleted.     │
  │                                                            │
  │ Type "delete acme" to confirm:                             │
  │ ❯ ________                                                 │
  │                                                            │
  └────────────────────────────────────────────────────────────┘
```

### 8.3 Undo System

**For reversible actions**, show toast with undo:

```
┌─────────────────────────────────────────────────────────────┐
│  ✓ Invoice INV-1043 created for Acme Corp ($1,500)         │
│                                                             │
│  [View] [Undo - 8s]                                        │
└─────────────────────────────────────────────────────────────┘
```

**Undo window**: 10 seconds for most actions, 30 seconds for bulk ops.

**Undo implementation**:
- Store the inverse action with original params
- On undo, execute the inverse within a transaction
- Audit log records both original action and undo

```typescript
interface UndoableResult {
  success: true
  message: string
  data: any
  undo?: {
    action: string
    params: Record<string, any>
    expiresAt: number  // Unix timestamp
  }
}
```

### 8.4 Anomaly Warnings

Before executing, check for anomalies:

```
❯ invoice.create acme 150000
  ┌────────────────────────────────────────────────────────────┐
  │ ⚠️  UNUSUAL AMOUNT                                         │
  │                                                            │
  │ $150,000 is significantly higher than typical invoices     │
  │ for Acme Corp (average: $2,400, max: $8,500).              │
  │                                                            │
  │ [c] Continue anyway    [e] Edit amount    [Esc] Cancel     │
  └────────────────────────────────────────────────────────────┘
```

Anomalies to detect:
- Amount > 10x customer average
- Duplicate invoice (same customer + amount within 7 days)
- Posting to closed period
- Negative amounts (unless explicitly a credit)

---

## 9. Onboarding Strategy

### 9.1 Progressive Disclosure

**Level 0: Pure GUI** (Week 1)
- User doesn't know palette exists
- Subtle hint in corner: `Cmd+K for quick actions`
- No aggressive pushing

**Level 1: Awareness** (After 5 GUI actions)
- Toast after create: "Tip: `inv new acme 1500` does this faster"
- One-time modal: "Meet your command bar" (dismissable, never show again)

**Level 2: First Success** (After first palette use)
- Celebrate: "🎉 You just saved 8 seconds!"
- Suggest: "Try `inv --unpaid` to see unpaid invoices"

**Level 3: Habit Building** (After 10 palette uses)
- Show history: "Your recent commands..."
- Introduce TAB completion
- Suggest favorites

**Level 4: Power User** (After 50 palette uses)
- Introduce templates
- Introduce batch operations
- Reduce/eliminate GUI hints

### 9.2 "Learn as you click" Mode

When enabled (default for first 2 weeks):

Every GUI action shows the equivalent command:

```
┌─────────────────────────────────────────────────────────────┐
│  Creating invoice...                                        │
│  ─────────────────────────────────────────────────────────  │
│  Command equivalent:                                        │
│  invoice.create -c "Acme Corp" -a 1500 --due="2025-02-15"  │
│  ─────────────────────────────────────────────────────────  │
│  [Copy command]  [Don't show these]                        │
└─────────────────────────────────────────────────────────────┘
```

### 9.3 Beginner Mode (Menu-driven)

For users who open palette but don't know what to type:

```
❯ (empty - press Enter for menu)
  ┌────────────────────────────────────────────────────────────┐
  │ WHAT WOULD YOU LIKE TO DO?                      [1-6, ?]  │
  ├────────────────────────────────────────────────────────────┤
  │                                                            │
  │  [1] 📄 Create invoice                                     │
  │  [2] 💰 Record payment                                     │
  │  [3] 📋 View unpaid invoices                               │
  │  [4] 📊 Run a report                                       │
  │  [5] ➕ Add customer or vendor                             │
  │  [6] ⚙️  Settings                                          │
  │                                                            │
  │  [?] Help    [Esc] Close                                   │
  │                                                            │
  │  Or start typing a command...                              │
  └────────────────────────────────────────────────────────────┘
```

Selecting an option shows the command being built:

```
❯ (Selected: Create invoice)
  ┌────────────────────────────────────────────────────────────┐
  │ CREATE INVOICE                                             │
  │ Command: invoice.create                                    │
  ├────────────────────────────────────────────────────────────┤
  │                                                            │
  │ Customer: ________________  (type to search)               │
  │                                                            │
  └────────────────────────────────────────────────────────────┘
```

As they fill fields, show the command building:

```
  │ Command: invoice.create -c "Acme Corp" -a 1500            │
```

---

## 10. UI/UX Specification

### 10.1 Palette States

```
State: COLLAPSED (default)
┌─────────────────────────────────────────────────────────────────┐
│ ❯ Type command or Cmd+K...                       [▭] [—] [⛶] │
└─────────────────────────────────────────────────────────────────┘

State: HALF (expanded)
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│                      [ Main GUI - 50% ]                         │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ ❯ invoice.create acme                            [▭] [—] [⛶] │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ invoice.create                                              │ │
│ │   customer: Acme Corp ✓                                     │ │
│ │   amount: _____ ← required                                  │ │
│ │                                                             │ │
│ │ Suggestions:                                                │ │
│ │   invoice.create acme 1500                                  │ │
│ │   invoice.create acme 2500 --due="net 30"                   │ │
│ └─────────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ History: inv list --unpaid | pay inv-1041 800 | ...        │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

State: FULLSCREEN
┌─────────────────────────────────────────────────────────────────┐
│ 🏢 Acme Textiles │ yasir │ prod               [▭] [—] [⛶] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ❯ invoice.list --unpaid                                        │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ UNPAID INVOICES                               3 of 3 shown │ │
│ ├─────────────────────────────────────────────────────────────┤ │
│ │ INV#     Customer        Amount     Due        Status      │ │
│ │ ──────── ─────────────── ────────── ────────── ──────────  │ │
│ │ 1042     Acme Corp       $1,500.00  Jan 15     ⚠️ Overdue  │ │
│ │ 1043     Beta LLC        $2,200.00  Jan 20     Due in 5d   │ │
│ │ 1044     Gamma Inc       $800.00    Jan 25     Due in 10d  │ │
│ │                                                             │ │
│ │ Total Outstanding: $4,500.00                                │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ [Enter] View  [s] Send  [a] Select all  [/] Filter  [?] Help   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 10.2 Color Semantics

| Element | Color | Hex | Usage |
|---------|-------|-----|-------|
| Success | Green | `#22c55e` | Completed actions, positive balances |
| Warning | Amber | `#f59e0b` | Overdue, anomalies, confirmations |
| Error | Red | `#ef4444` | Failed actions, negative states |
| Info | Blue | `#3b82f6` | Hints, suggestions, links |
| Muted | Gray | `#6b7280` | Secondary text, disabled |
| Selection | Indigo bg | `#4f46e5` | Highlighted row/option |

### 10.3 Keyboard Map

| Key | Context | Action |
|-----|---------|--------|
| `Cmd/Ctrl+K` | Global | Toggle palette |
| `Esc` | Palette | Close / shrink |
| `Enter` | Palette | Execute command / select |
| `Tab` | Palette | Autocomplete |
| `Shift+Tab` | Palette | Previous suggestion |
| `↑` | Palette (empty) | Previous history |
| `↓` | Palette | Next suggestion |
| `Cmd/Ctrl+Enter` | Palette | Execute immediately (skip confirm) |
| `Cmd/Ctrl+Shift+K` | Global | Palette fullscreen |
| `?` | Palette | Show help |
| `/` | List view | Filter |
| `j/k` | List view | Navigate rows |
| `Space` | List view | Toggle selection |
| `a` | List view | Select all |
| `y` | Confirmation | Confirm yes |
| `n` | Confirmation | Confirm no |

### 10.4 Accessibility Requirements

| Requirement | Implementation |
|-------------|----------------|
| Focus trap | Palette captures focus when open |
| Screen reader | `role="dialog"`, `aria-live` for results |
| Focus visible | 2px ring on all interactive elements |
| Motion | Respect `prefers-reduced-motion` |
| Touch targets | Minimum 44×44px for controls |
| Contrast | WCAG AA minimum (4.5:1 for text) |

---

## 11. Backend Contract

### 11.1 Endpoint

```
POST /api/commands

Headers:
  Authorization: Bearer <token> | Session cookie
  X-Action: <entity.verb>
  X-Idempotency-Key: <uuid>
  X-Company-Id: <tenant_id>  (optional, uses active company if omitted)

Body:
  {
    "params": { ... }
  }
```

### 11.2 Response Codes

| Code | Meaning | Body |
|------|---------|------|
| `200` | Success (query) | `{ ok: true, data, meta? }` |
| `201` | Success (mutation) | `{ ok: true, message, data, undo?, redirect? }` |
| `400` | Bad request | `{ ok: false, code: "BAD_REQUEST", message }` |
| `401` | Unauthorized | `{ ok: false, code: "UNAUTHORIZED" }` |
| `403` | Forbidden | `{ ok: false, code: "FORBIDDEN", message }` |
| `404` | Not found | `{ ok: false, code: "NOT_FOUND", message }` |
| `409` | Idempotent replay | `{ ok: false, code: "IDEMPOTENT_REPLAY", original }` |
| `422` | Validation failed | `{ ok: false, code: "VALIDATION", errors: {...} }` |
| `423` | Period locked | `{ ok: false, code: "PERIOD_LOCKED", message }` |
| `500` | Server error | `{ ok: false, code: "SERVER_ERROR" }` |

### 11.3 Command Bus

```php
// config/command-bus.php
return [
    // Foundations
    'company.create'    => \App\Actions\Company\Create::class,
    'company.delete'    => \App\Actions\Company\Delete::class,
    'company.assign'    => \App\Actions\Company\Assign::class,
    'company.unassign'  => \App\Actions\Company\Unassign::class,
    'user.create'       => \App\Actions\User\Create::class,
    'user.delete'       => \App\Actions\User\Delete::class,
    'user.update'       => \App\Actions\User\Update::class,

    // Accounting (Phase 2)
    'customer.create'   => \App\Actions\Customer\Create::class,
    'customer.list'     => \App\Actions\Customer\Index::class,
    'customer.view'     => \App\Actions\Customer\Show::class,
    'customer.update'   => \App\Actions\Customer\Update::class,
    'customer.delete'   => \App\Actions\Customer\Delete::class,

    'invoice.create'    => \App\Actions\Invoice\Create::class,
    'invoice.list'      => \App\Actions\Invoice\Index::class,
    'invoice.view'      => \App\Actions\Invoice\Show::class,
    'invoice.send'      => \App\Actions\Invoice\Send::class,
    'invoice.void'      => \App\Actions\Invoice\Void::class,

    // ... etc
];
```

### 11.4 Action Contract

```php
// app/Actions/Invoice/Create.php

namespace App\Actions\Invoice;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class Create
{
    public function handle(array $params, User $actor): array
    {
        // 1. Authorization
        Gate::forUser($actor)->authorize('invoice.create');

        // 2. Validation
        $validated = validator($params, [
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date|after:today',
            'line_items' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
            'draft' => 'nullable|boolean',
        ])->validate();

        // 3. Business logic
        return DB::transaction(function () use ($validated, $actor) {
            $invoice = Invoice::create([
                'company_id' => tenant_company_id(),
                'customer_id' => $validated['customer_id'],
                'amount' => $validated['amount'],
                'due_date' => $validated['due_date'] ?? now()->addDays(30),
                'status' => $validated['draft'] ?? false ? 'draft' : 'pending',
                'created_by' => $actor->id,
            ]);

            // Post to GL unless draft
            if (!($validated['draft'] ?? false)) {
                $this->postToLedger($invoice);
            }

            return [
                'message' => "Invoice {$invoice->number} created",
                'data' => $invoice->toArray(),
                'redirect' => "/invoices/{$invoice->id}",
                'undo' => [
                    'action' => 'invoice.void',
                    'params' => ['invoice_id' => $invoice->id],
                    'expiresAt' => now()->addSeconds(10)->unix(),
                ],
            ];
        });
    }
}
```

---

## 12. Performance Targets

### 12.1 Benchmarks

| Metric | Target | Measurement |
|--------|--------|-------------|
| Keypress → suggestions | < 60ms p95 | Client-side timer |
| Enter → optimistic UI | < 50ms | Client-side timer |
| Enter → server confirmed | < 300ms p50, < 500ms p95 | Round-trip |
| Fuse index build | < 200ms | Worker completion |
| History lookup | < 10ms | IndexedDB read |

### 12.2 Optimization Strategies

**Client-side**:
- Web Worker for Fuse.js index building
- Debounce input (30ms) before searching
- Virtual list for large result sets
- Preload entity catalogs on palette focus
- Cache hot entities in memory (customers, accounts)

**Server-side**:
- Lightweight entity endpoints (id, name, search_text only)
- Redis cache for entity catalogs (5min TTL)
- Async audit logging (queue)
- Connection pooling for PostgreSQL

### 12.3 Monitoring

```typescript
// Emit performance events
window.dispatchEvent(new CustomEvent('haasib:perf', {
  detail: {
    event: 'command.execute',
    action: 'invoice.create',
    keystrokeToSuggestions: 45,  // ms
    enterToOptimistic: 30,       // ms
    enterToConfirmed: 280,       // ms
    success: true
  }
}));
```

Aggregate and alert on p95 regression > 20%.

---

## 13. Testing Strategy

### 13.1 Parser Tests (Unit)

```typescript
// tests/unit/parser.test.ts
describe('Parser', () => {
  describe('shortcut expansion', () => {
    test('inv → invoice.list', () => {
      expect(parse('inv').action).toBe('invoice.list')
    })
    test('inv new → invoice.create', () => {
      expect(parse('inv new').action).toBe('invoice.create')
    })
  })

  describe('forgiving input', () => {
    test('handles typos', () => {
      expect(parse('invocie.create').action).toBe('invoice.create')
    })
    test('order independent', () => {
      const a = parse('inv new acme 1500')
      const b = parse('inv new 1500 acme')
      expect(a.params).toEqual(b.params)
    })
  })

  describe('amount parsing', () => {
    test.each([
      ['1500', 1500],
      ['1500$', 1500],
      ['$1,500', 1500],
      ['1.5k', 1500],
    ])('%s → %d', (input, expected) => {
      expect(parseAmount(input)).toBe(expected)
    })
  })
})
```

### 13.2 Action Tests (Integration)

```php
// tests/Feature/Commands/InvoiceCreateTest.php
class InvoiceCreateTest extends TestCase
{
    public function test_creates_invoice_with_valid_params()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $customer = Customer::factory()->for($company)->create();

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Action' => 'invoice.create',
                'X-Idempotency-Key' => Str::uuid(),
                'X-Company-Id' => $company->id,
            ])
            ->postJson('/api/commands', [
                'params' => [
                    'customer_id' => $customer->id,
                    'amount' => 1500,
                ]
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['id', 'number', 'amount']]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'amount' => 1500,
        ]);
    }

    public function test_idempotent_replay_returns_409()
    {
        $key = Str::uuid();
        // First request succeeds
        $this->postCommand('invoice.create', [...], $key)
            ->assertStatus(201);

        // Second request with same key
        $this->postCommand('invoice.create', [...], $key)
            ->assertStatus(409)
            ->assertJsonPath('code', 'IDEMPOTENT_REPLAY');
    }
}
```

### 13.3 E2E Tests (Playwright)

```typescript
// tests/e2e/palette.spec.ts
test.describe('Command Palette', () => {
  test('opens with Cmd+K', async ({ page }) => {
    await page.goto('/dashboard')
    await page.keyboard.press('Meta+k')
    await expect(page.locator('[data-testid="palette"]')).toBeVisible()
  })

  test('creates invoice via command', async ({ page }) => {
    await page.goto('/dashboard')
    await page.keyboard.press('Meta+k')
    await page.keyboard.type('inv new acme 1500')
    await page.keyboard.press('Enter')

    // Wait for success toast
    await expect(page.locator('.toast-success')).toContainText('Invoice')
  })

  test('shows autocomplete suggestions', async ({ page }) => {
    await page.goto('/dashboard')
    await page.keyboard.press('Meta+k')
    await page.keyboard.type('inv')

    await expect(page.locator('[data-testid="suggestions"]'))
      .toContainText('invoice.create')
  })
})
```

### 13.4 Performance Probes

```python
# tools/perf_probe.py
import time
import statistics
from playwright.sync_api import sync_playwright

def measure_suggestion_latency(page, query):
    start = time.perf_counter()
    page.keyboard.type(query)
    page.wait_for_selector('[data-testid="suggestions"]')
    return (time.perf_counter() - start) * 1000

def run_probe():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto('http://localhost:8000/dashboard')
        page.keyboard.press('Meta+k')

        latencies = []
        for _ in range(20):
            page.keyboard.press('Escape')
            page.keyboard.press('Meta+k')
            latencies.append(measure_suggestion_latency(page, 'inv'))

        print(f"p50: {statistics.median(latencies):.1f}ms")
        print(f"p95: {statistics.quantiles(latencies, n=20)[18]:.1f}ms")

        browser.close()
```

---

## 14. Rollout Milestones

### Milestone 1: Foundations Complete (Current → +2 weeks)
- [ ] Fix grammar inconsistency (standardize on `entity.verb`)
- [ ] Polish existing guided flow
- [ ] Add basic help system (`help`, `?`, `--help`)
- [ ] Implement undo for create actions
- [ ] Add confirmation for delete actions
- [ ] Performance baseline measurements

### Milestone 2: Accounting Core (+2 → +7 weeks)
- [ ] `customer.*` commands
- [ ] `vendor.*` commands
- [ ] `invoice.*` commands with full flow
- [ ] `payment.*` commands
- [ ] `bill.*` commands
- [ ] `expense.*` commands
- [ ] Basic reports (`report.aging`, `report.profit-loss`)

### Milestone 3: Discoverability (+7 → +9 weeks)
- [ ] GUI hint toasts (post-action tips)
- [ ] Button tooltips with command hints
- [ ] Empty state nudges
- [ ] "Learn as you click" mode
- [ ] Beginner menu mode
- [ ] User tenure tracking for hint frequency

### Milestone 4: Power Features (+9 → +12 weeks)
- [ ] Command history (↑/↓)
- [ ] Favorites/pins
- [ ] Templates (save, list, run)
- [ ] Batch operations
- [ ] Export formats (CSV, JSON)
- [ ] Anomaly warnings

### Milestone 5: Polish & Intelligence (+12 → +16 weeks)
- [ ] Natural language queries
- [ ] Personalized suggestions
- [ ] Comprehensive help content
- [ ] A11y audit and fixes
- [ ] Performance optimization pass
- [ ] User feedback integration

---

## 15. Command Reference

### 15.1 Company & Users

```bash
# Company management
company.list                      # List companies you have access to
company.create -name "Acme Corp"  # Create new company
company.switch acme               # Switch active company
company.delete acme               # Delete company (critical)

# User management
user.list                         # List users in current company
user.create -email joe@acme.com -role admin
user.update joe@acme.com -role member
user.delete joe@acme.com

# Assignments
company.assign joe@acme.com to acme -role admin
company.unassign joe@acme.com from acme
```

### 15.2 Customers & Vendors

```bash
# Customers (AR)
customer.create "Acme Corp" -email ar@acme.com
customer.list
customer.list --outstanding       # With balance > 0
customer.view acme
customer.update acme -email new@acme.com

# Vendors (AP)
vendor.create "Office Depot" -email ap@depot.com
vendor.list
vendor.list --owed                # With balance owed
vendor.view depot
```

### 15.3 Invoices & Payments

```bash
# Invoices
invoice.create acme 1500          # Quick create
invoice.create -c "Acme" -a 1500 --due="net 30" --notes="Q1 services"
invoice.list                      # All invoices
invoice.list --unpaid             # Unpaid only
invoice.list --overdue            # Past due
invoice.list -c acme              # For specific customer
invoice.view 1042                 # View INV-1042
invoice.send 1042                 # Email invoice
invoice.void 1042                 # Void invoice

# Shorthand
inv                               # → invoice.list
inv new acme 1500                 # → invoice.create
inv --unpaid                      # → invoice.list --unpaid
inv 1042                          # → invoice.view 1042

# Payments
payment.create 1042 1500          # Pay INV-1042 $1500
payment.create -inv 1042 -a 1500 --method bank --date today
payment.list
payment.list --today
payment.void 501

# Shorthand
pay 1042 1500                     # → payment.create
```

### 15.4 Bills & Expenses

```bash
# Bills (AP)
bill.create depot 500             # Bill from Office Depot
bill.create -v "Office Depot" -a 500 --due="jan 30" --account supplies
bill.list
bill.list --unpaid
bill.pay 201 500                  # Pay bill

# Expenses (quick entry)
expense.create 50 --account meals --notes "Team lunch"
expense.list --from="jan 1" --to="jan 31"

# Shorthand
exp 50 meals "Team lunch"         # → expense.create
```

### 15.5 Reports

```bash
report.list                       # Available reports
report.aging                      # AR aging (who owes me)
report.owed                       # AP aging (what I owe)
report.profit-loss --from="jan 1" --to="jan 31"
report.balance-sheet --as-of="jan 31"
report.cash-flow --period=q1
report.trial-balance

# Export
report.profit-loss --format=csv > pl-jan.csv
report.aging --format=json
```

### 15.6 Templates & Workflows

```bash
# Templates
template.save "monthly-rent" bill.create landlord 2500 --account rent
template.list
template.run monthly-rent

# Workflows
workflow.list
workflow.month-end                # Run month-end checklist
```

---

## Appendix A: Migration from cli.md v1

| v1 Pattern | v2 Pattern | Notes |
|------------|------------|-------|
| `invoice -c acme` | `invoice.list -c acme` | Explicit verb required |
| `invoice` (bare) | `invoice.list` | Shortcut expansion |
| `invoice:list` | `invoice.list` | Dot, not colon |
| Natural language queries | Deferred to Phase 4 | Scope control |
| Templates | Deferred to Phase 3 | Scope control |

## Appendix B: Glossary

| Term | Definition |
|------|------------|
| **Entity** | A domain object (invoice, customer, etc.) |
| **Verb** | An action on an entity (create, list, etc.) |
| **Palette** | The command input UI |
| **Guided flow** | Step-by-step field collection |
| **Freeform** | Direct command typing |
| **Idempotency key** | UUID preventing duplicate submissions |
| **Anomaly** | Unusual pattern triggering warning |

---

*Document version: 2.0*
*Last updated: 2025-01-15*
*Author: Claude + Yasir*


---

## Appendix C: Technology Stack

### Frontend (Palette)

| Layer | Technology | Purpose |
|-------|------------|---------|
| **Collapsed State** | Custom Vue 3 component | Lightweight input + suggestions |
| **Expanded State** | jQuery Terminal 2.45.x | Full terminal experience |
| **Styling** | Custom CSS (retro-future theme) | 80s aesthetic + modern touches |
| **Parser** | TypeScript (existing) | Freeform input → structured command |
| **Search** | Fuse.js (Web Worker) | Fuzzy matching for autocomplete |
| **State** | IndexedDB + localStorage | History, preferences |
| **API Client** | Fetch + your existing apiPost | Command execution |

### Backend (Command Bus)

| Layer | Technology | Purpose |
|-------|------------|---------|
| **Endpoint** | `POST /api/commands` | Single entry point |
| **Router** | CommandBus (match dispatch) | Action resolution |
| **Actions** | Laravel Actions (per entity.verb) | Business logic |
| **Validation** | Laravel Validator | Input validation |
| **Authorization** | Laravel Policies + Gates | RBAC |
| **Idempotency** | Custom middleware + table | Prevent duplicates |
| **Audit** | AuditLog model | Command history |

### Integration Points

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│    ┌──────────────────┐        ┌──────────────────┐            │
│    │   Vue/Inertia    │        │  jQuery Terminal │            │
│    │      GUI         │        │     Palette      │            │
│    └────────┬─────────┘        └────────┬─────────┘            │
│             │                           │                       │
│             │     Same Services         │                       │
│             └───────────┬───────────────┘                       │
│                         │                                       │
│                         ▼                                       │
│    ┌─────────────────────────────────────────────────────────┐ │
│    │                    useCommandBus()                      │ │
│    │                                                         │ │
│    │  - parse(input) → { action, params, missing }          │ │
│    │  - execute(parsed) → POST /api/commands                │ │
│    │  - getCompletions(partial) → string[]                  │ │
│    └─────────────────────────────────────────────────────────┘ │
│                         │                                       │
│                         ▼                                       │
│    ┌─────────────────────────────────────────────────────────┐ │
│    │                   Laravel Backend                       │ │
│    │                                                         │ │
│    │  CommandController → CommandBus → Actions → Services   │ │
│    │                                                         │ │
│    └─────────────────────────────────────────────────────────┘ │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Package Dependencies

```json
{
  "dependencies": {
    "jquery": "^3.7.1",
    "jquery.terminal": "^2.45.2",
    "fuse.js": "^7.0.0"
  },
  "devDependencies": {
    "@types/jquery": "^3.5.29",
    "@types/jquery.terminal": "^2.37.2"
  }
}
```

### Why jQuery Terminal Over Alternatives

| Criterion | jQuery Terminal | xterm.js | vue-command-palette |
|-----------|-----------------|----------|---------------------|
| **Use case fit** | ✅ Command interpreter | ❌ Shell emulator | ⚠️ Modern palette |
| **Autocomplete** | ✅ Built-in | ❌ DIY | ✅ Built-in |
| **History** | ✅ Built-in | ❌ DIY | ⚠️ Basic |
| **Color syntax** | ✅ Simple `[[;color;]text]` | ⚠️ ANSI escapes | ❌ CSS only |
| **Retro aesthetic** | ✅ Natural fit | ✅ Natural fit | ❌ Modern design |
| **JSON-RPC** | ✅ Native | ❌ None | ❌ None |
| **Bundle size** | 150KB | 400KB | 15KB |
| **Learning curve** | Low | Medium | Low |

**Verdict**: jQuery Terminal is purpose-built for fake terminals / command interpreters, which is exactly our use case. xterm.js would be overkill (and require building everything jQuery Terminal provides out of the box).
