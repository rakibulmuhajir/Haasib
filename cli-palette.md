# Haasib Command Palette — Implementation Specification

> **Stack**: Custom Vue 3 component. No terminal emulator libraries.
> **Philosophy**: Terminal aesthetic + modern features. Same API as GUI.

---

## 1. Architecture

### 1.1 Component Tree

```
CommandPalette.vue
├── PaletteHeader.vue         # Company context, mode indicator
├── PaletteInput.vue          # Input line with prompt and cursor
├── PaletteOutput.vue         # Scrollable output history
│   ├── OutputLine.vue        # Single output line (text, table, progress, etc.)
│   └── OutputTable.vue       # Tabular data renderer
├── PaletteSuggestions.vue    # Autocomplete dropdown
└── PaletteHelp.vue           # Inline help panel
```

### 1.2 State Shape

```typescript
interface PaletteState {
  // UI State
  mode: 'collapsed' | 'half' | 'full'
  visible: boolean
  inputFocused: boolean

  // Input
  input: string
  cursorPosition: number

  // History
  commandHistory: string[]
  historyIndex: number           // -1 = not browsing history

  // Output
  outputLines: OutputLine[]
  maxOutputLines: 500

  // Suggestions
  suggestions: Suggestion[]
  suggestionIndex: number        // -1 = none selected
  showSuggestions: boolean

  // Execution
  executing: boolean
  pendingUndo: UndoAction | null
}

interface OutputLine {
  id: string
  type: 'input' | 'output' | 'error' | 'success' | 'table' | 'progress' | 'system'
  content: string | TableData | ProgressData
  timestamp: number
}

interface Suggestion {
  type: 'command' | 'entity' | 'history' | 'flag'
  value: string
  label: string
  description?: string
  icon?: string
}

interface UndoAction {
  action: string
  params: Record<string, unknown>
  expiresAt: number
  message: string
}
```

### 1.3 File Structure

```
resources/js/
├── components/
│   └── palette/
│       ├── CommandPalette.vue
│       ├── PaletteHeader.vue
│       ├── PaletteInput.vue
│       ├── PaletteOutput.vue
│       ├── PaletteSuggestions.vue
│       ├── PaletteHelp.vue
│       └── renderers/
│           ├── OutputLine.vue
│           ├── OutputTable.vue
│           └── OutputProgress.vue
├── composables/
│   ├── usePalette.ts           # Main palette state and logic
│   ├── useCommandParser.ts     # Input → parsed command
│   ├── useCommandExecutor.ts   # API calls, idempotency
│   ├── useAutocomplete.ts      # Suggestion generation
│   └── useCommandHistory.ts    # History persistence
├── palette/
│   ├── grammar.ts              # Command definitions
│   ├── parser.ts               # Tokenizer and parser
│   ├── formatter.ts            # Output formatting (colors, tables)
│   └── constants.ts            # Keybindings, limits, colors
└── stores/
    └── paletteStore.ts         # Pinia store (if needed)
```

---

## 2. Visual Design

### 2.1 Layout

```
COLLAPSED (56px height)
┌─────────────────────────────────────────────────────────────────────────┐
│ ❯ invoice.create acme 1500_                              [½] [−] [□]   │
└─────────────────────────────────────────────────────────────────────────┘

HALF (50% viewport height)
┌─────────────────────────────────────────────────────────────────────────┐
│ 🏢 Acme Corp                                              [½] [−] [□]  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ❯ invoice.list --unpaid                                                 │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ INV#     Customer        Amount      Due         Status             │ │
│ │ 1042     Acme Corp       $1,500      Jan 15      ⚠ Overdue         │ │
│ │ 1043     Beta LLC        $2,200      Jan 20      ◐ Pending         │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ ❯ _                                                                     │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

FULL (100% viewport height, modal overlay)
┌─────────────────────────────────────────────────────────────────────────┐
│ 🏢 Acme Corp │ yasir@acme.com │ prod                      [½] [−] [□]  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  [Full scrollable output area]                                          │
│                                                                         │
│                                                                         │
│                                                                         │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ ❯ _                                                                     │
└─────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Colors (Posting.sh-inspired semantic system)

**Only 7 semantic colors.** Swap them = new theme. No complexity.

```typescript
// palette/theme.ts
export interface PaletteTheme {
  name: string
  primary: string      // Buttons, key interactive elements, prompt
  secondary: string    // Minor labels, muted interactive elements
  accent: string       // Focus rings, cursors, highlights, links
  background: string   // Main background
  surface: string      // Panels, cards, elevated surfaces
  error: string        // Error messages, destructive actions
  success: string      // Success messages, positive states
  warning: string      // Warnings, overdue, anomalies
}

// Default theme: "Galaxy" (inspired by posting.sh)
export const THEME_GALAXY: PaletteTheme = {
  name: 'galaxy',
  primary: '#6366f1',      // Indigo - buttons, prompt
  secondary: '#64748b',    // Slate - muted text
  accent: '#22d3ee',       // Cyan - cursor, focus, links
  background: '#0f172a',   // Dark slate - main bg
  surface: '#1e293b',      // Lighter slate - panels
  error: '#f43f5e',        // Rose - errors
  success: '#10b981',      // Emerald - success
  warning: '#f59e0b',      // Amber - warnings
}

// Alternative: "Monokai"
export const THEME_MONOKAI: PaletteTheme = {
  name: 'monokai',
  primary: '#a6e22e',      // Green
  secondary: '#75715e',    // Comment gray
  accent: '#66d9ef',       // Cyan
  background: '#272822',   // Dark
  surface: '#3e3d32',      // Lighter
  error: '#f92672',        // Pink
  success: '#a6e22e',      // Green
  warning: '#fd971f',      // Orange
}

// Alternative: "Nord"
export const THEME_NORD: PaletteTheme = {
  name: 'nord',
  primary: '#88c0d0',      // Frost
  secondary: '#4c566a',    // Polar night
  accent: '#8fbcbb',       // Frost
  background: '#2e3440',   // Polar night
  surface: '#3b4252',      // Polar night
  error: '#bf616a',        // Aurora red
  success: '#a3be8c',      // Aurora green
  warning: '#ebcb8b',      // Aurora yellow
}

// Alternative: "Dracula"
export const THEME_DRACULA: PaletteTheme = {
  name: 'dracula',
  primary: '#bd93f9',      // Purple
  secondary: '#6272a4',    // Comment
  accent: '#8be9fd',       // Cyan
  background: '#282a36',   // Background
  surface: '#44475a',      // Current line
  error: '#ff5555',        // Red
  success: '#50fa7b',      // Green
  warning: '#f1fa8c',      // Yellow
}

export const THEMES = {
  galaxy: THEME_GALAXY,
  monokai: THEME_MONOKAI,
  nord: THEME_NORD,
  dracula: THEME_DRACULA,
} as const

export type ThemeName = keyof typeof THEMES
```

**Usage in CSS (via CSS custom properties):**

```css
.palette {
  --primary: v-bind('theme.primary');
  --secondary: v-bind('theme.secondary');
  --accent: v-bind('theme.accent');
  --background: v-bind('theme.background');
  --surface: v-bind('theme.surface');
  --error: v-bind('theme.error');
  --success: v-bind('theme.success');
  --warning: v-bind('theme.warning');

  /* Derived colors (computed from above) */
  --text-primary: color-mix(in srgb, var(--accent) 20%, white);
  --text-secondary: var(--secondary);
  --text-muted: color-mix(in srgb, var(--secondary) 50%, var(--background));
}

/* Then use everywhere */
.palette { background: var(--background); }
.palette-surface { background: var(--surface); }
.palette-prompt { color: var(--primary); }
.palette-cursor { background: var(--accent); }
.palette-success { color: var(--success); }
.palette-error { color: var(--error); }
.palette-link { color: var(--accent); }
.palette-link:hover { text-decoration: underline; }
```

**Syntax highlighting (derived from theme):**

```typescript
// Syntax colors are derived from the 7 semantic colors
export function getSyntaxColors(theme: PaletteTheme) {
  return {
    entity: theme.primary,           // invoice, payment, etc.
    verb: theme.accent,              // .create, .list, etc.
    flag: theme.warning,             // --unpaid, -c, etc.
    string: theme.success,           // "Acme Corp"
    number: theme.warning,           // 1500
    operator: theme.secondary,       // ., =
  }
}
```

**Why this approach:**

| Approach | Colors to manage | Theme switching |
|----------|------------------|-----------------|
| Complex (my original) | 20+ tokens | Hard |
| Posting.sh style | 7 tokens | Trivial |

User preference stored in `localStorage`. Default: `galaxy`.

### 2.3 Typography

```css
.palette {
  font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', 'Consolas', monospace;
  font-size: 14px;
  line-height: 1.6;
  font-feature-settings: 'liga' 1, 'calt' 1;  /* Enable ligatures */
}
```

### 2.4 Cursor

```css
.cursor {
  display: inline-block;
  width: 0.6em;
  height: 1.2em;
  background: var(--cursor-color);
  animation: blink 1s step-end infinite;
  vertical-align: text-bottom;
}

