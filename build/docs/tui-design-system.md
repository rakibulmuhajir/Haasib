# TUI Design System

**Purpose**: Terminal-native interface for power users
**Style**: htop/btop inspired - dense, colorful text, no GUI elements

---

## Design Principles

1. **No GUI elements** - No border-radius, no background fills, no box-shadow
2. **Color is meaning** - Distinguish state through text color, not containers
3. **Monospace alignment** - Use the terminal grid, align columns
4. **Progressive disclosure** - Show fields as user progresses through command
5. **Vertical dropdowns** - fzf-style lists, not horizontal pill bars

---

## Color System

### Semantic Colors
| Color | Hex | Usage |
|-------|-----|-------|
| White | `#f8fafc` | Primary content, data values |
| Cyan | `#22d3ee` | Active/focused field, selection |
| Green | `#10b981` | Filled/complete, success |
| Amber | `#fbbf24` | Warnings, attention needed |
| Red | `#f43f5e` | Errors, required unfilled |
| Dim | `#64748b` | Optional fields, hints, secondary |
| Very Dim | `#475569` | Optional flags, disabled |

### Usage Rules
- **Active field label**: cyan
- **Required unfilled label**: white (or dim cyan)
- **Filled field label**: green
- **Filled field value**: white
- **Optional field**: very dim
- **Error state**: red text (no red boxes)

---

## Input Format

### Progressive Inline Fields
```
❯ invoice.create
  customer: Acme Corp            ← green (filled)
  amount: _                      ← cyan (active)
  --currency: USD                ← dim (optional, has default)
  --due:                         ← very dim (optional, empty)
```

### Field States

**Empty, unfocused:**
```
  amount:                        ← dim white
```

**Active (focused):**
```
  customer: _                    ← cyan label, cursor
```

**Filled:**
```
  customer: Acme Corp            ← green label, white value
```

**Error:**
```
  email: invalid@                ← red label + value
         └─ Invalid email format ← dim red hint below
```

### Required vs Optional
- Required fields: plain labels (`customer:`, `amount:`)
- Optional flags: prefixed with `--` (`--currency:`, `--due:`)
- Color differentiates: required = white/cyan, optional = dim

---

## Vertical Dropdown

### Command/Entity Suggestions
```
❯ inv_
  ┌────────────────────┐
  │ › invoice          │
  │   inventory        │
  │   investment       │
  └────────────────────┘
```

### Entity Search Results
```
  customer: acm_
  ┌─────────────────────────────┐
  │ › Acme Corp     $1,250 due  │
  │   Acme Tech     $0          │
  │   Acme International        │
  └─────────────────────────────┘
```

### Dropdown Rules
- Fit content width (not full row)
- No icons - text only
- Border: simple `1px solid #334155`
- Selected row: `›` prefix + cyan text
- Unselected: white text (`#f8fafc`)
- Meta info: right-aligned, dimmer
- Max 8 items visible

---

## Status Indicators

### Status Symbols
| Status | Symbol | Color |
|--------|--------|-------|
| Paid | `●` | Green |
| Draft | `○` | Dim |
| Pending | `◐` | Amber |
| Sent | `◑` | Cyan |
| Overdue | `✗` | Red |
| Void | `⊘` | Dim |

### In Tables
```
  INV-001  Acme Corp       $500.00   ● paid
  INV-002  TechStart       $250.00   ◐ pending
  INV-003  GlobalTrade   $1,200.00   ✗ overdue
```

---

## Tables

### Structure
```
❯ invoice.list

  #       Customer        Amount     Status    Due
  ─────────────────────────────────────────────────
  INV-001 Acme Corp       $500.00    ● paid    -
  INV-002 TechStart       $250.00    ◐ pending Dec 15
  INV-003 GlobalTrade   $1,200.00    ✗ overdue Nov 30

  3 invoices | $1,950.00 total | 1 overdue
```

### Rules
- Header: dim text, underlined with `─`
- Columns: left-aligned text, right-aligned numbers
- Selected row: cyan highlight (text color, not background)
- Summary line at bottom, separated by blank line

---

## Animations

### Allowed
- Opacity fade: 150ms ease-out
- Stagger timing: 30ms per item

### Not Allowed
- translateY/translateX
- scale transforms
- box-shadow transitions
- glow effects
- shake animations

---

## Full Layout Example

```
┌─ HAASIB ─────────────────────── demo-company ─┐
│                                               │
│  Recent:                                      │
│    invoice.list --status=pending              │
│    customer.view "Acme Corp"                  │
│                                               │
│  ❯ invoice.create                             │
│    customer: Acme Corp                ✓       │
│    amount: _                                  │
│    --currency: USD                            │
│    --due: +30d                                │
│    --description:                             │
│                                               │
│  ┌────────────────────────────────────────┐   │
│  │ › 500.00    Last invoice amount        │   │
│  │   1000.00   Common amount              │   │
│  │   250.00    Minimum                    │   │
│  └────────────────────────────────────────┘   │
│                                               │
│  Tab: next  ↑↓: select  Enter: confirm       │
└───────────────────────────────────────────────┘
```

---

## CSS Class Reference

### Base Classes
| Class | Purpose |
|-------|---------|
| `.palette` | Main container |
| `.palette-prompt` | `❯` indicator |
| `.palette-input` | Main text input |
| `.palette-fields` | Vertical field container |
| `.palette-field` | Single field row |
| `.palette-dropdown` | Vertical suggestion list |
| `.palette-status` | Bottom status bar |

### State Modifiers
| Modifier | Effect |
|----------|--------|
| `--active` | Cyan text |
| `--filled` | Green label |
| `--error` | Red text |
| `--optional` | Very dim |
| `--selected` | Cyan + `›` prefix |

---

## Don't Do This

```css
/* NO: GUI elements */
border-radius: 6px;
background: rgba(30, 41, 59, 0.5);
box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.2);

/* NO: Transform animations */
transform: translateY(-1px);
transform: scale(1.02);

/* NO: Horizontal chip layouts */
display: flex;
gap: 8px;
flex-wrap: nowrap;
```

## Do This

```css
/* YES: Text-only styling */
color: #22d3ee;

/* YES: Simple borders if needed */
border-left: 2px solid #22d3ee;

/* YES: Opacity transitions */
opacity: 0;
transition: opacity 150ms ease-out;

/* YES: Vertical layouts */
display: flex;
flex-direction: column;
gap: 4px;
```