@keyframes blink {
  50% { opacity: 0; }
}

/* Steady cursor when typing */
.palette:focus-within .cursor {
  animation: none;
  opacity: 1;
}
```

---

## 3. Input Handling

### 3.1 Keyboard Map

| Key | Context | Action |
|-----|---------|--------|
| `Cmd/Ctrl + K` | Global | Toggle palette |
| `Escape` | Palette open | Close or shrink |
| `Enter` | Input has text | Execute command |
| `Enter` | Input empty | Show menu (beginner mode) |
| `Tab` | Suggestions visible | Accept suggestion |
| `Tab` | No suggestions | Trigger autocomplete |
| `Shift + Tab` | Suggestions visible | Previous suggestion |
| `↑` | Input empty | Previous history |
| `↑` | Suggestions visible | Previous suggestion |
| `↓` | Suggestions visible | Next suggestion |
| `↓` | Browsing history | Next history |
| `Ctrl + C` | Executing | Cancel execution |
| `Ctrl + L` | Any | Clear output |
| `Ctrl + U` | Any | Clear input line |
| `Ctrl + A` | Any | Cursor to start |
| `Ctrl + E` | Any | Cursor to end |
| `Ctrl + W` | Any | Delete word backward |
| `Cmd/Ctrl + ↑` | Half mode | Expand to full |
| `Cmd/Ctrl + ↓` | Full mode | Shrink to half |

### 3.2 Input Processing

```typescript
// composables/usePaletteInput.ts
export function usePaletteInput() {
  const state = usePaletteState()

  function handleKeyDown(event: KeyboardEvent) {
    const { key, ctrlKey, metaKey, shiftKey } = event

    // Global shortcuts
    if ((ctrlKey || metaKey) && key === 'k') {
      event.preventDefault()
      state.toggle()
      return
    }

    if (!state.visible) return

    // Prevent default for handled keys
    const handled = processKey(key, { ctrl: ctrlKey, meta: metaKey, shift: shiftKey })
    if (handled) event.preventDefault()
  }

  function processKey(key: string, mods: Modifiers): boolean {
    switch (key) {
      case 'Enter':
        if (state.input.trim()) {
          execute()
        } else {
          showBeginnerMenu()
        }
        return true

      case 'Tab':
        if (mods.shift) {
          selectPreviousSuggestion()
        } else {
          acceptSuggestionOrAutocomplete()
        }
        return true

      case 'ArrowUp':
        if (state.showSuggestions) {
          selectPreviousSuggestion()
        } else if (state.input === '') {
          browsePreviousHistory()
        }
        return true

      case 'ArrowDown':
        if (state.showSuggestions) {
          selectNextSuggestion()
        } else if (state.historyIndex >= 0) {
          browseNextHistory()
        }
        return true

      case 'Escape':
        if (state.showSuggestions) {
          state.showSuggestions = false
        } else {
          state.shrinkOrClose()
        }
        return true

      case 'c':
        if (mods.ctrl && state.executing) {
          cancelExecution()
          return true
        }
        return false

      case 'l':
        if (mods.ctrl) {
          clearOutput()
          return true
        }
        return false

      case 'u':
        if (mods.ctrl) {
          state.input = ''
          state.cursorPosition = 0
          return true
        }
        return false

      default:
        return false
    }
  }

  return { handleKeyDown }
}
```

---

## 4. Grammar

### 4.1 Canonical Form

```
<entity>.<verb> [subject] [flags]
```

Every command resolves to this form. No exceptions.

### 4.2 Entities and Verbs

```typescript
// palette/grammar.ts
export const GRAMMAR = {
  entities: {
    company: {
      verbs: ['create', 'list', 'view', 'update', 'delete', 'switch'],
      shortcuts: ['co'],
    },
    user: {
      verbs: ['create', 'list', 'view', 'update', 'delete', 'assign', 'unassign'],
      shortcuts: ['usr'],
    },
    customer: {
      verbs: ['create', 'list', 'view', 'update', 'delete'],
      shortcuts: ['cust', 'c'],
    },
    vendor: {
      verbs: ['create', 'list', 'view', 'update', 'delete'],
      shortcuts: ['vend', 'v'],
    },
    invoice: {
      verbs: ['create', 'list', 'view', 'send', 'void'],
      shortcuts: ['inv', 'i'],
    },
    payment: {
      verbs: ['create', 'list', 'view', 'void'],
      shortcuts: ['pay', 'p'],
    },
    bill: {
      verbs: ['create', 'list', 'view', 'pay', 'void'],
      shortcuts: ['bl'],
    },
    expense: {
      verbs: ['create', 'list', 'view', 'update', 'delete'],
      shortcuts: ['exp', 'e'],
    },
    account: {
      verbs: ['create', 'list', 'view', 'update', 'reconcile'],
      shortcuts: ['acc', 'a'],
    },
    report: {
      verbs: ['list', 'view', 'export'],
      shortcuts: ['rep', 'r'],
    },
  },

  // Default verb when entity typed alone
  defaultVerb: 'list',

  // Verb synonyms (normalized to canonical)
  verbSynonyms: {
    'new': 'create',
    'add': 'create',
    'make': 'create',
    'rm': 'delete',
    'remove': 'delete',
    'show': 'view',
    'get': 'view',
    'edit': 'update',
    'modify': 'update',
    'ls': 'list',
    'all': 'list',
  },
} as const
```

### 4.3 Flags

```typescript
// palette/grammar.ts
export const FLAGS = {
  // Universal flags (work on all applicable commands)
  universal: {
    '--help':     { short: '-h', type: 'boolean', description: 'Show help' },
    '--format':   { short: '-f', type: 'enum', values: ['table', 'json', 'csv'], description: 'Output format' },
    '--draft':    { short: null, type: 'boolean', description: 'Save without posting' },
  },

  // Entity-specific flags
  invoice: {
    '--customer': { short: '-c', type: 'string', description: 'Customer name or ID' },
    '--amount':   { short: '-a', type: 'money', description: 'Invoice amount' },
    '--due':      { short: '-d', type: 'date', description: 'Due date' },
    '--status':   { short: '-s', type: 'enum', values: ['draft', 'pending', 'sent', 'paid', 'overdue', 'void'], description: 'Filter by status' },
    '--unpaid':   { short: null, type: 'boolean', description: 'Shorthand for --status=pending,sent,overdue' },
    '--overdue':  { short: null, type: 'boolean', description: 'Shorthand for --status=overdue' },
  },

  payment: {
    '--invoice':  { short: '-i', type: 'string', description: 'Invoice ID' },
    '--amount':   { short: '-a', type: 'money', description: 'Payment amount' },
    '--method':   { short: '-m', type: 'enum', values: ['cash', 'check', 'bank', 'card'], description: 'Payment method' },
    '--date':     { short: '-d', type: 'date', description: 'Payment date' },
  },

  report: {
    '--from':     { short: null, type: 'date', description: 'Start date' },
    '--to':       { short: null, type: 'date', description: 'End date' },
    '--period':   { short: '-p', type: 'enum', values: ['today', 'week', 'month', 'quarter', 'year', 'ytd'], description: 'Predefined period' },
  },
} as const
```

### 4.4 Parser

```typescript
// palette/parser.ts
export interface ParsedCommand {
  raw: string
  entity: string | null
  verb: string | null
  subject: string | null
  flags: Record<string, string | boolean | number>
  errors: string[]
  complete: boolean
  confidence: number           // 0-1, how confident we are in the parse
  idemKey: string
}

export function parse(input: string): ParsedCommand {
  const tokens = tokenize(input)
  const result: ParsedCommand = {
    raw: input,
    entity: null,
    verb: null,
    subject: null,
    flags: {},
    errors: [],
    complete: false,
    confidence: 0,
    idemKey: '',
  }

  if (tokens.length === 0) return result

  // Step 1: Extract entity.verb or shortcut
  const [first, ...rest] = tokens
  const entityVerb = parseEntityVerb(first)

  if (entityVerb) {
    result.entity = entityVerb.entity
    result.verb = entityVerb.verb
  } else {
    result.errors.push(`Unknown command: ${first}`)
    return result
  }

  // Step 2: Extract flags
  const { flags, remaining, flagErrors } = extractFlags(rest, result.entity)
  result.flags = flags
  result.errors.push(...flagErrors)

  // Step 3: Remaining tokens are subject
  if (remaining.length > 0) {
    result.subject = remaining.join(' ')
  }

  // Step 4: Infer missing pieces from subject
  inferFromSubject(result)

  // Step 5: Check completeness
  result.complete = isComplete(result)
  result.confidence = calculateConfidence(result)
  result.idemKey = generateIdemKey(result)

  return result
}

function parseEntityVerb(token: string): { entity: string, verb: string } | null {
  // Try entity.verb format
  if (token.includes('.')) {
    const [entity, verb] = token.split('.')
    const normalizedEntity = resolveEntityShortcut(entity)
    const normalizedVerb = resolveVerbSynonym(verb)

    if (normalizedEntity && isValidVerb(normalizedEntity, normalizedVerb)) {
      return { entity: normalizedEntity, verb: normalizedVerb }
    }
  }

  // Try shortcut only (implies default verb)
  const entity = resolveEntityShortcut(token)
  if (entity) {
    return { entity, verb: GRAMMAR.defaultVerb }
  }

  return null
}

function tokenize(input: string): string[] {
  const tokens: string[] = []
  let current = ''
  let inQuotes = false
  let quoteChar = ''

  for (const char of input) {
    if ((char === '"' || char === "'") && !inQuotes) {
      inQuotes = true
      quoteChar = char
    } else if (char === quoteChar && inQuotes) {
      inQuotes = false
      quoteChar = ''
    } else if (char === ' ' && !inQuotes) {
      if (current) {
        tokens.push(current)
        current = ''
      }
    } else {
      current += char
    }
  }

  if (current) tokens.push(current)
  return tokens
}

function extractFlags(
  tokens: string[],
  entity: string
): { flags: Record<string, unknown>; remaining: string[]; flagErrors: string[] } {
  const flags: Record<string, unknown> = {}
  const remaining: string[] = []
  const errors: string[] = []

  let i = 0
  while (i < tokens.length) {
    const token = tokens[i]

    if (token.startsWith('--')) {
      const { name, value, consumed, error } = parseLongFlag(token, tokens[i + 1], entity)
      if (error) errors.push(error)
      else if (name) flags[name] = value
      i += consumed
    } else if (token.startsWith('-') && token.length === 2) {
      const { name, value, consumed, error } = parseShortFlag(token, tokens[i + 1], entity)
      if (error) errors.push(error)
      else if (name) flags[name] = value
      i += consumed
    } else {
      remaining.push(token)
      i++
    }
  }

  return { flags, remaining, flagErrors: errors }
}

function inferFromSubject(result: ParsedCommand) {
  if (!result.subject || !result.entity) return

  const parts = result.subject.split(/\s+/)

  for (const part of parts) {
    // Money pattern: 1500, $1500, 1,500.00
    if (/^\$?[\d,]+\.?\d*$/.test(part) && !result.flags['amount']) {
      result.flags['amount'] = parseMoney(part)
      continue
    }

    // Date pattern: jan15, jan 15, 2025-01-15
    const date = parseDate(part)
    if (date && !result.flags['date'] && !result.flags['due']) {
      if (result.entity === 'invoice' && result.verb === 'create') {
        result.flags['due'] = date
      } else {
        result.flags['date'] = date
      }
      continue
    }

    // Remaining: likely customer/vendor/subject name
    if (!result.flags['customer'] && !result.flags['vendor']) {
      if (['invoice', 'payment'].includes(result.entity)) {
        result.flags['customer'] = part
      } else if (['bill', 'expense'].includes(result.entity)) {
        result.flags['vendor'] = part
      }
    }
  }
}
```

---

## 5. Autocomplete

### 5.1 Trigger Conditions

| Condition | Behavior |
|-----------|----------|
| Empty input | Show recent commands (max 5) |
| Typing (debounce 50ms) | Show matching suggestions |
| Tab with no suggestions | Force generate suggestions |
| After `.` | Show verbs for entity |
| After `--` | Show available flags |
| After flag expecting value | Show valid values |

### 5.2 Suggestion Sources

```typescript
// composables/useAutocomplete.ts
export function useAutocomplete() {
  const state = usePaletteState()
  const { entities, recentCommands, customers, vendors, invoices } = useEntityCatalogs()

  function generateSuggestions(input: string): Suggestion[] {
    const parsed = parse(input)
    const suggestions: Suggestion[] = []

    // Priority 1: Recent matching commands
    if (input.length > 0) {
      const recentMatches = recentCommands
        .filter(cmd => cmd.startsWith(input))
        .slice(0, 3)
        .map(cmd => ({
          type: 'history' as const,
          value: cmd,
          label: cmd,
          icon: '⏱',
        }))
      suggestions.push(...recentMatches)
    }

    // Priority 2: Entity/verb completions
    if (!parsed.entity) {
      const entityMatches = Object.keys(GRAMMAR.entities)
        .filter(e => e.startsWith(input.toLowerCase()))
        .map(e => ({
          type: 'command' as const,
          value: `${e}.`,
          label: e,
          description: `${GRAMMAR.entities[e].verbs.join(', ')}`,
          icon: getEntityIcon(e),
        }))
      suggestions.push(...entityMatches)
    } else if (parsed.entity && !parsed.verb) {
      const verbMatches = GRAMMAR.entities[parsed.entity].verbs
        .map(v => ({
          type: 'command' as const,
          value: `${parsed.entity}.${v}`,
          label: `${parsed.entity}.${v}`,
          description: getVerbDescription(parsed.entity, v),
          icon: getVerbIcon(v),
        }))
      suggestions.push(...verbMatches)
    }

    // Priority 3: Flag completions
    const lastToken = input.split(/\s+/).pop() || ''
    if (lastToken.startsWith('--')) {
      const flagPrefix = lastToken.slice(2)
      const availableFlags = getAvailableFlags(parsed.entity)
      const flagMatches = Object.keys(availableFlags)
        .filter(f => f.startsWith(flagPrefix))
        .map(f => ({
          type: 'flag' as const,
          value: input.replace(/--\w*$/, `--${f}`),
          label: `--${f}`,
          description: availableFlags[f].description,
          icon: '⚑',
        }))
      suggestions.push(...flagMatches)
    }

    // Priority 4: Entity value completions (customers, vendors, etc.)
    if (parsed.entity === 'invoice' && parsed.verb === 'create' && !parsed.flags['customer']) {
      const customerMatches = customers
        .filter(c => c.name.toLowerCase().includes(lastToken.toLowerCase()))
        .slice(0, 5)
        .map(c => ({
          type: 'entity' as const,
          value: `${input} ${c.name}`.trim(),
          label: c.name,
          description: c.outstanding ? `$${c.outstanding} outstanding` : '',
          icon: '👤',
        }))
      suggestions.push(...customerMatches)
    }

    return suggestions.slice(0, 8)  // Max 8 suggestions
  }

  return { generateSuggestions }
}
```

### 5.3 Suggestion UI

```
❯ inv cr
  ┌──────────────────────────────────────────────────────────────┐
  │ ⏱  inv create acme 1500                          recent     │  ← highlighted
  │ 📄 invoice.create         Create new invoice                │
  │ 📄 invoice.create acme    Recent customer                   │
  └──────────────────────────────────────────────────────────────┘
```

---

## 6. Output Rendering

### 6.1 Line Types

```typescript
type OutputLineType =
  | 'input'      // Echo of executed command
  | 'output'     // Normal text output
  | 'success'    // Success message (green)
  | 'error'      // Error message (red)
  | 'warning'    // Warning message (amber)
  | 'info'       // Info message (blue)
  | 'table'      // Tabular data
  | 'progress'   // Progress bar
  | 'system'     // System message (muted)
```

### 6.2 Text Formatting

Inline formatting syntax (parsed at render time). Uses semantic theme colors:

```
{success}text{/}        → success color (green)
{error}text{/}          → error color (red)
{warning}text{/}        → warning color (amber)
{accent}text{/}         → accent color (cyan)
{primary}text{/}        → primary color
{secondary}text{/}      → secondary/muted color
{bold}text{/}           → bold text
{dim}text{/}            → dimmed text (50% opacity)
{link:url}text{/}       → clickable link (accent color)
{code}text{/}           → inline code style (surface bg)
```

```typescript
// palette/formatter.ts
export function formatText(text: string): VNode[] {
  const segments: VNode[] = []
  const regex = /\{(\w+)(?::([^}]+))?\}(.*?)\{\/\}/g
  let lastIndex = 0
  let match

  while ((match = regex.exec(text)) !== null) {
    // Add text before match
    if (match.index > lastIndex) {
      segments.push(h('span', text.slice(lastIndex, match.index)))
    }

    const [, type, arg, content] = match

    switch (type) {
      // Semantic colors (from theme)
      case 'success':
      case 'error':
      case 'warning':
      case 'accent':
      case 'primary':
      case 'secondary':
        segments.push(h('span', { style: { color: `var(--${type})` } }, content))
        break
      case 'bold':
        segments.push(h('span', { style: { fontWeight: 'bold' } }, content))
        break
      case 'dim':
        segments.push(h('span', { style: { opacity: 0.5 } }, content))
        break
      case 'link':
        segments.push(h('a', {
          href: arg,
          class: 'palette-link',
          style: { color: 'var(--accent)' }
        }, content))
        break
      case 'code':
        segments.push(h('code', {
          class: 'palette-code',
          style: {
            background: 'var(--surface)',
            padding: '0.125em 0.25em',
            borderRadius: '0.25em'
          }
        }, content))
        break
    }

    lastIndex = regex.lastIndex
  }

  // Add remaining text
  if (lastIndex < text.length) {
    segments.push(h('span', text.slice(lastIndex)))
  }

  return segments
}
```

### 6.3 Table Rendering

```typescript
// palette/renderers/OutputTable.vue
interface TableData {
  headers: string[]
  rows: (string | number)[][]
  footer?: string
}

// Example output:
// ┌──────────┬─────────────────┬────────────┬────────────┬───────────┐
// │ INV#     │ Customer        │ Amount     │ Due        │ Status    │
// ├──────────┼─────────────────┼────────────┼────────────┼───────────┤
// │ 1042     │ Acme Corp       │ $1,500.00  │ Jan 15     │ ⚠ Overdue │
// │ 1043     │ Beta LLC        │ $2,200.00  │ Jan 20     │ ◐ Pending │
// └──────────┴─────────────────┴────────────┴────────────┴───────────┘
//   2 invoices │ $3,700.00 total outstanding
```

### 6.4 Progress Bar

```typescript
// palette/renderers/OutputProgress.vue
interface ProgressData {
  label: string
  percent: number
  showPercent: boolean
}

// Example:
// Sending invoices ████████████░░░░░░░░ 60%
```

### 6.5 Status Indicators

```typescript
// Uses semantic theme colors
export const STATUS_INDICATORS = {
  paid:     { icon: '●', color: 'success', label: 'Paid' },
  pending:  { icon: '◐', color: 'warning', label: 'Pending' },
  sent:     { icon: '◑', color: 'accent', label: 'Sent' },
  overdue:  { icon: '⚠', color: 'error', label: 'Overdue' },
  draft:    { icon: '○', color: 'secondary', label: 'Draft' },
  void:     { icon: '⊘', color: 'secondary', label: 'Void' },
} as const

// Rendered as:
// <span :style="{ color: `var(--${status.color})` }">{{ status.icon }} {{ status.label }}</span>
```

---

## 7. API Integration

### 7.1 Request Format

```typescript
// POST /api/commands
interface CommandRequest {
  headers: {
    'Content-Type': 'application/json'
    'Authorization': `Bearer ${token}` | session
    'X-Action': string              // e.g., 'invoice.create'
    'X-Idempotency-Key': string     // SHA256 hash
    'X-Company-Slug': string        // Company slug from URL
  }
  body: {
    params: Record<string, unknown>
  }
}
```

### 7.2 Response Format

```typescript
// Success (mutation)
interface SuccessResponse {
  ok: true
  message: string
  data: Record<string, unknown>
  redirect?: string                 // GUI URL to open
  undo?: {
    action: string
    params: Record<string, unknown>
    expiresAt: number               // Unix timestamp
  }
}

// Success (query)
interface QueryResponse {
  ok: true
  data: unknown[]
  meta?: {
    total: number
    page: number
    perPage: number
  }
}

// Error
interface ErrorResponse {
  ok: false
  code: 'VALIDATION' | 'NOT_FOUND' | 'FORBIDDEN' | 'IDEMPOTENT_REPLAY' | 'SERVER_ERROR'
  message: string
  errors?: Record<string, string[]>  // Field-level errors
}
```

### 7.3 Executor

```typescript
// composables/useCommandExecutor.ts
export function useCommandExecutor() {
  const state = usePaletteState()
  const { currentCompany } = useAuth()

  async function execute(parsed: ParsedCommand): Promise<void> {
    if (!parsed.complete) {
      state.addOutput({
        type: 'error',
        content: `Missing: ${getMissingFields(parsed).join(', ')}`,
      })
      return
    }

    // Echo input
    state.addOutput({
      type: 'input',
      content: parsed.raw,
    })

    state.executing = true

    try {
      const response = await fetch('/api/commands', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Action': `${parsed.entity}.${parsed.verb}`,
          'X-Idempotency-Key': parsed.idemKey,
          'X-Company-Id': String(currentCompany.value?.id || ''),
        },
        body: JSON.stringify({
          params: buildParams(parsed),
        }),
      })

      const result = await response.json()

      if (result.ok) {
        handleSuccess(result, parsed)
      } else {
        handleError(result)
      }
    } catch (err) {
      state.addOutput({
        type: 'error',
        content: 'Network error. Check your connection.',
      })
    } finally {
      state.executing = false
      state.addToHistory(parsed.raw)
    }
  }

  function handleSuccess(result: SuccessResponse, parsed: ParsedCommand) {
    // Format based on command type
    if (parsed.verb === 'list' && Array.isArray(result.data)) {
      state.addOutput({
        type: 'table',
        content: formatAsTable(result.data, parsed.entity),
      })
    } else {
      state.addOutput({
        type: 'success',
        content: `{success}✓{/} ${result.message}`,
      })
    }

    // Show link if redirect provided
    if (result.redirect) {
      state.addOutput({
        type: 'info',
        content: `{link:${result.redirect}}→ Open in GUI{/}`,
      })
    }

    // Set up undo
    if (result.undo) {
      state.pendingUndo = {
        ...result.undo,
        message: result.message,
      }
      startUndoTimer()
    }
  }

  function handleError(result: ErrorResponse) {
    if (result.code === 'VALIDATION' && result.errors) {
      for (const [field, messages] of Object.entries(result.errors)) {
        state.addOutput({
          type: 'error',
          content: `{error}✗{/} ${field}: ${messages.join(', ')}`,
        })
      }
    } else {
      state.addOutput({
        type: 'error',
        content: `{error}✗{/} ${result.message}`,
      })
    }
  }

  return { execute }
}
```

---

## 8. History & Persistence

### 8.1 Storage

```typescript
// IndexedDB schema
interface PaletteDB {
  commandHistory: {
    key: number           // Auto-increment
    value: {
      command: string
      timestamp: number
      companyId: number
    }
    indexes: {
      byTimestamp: number
      byCompany: number
    }
  }
  preferences: {
    key: string           // 'mode' | 'fontSize' | etc.
    value: unknown
  }
}
```

### 8.2 History Limits

```typescript
export const HISTORY_CONFIG = {
  maxEntries: 500,
  maxAge: 90 * 24 * 60 * 60 * 1000,  // 90 days
  deduplicateConsecutive: true,
} as const
```

---

## 9. Undo System

### 9.1 Flow

```
1. User executes command
2. Backend returns undo action in response
3. Frontend shows toast: "✓ Invoice created [Undo - 10s]"
4. Timer counts down
5. User clicks Undo OR timer expires
6. If undo: execute undo action, show confirmation
7. Clear pending undo
```

### 9.2 Toast UI

```
┌──────────────────────────────────────────────────────────────────┐
│  ✓ Invoice INV-1044 created for Acme Corp ($1,500)              │
│                                                                  │
│  [View]  [Undo - 8s]                                            │
└──────────────────────────────────────────────────────────────────┘
```

---

## 10. Confirmation System

### 10.1 Action Classification

```typescript
export const ACTION_CLASSIFICATION = {
  safe: [
    '*.list',
    '*.view',
    'report.*',
  ],
  reversible: [
    '*.create',
    '*.update',
    '*.send',
  ],
  destructive: [
    'invoice.void',
    'payment.void',
    'bill.void',
    '*.delete',
  ],
  critical: [
    'company.delete',
  ],
} as const
```

### 10.2 Confirmation UI

**Destructive action:**
```
❯ invoice.void 1042
  ┌────────────────────────────────────────────────────────────────┐
  │ ⚠ VOID INVOICE                                                 │
  │                                                                │
  │ Invoice: INV-1042                                              │
  │ Customer: Acme Corp                                            │
  │ Amount: $1,500.00                                              │
  │                                                                │
  │ This will reverse GL entries.                                  │
  │                                                                │
  │ Press Y to confirm, N or Esc to cancel                         │
  └────────────────────────────────────────────────────────────────┘
```

**Critical action:**
```
❯ company.delete acme
  ┌────────────────────────────────────────────────────────────────┐
  │ 🔴 DELETE COMPANY                                              │
  │                                                                │
  │ Company: Acme Corp                                             │
  │ Data: 1,247 invoices, 892 payments, 3 years                    │
  │                                                                │
  │ ⚠ This action is IRREVERSIBLE.                                 │
  │                                                                │
  │ Type "delete acme" to confirm:                                 │
  │ ❯ _                                                            │
  └────────────────────────────────────────────────────────────────┘
```

---

## 11. Help System

### 11.1 Triggers

| Input | Shows |
|-------|-------|
| `help` or `?` | Global help |
| `help invoice` | Invoice commands |
| `invoice.create --help` | Create invoice syntax |
| Unknown command | "Did you mean...?" |

### 11.2 Help Content Structure

```typescript
interface HelpContent {
  global: {
    quickStart: Example[]
    navigation: Shortcut[]
    modules: ModuleOverview[]
  }
  entities: Record<string, {
    description: string
    commands: CommandHelp[]
  }>
  commands: Record<string, {
    usage: string
    description: string
    arguments: ArgumentHelp[]
    flags: FlagHelp[]
    examples: Example[]
  }>
}
```

---

## 12. Accessibility

### 12.1 Requirements

| Requirement | Implementation |
|-------------|----------------|
| Focus management | Focus trap when palette open |
| Screen reader | `role="dialog"`, `aria-label`, `aria-live="polite"` for output |
| Keyboard navigation | All actions reachable via keyboard |
| Focus visible | 2px ring on interactive elements |
| Motion | Respect `prefers-reduced-motion` |
| Contrast | WCAG AA minimum (4.5:1) |

### 12.2 ARIA Attributes

```html
<div
  role="dialog"
  aria-label="Command palette"
  aria-modal="true"
>
  <div role="log" aria-live="polite" aria-label="Command output">
    <!-- Output lines -->
  </div>

  <input
    role="combobox"
    aria-autocomplete="list"
    aria-controls="suggestions"
    aria-expanded="true/false"
    aria-activedescendant="suggestion-0"
  />

  <ul id="suggestions" role="listbox">
    <li id="suggestion-0" role="option" aria-selected="true">...</li>
  </ul>
</div>
```

---

## 13. Performance

### 13.1 Targets

| Metric | Target | Measurement |
|--------|--------|-------------|
| Keypress → suggestions | < 50ms | `performance.now()` |
| Enter → optimistic feedback | < 30ms | `performance.now()` |
| Enter → server confirmed | < 300ms p50 | Round-trip |
| Output scroll | 60fps | No jank |
| Memory | < 50MB | DevTools |

### 13.2 Optimizations

1. **Debounce suggestions**: 50ms after keypress
2. **Virtual scrolling**: For output > 100 lines
3. **Memoize parsing**: Cache parse results by input
4. **Web Worker**: Heavy operations (search, formatting)
5. **Preload catalogs**: Customers, vendors on mount

---

## 14. Testing

### 14.1 Unit Tests

```typescript
// tests/unit/parser.test.ts
describe('parse', () => {
  test('parses entity.verb', () => {
    const result = parse('invoice.create')
    expect(result.entity).toBe('invoice')
    expect(result.verb).toBe('create')
  })

  test('expands shortcuts', () => {
    expect(parse('inv').entity).toBe('invoice')
    expect(parse('inv').verb).toBe('list')
  })

  test('extracts flags', () => {
    const result = parse('invoice.list --unpaid --customer=acme')
    expect(result.flags.unpaid).toBe(true)
    expect(result.flags.customer).toBe('acme')
  })

  test('infers amount from subject', () => {
    const result = parse('invoice.create acme 1500')
    expect(result.flags.customer).toBe('acme')
    expect(result.flags.amount).toBe(1500)
  })
})
```

### 14.2 Component Tests

```typescript
// tests/component/PaletteInput.test.ts
describe('PaletteInput', () => {
  test('shows cursor at end of input', () => {
    const wrapper = mount(PaletteInput, { props: { modelValue: 'test' } })
    expect(wrapper.find('.cursor').exists()).toBe(true)
  })

  test('emits on Enter', async () => {
    const wrapper = mount(PaletteInput)
    await wrapper.find('input').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('execute')).toBeTruthy()
  })
})
```

### 14.3 E2E Tests

```typescript
// tests/e2e/palette.spec.ts
test('executes invoice.list', async ({ page }) => {
  await page.goto('/dashboard')
  await page.keyboard.press('Meta+k')
  await page.keyboard.type('invoice.list --unpaid')
  await page.keyboard.press('Enter')

  await expect(page.locator('[data-testid="palette-output"]'))
    .toContainText('INV#')
})
```

---

## 15. Migration Path

### Phase 1: Foundation (Week 1-2)
- [ ] Core components: Palette, Input, Output
- [ ] Keyboard handling
- [ ] Basic parser (entity.verb only)
- [ ] Connect to existing `/api/commands`

### Phase 2: Intelligence (Week 3-4)
- [ ] Full parser with flags, inference
- [ ] Autocomplete
- [ ] History
- [ ] Help system

### Phase 3: Polish (Week 5-6)
- [ ] Confirmation flows
- [ ] Undo system
- [ ] Table/progress renderers
- [ ] Accessibility audit

### Phase 4: Integration (Week 7-8)
- [ ] GUI discoverability hints
- [ ] All entity commands wired
- [ ] Performance optimization
- [ ] Testing

---

## Appendix A: Entity Icons

```typescript
export const ENTITY_ICONS = {
  company: '🏢',
  user: '👤',
  customer: '👤',
  vendor: '🏪',
  invoice: '📄',
  payment: '💰',
  bill: '📋',
  expense: '💳',
  account: '🏦',
  report: '📊',
} as const
```

## Appendix B: Example Commands

```bash
# Invoices
invoice.list                          # All invoices
invoice.list --unpaid                 # Unpaid only
invoice.create acme 1500              # Quick create
invoice.create -c "Acme Corp" -a 1500 --due="net 30"
invoice.send 1042                     # Email invoice
invoice.void 1042                     # Void (requires confirm)

# Shortcuts
inv                                   # → invoice.list
inv --unpaid                          # → invoice.list --unpaid
inv new acme 1500                     # → invoice.create acme 1500

# Payments
payment.create 1042 1500              # Pay invoice 1042
pay 1042 1500 --method=bank           # Shorthand

# Reports
report.aging                          # AR aging
report.profit-loss --period=month     # P&L
report.balance-sheet --as-of=dec31    # Balance sheet
```

---

## 16. Laravel Backend Integration

### 16.1 Command Bus Architecture

Single endpoint. Config-based routing. No magic.

**Route:**

```php
// routes/api.php

// Commands use header-based company context (X-Company-Slug)
// identify.company middleware sets context from header if present
Route::post('/commands', \App\Http\Controllers\CommandController::class)
    ->middleware(['auth', 'identify.company', 'throttle:commands']);

// Catalog endpoints also need company context
Route::middleware(['auth', 'identify.company', 'throttle:catalog'])->prefix('palette')->group(function () {
    Route::get('/customers', [PaletteCatalogController::class, 'customers']);
    Route::get('/vendors', [PaletteCatalogController::class, 'vendors']);
    Route::get('/accounts', [PaletteCatalogController::class, 'accounts']);
    Route::get('/invoices/recent', [PaletteCatalogController::class, 'recentInvoices']);
});

// app/Providers/RouteServiceProvider.php
RateLimiter::for('commands', fn($request) => Limit::perMinute(120)->by($request->user()->id));
RateLimiter::for('catalog', fn($request) => Limit::perMinute(300)->by($request->user()->id));
```

> **Note:** The `identify.company` middleware reads `X-Company-Slug` header and calls
> `CurrentCompany::setBySlug()`. If your middleware only reads from URL params,
> extend it to also check headers:
>
> ```php
> // In IdentifyCompanyMiddleware::handle()
> $slug = $request->route('company')
>     ?? $request->header('X-Company-Slug');
> ```

**Routing Config:**

```php
// config/command-bus.php
return [
    // entity.verb => Action class

    // Company (Core module)
    'company.create'     => \App\Modules\Core\Actions\Company\CreateAction::class,
    'company.list'       => \App\Modules\Core\Actions\Company\IndexAction::class,
    'company.view'       => \App\Modules\Core\Actions\Company\ShowAction::class,
    'company.switch'     => \App\Modules\Core\Actions\Company\SwitchAction::class,

    // Customer
    'customer.create'    => \App\Modules\Accounting\Actions\Customer\CreateAction::class,
    'customer.list'      => \App\Modules\Accounting\Actions\Customer\IndexAction::class,
    'customer.view'      => \App\Modules\Accounting\Actions\Customer\ShowAction::class,
    'customer.update'    => \App\Modules\Accounting\Actions\Customer\UpdateAction::class,
    'customer.delete'    => \App\Modules\Accounting\Actions\Customer\DeleteAction::class,

    // Vendor
    'vendor.create'      => \App\Modules\Accounting\Actions\Vendor\CreateAction::class,
    'vendor.list'        => \App\Modules\Accounting\Actions\Vendor\IndexAction::class,
    'vendor.view'        => \App\Modules\Accounting\Actions\Vendor\ShowAction::class,

    // Invoice
    'invoice.create'     => \App\Modules\Accounting\Actions\Invoice\CreateAction::class,
    'invoice.list'       => \App\Modules\Accounting\Actions\Invoice\IndexAction::class,
    'invoice.view'       => \App\Modules\Accounting\Actions\Invoice\ShowAction::class,
    'invoice.send'       => \App\Modules\Accounting\Actions\Invoice\SendAction::class,
    'invoice.void'       => \App\Modules\Accounting\Actions\Invoice\VoidAction::class,

    // Payment
    'payment.create'     => \App\Modules\Accounting\Actions\Payment\CreateAction::class,
    'payment.list'       => \App\Modules\Accounting\Actions\Payment\IndexAction::class,
    'payment.view'       => \App\Modules\Accounting\Actions\Payment\ShowAction::class,
    'payment.void'       => \App\Modules\Accounting\Actions\Payment\VoidAction::class,

    // Bill
    'bill.create'        => \App\Modules\Accounting\Actions\Bill\CreateAction::class,
    'bill.list'          => \App\Modules\Accounting\Actions\Bill\IndexAction::class,
    'bill.pay'           => \App\Modules\Accounting\Actions\Bill\PayAction::class,
    'bill.void'          => \App\Modules\Accounting\Actions\Bill\VoidAction::class,

    // Expense
    'expense.create'     => \App\Modules\Accounting\Actions\Expense\CreateAction::class,
    'expense.list'       => \App\Modules\Accounting\Actions\Expense\IndexAction::class,

    // Reports
    'report.aging'       => \App\Modules\Accounting\Actions\Report\AgingAction::class,
    'report.profit-loss' => \App\Modules\Accounting\Actions\Report\ProfitLossAction::class,
    'report.balance-sheet' => \App\Modules\Accounting\Actions\Report\BalanceSheetAction::class,
];
```

### 16.2 Action Contract

All palette actions implement this interface:

```php
// app/Contracts/PaletteAction.php

namespace App\Contracts;

interface PaletteAction
{
    /**
     * Execute the action.
     *
     * @param array $params Validated parameters from palette
     * @return array Response with 'message', 'data', 'redirect', 'undo' keys
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(array $params): array;

    /**
     * Validation rules for this action.
     */
    public function rules(): array;

    /**
     * Permission required (null = no check).
     */
    public function permission(): ?string;
}
```

### 16.3 Command Controller

```php
// app/Http/Controllers/CommandController.php

namespace App\Http\Controllers;

use App\Contracts\PaletteAction;
use App\Services\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommandController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Extract action from header
        $action = $request->header('X-Action');

        if (!$action || !preg_match('/^[a-z]+\.[a-z-]+$/', $action)) {
            return $this->error('BAD_REQUEST', 'Invalid or missing X-Action header', 400);
        }

        // 2. Resolve Action class
        $actionClass = config("command-bus.{$action}");

        if (!$actionClass || !class_exists($actionClass)) {
            return $this->error('NOT_FOUND', "Unknown command: {$action}", 404);
        }

        // 3. Verify company context (set by identify.company middleware)
        //    Middleware reads X-Company-Slug header and calls setBySlug()
        $currentCompany = app(CurrentCompany::class);
        $company = $currentCompany->get();

        if (!$company) {
            return $this->error('BAD_REQUEST', 'Company context required. Set X-Company-Slug header.', 400);
        }

        // 4. Check idempotency (skip for queries)
        $idemKey = $request->header('X-Idempotency-Key');
        $isQuery = str_ends_with($action, '.list') ||
                   str_ends_with($action, '.view') ||
                   str_starts_with($action, 'report.');

        if ($idemKey && !$isQuery) {
            $previous = $this->checkIdempotency($idemKey);
            if ($previous) {
                return response()->json([
                    'ok' => true,
                    'replayed' => true,
                    ...$previous,
                ], 200);
            }
        }

        // 5. Instantiate and execute action
        try {
            /** @var PaletteAction $actionInstance */
            $actionInstance = app($actionClass);
            $params = $request->input('params', []);

            // Validate
            if ($rules = $actionInstance->rules()) {
                $params = Validator::make($params, $rules)->validate();
            }

            // Authorize
            if ($permission = $actionInstance->permission()) {
                if (!$request->user()->hasCompanyPermission($permission)) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        "Permission denied: {$permission}"
                    );
                }
            }

            // Execute
            $result = $actionInstance->handle($params);

            // 6. Store idempotency record (mutations only)
            if ($idemKey && !$isQuery) {
                $this->storeIdempotency($idemKey, $result);
            }

            // 7. Return formatted response
            return $this->formatResponse($result, $action);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'VALIDATION',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'FORBIDDEN',
                'message' => $e->getMessage(),
            ], 403);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'code' => 'NOT_FOUND',
                'message' => 'Record not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error("Command execution failed: {$action}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $request->input('params'),
                'user' => $request->user()?->id,
                'company' => $company->id,
            ]);

            return $this->error(
                'SERVER_ERROR',
                app()->environment('production') ? 'An error occurred' : $e->getMessage(),
                500
            );
        }
    }

    private function formatResponse(array $result, string $action): JsonResponse
    {
        $isQuery = str_ends_with($action, '.list') ||
                   str_ends_with($action, '.view') ||
                   str_starts_with($action, 'report.');

        if ($isQuery) {
            return response()->json([
                'ok' => true,
                'data' => $result['data'] ?? $result,
                'meta' => $result['meta'] ?? null,
            ], 200);
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Success',
            'data' => $result['data'] ?? null,
            'redirect' => $result['redirect'] ?? null,
            'undo' => $result['undo'] ?? null,
        ], 201);
    }

    private function checkIdempotency(string $key): ?array
    {
        $record = DB::table('command_idempotency')
            ->where('key', hash('sha256', $key))
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        return $record ? json_decode($record->result, true) : null;
    }

    private function storeIdempotency(string $key, array $result): void
    {
        DB::table('command_idempotency')->updateOrInsert(
            ['key' => hash('sha256', $key)],
            [
                'result' => json_encode($result),
                'created_at' => now(),
            ]
        );
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
```

### 16.4 Idempotency Migration

```php
// database/migrations/xxxx_create_command_idempotency_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_idempotency', function (Blueprint $table) {
            $table->string('key', 64)->primary();  // SHA256 hash
            $table->jsonb('result');
            $table->timestamp('created_at');

            $table->index('created_at');  // For cleanup job
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_idempotency');
    }
};
```

### 16.5 Idempotency Cleanup Job

```php
// app/Console/Commands/CleanupIdempotencyCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupIdempotencyCommand extends Command
{
    protected $signature = 'palette:cleanup-idempotency';
    protected $description = 'Remove expired idempotency records';

    public function handle(): int
    {
        $deleted = DB::table('command_idempotency')
            ->where('created_at', '<', now()->subHours(24))
            ->delete();

        $this->info("Deleted {$deleted} expired idempotency records");

        return self::SUCCESS;
    }
}

// app/Console/Kernel.php
$schedule->command('palette:cleanup-idempotency')->dailyAt('03:00');
```

### 16.6 PostgreSQL Extensions Migration

```php
// database/migrations/xxxx_add_pg_trgm_extension.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Required for fuzzy text matching (similarity function)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};
```

### 16.7 Example Action: Invoice Create

```php
// modules/Accounting/Actions/Invoice/CreateAction.php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;  // Per CLAUDE.md conventions
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CurrentCompany;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'customer' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'due' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'draft' => 'nullable|boolean',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_CREATE;
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->get();

        // Resolve customer (by ID or fuzzy name match)
        $customer = $this->resolveCustomer($params['customer'], $company);

        if (!$customer) {
            throw new \InvalidArgumentException(
                "Customer not found: {$params['customer']}"
            );
        }

        // Create invoice in transaction
        $invoice = DB::transaction(function () use ($params, $customer, $company) {
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'amount' => $params['amount'],
                'due_date' => $params['due'] ?? now()->addDays($company->default_payment_terms ?? 30),
                'status' => ($params['draft'] ?? false) ? 'draft' : 'pending',
                'notes' => $params['notes'] ?? null,
            ]);

            // Post to GL unless draft
            if ($invoice->status !== 'draft') {
                $this->postToLedger($invoice);
            }

            return $invoice;
        });

        return [
            'message' => "Invoice {$invoice->number} created for {$customer->name}",
            'data' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'amount' => PaletteFormatter::money($invoice->amount, $company->currency),
                'customer' => $customer->name,
                'status' => $invoice->status,
            ],
            'redirect' => "/{$company->slug}/invoices/{$invoice->id}",
            'undo' => [
                'action' => 'invoice.void',
                'params' => ['id' => $invoice->id],
                'expiresAt' => now()->addSeconds(10)->timestamp,
            ],
        ];
    }

    private function resolveCustomer(string $identifier, $company): ?Customer
    {
        // Try exact ID match first
        if (is_numeric($identifier)) {
            return Customer::where('company_id', $company->id)
                ->find((int) $identifier);
        }

        // Try exact name match
        $exact = Customer::where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
            ->first();

        if ($exact) {
            return $exact;
        }

        // Fuzzy match using pg_trgm (requires extension)
        return Customer::where('company_id', $company->id)
            ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
            ->orderByRaw('similarity(name, ?) DESC', [$identifier])
            ->first();
    }

    private function postToLedger(Invoice $invoice): void
    {
        // Your existing GL posting logic
        // Debit: Accounts Receivable
        // Credit: Revenue
    }
}
```

### 16.8 Example Action: Invoice List

```php
// modules/Accounting/Actions/Invoice/IndexAction.php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;  // Per CLAUDE.md conventions
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CurrentCompany;
use App\Support\PaletteFormatter;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'customer' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,pending,sent,paid,overdue,void',
            'unpaid' => 'nullable|boolean',
            'overdue' => 'nullable|boolean',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_VIEW;
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->get();
        $limit = $params['limit'] ?? 50;

        $query = Invoice::where('company_id', $company->id)
            ->with('customer:id,name');

        // Status filters
        if ($params['unpaid'] ?? false) {
            $query->whereIn('status', ['pending', 'sent', 'overdue']);
        } elseif ($params['overdue'] ?? false) {
            $query->where('status', 'overdue');
        } elseif (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        // Customer filter
        if (isset($params['customer'])) {
            $customer = $this->resolveCustomer($params['customer'], $company);
            if ($customer) {
                $query->where('customer_id', $customer->id);
            }
        }

        // Date range
        if (isset($params['from'])) {
            $query->whereDate('created_at', '>=', $params['from']);
        }
        if (isset($params['to'])) {
            $query->whereDate('created_at', '<=', $params['to']);
        }

        $invoices = $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Format as palette table
        return [
            'data' => PaletteFormatter::table(
                headers: ['INV#', 'Customer', 'Amount', 'Due', 'Status'],
                rows: $invoices->map(fn($inv) => [
                    $inv->number,
                    $inv->customer->name,
                    PaletteFormatter::money($inv->amount, $company->currency),
                    $inv->due_date->format('M j'),
                    PaletteFormatter::status($inv->status),
                ])->toArray(),
                footer: $this->buildFooter($invoices, $company)
            ),
            'meta' => [
                'total' => $invoices->count(),
                'sum' => $invoices->sum('amount'),
            ],
        ];
    }

    private function resolveCustomer(string $identifier, $company): ?Customer
    {
        if (is_numeric($identifier)) {
            return Customer::where('company_id', $company->id)->find((int) $identifier);
        }

        return Customer::where('company_id', $company->id)
            ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
            ->orderByRaw('similarity(name, ?) DESC', [$identifier])
            ->first();
    }

    private function buildFooter($invoices, $company): string
    {
        $count = $invoices->count();
        $sum = PaletteFormatter::money($invoices->sum('amount'), $company->currency);
        return "{$count} invoice" . ($count !== 1 ? 's' : '') . " · {$sum} total";
    }
}
```

### 16.9 Palette Formatter Helper

```php
// app/Support/PaletteFormatter.php

namespace App\Support;

use App\Services\CurrentCompany;
use NumberFormatter;

class PaletteFormatter
{
    /**
     * Format data as table for palette renderer.
     */
    public static function table(array $headers, array $rows, ?string $footer = null): array
    {
        return [
            'type' => 'table',
            'headers' => $headers,
            'rows' => $rows,
            'footer' => $footer,
        ];
    }

    /**
     * Format money using company locale/currency.
     */
    public static function money(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? app(CurrentCompany::class)->get()?->currency ?? 'USD';
        $locale = app(CurrentCompany::class)->get()?->locale ?? 'en_US';

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    /**
     * Format status with semantic color tag and icon.
     */
    public static function status(string $status): string
    {
        return match ($status) {
            'paid' => '{success}● Paid{/}',
            'pending' => '{warning}◐ Pending{/}',
            'sent' => '{accent}◑ Sent{/}',
            'overdue' => '{error}⚠ Overdue{/}',
            'draft' => '{secondary}○ Draft{/}',
            'void' => '{secondary}⊘ Void{/}',
            default => $status,
        };
    }

    /**
     * Format date relative to today.
     */
    public static function relativeDate(\DateTimeInterface $date): string
    {
        $diff = now()->diffInDays($date, false);

        return match (true) {
            $diff === 0 => 'Today',
            $diff === 1 => 'Tomorrow',
            $diff === -1 => 'Yesterday',
            $diff > 0 && $diff <= 7 => "In {$diff} days",
            $diff < 0 && $diff >= -7 => abs($diff) . ' days ago',
            default => $date->format('M j'),
        };
    }

    /**
     * Success message with icon.
     */
    public static function success(string $message): string
    {
        return "{success}✓{/} {$message}";
    }

    /**
     * Error message with icon.
     */
    public static function error(string $message): string
    {
        return "{error}✗{/} {$message}";
    }

    /**
     * Warning message with icon.
     */
    public static function warning(string $message): string
    {
        return "{warning}⚠{/} {$message}";
    }
}
```

### 16.10 Company Context Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          Frontend (Vue)                                  │
│                                                                         │
│  1. User types: inv new acme 1500                                       │
│  2. Parser extracts: entity=invoice, verb=create, flags={customer,amt}  │
│  3. Company slug from URL: /{company}/dashboard → "acme-corp"           │
│  4. Generate idempotency key: SHA256(user + action + params + timestamp)│
│                                                                         │
│  POST /api/commands                                                     │
│  Headers:                                                               │
│    X-Action: invoice.create                                             │
│    X-Company-Slug: acme-corp                                            │
│    X-Idempotency-Key: a1b2c3d4...                                       │
│  Body:                                                                  │
│    { "params": { "customer": "acme", "amount": 1500 } }                 │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        Laravel Backend                                   │
│                                                                         │
│  1. CommandController receives request                                  │
│  2. Validates X-Action header format                                    │
│  3. Sets company: CurrentCompany::setBySlug('acme-corp')                │
│  4. Checks idempotency table (skip if replay)                           │
│  5. Resolves: config('command-bus.invoice.create') → CreateAction       │
│  6. Validates params against CreateAction::rules()                      │
│  7. Checks permission: CreateAction::permission()                       │
│  8. Executes: CreateAction::handle($params)                             │
│  9. Stores idempotency record                                           │
│  10. Returns formatted JSON                                             │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       Response to Frontend                               │
│                                                                         │
│  {                                                                      │
│    "ok": true,                                                          │
│    "message": "Invoice INV-001 created for Acme Corp",                  │
│    "data": {                                                            │
│      "id": 42,                                                          │
│      "number": "INV-001",                                               │
│      "amount": "$1,500.00",                                             │
│      "customer": "Acme Corp"                                            │
│    },                                                                   │
│    "redirect": "/acme-corp/invoices/42",                                │
│    "undo": {                                                            │
│      "action": "invoice.void",                                          │
│      "params": { "id": 42 },                                            │
│      "expiresAt": 1704067210                                            │
│    }                                                                    │
│  }                                                                      │
│                                                                         │
│  Palette renders:                                                       │
│  ✓ Invoice INV-001 created for Acme Corp ($1,500.00)                    │
│  → Open in GUI                                                          │
│  [Undo - 10s]                                                           │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 17. Autocomplete Data Sources

### 17.1 Catalog Controller

```php
// app/Http/Controllers/PaletteCatalogController.php

namespace App\Http\Controllers;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaletteCatalogController extends Controller
{
    private const MAX_RESULTS = 500;
    private const SEARCH_LIMIT = 50;

    public function customers(Request $request): JsonResponse
    {
        $company = app(CurrentCompany::class)->get();
        $search = $request->query('q');

        $query = Customer::where('company_id', $company->id)
            ->select('id', 'name', 'email')
            ->selectRaw('COALESCE(balance_outstanding, 0) as outstanding');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('name ILIKE ?', ["%{$search}%"])
                  ->orWhereRaw('email ILIKE ?', ["%{$search}%"]);
            })->limit(self::SEARCH_LIMIT);
        } else {
            $query->orderBy('name')->limit(self::MAX_RESULTS);
        }

        $customers = $query->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'searchText' => strtolower("{$c->name} {$c->email}"),
            'meta' => $c->outstanding > 0
                ? ['outstanding' => PaletteFormatter::money($c->outstanding)]
                : null,
        ]);

        return response()->json($customers)
            ->header('Cache-Control', 'private, max-age=300');  // 5 min
    }

    public function vendors(Request $request): JsonResponse
    {
        $company = app(CurrentCompany::class)->get();
        $search = $request->query('q');

        $query = Vendor::where('company_id', $company->id)
            ->select('id', 'name', 'email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('name ILIKE ?', ["%{$search}%"])
                  ->orWhereRaw('email ILIKE ?', ["%{$search}%"]);
            })->limit(self::SEARCH_LIMIT);
        } else {
            $query->orderBy('name')->limit(self::MAX_RESULTS);
        }

        $vendors = $query->get()->map(fn($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'searchText' => strtolower("{$v->name} {$v->email}"),
        ]);

        return response()->json($vendors)
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function accounts(): JsonResponse
    {
        $company = app(CurrentCompany::class)->get();

        $accounts = Account::where('company_id', $company->id)
            ->select('id', 'code', 'name', 'type')
            ->orderBy('code')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => "{$a->code} - {$a->name}",
                'searchText' => strtolower("{$a->code} {$a->name}"),
                'meta' => ['type' => $a->type],
            ]);

        return response()->json($accounts)
            ->header('Cache-Control', 'private, max-age=600');  // 10 min
    }

    public function recentInvoices(): JsonResponse
    {
        $company = app(CurrentCompany::class)->get();

        $invoices = Invoice::where('company_id', $company->id)
            ->with('customer:id,name')
            ->whereIn('status', ['pending', 'sent', 'overdue'])  // Active only
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn($i) => [
                'id' => $i->id,
                'number' => $i->number,
                'searchText' => strtolower("{$i->number} {$i->customer->name}"),
                'meta' => [
                    'customer' => $i->customer->name,
                    'amount' => PaletteFormatter::money($i->amount),
                    'status' => $i->status,
                ],
            ]);

        return response()->json($invoices)
            ->header('Cache-Control', 'private, max-age=60');  // 1 min
    }
}
```

### 17.2 Frontend Catalog Composable

```typescript
// composables/useEntityCatalogs.ts

import { ref, onMounted } from 'vue'

interface CatalogEntity {
  id: number | string
  name: string
  searchText: string
  meta?: Record<string, unknown>
}

export function useEntityCatalogs() {
  const customers = ref<CatalogEntity[]>([])
  const vendors = ref<CatalogEntity[]>([])
  const accounts = ref<CatalogEntity[]>([])
  const recentInvoices = ref<CatalogEntity[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load() {
    loading.value = true
    error.value = null

    try {
      const [c, v, a, i] = await Promise.all([
        fetch('/api/palette/customers').then(r => r.ok ? r.json() : []),
        fetch('/api/palette/vendors').then(r => r.ok ? r.json() : []),
        fetch('/api/palette/accounts').then(r => r.ok ? r.json() : []),
        fetch('/api/palette/invoices/recent').then(r => r.ok ? r.json() : []),
      ])

      customers.value = c
      vendors.value = v
      accounts.value = a
      recentInvoices.value = i
    } catch (e) {
      error.value = 'Failed to load catalogs'
      console.error('Catalog load failed:', e)
    } finally {
      loading.value = false
    }
  }

  // Search with server-side filtering (for large datasets)
  async function searchCustomers(query: string): Promise<CatalogEntity[]> {
    if (!query || query.length < 2) return customers.value.slice(0, 10)

    const response = await fetch(`/api/palette/customers?q=${encodeURIComponent(query)}`)
    return response.ok ? response.json() : []
  }

  async function searchVendors(query: string): Promise<CatalogEntity[]> {
    if (!query || query.length < 2) return vendors.value.slice(0, 10)

    const response = await fetch(`/api/palette/vendors?q=${encodeURIComponent(query)}`)
    return response.ok ? response.json() : []
  }

  onMounted(() => load())

  return {
    customers,
    vendors,
    accounts,
    recentInvoices,
    loading,
    error,
    reload: load,
    searchCustomers,
    searchVendors,
  }
}
```

### 17.3 Module Structure

```
app/
├── Contracts/
│   └── PaletteAction.php              # Action interface
├── Http/Controllers/
│   ├── CommandController.php          # Single command endpoint
│   └── PaletteCatalogController.php   # Entity catalogs
├── Support/
│   └── PaletteFormatter.php           # Response formatting
└── Console/Commands/
    └── CleanupIdempotencyCommand.php  # Daily cleanup

config/
└── command-bus.php                    # Action routing map

modules/
├── Accounting/
│   ├── Actions/
│   │   ├── Invoice/
│   │   │   ├── CreateAction.php
│   │   │   ├── IndexAction.php
│   │   │   ├── ShowAction.php
│   │   │   ├── SendAction.php
│   │   │   └── VoidAction.php
│   │   ├── Payment/
│   │   │   ├── CreateAction.php
│   │   │   ├── IndexAction.php
│   │   │   └── VoidAction.php
│   │   ├── Customer/
│   │   │   ├── CreateAction.php
│   │   │   ├── IndexAction.php
│   │   │   └── UpdateAction.php
│   │   ├── Vendor/
│   │   │   ├── CreateAction.php
│   │   │   └── IndexAction.php
│   │   ├── Bill/
│   │   │   ├── CreateAction.php
│   │   │   ├── IndexAction.php
│   │   │   └── PayAction.php
│   │   ├── Expense/
│   │   │   ├── CreateAction.php
│   │   │   └── IndexAction.php
│   │   └── Report/
│   │       ├── AgingAction.php
│   │       ├── ProfitLossAction.php
│   │       └── BalanceSheetAction.php
│   └── Models/
│       └── ...
│
└── Core/
    └── Actions/
        └── Company/
            ├── CreateAction.php
            ├── IndexAction.php
            ├── SwitchAction.php
            └── ShowAction.php

database/migrations/
├── xxxx_create_command_idempotency_table.php
└── xxxx_add_pg_trgm_extension.php
```

---

*Document version: 3.3 (Final)*
*Stack: Custom Vue 3, no external terminal libraries*
*Theming: Posting.sh-inspired 7-color semantic system (galaxy, monokai, nord, dracula)*
*Backend: Laravel command bus pattern with Action classes*
