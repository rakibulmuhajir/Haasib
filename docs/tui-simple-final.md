# TUI Accounting App — Detailed Implementation Guide

A step-by-step guide for building a terminal-style interface with Vue 3, Inertia, and Laravel.

---

## Table of Contents

1. [Project Setup](#1-project-setup)
2. [Design System & Tokens](#2-design-system--tokens)
3. [Core Terminal Shell](#3-core-terminal-shell)
4. [Keyboard Management](#4-keyboard-management)
5. [Command Parser](#5-command-parser)
6. [Output System](#6-output-system)
7. [Autocomplete & Dropdowns](#7-autocomplete--dropdowns)
8. [Dialog System](#8-dialog-system)
9. [Table Navigation](#9-table-navigation)
10. [Transaction Entry Form](#10-transaction-entry-form)
11. [Backend Integration](#11-backend-integration)
12. [State Management](#12-state-management)
13. [Testing Checklist](#13-testing-checklist)

---

## 1. Project Setup

### 1.1 Directory Structure

Create this structure in your Vue app:

```
src/
├── components/
│   └── tui/
│       ├── core/
│       │   ├── TerminalShell.vue       # Main container
│       │   ├── TerminalOutput.vue      # Output history display
│       │   ├── CommandLine.vue         # Input line with cursor
│       │   ├── Cursor.vue              # Blinking cursor animation
│       │   └── StatusBar.vue           # Bottom status bar
│       │
│       ├── input/
│       │   ├── SuggestionDropdown.vue  # Autocomplete dropdown
│       │   ├── GhostText.vue           # Faded completion preview
│       │   └── InlineValidation.vue    # Error/success indicators
│       │
│       ├── display/
│       │   ├── TableView.vue           # Navigable data table
│       │   ├── OutputBlock.vue         # Single output entry
│       │   └── LoadingSpinner.vue      # Animated loading indicator
│       │
│       ├── dialogs/
│       │   ├── DialogContainer.vue     # Dialog wrapper/backdrop
│       │   ├── ConfirmDialog.vue       # Yes/No confirmation
│       │   ├── AlertDialog.vue         # Info/Error alert
│       │   └── FormDialog.vue          # Multi-field input dialog
│       │
│       └── forms/
│           ├── TransactionForm.vue     # Quick transaction entry
│           ├── AccountForm.vue         # Account creation
│           └── FormField.vue           # Reusable form field
│
├── composables/
│   ├── useKeyboard.ts                  # Keyboard event handling
│   ├── useCommandParser.ts             # Parse command strings
│   ├── useCommandExecutor.ts           # Execute parsed commands
│   ├── useAutocomplete.ts              # Suggestion logic
│   ├── useValidation.ts                # Input validation
│   ├── useDialog.ts                    # Dialog management
│   ├── useUndo.ts                      # Undo/redo stack
│   └── useSmartDefaults.ts             # Learning from usage patterns
│
├── stores/
│   ├── terminal.ts                     # Terminal UI state
│   ├── accounts.ts                     # Account data cache
│   ├── transactions.ts                 # Transaction data
│   └── commands.ts                     # Command registry & history
│
├── types/
│   └── tui.ts                          # TypeScript interfaces
│
└── utils/
    ├── formatters.ts                   # Currency, date formatting
    ├── textHelpers.ts                  # String manipulation
    └── commandGrammar.ts               # Command definitions
```

### 1.2 Install Dependencies

```bash
# Tailwind (if not already installed)
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p

# State management
npm install pinia

# Utilities
npm install lodash-es
npm install @vueuse/core

# TypeScript types
npm install -D @types/lodash-es
```

### 1.3 Font Setup

Add to your main CSS file (`resources/css/app.css`):

```css
/* Import JetBrains Mono from Google Fonts */
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap');

/* Base terminal styles */
.tui-container {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    line-height: 1.5;
    letter-spacing: 0;
}

/* Ensure consistent character width */
.tui-container * {
    font-variant-ligatures: none;
}
```

---

## 2. Design System & Tokens

### 2.1 Tailwind Configuration

Update `tailwind.config.js`:

```javascript
/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                tui: {
                    // Backgrounds
                    'bg-primary': '#0f1419',      // Main background
                    'bg-secondary': '#151a1f',    // Elevated surfaces
                    'bg-tertiary': '#1a2027',     // Hover states
                    'bg-highlight': '#232a32',    // Selected row

                    // Borders
                    'border-subtle': '#2d3640',   // Subtle lines
                    'border-default': '#3d4750',  // Default borders
                    'border-focus': '#56d4dd',    // Focused element

                    // Text
                    'text-primary': '#e6e6e6',    // Main text
                    'text-secondary': '#9da5b4',  // Secondary text
                    'text-muted': '#5c6370',      // Hints, disabled
                    'text-placeholder': '#4b5263', // Placeholder text

                    // Semantic colors
                    'primary': '#56d4dd',         // Cyan - commands, active
                    'success': '#98c379',         // Green - credits, success
                    'warning': '#e5c07b',         // Amber - pending, warning
                    'danger': '#e06c75',          // Red - debits, errors
                    'accent': '#c678dd',          // Purple - amounts, special
                    'info': '#61afef',            // Blue - info, links

                    // Interactive states
                    'hover': '#2c3540',           // Hover background
                    'active': '#3c4550',          // Active/pressed
                    'selected': '#1e3a4d',        // Selected item bg
                },
            },
            fontFamily: {
                mono: ['JetBrains Mono', 'Fira Code', 'Consolas', 'monospace'],
            },
            fontSize: {
                'tui-sm': ['12px', '18px'],
                'tui-base': ['14px', '21px'],
                'tui-lg': ['16px', '24px'],
            },
            spacing: {
                'tui-char': '8.4px',  // Approximate character width
            },
            animation: {
                'cursor-blink': 'blink 1s step-end infinite',
                'fade-in': 'fadeIn 0.15s ease-out',
                'slide-up': 'slideUp 0.15s ease-out',
            },
            keyframes: {
                blink: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0' },
                },
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { opacity: '0', transform: 'translateY(4px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
        },
    },
    plugins: [],
}
```

### 2.2 CSS Utility Classes

Create `resources/css/tui-utilities.css`:

```css
/* Box drawing characters need consistent sizing */
.tui-box {
    @apply font-mono text-tui-base;
}

/* Standard box border using Unicode */
.tui-border {
    border: 1px solid theme('colors.tui.border-default');
}

/* Focus ring for accessibility */
.tui-focus-ring {
    @apply outline-none ring-1 ring-tui-primary ring-offset-0;
}

/* Text selection */
.tui-container ::selection {
    @apply bg-tui-primary/30 text-tui-text-primary;
}

/* Scrollbar styling */
.tui-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.tui-scrollbar::-webkit-scrollbar-track {
    @apply bg-tui-bg-primary;
}

.tui-scrollbar::-webkit-scrollbar-thumb {
    @apply bg-tui-border-default rounded;
}

.tui-scrollbar::-webkit-scrollbar-thumb:hover {
    @apply bg-tui-border-focus;
}
```

### 2.3 TypeScript Types

Create `src/types/tui.ts`:

```typescript
// ======================
// CORE TYPES
// ======================

/**
 * UI Mode determines which component handles keyboard input
 */
export type UIMode =
    | 'command'     // Default - typing in command line
    | 'browse'      // Navigating a table or list
    | 'dialog'      // Modal dialog is open
    | 'form'        // Multi-field form is open
    | 'search'      // Search mode active
    | 'palette'     // Command palette open

/**
 * Output block types for terminal history
 */
export type OutputBlockType =
    | 'command'     // User-entered command
    | 'result'      // Command result text
    | 'error'       // Error message
    | 'success'     // Success message
    | 'warning'     // Warning message
    | 'info'        // Informational message
    | 'table'       // Data table
    | 'loading'     // Loading indicator

/**
 * Single output entry in terminal history
 */
export interface OutputBlock {
    id: string
    type: OutputBlockType
    timestamp: Date
    content: OutputContent
}

export type OutputContent =
    | { type: 'text'; text: string }
    | { type: 'lines'; lines: string[] }
    | { type: 'table'; table: TableData }

export interface TableData {
    headers: TableHeader[]
    rows: TableRow[]
    summary?: TableRow  // Optional totals row
}

export interface TableHeader {
    key: string
    label: string
    align: 'left' | 'right' | 'center'
    width?: number  // Character width
}

export interface TableRow {
    id: string | number
    cells: Record<string, TableCell>
    selectable?: boolean
    meta?: Record<string, any>  // Additional data for actions
}

export interface TableCell {
    value: string
    color?: 'primary' | 'success' | 'warning' | 'danger' | 'accent' | 'muted'
}

// ======================
// COMMAND TYPES
// ======================

/**
 * Parsed command structure
 */
export interface ParsedCommand {
    raw: string                          // Original input string
    verb: string | null                  // e.g., "add", "list", "delete"
    noun: string | null                  // e.g., "tx", "account"
    args: string[]                       // Positional arguments
    params: Record<string, string>       // Named parameters (key:value)
    flags: string[]                      // Boolean flags (--force)
    valid: boolean
    errors: ValidationError[]
    cursorContext: CursorContext         // Where cursor is for autocomplete
}

export interface ValidationError {
    field: string
    message: string
    type: 'error' | 'warning'
}

export interface CursorContext {
    position: number                     // Cursor position in string
    segment: 'verb' | 'noun' | 'param-key' | 'param-value' | 'arg' | 'flag'
    currentWord: string                  // Word being typed
    paramKey?: string                    // If in param-value, which param
}

/**
 * Command definition for registry
 */
export interface CommandDefinition {
    verb: string
    noun?: string
    description: string
    usage: string
    params: ParamDefinition[]
    flags: FlagDefinition[]
    examples: string[]
    handler: string                      // Name of handler function
}

export interface ParamDefinition {
    key: string
    label: string
    required: boolean
    type: 'string' | 'number' | 'date' | 'account' | 'amount'
    description: string
    autocomplete?: 'accounts' | 'tags' | 'recent' | 'none'
    validate?: (value: string) => ValidationError | null
}

export interface FlagDefinition {
    name: string
    short?: string                       // e.g., "-f" for "--force"
    description: string
}

// ======================
// AUTOCOMPLETE TYPES
// ======================

export interface Suggestion {
    value: string                        // The actual value to insert
    label: string                        // Display label
    description?: string                 // Secondary text
    icon?: string                        // Optional icon/emoji
    category?: string                    // Group header
    score: number                        // Relevance score (higher = better)
    meta?: Record<string, any>           // Additional data
}

export interface AutocompleteState {
    active: boolean
    suggestions: Suggestion[]
    selectedIndex: number
    loading: boolean
    query: string
    context: CursorContext | null
}

// ======================
// DIALOG TYPES
// ======================

export type DialogType = 'confirm' | 'alert' | 'form' | 'select'

export interface DialogConfig {
    id: string
    type: DialogType
    title: string
    message?: string
    confirmLabel?: string
    cancelLabel?: string
    confirmKey?: string                  // Keyboard shortcut (default: 'y' or Enter)
    cancelKey?: string                   // Keyboard shortcut (default: 'n' or Escape)
    destructive?: boolean                // Red confirm button
    fields?: FormFieldConfig[]           // For form dialogs
    options?: SelectOption[]             // For select dialogs
}

export interface FormFieldConfig {
    key: string
    label: string
    type: 'text' | 'number' | 'date' | 'select' | 'account'
    required: boolean
    placeholder?: string
    defaultValue?: string
    autocomplete?: 'accounts' | 'tags' | 'none'
    validate?: (value: string) => string | null
}

export interface SelectOption {
    value: string
    label: string
    description?: string
}

export interface DialogResult<T = any> {
    confirmed: boolean
    data?: T
}

// ======================
// FORM TYPES
// ======================

export interface TransactionFormData {
    date: string
    entries: TransactionEntry[]
    memo: string
    tags: string[]
}

export interface TransactionEntry {
    id: string
    type: 'debit' | 'credit'
    account: string
    amount: string
}

export interface AccountFormData {
    name: string
    type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'
    parent?: string
    code?: string
    description?: string
}

// ======================
// API RESPONSE TYPES
// ======================

export interface Account {
    id: number
    name: string
    slug: string                         // URL-safe identifier
    type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'
    parent_id: number | null
    code: string | null
    balance: number
    currency: string
    created_at: string
    updated_at: string
}

export interface Transaction {
    id: number
    date: string
    memo: string | null
    entries: JournalEntry[]
    tags: string[]
    created_at: string
    updated_at: string
}

export interface JournalEntry {
    id: number
    account_id: number
    account_name: string
    debit: number | null
    credit: number | null
}

export interface ApiResponse<T> {
    success: boolean
    data?: T
    error?: string
    errors?: Record<string, string[]>
}

export interface PaginatedResponse<T> {
    data: T[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
}
```

---

## 3. Core Terminal Shell

### 3.1 TerminalShell.vue — The Main Container

This is the root component that wraps everything.

```vue
<!-- src/components/tui/core/TerminalShell.vue -->
<script setup lang="ts">
import { ref, onMounted, onUnmounted, provide, computed } from 'vue'
import { useTerminalStore } from '@/stores/terminal'
import { useKeyboard } from '@/composables/useKeyboard'
import TerminalOutput from './TerminalOutput.vue'
import CommandLine from './CommandLine.vue'
import StatusBar from './StatusBar.vue'
import DialogContainer from '../dialogs/DialogContainer.vue'
import SuggestionDropdown from '../input/SuggestionDropdown.vue'

// ======================
// STORE & STATE
// ======================
const terminalStore = useTerminalStore()
const containerRef = ref<HTMLElement | null>(null)
const commandLineRef = ref<InstanceType<typeof CommandLine> | null>(null)

// ======================
// KEYBOARD HANDLING
// ======================
const { handleKeyDown } = useKeyboard({
    mode: computed(() => terminalStore.mode),
    onCommand: (key) => handleCommandModeKey(key),
    onBrowse: (key) => handleBrowseModeKey(key),
    onDialog: (key) => handleDialogModeKey(key),
    onGlobal: (key) => handleGlobalKey(key),
})

function handleCommandModeKey(event: KeyboardEvent) {
    // Forward to command line component
    commandLineRef.value?.handleKey(event)
}

function handleBrowseModeKey(event: KeyboardEvent) {
    // Handled by TableView when active
    // This is a fallback
    if (event.key === 'Escape' || event.key === 'q') {
        terminalStore.setMode('command')
    }
}

function handleDialogModeKey(event: KeyboardEvent) {
    // Handled by DialogContainer
}

function handleGlobalKey(event: KeyboardEvent) {
    // Global shortcuts that work in any mode

    // Ctrl+P: Command palette
    if (event.ctrlKey && event.key === 'p') {
        event.preventDefault()
        terminalStore.togglePalette()
        return
    }

    // Ctrl+N: New transaction
    if (event.ctrlKey && event.key === 'n') {
        event.preventDefault()
        terminalStore.openTransactionForm()
        return
    }

    // Ctrl+L: Clear screen
    if (event.ctrlKey && event.key === 'l') {
        event.preventDefault()
        terminalStore.clearOutput()
        return
    }
}

// ======================
// FOCUS MANAGEMENT
// ======================
function focusTerminal() {
    containerRef.value?.focus()
}

// Expose method for parent components
defineExpose({ focus: focusTerminal })

// ======================
// LIFECYCLE
// ======================
onMounted(() => {
    // Auto-focus terminal on mount
    focusTerminal()

    // Print welcome message
    terminalStore.addOutput({
        type: 'info',
        content: {
            type: 'lines',
            lines: [
                '╭─────────────────────────────────────────────╮',
                '│  LEDGER - Double Entry Accounting           │',
                '│  Type "help" for commands, Ctrl+P palette   │',
                '╰─────────────────────────────────────────────╯',
                ''
            ]
        }
    })
})

// ======================
// PROVIDE TO CHILDREN
// ======================
provide('terminalFocus', focusTerminal)
</script>

<template>
    <div
        ref="containerRef"
        class="tui-container tui-scrollbar"
        tabindex="0"
        @keydown="handleKeyDown"
    >
        <!-- Main terminal area -->
        <div class="tui-main">
            <!-- Scrollable output history -->
            <TerminalOutput
                :blocks="terminalStore.outputHistory"
                class="tui-output"
            />

            <!-- Command input line -->
            <div class="tui-input-area">
                <CommandLine
                    ref="commandLineRef"
                    v-model="terminalStore.currentInput"
                    :disabled="terminalStore.mode !== 'command'"
                    @submit="terminalStore.executeCommand"
                />

                <!-- Autocomplete dropdown (positioned above input) -->
                <SuggestionDropdown
                    v-if="terminalStore.autocomplete.active"
                    :suggestions="terminalStore.autocomplete.suggestions"
                    :selected-index="terminalStore.autocomplete.selectedIndex"
                    :loading="terminalStore.autocomplete.loading"
                    @select="terminalStore.acceptSuggestion"
                />
            </div>
        </div>

        <!-- Status bar at bottom -->
        <StatusBar
            :mode="terminalStore.mode"
            :hints="terminalStore.contextHints"
        />

        <!-- Dialog overlay -->
        <DialogContainer
            v-if="terminalStore.activeDialog"
            :config="terminalStore.activeDialog"
            @close="terminalStore.closeDialog"
        />
    </div>
</template>

<style scoped>
.tui-container {
    @apply h-screen w-full flex flex-col;
    @apply bg-tui-bg-primary text-tui-text-primary;
    @apply font-mono text-tui-base;
    @apply outline-none;
    @apply overflow-hidden;
}

.tui-main {
    @apply flex-1 flex flex-col min-h-0;
    @apply px-4 pt-4;
}

.tui-output {
    @apply flex-1 overflow-y-auto;
    @apply mb-2;
}

.tui-input-area {
    @apply relative;
    @apply pb-2;
}
</style>
```

### 3.2 CommandLine.vue — Input with Cursor

```vue
<!-- src/components/tui/core/CommandLine.vue -->
<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useCommandParser } from '@/composables/useCommandParser'
import Cursor from './Cursor.vue'
import GhostText from '../input/GhostText.vue'
import InlineValidation from '../input/InlineValidation.vue'

// ======================
// PROPS & EMITS
// ======================
const props = defineProps<{
    modelValue: string
    disabled: boolean
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'submit': [command: string]
}>()

// ======================
// LOCAL STATE
// ======================
const cursorPosition = ref(0)
const inputRef = ref<HTMLDivElement | null>(null)

// ======================
// COMMAND PARSING
// ======================
const { parse, getGhostText, getValidation } = useCommandParser()

const parsed = computed(() => parse(props.modelValue, cursorPosition.value))
const ghostText = computed(() => getGhostText(props.modelValue, parsed.value))
const validation = computed(() => getValidation(parsed.value))

// ======================
// TEXT BEFORE/AFTER CURSOR
// ======================
const textBeforeCursor = computed(() =>
    props.modelValue.substring(0, cursorPosition.value)
)
const textAfterCursor = computed(() =>
    props.modelValue.substring(cursorPosition.value)
)

// ======================
// KEY HANDLING
// ======================
function handleKey(event: KeyboardEvent) {
    if (props.disabled) return

    const key = event.key
    const ctrl = event.ctrlKey
    const meta = event.metaKey

    // Prevent default for handled keys
    const handled = handleKeyInternal(key, ctrl, meta, event)
    if (handled) {
        event.preventDefault()
    }
}

function handleKeyInternal(
    key: string,
    ctrl: boolean,
    meta: boolean,
    event: KeyboardEvent
): boolean {
    // ======================
    // SUBMISSION
    // ======================
    if (key === 'Enter' && !ctrl) {
        if (props.modelValue.trim()) {
            emit('submit', props.modelValue)
        }
        return true
    }

    // ======================
    // CURSOR MOVEMENT
    // ======================
    if (key === 'ArrowLeft') {
        if (ctrl || meta) {
            // Move to previous word
            cursorPosition.value = findPreviousWordBoundary()
        } else {
            cursorPosition.value = Math.max(0, cursorPosition.value - 1)
        }
        return true
    }

    if (key === 'ArrowRight') {
        if (ctrl || meta) {
            // Move to next word
            cursorPosition.value = findNextWordBoundary()
        } else {
            cursorPosition.value = Math.min(props.modelValue.length, cursorPosition.value + 1)
        }
        return true
    }

    if (key === 'Home' || (ctrl && key === 'a')) {
        cursorPosition.value = 0
        return true
    }

    if (key === 'End' || (ctrl && key === 'e')) {
        cursorPosition.value = props.modelValue.length
        return true
    }

    // ======================
    // DELETION
    // ======================
    if (key === 'Backspace') {
        if (cursorPosition.value > 0) {
            const newValue =
                props.modelValue.substring(0, cursorPosition.value - 1) +
                props.modelValue.substring(cursorPosition.value)
            emit('update:modelValue', newValue)
            cursorPosition.value--
        }
        return true
    }

    if (key === 'Delete') {
        if (cursorPosition.value < props.modelValue.length) {
            const newValue =
                props.modelValue.substring(0, cursorPosition.value) +
                props.modelValue.substring(cursorPosition.value + 1)
            emit('update:modelValue', newValue)
        }
        return true
    }

    // Ctrl+U: Delete line before cursor
    if (ctrl && key === 'u') {
        emit('update:modelValue', props.modelValue.substring(cursorPosition.value))
        cursorPosition.value = 0
        return true
    }

    // Ctrl+K: Delete line after cursor
    if (ctrl && key === 'k') {
        emit('update:modelValue', props.modelValue.substring(0, cursorPosition.value))
        return true
    }

    // Ctrl+W: Delete word before cursor
    if (ctrl && key === 'w') {
        const boundary = findPreviousWordBoundary()
        const newValue =
            props.modelValue.substring(0, boundary) +
            props.modelValue.substring(cursorPosition.value)
        emit('update:modelValue', newValue)
        cursorPosition.value = boundary
        return true
    }

    // ======================
    // CHARACTER INPUT
    // ======================
    if (key.length === 1 && !ctrl && !meta) {
        const newValue =
            props.modelValue.substring(0, cursorPosition.value) +
            key +
            props.modelValue.substring(cursorPosition.value)
        emit('update:modelValue', newValue)
        cursorPosition.value++
        return true
    }

    return false
}

// ======================
// WORD BOUNDARY HELPERS
// ======================
function findPreviousWordBoundary(): number {
    const text = props.modelValue
    let pos = cursorPosition.value - 1

    // Skip any spaces immediately before cursor
    while (pos > 0 && text[pos] === ' ') pos--

    // Find start of current word
    while (pos > 0 && text[pos - 1] !== ' ' && text[pos - 1] !== ':') pos--

    return Math.max(0, pos)
}

function findNextWordBoundary(): number {
    const text = props.modelValue
    let pos = cursorPosition.value

    // Skip current word
    while (pos < text.length && text[pos] !== ' ' && text[pos] !== ':') pos++

    // Skip any spaces
    while (pos < text.length && text[pos] === ' ') pos++

    return pos
}

// ======================
// RESET CURSOR ON CLEAR
// ======================
watch(() => props.modelValue, (newVal, oldVal) => {
    // If input was cleared externally, reset cursor
    if (newVal === '' && oldVal !== '') {
        cursorPosition.value = 0
    }
    // Keep cursor within bounds
    if (cursorPosition.value > newVal.length) {
        cursorPosition.value = newVal.length
    }
})

// ======================
// EXPOSE FOR PARENT
// ======================
defineExpose({ handleKey })
</script>

<template>
    <div class="command-line" ref="inputRef">
        <!-- Prompt symbol -->
        <span class="prompt">❯</span>

        <!-- Input display area -->
        <div class="input-display">
            <!-- Text before cursor -->
            <span class="text-content">{{ textBeforeCursor }}</span>

            <!-- Cursor -->
            <Cursor :active="!disabled" />

            <!-- Text after cursor -->
            <span class="text-content">{{ textAfterCursor }}</span>

            <!-- Ghost text (autocomplete preview) -->
            <GhostText
                v-if="ghostText && !disabled"
                :text="ghostText"
            />
        </div>

        <!-- Inline validation indicator -->
        <InlineValidation
            v-if="validation && modelValue.length > 0"
            :validation="validation"
        />
    </div>
</template>

<style scoped>
.command-line {
    @apply flex items-center gap-2;
    @apply py-1;
}

.prompt {
    @apply text-tui-primary font-bold;
    @apply select-none;
}

.input-display {
    @apply flex-1 flex items-center;
    @apply whitespace-pre;
}

.text-content {
    @apply text-tui-text-primary;
}
</style>
```

### 3.3 Cursor.vue — Blinking Block Cursor

```vue
<!-- src/components/tui/core/Cursor.vue -->
<script setup lang="ts">
defineProps<{
    active: boolean
}>()
</script>

<template>
    <span
        class="cursor"
        :class="{
            'cursor--active': active,
            'cursor--inactive': !active
        }"
    >
        &nbsp;
    </span>
</template>

<style scoped>
.cursor {
    @apply inline-block;
    @apply w-[0.6em] h-[1.2em];
    @apply -mb-[0.2em]; /* Align with text baseline */
}

.cursor--active {
    @apply bg-tui-primary;
    @apply animate-cursor-blink;
}

.cursor--inactive {
    @apply bg-tui-text-muted;
    @apply opacity-50;
    animation: none;
}
</style>
```

### 3.4 StatusBar.vue — Bottom Information Bar

```vue
<!-- src/components/tui/core/StatusBar.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { UIMode } from '@/types/tui'

const props = defineProps<{
    mode: UIMode
    hints: string[]
}>()

const modeDisplay = computed(() => {
    const modes: Record<UIMode, { label: string; color: string }> = {
        command: { label: 'COMMAND', color: 'text-tui-primary' },
        browse: { label: 'BROWSE', color: 'text-tui-info' },
        dialog: { label: 'DIALOG', color: 'text-tui-warning' },
        form: { label: 'FORM', color: 'text-tui-accent' },
        search: { label: 'SEARCH', color: 'text-tui-success' },
        palette: { label: 'PALETTE', color: 'text-tui-accent' },
    }
    return modes[props.mode] || modes.command
})

// Default hints based on mode
const defaultHints = computed(() => {
    switch (props.mode) {
        case 'command':
            return ['Ctrl+P palette', 'Ctrl+N new tx', '/ search', '? help']
        case 'browse':
            return ['j/k navigate', 'Enter select', 'e edit', 'q back']
        case 'dialog':
            return ['Enter confirm', 'Esc cancel']
        case 'form':
            return ['Tab next', 'Shift+Tab prev', 'Esc cancel']
        case 'search':
            return ['Enter search', 'Esc cancel']
        default:
            return []
    }
})

const displayHints = computed(() =>
    props.hints.length > 0 ? props.hints : defaultHints.value
)
</script>

<template>
    <div class="status-bar">
        <!-- Mode indicator -->
        <div class="status-mode">
            <span
                class="mode-label"
                :class="modeDisplay.color"
            >
                {{ modeDisplay.label }}
            </span>
        </div>

        <!-- Separator -->
        <span class="separator">│</span>

        <!-- Keyboard hints -->
        <div class="status-hints">
            <span
                v-for="(hint, index) in displayHints"
                :key="index"
                class="hint"
            >
                {{ hint }}
                <span v-if="index < displayHints.length - 1" class="hint-sep">
                    &nbsp;&nbsp;
                </span>
            </span>
        </div>

        <!-- Right side: time or other info -->
        <div class="status-right">
            <slot name="right" />
        </div>
    </div>
</template>

<style scoped>
.status-bar {
    @apply flex items-center gap-2;
    @apply px-4 py-1;
    @apply bg-tui-bg-secondary;
    @apply border-t border-tui-border-subtle;
    @apply text-tui-sm text-tui-text-secondary;
}

.status-mode {
    @apply flex-shrink-0;
}

.mode-label {
    @apply font-semibold;
}

.separator {
    @apply text-tui-border-default;
}

.status-hints {
    @apply flex-1 flex items-center;
}

.hint {
    @apply text-tui-text-muted;
}

.hint-sep {
    @apply text-tui-border-subtle;
}

.status-right {
    @apply flex-shrink-0;
    @apply text-tui-text-muted;
}
</style>
```

---

## 4. Keyboard Management

### 4.1 useKeyboard Composable

This is the central keyboard handler that routes keys based on current mode.

```typescript
// src/composables/useKeyboard.ts
import { computed, type ComputedRef } from 'vue'
import type { UIMode } from '@/types/tui'

interface UseKeyboardOptions {
    mode: ComputedRef<UIMode>
    onCommand: (event: KeyboardEvent) => void
    onBrowse: (event: KeyboardEvent) => void
    onDialog: (event: KeyboardEvent) => void
    onForm?: (event: KeyboardEvent) => void
    onSearch?: (event: KeyboardEvent) => void
    onPalette?: (event: KeyboardEvent) => void
    onGlobal: (event: KeyboardEvent) => void
}

export function useKeyboard(options: UseKeyboardOptions) {
    const {
        mode,
        onCommand,
        onBrowse,
        onDialog,
        onForm,
        onSearch,
        onPalette,
        onGlobal,
    } = options

    /**
     * Main key handler - called on every keydown
     */
    function handleKeyDown(event: KeyboardEvent) {
        // ======================
        // GLOBAL SHORTCUTS FIRST
        // ======================
        // These work in any mode

        // Ctrl+P: Command palette
        if (event.ctrlKey && event.key === 'p') {
            event.preventDefault()
            onGlobal(event)
            return
        }

        // Ctrl+N: New transaction
        if (event.ctrlKey && event.key === 'n') {
            event.preventDefault()
            onGlobal(event)
            return
        }

        // Ctrl+L: Clear
        if (event.ctrlKey && event.key === 'l') {
            event.preventDefault()
            onGlobal(event)
            return
        }

        // ======================
        // MODE-SPECIFIC HANDLING
        // ======================
        switch (mode.value) {
            case 'command':
                onCommand(event)
                break

            case 'browse':
                handleBrowseMode(event)
                break

            case 'dialog':
                onDialog(event)
                break

            case 'form':
                if (onForm) onForm(event)
                break

            case 'search':
                if (onSearch) onSearch(event)
                break

            case 'palette':
                if (onPalette) onPalette(event)
                break
        }
    }

    /**
     * Browse mode - vim-style navigation
     */
    function handleBrowseMode(event: KeyboardEvent) {
        const key = event.key

        // Vim navigation keys
        const navKeys = ['j', 'k', 'h', 'l', 'g', 'G', 'Enter', 'Escape', 'q', 'e', 'd', '/', 'n', 'N']

        if (navKeys.includes(key)) {
            event.preventDefault()
            onBrowse(event)
            return
        }

        // Arrow keys also work
        if (key.startsWith('Arrow')) {
            event.preventDefault()
            onBrowse(event)
            return
        }
    }

    /**
     * Check if a key combination matches a shortcut
     */
    function matchesShortcut(
        event: KeyboardEvent,
        shortcut: string
    ): boolean {
        const parts = shortcut.toLowerCase().split('+')
        const key = parts[parts.length - 1]
        const needsCtrl = parts.includes('ctrl')
        const needsShift = parts.includes('shift')
        const needsAlt = parts.includes('alt')
        const needsMeta = parts.includes('meta') || parts.includes('cmd')

        return (
            event.key.toLowerCase() === key &&
            event.ctrlKey === needsCtrl &&
            event.shiftKey === needsShift &&
            event.altKey === needsAlt &&
            event.metaKey === needsMeta
        )
    }

    return {
        handleKeyDown,
        matchesShortcut,
    }
}
```

### 4.2 Key Binding Reference

Create a constant file for all keyboard shortcuts:

```typescript
// src/utils/keybindings.ts

export const KEYBINDINGS = {
    // ======================
    // GLOBAL (work anywhere)
    // ======================
    global: {
        palette: { key: 'Ctrl+P', description: 'Open command palette' },
        newTransaction: { key: 'Ctrl+N', description: 'New transaction' },
        clear: { key: 'Ctrl+L', description: 'Clear screen' },
        help: { key: '?', description: 'Show help' },
        quit: { key: 'Ctrl+Q', description: 'Quit / close' },
    },

    // ======================
    // COMMAND MODE
    // ======================
    command: {
        submit: { key: 'Enter', description: 'Execute command' },
        historyUp: { key: 'ArrowUp', description: 'Previous command' },
        historyDown: { key: 'ArrowDown', description: 'Next command' },
        autocomplete: { key: 'Tab', description: 'Autocomplete / cycle' },
        clearLine: { key: 'Ctrl+U', description: 'Clear line' },
        deleteWord: { key: 'Ctrl+W', description: 'Delete word' },
    },

    // ======================
    // BROWSE MODE (tables)
    // ======================
    browse: {
        up: { key: 'k / ↑', description: 'Move up' },
        down: { key: 'j / ↓', description: 'Move down' },
        top: { key: 'g g', description: 'Go to top' },
        bottom: { key: 'G', description: 'Go to bottom' },
        select: { key: 'Enter', description: 'Select / view' },
        edit: { key: 'e', description: 'Edit item' },
        delete: { key: 'd', description: 'Delete item' },
        search: { key: '/', description: 'Search' },
        back: { key: 'q / Esc', description: 'Back to command' },
    },

    // ======================
    // DIALOG MODE
    // ======================
    dialog: {
        confirm: { key: 'Enter / y', description: 'Confirm' },
        cancel: { key: 'Esc / n', description: 'Cancel' },
    },

    // ======================
    // FORM MODE
    // ======================
    form: {
        nextField: { key: 'Tab', description: 'Next field' },
        prevField: { key: 'Shift+Tab', description: 'Previous field' },
        submit: { key: 'Ctrl+Enter', description: 'Submit form' },
        cancel: { key: 'Esc', description: 'Cancel' },
    },

    // ======================
    // AUTOCOMPLETE DROPDOWN
    // ======================
    autocomplete: {
        next: { key: 'Tab / ↓', description: 'Next suggestion' },
        prev: { key: 'Shift+Tab / ↑', description: 'Previous suggestion' },
        accept: { key: 'Enter / Tab', description: 'Accept suggestion' },
        dismiss: { key: 'Esc', description: 'Dismiss' },
    },
} as const

/**
 * Format keybinding for display
 */
export function formatKeybinding(key: string): string {
    return key
        .replace('Ctrl+', '⌃')
        .replace('Shift+', '⇧')
        .replace('Alt+', '⌥')
        .replace('Meta+', '⌘')
        .replace('Enter', '⏎')
        .replace('Escape', 'Esc')
        .replace('ArrowUp', '↑')
        .replace('ArrowDown', '↓')
        .replace('ArrowLeft', '←')
        .replace('ArrowRight', '→')
}
```

---

## 5. Command Parser

### 5.1 Command Grammar Definition

```typescript
// src/utils/commandGrammar.ts
import type { CommandDefinition, ParamDefinition } from '@/types/tui'

/**
 * All available commands in the system
 */
export const COMMAND_REGISTRY: CommandDefinition[] = [
    // ======================
    // TRANSACTION COMMANDS
    // ======================
    {
        verb: 'add',
        noun: 'tx',
        description: 'Create a new transaction',
        usage: 'add tx from:<account> to:<account> amt:<amount> [memo:<text>] [date:<date>] [tags:<tags>]',
        params: [
            {
                key: 'from',
                label: 'From Account',
                required: true,
                type: 'account',
                description: 'Source account (credited)',
                autocomplete: 'accounts',
            },
            {
                key: 'to',
                label: 'To Account',
                required: true,
                type: 'account',
                description: 'Destination account (debited)',
                autocomplete: 'accounts',
            },
            {
                key: 'amt',
                label: 'Amount',
                required: true,
                type: 'amount',
                description: 'Transaction amount',
                autocomplete: 'none',
            },
            {
                key: 'memo',
                label: 'Memo',
                required: false,
                type: 'string',
                description: 'Transaction description',
                autocomplete: 'recent',
            },
            {
                key: 'date',
                label: 'Date',
                required: false,
                type: 'date',
                description: 'Transaction date (default: today)',
                autocomplete: 'none',
            },
            {
                key: 'tags',
                label: 'Tags',
                required: false,
                type: 'string',
                description: 'Comma-separated tags',
                autocomplete: 'tags',
            },
        ],
        flags: [
            { name: 'force', short: 'f', description: 'Skip confirmation' },
        ],
        examples: [
            'add tx from:checking to:groceries amt:125.50',
            'add tx from:savings to:checking amt:1000 memo:"Monthly transfer"',
            'add tx from:checking to:rent amt:1500 date:2024-01-01',
        ],
        handler: 'handleAddTransaction',
    },

    {
        verb: 'list',
        noun: 'tx',
        description: 'List transactions',
        usage: 'list tx [--from:<date>] [--to:<date>] [--account:<account>] [--limit:<n>]',
        params: [
            {
                key: 'from',
                label: 'From Date',
                required: false,
                type: 'date',
                description: 'Start date',
                autocomplete: 'none',
            },
            {
                key: 'to',
                label: 'To Date',
                required: false,
                type: 'date',
                description: 'End date',
                autocomplete: 'none',
            },
            {
                key: 'account',
                label: 'Account',
                required: false,
                type: 'account',
                description: 'Filter by account',
                autocomplete: 'accounts',
            },
            {
                key: 'limit',
                label: 'Limit',
                required: false,
                type: 'number',
                description: 'Number of results',
                autocomplete: 'none',
            },
        ],
        flags: [],
        examples: [
            'list tx',
            'list tx --limit:20',
            'list tx --account:checking --from:2024-01-01',
        ],
        handler: 'handleListTransactions',
    },

    {
        verb: 'edit',
        noun: 'tx',
        description: 'Edit a transaction',
        usage: 'edit tx <id> [field:value...]',
        params: [],
        flags: [],
        examples: [
            'edit tx 123',
            'edit tx 123 memo:"Updated memo"',
        ],
        handler: 'handleEditTransaction',
    },

    {
        verb: 'delete',
        noun: 'tx',
        description: 'Delete a transaction',
        usage: 'delete tx <id> [--force]',
        params: [],
        flags: [
            { name: 'force', short: 'f', description: 'Skip confirmation' },
        ],
        examples: [
            'delete tx 123',
            'delete tx 123 --force',
        ],
        handler: 'handleDeleteTransaction',
    },

    // ======================
    // ACCOUNT COMMANDS
    // ======================
    {
        verb: 'add',
        noun: 'account',
        description: 'Create a new account',
        usage: 'add account <name> type:<type> [parent:<account>] [code:<code>]',
        params: [
            {
                key: 'type',
                label: 'Account Type',
                required: true,
                type: 'string',
                description: 'asset, liability, equity, revenue, expense',
                autocomplete: 'none',
            },
            {
                key: 'parent',
                label: 'Parent Account',
                required: false,
                type: 'account',
                description: 'Parent account for hierarchy',
                autocomplete: 'accounts',
            },
            {
                key: 'code',
                label: 'Account Code',
                required: false,
                type: 'string',
                description: 'Accounting code (e.g., 1000)',
                autocomplete: 'none',
            },
        ],
        flags: [],
        examples: [
            'add account checking type:asset',
            'add account "Office Supplies" type:expense parent:expenses',
        ],
        handler: 'handleAddAccount',
    },

    {
        verb: 'list',
        noun: 'accounts',
        description: 'List all accounts',
        usage: 'list accounts [--type:<type>]',
        params: [
            {
                key: 'type',
                label: 'Account Type',
                required: false,
                type: 'string',
                description: 'Filter by type',
                autocomplete: 'none',
            },
        ],
        flags: [
            { name: 'tree', short: 't', description: 'Show as tree' },
        ],
        examples: [
            'list accounts',
            'list accounts --type:expense',
            'list accounts --tree',
        ],
        handler: 'handleListAccounts',
    },

    // ======================
    // REPORT COMMANDS
    // ======================
    {
        verb: 'report',
        noun: 'balance',
        description: 'Generate balance sheet',
        usage: 'report balance [--date:<date>]',
        params: [
            {
                key: 'date',
                label: 'As of Date',
                required: false,
                type: 'date',
                description: 'Report date (default: today)',
                autocomplete: 'none',
            },
        ],
        flags: [],
        examples: [
            'report balance',
            'report balance --date:2024-01-31',
        ],
        handler: 'handleBalanceReport',
    },

    {
        verb: 'report',
        noun: 'income',
        description: 'Generate income statement',
        usage: 'report income [--from:<date>] [--to:<date>]',
        params: [
            {
                key: 'from',
                label: 'From Date',
                required: false,
                type: 'date',
                description: 'Start date',
                autocomplete: 'none',
            },
            {
                key: 'to',
                label: 'To Date',
                required: false,
                type: 'date',
                description: 'End date',
                autocomplete: 'none',
            },
        ],
        flags: [],
        examples: [
            'report income',
            'report income --from:2024-01-01 --to:2024-01-31',
        ],
        handler: 'handleIncomeReport',
    },

    // ======================
    // UTILITY COMMANDS
    // ======================
    {
        verb: 'help',
        noun: undefined,
        description: 'Show help',
        usage: 'help [<command>]',
        params: [],
        flags: [],
        examples: [
            'help',
            'help add tx',
        ],
        handler: 'handleHelp',
    },

    {
        verb: 'clear',
        noun: undefined,
        description: 'Clear terminal',
        usage: 'clear',
        params: [],
        flags: [],
        examples: ['clear'],
        handler: 'handleClear',
    },
]

/**
 * Get command definition by verb and noun
 */
export function getCommand(verb: string, noun?: string): CommandDefinition | null {
    return COMMAND_REGISTRY.find(cmd =>
        cmd.verb === verb && cmd.noun === noun
    ) || null
}

/**
 * Get all commands matching a partial verb
 */
export function findCommands(partial: string): CommandDefinition[] {
    const lower = partial.toLowerCase()
    return COMMAND_REGISTRY.filter(cmd =>
        cmd.verb.startsWith(lower) ||
        (cmd.noun && `${cmd.verb} ${cmd.noun}`.startsWith(lower))
    )
}

/**
 * Get all unique verbs
 */
export function getVerbs(): string[] {
    return [...new Set(COMMAND_REGISTRY.map(cmd => cmd.verb))]
}

/**
 * Get nouns for a verb
 */
export function getNounsForVerb(verb: string): string[] {
    return COMMAND_REGISTRY
        .filter(cmd => cmd.verb === verb && cmd.noun)
        .map(cmd => cmd.noun as string)
}
```

### 5.2 Command Parser Composable

```typescript
// src/composables/useCommandParser.ts
import { computed } from 'vue'
import type {
    ParsedCommand,
    CursorContext,
    ValidationError,
    CommandDefinition
} from '@/types/tui'
import {
    getCommand,
    findCommands,
    COMMAND_REGISTRY
} from '@/utils/commandGrammar'

export function useCommandParser() {
    /**
     * Parse a command string into structured data
     */
    function parse(input: string, cursorPos: number): ParsedCommand {
        const trimmed = input.trim()
        const tokens = tokenize(trimmed)

        // Extract verb (first token)
        const verb = tokens[0] || null

        // Extract noun (second token if not a param/flag)
        let noun: string | null = null
        let startIndex = 1

        if (tokens[1] && !tokens[1].includes(':') && !tokens[1].startsWith('-')) {
            noun = tokens[1]
            startIndex = 2
        }

        // Parse remaining tokens
        const args: string[] = []
        const params: Record<string, string> = {}
        const flags: string[] = []

        for (let i = startIndex; i < tokens.length; i++) {
            const token = tokens[i]

            if (token.startsWith('--')) {
                // Long flag or param
                const withoutDash = token.substring(2)
                if (withoutDash.includes(':')) {
                    const [key, value] = splitOnce(withoutDash, ':')
                    params[key] = value
                } else {
                    flags.push(withoutDash)
                }
            } else if (token.startsWith('-')) {
                // Short flag
                flags.push(token.substring(1))
            } else if (token.includes(':')) {
                // Param without --
                const [key, value] = splitOnce(token, ':')
                params[key] = value
            } else {
                // Positional argument
                args.push(token)
            }
        }

        // Get cursor context for autocomplete
        const cursorContext = getCursorContext(input, cursorPos, verb, noun)

        // Validate against command definition
        const commandDef = verb ? getCommand(verb, noun || undefined) : null
        const errors = validate(commandDef, params, args)

        return {
            raw: input,
            verb,
            noun,
            args,
            params,
            flags,
            valid: errors.length === 0,
            errors,
            cursorContext,
        }
    }

    /**
     * Tokenize input string (handles quoted strings)
     */
    function tokenize(input: string): string[] {
        const tokens: string[] = []
        let current = ''
        let inQuotes = false
        let quoteChar = ''

        for (let i = 0; i < input.length; i++) {
            const char = input[i]

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

        if (current) {
            tokens.push(current)
        }

        return tokens
    }

    /**
     * Split string on first occurrence of separator
     */
    function splitOnce(str: string, sep: string): [string, string] {
        const index = str.indexOf(sep)
        if (index === -1) return [str, '']
        return [str.substring(0, index), str.substring(index + 1)]
    }

    /**
     * Determine cursor context for autocomplete
     */
    function getCursorContext(
        input: string,
        cursorPos: number,
        verb: string | null,
        noun: string | null
    ): CursorContext {
        // Find what the cursor is currently in
        const textBeforeCursor = input.substring(0, cursorPos)
        const tokens = tokenize(textBeforeCursor)
        const currentToken = tokens[tokens.length - 1] || ''

        // Determine segment type
        let segment: CursorContext['segment'] = 'verb'
        let paramKey: string | undefined

        if (tokens.length === 0 || (tokens.length === 1 && !input.includes(' '))) {
            segment = 'verb'
        } else if (tokens.length === 1 || (tokens.length === 2 && !currentToken.includes(':'))) {
            // After verb, before any params
            segment = 'noun'
        } else if (currentToken.includes(':')) {
            const [key, value] = splitOnce(currentToken, ':')
            if (value === '' || cursorPos === textBeforeCursor.length) {
                segment = 'param-value'
                paramKey = key.replace(/^-+/, '')
            } else {
                segment = 'param-value'
                paramKey = key.replace(/^-+/, '')
            }
        } else if (currentToken.startsWith('-')) {
            segment = 'flag'
        } else {
            segment = 'arg'
        }

        return {
            position: cursorPos,
            segment,
            currentWord: currentToken,
            paramKey,
        }
    }

    /**
     * Validate command against definition
     */
    function validate(
        commandDef: CommandDefinition | null,
        params: Record<string, string>,
        args: string[]
    ): ValidationError[] {
        const errors: ValidationError[] = []

        if (!commandDef) {
            // Can't validate unknown command
            return errors
        }

        // Check required params
        for (const paramDef of commandDef.params) {
            if (paramDef.required && !params[paramDef.key]) {
                errors.push({
                    field: paramDef.key,
                    message: `Missing required parameter: ${paramDef.key}`,
                    type: 'error',
                })
            }
        }

        // Validate param types
        for (const [key, value] of Object.entries(params)) {
            const paramDef = commandDef.params.find(p => p.key === key)

            if (!paramDef) {
                errors.push({
                    field: key,
                    message: `Unknown parameter: ${key}`,
                    type: 'warning',
                })
                continue
            }

            // Type validation
            if (paramDef.type === 'number' || paramDef.type === 'amount') {
                if (isNaN(parseFloat(value))) {
                    errors.push({
                        field: key,
                        message: `${paramDef.label} must be a number`,
                        type: 'error',
                    })
                }
            }

            if (paramDef.type === 'date') {
                if (!isValidDate(value)) {
                    errors.push({
                        field: key,
                        message: `${paramDef.label} must be a valid date`,
                        type: 'error',
                    })
                }
            }
        }

        return errors
    }

    /**
     * Check if string is a valid date
     */
    function isValidDate(str: string): boolean {
        // Accept shortcuts
        if (['t', 'today', 'y', 'yesterday'].includes(str.toLowerCase())) {
            return true
        }
        // Accept relative days
        if (/^-\d+$/.test(str)) {
            return true
        }
        // Accept ISO date
        const date = new Date(str)
        return !isNaN(date.getTime())
    }

    /**
     * Get ghost text (completion preview)
     */
    function getGhostText(input: string, parsed: ParsedCommand): string | null {
        if (!parsed.verb) return null

        const { cursorContext } = parsed

        // If typing a verb, suggest completion
        if (cursorContext.segment === 'verb') {
            const matches = findCommands(cursorContext.currentWord)
            if (matches.length > 0) {
                const first = matches[0]
                const fullCommand = first.noun
                    ? `${first.verb} ${first.noun}`
                    : first.verb
                if (fullCommand.startsWith(input)) {
                    return fullCommand.substring(input.length)
                }
            }
        }

        // If we have a valid command, suggest next param
        const commandDef = getCommand(parsed.verb, parsed.noun || undefined)
        if (commandDef && cursorContext.segment === 'noun') {
            const firstParam = commandDef.params[0]
            if (firstParam && !parsed.params[firstParam.key]) {
                return ` ${firstParam.key}:`
            }
        }

        return null
    }

    /**
     * Get validation status for display
     */
    function getValidation(parsed: ParsedCommand): {
        valid: boolean;
        message?: string
    } | null {
        if (!parsed.verb) return null

        const commandDef = getCommand(parsed.verb, parsed.noun || undefined)

        if (!commandDef) {
            return {
                valid: false,
                message: `Unknown command: ${parsed.verb}${parsed.noun ? ' ' + parsed.noun : ''}`,
            }
        }

        if (parsed.errors.length > 0) {
            return {
                valid: false,
                message: parsed.errors[0].message,
            }
        }

        // Check if all required params are present
        const missingRequired = commandDef.params
            .filter(p => p.required && !parsed.params[p.key])

        if (missingRequired.length > 0) {
            return {
                valid: false,
                message: `Missing: ${missingRequired.map(p => p.key).join(', ')}`,
            }
        }

        return { valid: true }
    }

    return {
        parse,
        getGhostText,
        getValidation,
        tokenize,
    }
}
```

---

## 6. Output System

### 6.1 TerminalOutput.vue — Scrollable History

```vue
<!-- src/components/tui/core/TerminalOutput.vue -->
<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import type { OutputBlock } from '@/types/tui'
import OutputBlockComponent from '../display/OutputBlock.vue'

const props = defineProps<{
    blocks: OutputBlock[]
}>()

const containerRef = ref<HTMLElement | null>(null)

// Auto-scroll to bottom when new content added
watch(
    () => props.blocks.length,
    async () => {
        await nextTick()
        if (containerRef.value) {
            containerRef.value.scrollTop = containerRef.value.scrollHeight
        }
    }
)
</script>

<template>
    <div ref="containerRef" class="terminal-output tui-scrollbar">
        <OutputBlockComponent
            v-for="block in blocks"
            :key="block.id"
            :block="block"
        />
    </div>
</template>

<style scoped>
.terminal-output {
    @apply flex flex-col gap-1;
    @apply overflow-y-auto;
}
</style>
```

### 6.2 OutputBlock.vue — Single Output Entry

```vue
<!-- src/components/tui/display/OutputBlock.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { OutputBlock, TableData } from '@/types/tui'
import TableView from './TableView.vue'
import LoadingSpinner from './LoadingSpinner.vue'

const props = defineProps<{
    block: OutputBlock
}>()

const colorClass = computed(() => {
    switch (props.block.type) {
        case 'command': return 'text-tui-text-muted'
        case 'error': return 'text-tui-danger'
        case 'success': return 'text-tui-success'
        case 'warning': return 'text-tui-warning'
        case 'info': return 'text-tui-info'
        default: return 'text-tui-text-primary'
    }
})

const icon = computed(() => {
    switch (props.block.type) {
        case 'error': return 'x'
        case 'success': return 'v'
        case 'warning': return '!'
        case 'info': return 'i'
        default: return null
    }
})
</script>

<template>
    <div class="output-block" :class="colorClass">
        <!-- Command echo -->
        <template v-if="block.type === 'command'">
            <div class="command-echo">
                <span class="prompt">></span>
                <span class="command-text">{{ (block.content as any).text }}</span>
            </div>
        </template>

        <!-- Loading -->
        <template v-else-if="block.type === 'loading'">
            <LoadingSpinner />
        </template>

        <!-- Table -->
        <template v-else-if="block.type === 'table'">
            <TableView
                :data="(block.content as any).table"
                :interactive="false"
            />
        </template>

        <!-- Text content -->
        <template v-else-if="block.content.type === 'text'">
            <div class="text-output">
                <span v-if="icon" class="icon">[{{ icon }}]</span>
                <span>{{ block.content.text }}</span>
            </div>
        </template>

        <!-- Multi-line content -->
        <template v-else-if="block.content.type === 'lines'">
            <div class="lines-output">
                <div v-for="(line, i) in block.content.lines" :key="i">
                    {{ line }}
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.output-block {
    @apply font-mono text-tui-base;
}

.command-echo {
    @apply flex gap-2;
    @apply text-tui-text-muted;
}

.prompt {
    @apply text-tui-primary opacity-50;
}

.text-output {
    @apply flex gap-2;
}

.icon {
    @apply font-bold;
}

.lines-output {
    @apply flex flex-col;
}
</style>
```

### 6.3 LoadingSpinner.vue — Animated Loading

```vue
<!-- src/components/tui/display/LoadingSpinner.vue -->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const props = withDefaults(defineProps<{
    text?: string
}>(), {
    text: 'Loading'
})

// Braille spinner characters
const frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏']
const currentFrame = ref(0)
let interval: number | null = null

onMounted(() => {
    interval = window.setInterval(() => {
        currentFrame.value = (currentFrame.value + 1) % frames.length
    }, 80)
})

onUnmounted(() => {
    if (interval) {
        clearInterval(interval)
    }
})
</script>

<template>
    <div class="loading-spinner">
        <span class="spinner">{{ frames[currentFrame] }}</span>
        <span class="text">{{ text }}</span>
    </div>
</template>

<style scoped>
.loading-spinner {
    @apply flex items-center gap-2;
    @apply text-tui-primary;
}

.spinner {
    @apply text-lg;
}

.text {
    @apply text-tui-text-secondary;
}
</style>
```

---

## 7. Autocomplete & Dropdowns

### 7.1 useAutocomplete Composable

```typescript
// src/composables/useAutocomplete.ts
import { ref, computed, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useAccountsStore } from '@/stores/accounts'
import { useCommandsStore } from '@/stores/commands'
import type {
    Suggestion,
    AutocompleteState,
    CursorContext,
    ParsedCommand
} from '@/types/tui'
import {
    findCommands,
    getCommand,
    getNounsForVerb
} from '@/utils/commandGrammar'

export function useAutocomplete() {
    // Stores
    const accountsStore = useAccountsStore()
    const commandsStore = useCommandsStore()

    // State
    const state = ref<AutocompleteState>({
        active: false,
        suggestions: [],
        selectedIndex: 0,
        loading: false,
        query: '',
        context: null,
    })

    /**
     * Update suggestions based on parsed command and cursor context
     */
    async function updateSuggestions(parsed: ParsedCommand) {
        const context = parsed.cursorContext
        state.value.context = context
        state.value.query = context.currentWord

        let suggestions: Suggestion[] = []

        switch (context.segment) {
            case 'verb':
                suggestions = getVerbSuggestions(context.currentWord)
                break

            case 'noun':
                suggestions = getNounSuggestions(parsed.verb!, context.currentWord)
                break

            case 'param-value':
                suggestions = await getParamValueSuggestions(
                    parsed,
                    context.paramKey!,
                    context.currentWord
                )
                break

            case 'arg':
                suggestions = getArgSuggestions(parsed, context.currentWord)
                break
        }

        state.value.suggestions = suggestions
        state.value.selectedIndex = 0
        state.value.active = suggestions.length > 0
    }

    /**
     * Get verb suggestions (command names)
     */
    function getVerbSuggestions(query: string): Suggestion[] {
        const commands = findCommands(query)

        return commands.map(cmd => ({
            value: cmd.noun ? `${cmd.verb} ${cmd.noun}` : cmd.verb,
            label: cmd.noun ? `${cmd.verb} ${cmd.noun}` : cmd.verb,
            description: cmd.description,
            category: 'Commands',
            score: cmd.verb.startsWith(query) ? 100 : 50,
        }))
    }

    /**
     * Get noun suggestions for a verb
     */
    function getNounSuggestions(verb: string, query: string): Suggestion[] {
        const nouns = getNounsForVerb(verb)

        return nouns
            .filter(noun => noun.toLowerCase().includes(query.toLowerCase()))
            .map(noun => {
                const cmd = getCommand(verb, noun)
                return {
                    value: noun,
                    label: noun,
                    description: cmd?.description || '',
                    category: verb,
                    score: noun.startsWith(query) ? 100 : 50,
                }
            })
    }

    /**
     * Get parameter value suggestions
     * This is where database lookups happen (accounts, tags, etc.)
     */
    async function getParamValueSuggestions(
        parsed: ParsedCommand,
        paramKey: string,
        query: string
    ): Promise<Suggestion[]> {
        const commandDef = getCommand(parsed.verb!, parsed.noun || undefined)
        if (!commandDef) return []

        const paramDef = commandDef.params.find(p => p.key === paramKey)
        if (!paramDef) return []

        // Determine autocomplete type
        const autocompleteType = paramDef.autocomplete ||
            (paramDef.type === 'account' ? 'accounts' : 'none')

        switch (autocompleteType) {
            case 'accounts':
                return getAccountSuggestions(query, parsed)

            case 'tags':
                return getTagSuggestions(query)

            case 'recent':
                return getRecentValueSuggestions(paramKey, query)

            default:
                return []
        }
    }

    /**
     * Get account suggestions from store (fetched from database)
     */
    function getAccountSuggestions(
        query: string,
        parsed: ParsedCommand
    ): Suggestion[] {
        // Ensure accounts are loaded
        if (!accountsStore.loaded) {
            accountsStore.fetchAccounts()
        }

        const accounts = accountsStore.accounts
        const lowerQuery = query.toLowerCase()

        // Filter and score accounts
        let suggestions = accounts
            .filter(acc =>
                acc.name.toLowerCase().includes(lowerQuery) ||
                acc.slug.toLowerCase().includes(lowerQuery) ||
                (acc.code && acc.code.includes(query))
            )
            .map(acc => ({
                value: acc.slug,
                label: acc.name,
                description: `${acc.type} • ${formatCurrency(acc.balance)}`,
                category: capitalizeFirst(acc.type),
                score: calculateAccountScore(acc, query, parsed),
                meta: { id: acc.id, type: acc.type },
            }))

        // Sort by score (higher first)
        suggestions.sort((a, b) => b.score - a.score)

        // Limit results
        return suggestions.slice(0, 10)
    }

    /**
     * Calculate relevance score for an account
     * Higher score = more relevant
     */
    function calculateAccountScore(
        account: any,
        query: string,
        parsed: ParsedCommand
    ): number {
        let score = 0
        const lowerQuery = query.toLowerCase()
        const lowerName = account.name.toLowerCase()
        const lowerSlug = account.slug.toLowerCase()

        // Exact match
        if (lowerSlug === lowerQuery || lowerName === lowerQuery) {
            score += 100
        }
        // Starts with query
        else if (lowerSlug.startsWith(lowerQuery) || lowerName.startsWith(lowerQuery)) {
            score += 75
        }
        // Contains query
        else {
            score += 25
        }

        // Boost based on context
        // If we're looking for "to" account in a transaction from an asset,
        // boost expense accounts (common pattern)
        if (parsed.params.from) {
            const fromAccount = accountsStore.getBySlug(parsed.params.from)
            if (fromAccount) {
                if (fromAccount.type === 'asset' && account.type === 'expense') {
                    score += 20
                }
                if (fromAccount.type === 'revenue' && account.type === 'asset') {
                    score += 20
                }
            }
        }

        // Boost frequently used accounts
        const usageCount = commandsStore.getAccountUsageCount(account.slug)
        score += Math.min(usageCount * 2, 30)

        return score
    }

    /**
     * Get tag suggestions
     */
    function getTagSuggestions(query: string): Suggestion[] {
        // Get tags from command history
        const recentTags = commandsStore.getRecentTags()
        const lowerQuery = query.toLowerCase()

        return recentTags
            .filter(tag => tag.toLowerCase().includes(lowerQuery))
            .map(tag => ({
                value: tag,
                label: `#${tag}`,
                description: '',
                category: 'Tags',
                score: tag.startsWith(query) ? 100 : 50,
            }))
    }

    /**
     * Get recent values for a parameter
     */
    function getRecentValueSuggestions(
        paramKey: string,
        query: string
    ): Suggestion[] {
        const recentValues = commandsStore.getRecentParamValues(paramKey)
        const lowerQuery = query.toLowerCase()

        return recentValues
            .filter(val => val.toLowerCase().includes(lowerQuery))
            .slice(0, 5)
            .map(val => ({
                value: val,
                label: val,
                description: 'Recent',
                category: 'Recent',
                score: 50,
            }))
    }

    /**
     * Get argument suggestions
     */
    function getArgSuggestions(
        parsed: ParsedCommand,
        query: string
    ): Suggestion[] {
        // Context-dependent argument suggestions
        // e.g., for "edit tx <id>", suggest recent transaction IDs
        return []
    }

    // ======================
    // NAVIGATION
    // ======================

    function selectNext() {
        if (state.value.suggestions.length === 0) return
        state.value.selectedIndex =
            (state.value.selectedIndex + 1) % state.value.suggestions.length
    }

    function selectPrevious() {
        if (state.value.suggestions.length === 0) return
        state.value.selectedIndex =
            (state.value.selectedIndex - 1 + state.value.suggestions.length) %
            state.value.suggestions.length
    }

    function getSelected(): Suggestion | null {
        if (!state.value.active || state.value.suggestions.length === 0) {
            return null
        }
        return state.value.suggestions[state.value.selectedIndex]
    }

    function dismiss() {
        state.value.active = false
        state.value.suggestions = []
        state.value.selectedIndex = 0
    }

    // ======================
    // HELPERS
    // ======================

    function formatCurrency(amount: number): string {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount)
    }

    function capitalizeFirst(str: string): string {
        return str.charAt(0).toUpperCase() + str.slice(1)
    }

    // Debounced update for performance
    const debouncedUpdate = useDebounceFn(updateSuggestions, 100)

    return {
        state,
        updateSuggestions: debouncedUpdate,
        selectNext,
        selectPrevious,
        getSelected,
        dismiss,
    }
}
```

### 7.2 SuggestionDropdown.vue — Autocomplete UI

```vue
<!-- src/components/tui/input/SuggestionDropdown.vue -->
<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import type { Suggestion } from '@/types/tui'

const props = defineProps<{
    suggestions: Suggestion[]
    selectedIndex: number
    loading: boolean
}>()

const emit = defineEmits<{
    select: [suggestion: Suggestion]
}>()

// Group suggestions by category
const groupedSuggestions = computed(() => {
    const groups: Record<string, Suggestion[]> = {}

    for (const suggestion of props.suggestions) {
        const category = suggestion.category || 'Results'
        if (!groups[category]) {
            groups[category] = []
        }
        groups[category].push(suggestion)
    }

    return groups
})

// Calculate flat index for keyboard navigation
const flattenedSuggestions = computed(() => props.suggestions)

// Ref for scrolling selected item into view
const listRef = ref<HTMLElement | null>(null)
const selectedRef = ref<HTMLElement | null>(null)

watch(
    () => props.selectedIndex,
    async () => {
        await nextTick()
        selectedRef.value?.scrollIntoView({
            block: 'nearest',
            behavior: 'smooth',
        })
    }
)

function handleClick(suggestion: Suggestion) {
    emit('select', suggestion)
}

function isSelected(suggestion: Suggestion): boolean {
    return flattenedSuggestions.value[props.selectedIndex] === suggestion
}
</script>

<template>
    <div class="suggestion-dropdown">
        <!-- Loading state -->
        <div v-if="loading" class="dropdown-loading">
            <span class="spinner">⠋</span>
            <span>Loading...</span>
        </div>

        <!-- Empty state -->
        <div v-else-if="suggestions.length === 0" class="dropdown-empty">
            No suggestions
        </div>

        <!-- Suggestions list -->
        <div v-else ref="listRef" class="dropdown-list tui-scrollbar">
            <template v-for="(items, category) in groupedSuggestions" :key="category">
                <!-- Category header -->
                <div class="category-header">
                    {{ category }}
                </div>

                <!-- Items in category -->
                <div
                    v-for="suggestion in items"
                    :key="suggestion.value"
                    :ref="el => { if (isSelected(suggestion)) selectedRef = el as HTMLElement }"
                    class="suggestion-item"
                    :class="{ 'suggestion-item--selected': isSelected(suggestion) }"
                    @click="handleClick(suggestion)"
                    @mouseenter="/* could update selectedIndex */"
                >
                    <div class="suggestion-main">
                        <span class="suggestion-label">{{ suggestion.label }}</span>
                        <span v-if="suggestion.description" class="suggestion-desc">
                            {{ suggestion.description }}
                        </span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer hint -->
        <div class="dropdown-footer">
            <span>Tab accept</span>
            <span>↑↓ navigate</span>
            <span>Esc dismiss</span>
        </div>
    </div>
</template>

<style scoped>
.suggestion-dropdown {
    @apply absolute bottom-full left-0 right-0;
    @apply mb-1;
    @apply bg-tui-bg-secondary;
    @apply border border-tui-border-default rounded;
    @apply shadow-lg;
    @apply z-50;
    @apply max-h-64 overflow-hidden;
    @apply flex flex-col;
    @apply animate-slide-up;
}

.dropdown-loading,
.dropdown-empty {
    @apply px-3 py-2;
    @apply text-tui-text-muted text-tui-sm;
}

.dropdown-list {
    @apply flex-1 overflow-y-auto;
    @apply py-1;
}

.category-header {
    @apply px-3 py-1;
    @apply text-tui-text-muted text-tui-sm;
    @apply uppercase tracking-wide;
    @apply border-b border-tui-border-subtle;
    @apply sticky top-0 bg-tui-bg-secondary;
}

.suggestion-item {
    @apply px-3 py-1;
    @apply cursor-pointer;
    @apply transition-colors duration-75;
}

.suggestion-item:hover {
    @apply bg-tui-hover;
}

.suggestion-item--selected {
    @apply bg-tui-selected;
}

.suggestion-main {
    @apply flex items-center justify-between gap-4;
}

.suggestion-label {
    @apply text-tui-text-primary;
}

.suggestion-desc {
    @apply text-tui-text-muted text-tui-sm;
    @apply truncate;
}

.dropdown-footer {
    @apply flex items-center gap-4;
    @apply px-3 py-1;
    @apply text-tui-text-muted text-tui-sm;
    @apply border-t border-tui-border-subtle;
    @apply bg-tui-bg-tertiary;
}
</style>
```

### 7.3 GhostText.vue — Completion Preview

```vue
<!-- src/components/tui/input/GhostText.vue -->
<script setup lang="ts">
defineProps<{
    text: string
}>()
</script>

<template>
    <span class="ghost-text">{{ text }}</span>
</template>

<style scoped>
.ghost-text {
    @apply text-tui-text-muted;
    @apply opacity-50;
    @apply pointer-events-none;
    @apply select-none;
}
</style>
```

---

## 8. Dialog System

### 8.1 useDialog Composable

```typescript
// src/composables/useDialog.ts
import { ref, computed } from 'vue'
import type { DialogConfig, DialogResult } from '@/types/tui'

// Singleton dialog state
const activeDialog = ref<DialogConfig | null>(null)
const resolvePromise = ref<((result: DialogResult) => void) | null>(null)

export function useDialog() {
    /**
     * Show a confirmation dialog
     */
    function confirm(options: {
        title: string
        message?: string
        confirmLabel?: string
        cancelLabel?: string
        destructive?: boolean
    }): Promise<DialogResult<boolean>> {
        return showDialog({
            id: `confirm-${Date.now()}`,
            type: 'confirm',
            title: options.title,
            message: options.message,
            confirmLabel: options.confirmLabel || 'Confirm',
            cancelLabel: options.cancelLabel || 'Cancel',
            confirmKey: options.destructive ? 'y' : undefined,
            destructive: options.destructive,
        })
    }

    /**
     * Show an alert dialog
     */
    function alert(options: {
        title: string
        message: string
        type?: 'info' | 'error' | 'warning' | 'success'
    }): Promise<DialogResult> {
        return showDialog({
            id: `alert-${Date.now()}`,
            type: 'alert',
            title: options.title,
            message: options.message,
            confirmLabel: 'OK',
        })
    }

    /**
     * Show a form dialog
     */
    function form<T extends Record<string, any>>(options: {
        title: string
        fields: DialogConfig['fields']
        confirmLabel?: string
    }): Promise<DialogResult<T>> {
        return showDialog({
            id: `form-${Date.now()}`,
            type: 'form',
            title: options.title,
            fields: options.fields,
            confirmLabel: options.confirmLabel || 'Submit',
            cancelLabel: 'Cancel',
        })
    }

    /**
     * Show a selection dialog
     */
    function select<T extends string>(options: {
        title: string
        message?: string
        options: { value: T; label: string; description?: string }[]
    }): Promise<DialogResult<T>> {
        return showDialog({
            id: `select-${Date.now()}`,
            type: 'select',
            title: options.title,
            message: options.message,
            options: options.options,
            cancelLabel: 'Cancel',
        })
    }

    /**
     * Internal: show dialog and return promise
     */
    function showDialog<T = any>(config: DialogConfig): Promise<DialogResult<T>> {
        return new Promise((resolve) => {
            activeDialog.value = config
            resolvePromise.value = resolve as any
        })
    }

    /**
     * Close dialog with result
     */
    function closeDialog(result: DialogResult) {
        if (resolvePromise.value) {
            resolvePromise.value(result)
            resolvePromise.value = null
        }
        activeDialog.value = null
    }

    /**
     * Handle dialog keyboard input
     */
    function handleDialogKey(event: KeyboardEvent, config: DialogConfig): boolean {
        const key = event.key.toLowerCase()

        // Escape always cancels
        if (key === 'escape') {
            closeDialog({ confirmed: false })
            return true
        }

        // For confirm dialogs
        if (config.type === 'confirm') {
            if (key === 'enter' || key === (config.confirmKey || 'y')) {
                closeDialog({ confirmed: true, data: true })
                return true
            }
            if (key === 'n' || key === (config.cancelKey || 'n')) {
                closeDialog({ confirmed: false })
                return true
            }
        }

        // For alert dialogs
        if (config.type === 'alert') {
            if (key === 'enter') {
                closeDialog({ confirmed: true })
                return true
            }
        }

        return false
    }

    return {
        activeDialog: computed(() => activeDialog.value),
        confirm,
        alert,
        form,
        select,
        closeDialog,
        handleDialogKey,
    }
}
```

### 8.2 DialogContainer.vue — Dialog Wrapper

```vue
<!-- src/components/tui/dialogs/DialogContainer.vue -->
<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import type { DialogConfig, DialogResult } from '@/types/tui'
import ConfirmDialog from './ConfirmDialog.vue'
import AlertDialog from './AlertDialog.vue'
import FormDialog from './FormDialog.vue'
import SelectDialog from './SelectDialog.vue'

const props = defineProps<{
    config: DialogConfig
}>()

const emit = defineEmits<{
    close: [result: DialogResult]
}>()

const dialogComponent = computed(() => {
    switch (props.config.type) {
        case 'confirm': return ConfirmDialog
        case 'alert': return AlertDialog
        case 'form': return FormDialog
        case 'select': return SelectDialog
        default: return null
    }
})

function handleClose(result: DialogResult) {
    emit('close', result)
}

// Trap focus within dialog
onMounted(() => {
    // Could implement focus trap here
})
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div class="dialog-backdrop" @click="handleClose({ confirmed: false })">
            <!-- Dialog box (stop click propagation) -->
            <div class="dialog-container" @click.stop>
                <component
                    :is="dialogComponent"
                    :config="config"
                    @close="handleClose"
                />
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.dialog-backdrop {
    @apply fixed inset-0;
    @apply bg-black/60;
    @apply flex items-center justify-center;
    @apply z-[100];
    @apply animate-fade-in;
}

.dialog-container {
    @apply bg-tui-bg-secondary;
    @apply border border-tui-border-default rounded;
    @apply shadow-2xl;
    @apply min-w-[320px] max-w-[480px];
    @apply animate-slide-up;
}
</style>
```

### 8.3 ConfirmDialog.vue — Yes/No Dialog

```vue
<!-- src/components/tui/dialogs/ConfirmDialog.vue -->
<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import type { DialogConfig, DialogResult } from '@/types/tui'

const props = defineProps<{
    config: DialogConfig
}>()

const emit = defineEmits<{
    close: [result: DialogResult]
}>()

const confirmButtonRef = ref<HTMLButtonElement | null>(null)

function handleConfirm() {
    emit('close', { confirmed: true, data: true })
}

function handleCancel() {
    emit('close', { confirmed: false })
}

// Keyboard handling
function handleKeyDown(event: KeyboardEvent) {
    const key = event.key.toLowerCase()

    if (key === 'escape' || key === 'n') {
        event.preventDefault()
        handleCancel()
    } else if (key === 'enter' || key === 'y') {
        event.preventDefault()
        handleConfirm()
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeyDown)
    // Focus confirm button for destructive actions, cancel for others
    if (!props.config.destructive) {
        confirmButtonRef.value?.focus()
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
    <div class="confirm-dialog">
        <!-- Header -->
        <div class="dialog-header">
            <h3 class="dialog-title">{{ config.title }}</h3>
        </div>

        <!-- Body -->
        <div v-if="config.message" class="dialog-body">
            <p>{{ config.message }}</p>
        </div>

        <!-- Footer -->
        <div class="dialog-footer">
            <!-- Keyboard hint -->
            <div class="dialog-hints">
                <span class="hint">[{{ config.cancelKey || 'n' }}] Cancel</span>
                <span class="hint">[{{ config.confirmKey || 'y' }}] Confirm</span>
            </div>

            <!-- Buttons -->
            <div class="dialog-buttons">
                <button
                    class="btn btn-secondary"
                    @click="handleCancel"
                >
                    {{ config.cancelLabel || 'Cancel' }}
                </button>
                <button
                    ref="confirmButtonRef"
                    class="btn"
                    :class="config.destructive ? 'btn-danger' : 'btn-primary'"
                    @click="handleConfirm"
                >
                    {{ config.confirmLabel || 'Confirm' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.confirm-dialog {
    @apply flex flex-col;
}

.dialog-header {
    @apply px-4 py-3;
    @apply border-b border-tui-border-subtle;
}

.dialog-title {
    @apply text-tui-text-primary font-semibold;
}

.dialog-body {
    @apply px-4 py-4;
    @apply text-tui-text-secondary;
}

.dialog-footer {
    @apply px-4 py-3;
    @apply border-t border-tui-border-subtle;
    @apply flex items-center justify-between;
}

.dialog-hints {
    @apply flex gap-4;
    @apply text-tui-text-muted text-tui-sm;
}

.dialog-buttons {
    @apply flex gap-2;
}

.btn {
    @apply px-4 py-1.5;
    @apply font-mono text-tui-sm;
    @apply border rounded;
    @apply transition-colors;
    @apply cursor-pointer;
}

.btn:focus {
    @apply outline-none ring-1 ring-tui-primary;
}

.btn-primary {
    @apply bg-tui-primary text-tui-bg-primary;
    @apply border-tui-primary;
}

.btn-primary:hover {
    @apply bg-tui-primary/80;
}

.btn-secondary {
    @apply bg-transparent text-tui-text-secondary;
    @apply border-tui-border-default;
}

.btn-secondary:hover {
    @apply bg-tui-hover;
}

.btn-danger {
    @apply bg-tui-danger text-white;
    @apply border-tui-danger;
}

.btn-danger:hover {
    @apply bg-tui-danger/80;
}
</style>
```

### 8.4 FormDialog.vue — Multi-field Form

```vue
<!-- src/components/tui/dialogs/FormDialog.vue -->
<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import type { DialogConfig, DialogResult, FormFieldConfig } from '@/types/tui'
import FormField from '../forms/FormField.vue'

const props = defineProps<{
    config: DialogConfig
}>()

const emit = defineEmits<{
    close: [result: DialogResult]
}>()

// Form data
const formData = ref<Record<string, string>>({})
const errors = ref<Record<string, string>>({})
const currentFieldIndex = ref(0)

// Initialize form data with defaults
onMounted(() => {
    if (props.config.fields) {
        for (const field of props.config.fields) {
            formData.value[field.key] = field.defaultValue || ''
        }
    }
    document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown)
})

const fields = computed(() => props.config.fields || [])
const currentField = computed(() => fields.value[currentFieldIndex.value])

function handleKeyDown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault()
        handleCancel()
    } else if (event.key === 'Tab' && !event.shiftKey) {
        event.preventDefault()
        nextField()
    } else if (event.key === 'Tab' && event.shiftKey) {
        event.preventDefault()
        prevField()
    } else if (event.ctrlKey && event.key === 'Enter') {
        event.preventDefault()
        handleSubmit()
    }
}

function nextField() {
    if (currentFieldIndex.value < fields.value.length - 1) {
        currentFieldIndex.value++
    }
}

function prevField() {
    if (currentFieldIndex.value > 0) {
        currentFieldIndex.value--
    }
}

function validate(): boolean {
    errors.value = {}
    let valid = true

    for (const field of fields.value) {
        const value = formData.value[field.key]

        // Required check
        if (field.required && !value) {
            errors.value[field.key] = `${field.label} is required`
            valid = false
            continue
        }

        // Custom validation
        if (field.validate && value) {
            const error = field.validate(value)
            if (error) {
                errors.value[field.key] = error
                valid = false
            }
        }
    }

    return valid
}

function handleSubmit() {
    if (validate()) {
        emit('close', { confirmed: true, data: { ...formData.value } })
    }
}

function handleCancel() {
    emit('close', { confirmed: false })
}
</script>

<template>
    <div class="form-dialog">
        <!-- Header -->
        <div class="dialog-header">
            <h3 class="dialog-title">{{ config.title }}</h3>
        </div>

        <!-- Form fields -->
        <div class="dialog-body">
            <FormField
                v-for="(field, index) in fields"
                :key="field.key"
                :config="field"
                :value="formData[field.key]"
                :error="errors[field.key]"
                :focused="index === currentFieldIndex"
                @update:value="formData[field.key] = $event"
                @focus="currentFieldIndex = index"
            />
        </div>

        <!-- Footer -->
        <div class="dialog-footer">
            <div class="dialog-hints">
                <span class="hint">Tab next</span>
                <span class="hint">Shift+Tab prev</span>
                <span class="hint">Ctrl+Enter submit</span>
            </div>

            <div class="dialog-buttons">
                <button class="btn btn-secondary" @click="handleCancel">
                    {{ config.cancelLabel || 'Cancel' }}
                </button>
                <button class="btn btn-primary" @click="handleSubmit">
                    {{ config.confirmLabel || 'Submit' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.form-dialog {
    @apply flex flex-col;
}

.dialog-header {
    @apply px-4 py-3;
    @apply border-b border-tui-border-subtle;
}

.dialog-title {
    @apply text-tui-text-primary font-semibold;
}

.dialog-body {
    @apply px-4 py-4;
    @apply flex flex-col gap-3;
    @apply max-h-[400px] overflow-y-auto;
}

.dialog-footer {
    @apply px-4 py-3;
    @apply border-t border-tui-border-subtle;
    @apply flex items-center justify-between;
}

.dialog-hints {
    @apply flex gap-4;
    @apply text-tui-text-muted text-tui-sm;
}

.dialog-buttons {
    @apply flex gap-2;
}

.btn {
    @apply px-4 py-1.5;
    @apply font-mono text-tui-sm;
    @apply border rounded;
    @apply cursor-pointer;
}

.btn-primary {
    @apply bg-tui-primary text-tui-bg-primary border-tui-primary;
}

.btn-secondary {
    @apply bg-transparent text-tui-text-secondary border-tui-border-default;
}
</style>
```

### 8.5 FormField.vue — Reusable Form Field

```vue
<!-- src/components/tui/forms/FormField.vue -->
<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import type { FormFieldConfig } from '@/types/tui'
import { useAutocomplete } from '@/composables/useAutocomplete'
import SuggestionDropdown from '../input/SuggestionDropdown.vue'

const props = defineProps<{
    config: FormFieldConfig
    value: string
    error?: string
    focused: boolean
}>()

const emit = defineEmits<{
    'update:value': [value: string]
    'focus': []
}>()

const inputRef = ref<HTMLInputElement | null>(null)
const { state: autocompleteState, updateSuggestions } = useAutocomplete()

// Focus input when field becomes active
watch(
    () => props.focused,
    async (focused) => {
        if (focused) {
            await nextTick()
            inputRef.value?.focus()
        }
    },
    { immediate: true }
)

// Update autocomplete when value changes
watch(
    () => props.value,
    (value) => {
        if (props.config.autocomplete && props.config.autocomplete !== 'none') {
            // Trigger autocomplete update
            // This would need to be integrated with the parser for full context
        }
    }
)

function handleInput(event: Event) {
    const target = event.target as HTMLInputElement
    emit('update:value', target.value)
}

function handleFocus() {
    emit('focus')
}

const inputType = computed(() => {
    switch (props.config.type) {
        case 'number': return 'number'
        case 'date': return 'date'
        default: return 'text'
    }
})
</script>

<template>
    <div class="form-field" :class="{ 'form-field--focused': focused }">
        <label class="field-label">
            {{ config.label }}
            <span v-if="config.required" class="required">*</span>
        </label>

        <div class="field-input-wrapper">
            <input
                ref="inputRef"
                class="field-input"
                :class="{ 'field-input--error': error }"
                :type="inputType"
                :value="value"
                :placeholder="config.placeholder"
                @input="handleInput"
                @focus="handleFocus"
            />

            <!-- Autocomplete dropdown -->
            <SuggestionDropdown
                v-if="autocompleteState.active && focused"
                :suggestions="autocompleteState.suggestions"
                :selected-index="autocompleteState.selectedIndex"
                :loading="autocompleteState.loading"
                @select="emit('update:value', $event.value)"
            />
        </div>

        <div v-if="error" class="field-error">
            {{ error }}
        </div>
    </div>
</template>

<style scoped>
.form-field {
    @apply flex flex-col gap-1;
}

.field-label {
    @apply text-tui-text-secondary text-tui-sm;
}

.required {
    @apply text-tui-danger;
}

.field-input-wrapper {
    @apply relative;
}

.field-input {
    @apply w-full;
    @apply px-2 py-1;
    @apply bg-tui-bg-primary;
    @apply border border-tui-border-default rounded;
    @apply text-tui-text-primary font-mono;
    @apply outline-none;
    @apply transition-colors;
}

.field-input:focus {
    @apply border-tui-primary;
}

.field-input--error {
    @apply border-tui-danger;
}

.field-input::placeholder {
    @apply text-tui-text-placeholder;
}

.form-field--focused .field-label {
    @apply text-tui-primary;
}

.field-error {
    @apply text-tui-danger text-tui-sm;
}
</style>
```

---

## 9. Table Navigation

### 9.1 TableView.vue — Interactive Data Table

```vue
<!-- src/components/tui/display/TableView.vue -->
<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import type { TableData, TableRow, TableHeader } from '@/types/tui'
import { useTerminalStore } from '@/stores/terminal'

const props = withDefaults(defineProps<{
    data: TableData
    interactive?: boolean
    pageSize?: number
}>(), {
    interactive: true,
    pageSize: 20,
})

const emit = defineEmits<{
    select: [row: TableRow]
    edit: [row: TableRow]
    delete: [row: TableRow]
    action: [action: string, row: TableRow]
}>()

const terminalStore = useTerminalStore()

// State
const selectedIndex = ref(0)
const currentPage = ref(0)
const searchQuery = ref('')
const searchActive = ref(false)

// Computed
const filteredRows = computed(() => {
    if (!searchQuery.value) return props.data.rows

    const query = searchQuery.value.toLowerCase()
    return props.data.rows.filter(row =>
        Object.values(row.cells).some(cell =>
            cell.value.toLowerCase().includes(query)
        )
    )
})

const paginatedRows = computed(() => {
    const start = currentPage.value * props.pageSize
    return filteredRows.value.slice(start, start + props.pageSize)
})

const totalPages = computed(() =>
    Math.ceil(filteredRows.value.length / props.pageSize)
)

const selectedRow = computed(() =>
    paginatedRows.value[selectedIndex.value] || null
)

// Keep selection in bounds
watch(paginatedRows, () => {
    if (selectedIndex.value >= paginatedRows.value.length) {
        selectedIndex.value = Math.max(0, paginatedRows.value.length - 1)
    }
})

// Keyboard handling (when in browse mode)
function handleKeyDown(event: KeyboardEvent) {
    if (!props.interactive) return
    if (terminalStore.mode !== 'browse') return

    const key = event.key.toLowerCase()

    switch (key) {
        case 'j':
        case 'arrowdown':
            event.preventDefault()
            moveDown()
            break

        case 'k':
        case 'arrowup':
            event.preventDefault()
            moveUp()
            break

        case 'g':
            // gg = go to top (need to track if 'g' was pressed before)
            event.preventDefault()
            selectedIndex.value = 0
            currentPage.value = 0
            break

        case 'G': // Shift+G
            event.preventDefault()
            goToBottom()
            break

        case 'enter':
            event.preventDefault()
            if (selectedRow.value) {
                emit('select', selectedRow.value)
            }
            break

        case 'e':
            event.preventDefault()
            if (selectedRow.value) {
                emit('edit', selectedRow.value)
            }
            break

        case 'd':
            event.preventDefault()
            if (selectedRow.value) {
                emit('delete', selectedRow.value)
            }
            break

        case '/':
            event.preventDefault()
            searchActive.value = true
            break

        case 'escape':
        case 'q':
            event.preventDefault()
            if (searchActive.value) {
                searchActive.value = false
                searchQuery.value = ''
            } else {
                terminalStore.setMode('command')
            }
            break

        case 'n':
            // Next page
            event.preventDefault()
            if (currentPage.value < totalPages.value - 1) {
                currentPage.value++
                selectedIndex.value = 0
            }
            break

        case 'p':
            // Previous page
            event.preventDefault()
            if (currentPage.value > 0) {
                currentPage.value--
                selectedIndex.value = 0
            }
            break
    }
}

function moveDown() {
    if (selectedIndex.value < paginatedRows.value.length - 1) {
        selectedIndex.value++
    } else if (currentPage.value < totalPages.value - 1) {
        // Go to next page
        currentPage.value++
        selectedIndex.value = 0
    }
}

function moveUp() {
    if (selectedIndex.value > 0) {
        selectedIndex.value--
    } else if (currentPage.value > 0) {
        // Go to previous page
        currentPage.value--
        selectedIndex.value = props.pageSize - 1
    }
}

function goToBottom() {
    currentPage.value = totalPages.value - 1
    selectedIndex.value = paginatedRows.value.length - 1
}

// Calculate column widths
const columnWidths = computed(() => {
    const widths: Record<string, number> = {}

    for (const header of props.data.headers) {
        // Start with header label length
        let maxWidth = header.label.length

        // Check all row values
        for (const row of props.data.rows) {
            const cell = row.cells[header.key]
            if (cell) {
                maxWidth = Math.max(maxWidth, cell.value.length)
            }
        }

        // Add padding
        widths[header.key] = Math.min(maxWidth + 2, header.width || 40)
    }

    return widths
})

// Lifecycle
onMounted(() => {
    if (props.interactive) {
        document.addEventListener('keydown', handleKeyDown)
        terminalStore.setMode('browse')
    }
})

onUnmounted(() => {
    if (props.interactive) {
        document.removeEventListener('keydown', handleKeyDown)
    }
})

// Cell color class
function getCellColorClass(color?: string): string {
    if (!color) return 'text-tui-text-primary'
    return `text-tui-${color}`
}
</script>

<template>
    <div class="table-view">
        <!-- Search bar (when active) -->
        <div v-if="searchActive" class="table-search">
            <span class="search-prompt">/</span>
            <input
                v-model="searchQuery"
                class="search-input"
                placeholder="Search..."
                autofocus
            />
        </div>

        <!-- Table header -->
        <div class="table-header">
            <span
                v-for="header in data.headers"
                :key="header.key"
                class="table-cell header-cell"
                :class="`text-${header.align}`"
                :style="{ width: `${columnWidths[header.key]}ch` }"
            >
                {{ header.label }}
            </span>
        </div>

        <!-- Separator -->
        <div class="table-separator">
            <span
                v-for="header in data.headers"
                :key="header.key"
                class="separator-cell"
                :style="{ width: `${columnWidths[header.key]}ch` }"
            >
                {{ '─'.repeat(columnWidths[header.key]) }}
            </span>
        </div>

        <!-- Table body -->
        <div class="table-body">
            <div
                v-for="(row, index) in paginatedRows"
                :key="row.id"
                class="table-row"
                :class="{
                    'table-row--selected': interactive && index === selectedIndex,
                    'table-row--selectable': row.selectable !== false
                }"
                @click="emit('select', row)"
            >
                <!-- Selection indicator -->
                <span v-if="interactive" class="row-indicator">
                    {{ index === selectedIndex ? '>' : ' ' }}
                </span>

                <!-- Cells -->
                <span
                    v-for="header in data.headers"
                    :key="header.key"
                    class="table-cell"
                    :class="[
                        `text-${header.align}`,
                        getCellColorClass(row.cells[header.key]?.color)
                    ]"
                    :style="{ width: `${columnWidths[header.key]}ch` }"
                >
                    {{ row.cells[header.key]?.value || '' }}
                </span>
            </div>

            <!-- Empty state -->
            <div v-if="paginatedRows.length === 0" class="table-empty">
                {{ searchQuery ? 'No matching results' : 'No data' }}
            </div>
        </div>

        <!-- Summary row (if present) -->
        <div v-if="data.summary" class="table-summary">
            <div class="table-separator">
                <span
                    v-for="header in data.headers"
                    :key="header.key"
                    class="separator-cell"
                    :style="{ width: `${columnWidths[header.key]}ch` }"
                >
                    {{ '─'.repeat(columnWidths[header.key]) }}
                </span>
            </div>
            <div class="table-row summary-row">
                <span v-if="interactive" class="row-indicator"> </span>
                <span
                    v-for="header in data.headers"
                    :key="header.key"
                    class="table-cell"
                    :class="[
                        `text-${header.align}`,
                        getCellColorClass(data.summary.cells[header.key]?.color)
                    ]"
                    :style="{ width: `${columnWidths[header.key]}ch` }"
                >
                    {{ data.summary.cells[header.key]?.value || '' }}
                </span>
            </div>
        </div>

        <!-- Footer with pagination and hints -->
        <div v-if="interactive" class="table-footer">
            <div class="pagination">
                <span v-if="totalPages > 1">
                    Page {{ currentPage + 1 }}/{{ totalPages }}
                    ({{ filteredRows.length }} items)
                </span>
                <span v-else>
                    {{ filteredRows.length }} items
                </span>
            </div>
            <div class="hints">
                j/k navigate │ Enter select │ e edit │ d delete │ / search │ q back
            </div>
        </div>
    </div>
</template>

<style scoped>
.table-view {
    @apply font-mono text-tui-base;
    @apply flex flex-col;
}

.table-search {
    @apply flex items-center gap-1 mb-2;
    @apply text-tui-primary;
}

.search-prompt {
    @apply text-tui-primary;
}

.search-input {
    @apply flex-1 bg-transparent;
    @apply text-tui-text-primary;
    @apply outline-none;
}

.table-header {
    @apply flex;
    @apply text-tui-text-secondary;
}

.table-separator {
    @apply flex;
    @apply text-tui-border-subtle;
}

.table-cell {
    @apply px-1;
    @apply truncate;
}

.header-cell {
    @apply font-semibold;
}

.separator-cell {
    @apply px-1;
}

.table-body {
    @apply flex flex-col;
}

.table-row {
    @apply flex items-center;
    @apply transition-colors duration-75;
}

.table-row--selectable {
    @apply cursor-pointer;
}

.table-row--selectable:hover {
    @apply bg-tui-hover;
}

.table-row--selected {
    @apply bg-tui-selected;
}

.row-indicator {
    @apply w-4 text-tui-primary font-bold;
}

.table-empty {
    @apply py-4 text-center;
    @apply text-tui-text-muted;
}

.table-summary {
    @apply mt-1;
}

.summary-row {
    @apply font-semibold;
}

.table-footer {
    @apply flex justify-between items-center;
    @apply mt-2 pt-2;
    @apply border-t border-tui-border-subtle;
    @apply text-tui-text-muted text-tui-sm;
}

/* Text alignment utilities */
.text-left { text-align: left; }
.text-right { text-align: right; }
.text-center { text-align: center; }
</style>
```

---

## 10. Transaction Entry Form

### 10.1 TransactionForm.vue — Quick Transaction Entry

```vue
<!-- src/components/tui/forms/TransactionForm.vue -->
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useTerminalStore } from '@/stores/terminal'
import { useAccountsStore } from '@/stores/accounts'
import { useAutocomplete } from '@/composables/useAutocomplete'
import type { TransactionFormData, TransactionEntry } from '@/types/tui'
import SuggestionDropdown from '../input/SuggestionDropdown.vue'

const emit = defineEmits<{
    submit: [data: TransactionFormData]
    cancel: []
}>()

const terminalStore = useTerminalStore()
const accountsStore = useAccountsStore()
const { state: autocomplete, updateSuggestions, selectNext, selectPrevious, getSelected, dismiss } = useAutocomplete()

// Form state
const formData = ref<TransactionFormData>({
    date: formatDate(new Date()),
    entries: [
        { id: '1', type: 'debit', account: '', amount: '' },
        { id: '2', type: 'credit', account: '', amount: '' },
    ],
    memo: '',
    tags: [],
})

// Focus tracking
type FieldKey = 'date' | `entry-${number}-account` | `entry-${number}-amount` | 'memo'
const currentField = ref<FieldKey>('date')
const fieldRefs = ref<Record<string, HTMLInputElement | null>>({})

// Field order for Tab navigation
const fieldOrder = computed<FieldKey[]>(() => {
    const fields: FieldKey[] = ['date']
    formData.value.entries.forEach((_, i) => {
        fields.push(`entry-${i}-account`)
        fields.push(`entry-${i}-amount`)
    })
    fields.push('memo')
    return fields
})

// Balance calculation
const balance = computed(() => {
    let debitTotal = 0
    let creditTotal = 0

    for (const entry of formData.value.entries) {
        const amount = parseFloat(evaluateMath(entry.amount)) || 0
        if (entry.type === 'debit') {
            debitTotal += amount
        } else {
            creditTotal += amount
        }
    }

    return {
        debitTotal,
        creditTotal,
        balanced: Math.abs(debitTotal - creditTotal) < 0.01,
        difference: debitTotal - creditTotal,
    }
})

// ======================
// FIELD NAVIGATION
// ======================

function focusField(field: FieldKey) {
    currentField.value = field
    nextTick(() => {
        fieldRefs.value[field]?.focus()
    })
}

function nextField() {
    const currentIndex = fieldOrder.value.indexOf(currentField.value)
    if (currentIndex < fieldOrder.value.length - 1) {
        focusField(fieldOrder.value[currentIndex + 1])
    }
}

function prevField() {
    const currentIndex = fieldOrder.value.indexOf(currentField.value)
    if (currentIndex > 0) {
        focusField(fieldOrder.value[currentIndex - 1])
    }
}

// ======================
// KEYBOARD HANDLING
// ======================

function handleKeyDown(event: KeyboardEvent) {
    // Global form shortcuts
    if (event.key === 'Escape') {
        event.preventDefault()
        if (autocomplete.active) {
            dismiss()
        } else {
            emit('cancel')
        }
        return
    }

    if (event.ctrlKey && event.key === 'Enter') {
        event.preventDefault()
        handleSubmit()
        return
    }

    // Autocomplete navigation
    if (autocomplete.active) {
        if (event.key === 'ArrowDown' || (event.key === 'Tab' && !event.shiftKey)) {
            event.preventDefault()
            selectNext()
            return
        }
        if (event.key === 'ArrowUp' || (event.key === 'Tab' && event.shiftKey)) {
            event.preventDefault()
            selectPrevious()
            return
        }
        if (event.key === 'Enter') {
            event.preventDefault()
            const selected = getSelected()
            if (selected) {
                acceptSuggestion(selected.value)
            }
            return
        }
    }

    // Tab navigation (when not in autocomplete)
    if (event.key === 'Tab' && !autocomplete.active) {
        event.preventDefault()
        if (event.shiftKey) {
            prevField()
        } else {
            nextField()
        }
        return
    }
}

// ======================
// INPUT HANDLING
// ======================

function handleDateInput(value: string) {
    // Handle shortcuts
    const shortcut = parseDateShortcut(value)
    if (shortcut) {
        formData.value.date = formatDate(shortcut)
    } else {
        formData.value.date = value
    }
}

function handleAccountInput(entryIndex: number, value: string) {
    formData.value.entries[entryIndex].account = value

    // Trigger autocomplete
    if (value.length > 0) {
        // Simplified - would integrate with full parser in real implementation
        triggerAccountAutocomplete(value)
    } else {
        dismiss()
    }
}

function handleAmountInput(entryIndex: number, value: string) {
    formData.value.entries[entryIndex].amount = value

    // Auto-balance: if this is the second entry, calculate from first
    if (entryIndex === 1 && formData.value.entries[0].amount && !value) {
        const firstAmount = parseFloat(evaluateMath(formData.value.entries[0].amount))
        if (!isNaN(firstAmount)) {
            formData.value.entries[1].amount = firstAmount.toFixed(2)
        }
    }
}

function triggerAccountAutocomplete(query: string) {
    const accounts = accountsStore.accounts
    const lowerQuery = query.toLowerCase()

    const suggestions = accounts
        .filter(acc =>
            acc.name.toLowerCase().includes(lowerQuery) ||
            acc.slug.toLowerCase().includes(lowerQuery)
        )
        .slice(0, 8)
        .map(acc => ({
            value: acc.slug,
            label: acc.name,
            description: acc.type,
            category: acc.type,
            score: acc.slug.startsWith(lowerQuery) ? 100 : 50,
        }))

    autocomplete.suggestions = suggestions
    autocomplete.selectedIndex = 0
    autocomplete.active = suggestions.length > 0
}

function acceptSuggestion(value: string) {
    // Find which field is active and update it
    const field = currentField.value
    if (field.startsWith('entry-') && field.endsWith('-account')) {
        const index = parseInt(field.split('-')[1])
        formData.value.entries[index].account = value
    }
    dismiss()
    nextField()
}

// ======================
// HELPERS
// ======================

function formatDate(date: Date): string {
    return date.toISOString().split('T')[0]
}

function parseDateShortcut(input: string): Date | null {
    const lower = input.toLowerCase()
    const today = new Date()

    if (lower === 't' || lower === 'today') {
        return today
    }
    if (lower === 'y' || lower === 'yesterday') {
        today.setDate(today.getDate() - 1)
        return today
    }
    if (/^-\d+$/.test(input)) {
        const days = parseInt(input)
        today.setDate(today.getDate() + days)
        return today
    }
    return null
}

function evaluateMath(expr: string): string {
    // Simple math evaluation for amounts like "100 + 25.50"
    try {
        // Only allow numbers, +, -, *, /, ., and spaces
        if (!/^[\d\s+\-*/.()]+$/.test(expr)) {
            return expr
        }
        // eslint-disable-next-line no-eval
        const result = eval(expr)
        return typeof result === 'number' ? result.toString() : expr
    } catch {
        return expr
    }
}

function addEntry() {
    formData.value.entries.push({
        id: Date.now().toString(),
        type: 'debit',
        account: '',
        amount: '',
    })
}

function removeEntry(index: number) {
    if (formData.value.entries.length > 2) {
        formData.value.entries.splice(index, 1)
    }
}

// ======================
// SUBMISSION
// ======================

function validate(): string[] {
    const errors: string[] = []

    if (!formData.value.date) {
        errors.push('Date is required')
    }

    for (let i = 0; i < formData.value.entries.length; i++) {
        const entry = formData.value.entries[i]
        if (!entry.account) {
            errors.push(`Entry ${i + 1}: Account is required`)
        }
        if (!entry.amount) {
            errors.push(`Entry ${i + 1}: Amount is required`)
        }
    }

    if (!balance.value.balanced) {
        errors.push(`Transaction is not balanced (difference: ${balance.value.difference.toFixed(2)})`)
    }

    return errors
}

function handleSubmit() {
    const errors = validate()
    if (errors.length > 0) {
        // Show errors - could use dialog or inline
        terminalStore.addOutput({
            type: 'error',
            content: { type: 'lines', lines: errors }
        })
        return
    }

    emit('submit', { ...formData.value })
}

// ======================
// LIFECYCLE
// ======================

onMounted(() => {
    document.addEventListener('keydown', handleKeyDown)
    terminalStore.setMode('form')

    // Ensure accounts are loaded
    if (!accountsStore.loaded) {
        accountsStore.fetchAccounts()
    }

    // Focus first field
    focusField('date')
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
    <div class="transaction-form">
        <!-- Header -->
        <div class="form-header">
            <span class="header-border">╭─── NEW TRANSACTION </span>
            <span class="header-fill">─</span>
            <span class="header-border">╮</span>
        </div>

        <!-- Form body -->
        <div class="form-body">
            <!-- Date field -->
            <div class="form-row">
                <label class="form-label">Date</label>
                <div class="form-input-wrapper">
                    <input
                        :ref="el => fieldRefs['date'] = el as HTMLInputElement"
                        type="text"
                        class="form-input"
                        :class="{ 'form-input--focused': currentField === 'date' }"
                        :value="formData.date"
                        placeholder="YYYY-MM-DD or t/y/-N"
                        @input="handleDateInput(($event.target as HTMLInputElement).value)"
                        @focus="currentField = 'date'"
                    />
                    <span class="form-hint">t=today, y=yesterday, -N=N days ago</span>
                </div>
            </div>

            <!-- Separator -->
            <div class="form-separator">
                ├─────────────────────────────────────────────────────────┤
            </div>

            <!-- Entries -->
            <div
                v-for="(entry, index) in formData.entries"
                :key="entry.id"
                class="entry-row"
            >
                <div class="entry-type">
                    {{ entry.type === 'debit' ? 'Debit' : 'Credit' }}
                </div>

                <div class="entry-fields">
                    <!-- Account -->
                    <div class="field-group">
                        <input
                            :ref="el => fieldRefs[`entry-${index}-account`] = el as HTMLInputElement"
                            type="text"
                            class="form-input account-input"
                            :class="{ 'form-input--focused': currentField === `entry-${index}-account` }"
                            :value="entry.account"
                            placeholder="Account"
                            @input="handleAccountInput(index, ($event.target as HTMLInputElement).value)"
                            @focus="currentField = `entry-${index}-account`"
                        />

                        <!-- Autocomplete dropdown -->
                        <SuggestionDropdown
                            v-if="autocomplete.active && currentField === `entry-${index}-account`"
                            :suggestions="autocomplete.suggestions"
                            :selected-index="autocomplete.selectedIndex"
                            :loading="false"
                            @select="acceptSuggestion($event.value)"
                        />
                    </div>

                    <!-- Amount -->
                    <div class="field-group">
                        <span class="currency-symbol">$</span>
                        <input
                            :ref="el => fieldRefs[`entry-${index}-amount`] = el as HTMLInputElement"
                            type="text"
                            class="form-input amount-input"
                            :class="{ 'form-input--focused': currentField === `entry-${index}-amount` }"
                            :value="entry.amount"
                            placeholder="0.00"
                            @input="handleAmountInput(index, ($event.target as HTMLInputElement).value)"
                            @focus="currentField = `entry-${index}-amount`"
                        />
                    </div>
                </div>

                <!-- Remove button (if more than 2 entries) -->
                <button
                    v-if="formData.entries.length > 2"
                    class="remove-entry"
                    @click="removeEntry(index)"
                >
                    ×
                </button>
            </div>

            <!-- Add entry button -->
            <button class="add-entry" @click="addEntry">
                + Add line
            </button>

            <!-- Separator -->
            <div class="form-separator">
                ├─────────────────────────────────────────────────────────┤
            </div>

            <!-- Memo -->
            <div class="form-row">
                <label class="form-label">Memo</label>
                <input
                    :ref="el => fieldRefs['memo'] = el as HTMLInputElement"
                    type="text"
                    class="form-input"
                    :class="{ 'form-input--focused': currentField === 'memo' }"
                    v-model="formData.memo"
                    placeholder="Optional description"
                    @focus="currentField = 'memo'"
                />
            </div>

            <!-- Balance status -->
            <div class="balance-status" :class="balance.balanced ? 'balanced' : 'unbalanced'">
                <span v-if="balance.balanced">✓ Balanced</span>
                <span v-else>
                    ✗ Unbalanced: {{ balance.difference > 0 ? '+' : '' }}{{ balance.difference.toFixed(2) }}
                </span>
            </div>
        </div>

        <!-- Footer -->
        <div class="form-footer">
            <div class="footer-hints">
                Tab next │ Shift+Tab prev │ Ctrl+Enter save │ Esc cancel
            </div>
            <div class="footer-actions">
                <button class="btn btn-secondary" @click="emit('cancel')">
                    Cancel
                </button>
                <button
                    class="btn btn-primary"
                    :disabled="!balance.balanced"
                    @click="handleSubmit"
                >
                    Save
                </button>
            </div>
        </div>

        <!-- Bottom border -->
        <div class="form-header">
            <span class="header-border">╰─────────────────────────────────────────────────────────╯</span>
        </div>
    </div>
</template>

<style scoped>
.transaction-form {
    @apply font-mono text-tui-base;
    @apply bg-tui-bg-secondary;
    @apply border border-tui-border-default rounded;
    @apply max-w-2xl mx-auto;
}

.form-header {
    @apply flex;
    @apply text-tui-border-default;
    @apply px-4;
}

.header-fill {
    @apply flex-1;
    @apply overflow-hidden;
}

.form-body {
    @apply px-6 py-4;
    @apply flex flex-col gap-3;
}

.form-row {
    @apply flex items-center gap-4;
}

.form-label {
    @apply w-16 text-tui-text-secondary;
}

.form-input-wrapper {
    @apply flex-1 flex flex-col gap-1;
}

.form-input {
    @apply flex-1 px-2 py-1;
    @apply bg-tui-bg-primary;
    @apply border border-tui-border-subtle rounded;
    @apply text-tui-text-primary;
    @apply outline-none;
}

.form-input:focus,
.form-input--focused {
    @apply border-tui-primary;
}

.form-input::placeholder {
    @apply text-tui-text-placeholder;
}

.form-hint {
    @apply text-tui-text-muted text-tui-sm;
}

.form-separator {
    @apply text-tui-border-subtle;
    @apply overflow-hidden whitespace-nowrap;
}

.entry-row {
    @apply flex items-center gap-4;
}

.entry-type {
    @apply w-16 text-tui-text-secondary;
}

.entry-fields {
    @apply flex-1 flex gap-4;
}

.field-group {
    @apply relative flex items-center;
}

.account-input {
    @apply w-48;
}

.currency-symbol {
    @apply text-tui-text-muted mr-1;
}

.amount-input {
    @apply w-28 text-right;
}

.remove-entry {
    @apply w-6 h-6;
    @apply text-tui-danger;
    @apply hover:bg-tui-hover rounded;
}

.add-entry {
    @apply text-tui-text-muted text-tui-sm;
    @apply hover:text-tui-primary;
    @apply cursor-pointer;
}

.balance-status {
    @apply text-right py-2;
}

.balance-status.balanced {
    @apply text-tui-success;
}

.balance-status.unbalanced {
    @apply text-tui-danger;
}

.form-footer {
    @apply flex justify-between items-center;
    @apply px-6 py-3;
    @apply border-t border-tui-border-subtle;
}

.footer-hints {
    @apply text-tui-text-muted text-tui-sm;
}

.footer-actions {
    @apply flex gap-2;
}

.btn {
    @apply px-4 py-1;
    @apply font-mono text-tui-sm;
    @apply border rounded;
    @apply cursor-pointer;
}

.btn:disabled {
    @apply opacity-50 cursor-not-allowed;
}

.btn-primary {
    @apply bg-tui-primary text-tui-bg-primary border-tui-primary;
}

.btn-secondary {
    @apply bg-transparent text-tui-text-secondary border-tui-border-default;
}
</style>
```

---

## 11. Backend Integration

### 11.1 Laravel API Routes

```php
// routes/api.php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\SuggestionController;

Route::middleware('auth:sanctum')->group(function () {
    // Accounts
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::get('/accounts/{account}', [AccountController::class, 'show']);
    Route::put('/accounts/{account}', [AccountController::class, 'update']);
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

    // Suggestions (for autocomplete)
    Route::get('/suggest/accounts', [SuggestionController::class, 'accounts']);
    Route::get('/suggest/tags', [SuggestionController::class, 'tags']);
    Route::get('/suggest/memos', [SuggestionController::class, 'memos']);

    // Undo
    Route::post('/undo/{action}', [TransactionController::class, 'undo']);
});
```

### 11.2 Account Controller

```php
// app/Http/Controllers/Api/AccountController.php

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $query = Account::query()
            ->where('user_id', $request->user()->id)
            ->with('parent');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('type')->orderBy('name')->get();

        // Calculate balances
        $accounts->each(function ($account) {
            $account->balance = $account->calculateBalance();
        });

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:accounts,id',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($validated['name']);

        // Ensure unique slug for this user
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (Account::where('user_id', $validated['user_id'])
                      ->where('slug', $validated['slug'])
                      ->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        $account = Account::create($validated);

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => "Account '{$account->name}' created",
        ], 201);
    }

    public function show(Account $account)
    {
        $this->authorize('view', $account);

        $account->balance = $account->calculateBalance();
        $account->load('parent', 'children');

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:accounts,id',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $account->update($validated);

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => "Account '{$account->name}' updated",
        ]);
    }

    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        // Check if account has transactions
        if ($account->journalEntries()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete account with transactions',
            ], 422);
        }

        $name = $account->name;
        $account->delete();

        return response()->json([
            'success' => true,
            'message' => "Account '{$name}' deleted",
        ]);
    }
}
```

### 11.3 Transaction Controller

```php
// app/Http/Controllers/Api/TransactionController.php

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\UndoAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->with('entries.account');

        // Date filters
        if ($request->has('from')) {
            $query->where('date', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('date', '<=', $request->to);
        }

        // Account filter
        if ($request->has('account')) {
            $query->whereHas('entries', function ($q) use ($request) {
                $q->whereHas('account', function ($q2) use ($request) {
                    $q2->where('slug', $request->account);
                });
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('memo', 'like', "%{$search}%");
        }

        // Pagination
        $limit = min($request->get('limit', 50), 100);
        $transactions = $query
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'memo' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|exists:accounts,id',
            'entries.*.debit' => 'nullable|numeric|min:0',
            'entries.*.credit' => 'nullable|numeric|min:0',
        ]);

        // Validate balance
        $totalDebit = collect($validated['entries'])->sum('debit');
        $totalCredit = collect($validated['entries'])->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction must be balanced',
                'errors' => [
                    'entries' => ["Debits ({$totalDebit}) must equal credits ({$totalCredit})"],
                ],
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $request->user()->id,
                'date' => $validated['date'],
                'memo' => $validated['memo'] ?? null,
                'tags' => $validated['tags'] ?? [],
            ]);

            // Create journal entries
            foreach ($validated['entries'] as $entry) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'] ?? null,
                    'credit' => $entry['credit'] ?? null,
                ]);
            }

            // Store undo action
            UndoAction::create([
                'user_id' => $request->user()->id,
                'type' => 'create',
                'entity' => 'transaction',
                'entity_id' => $transaction->id,
                'data' => $transaction->load('entries')->toArray(),
            ]);

            DB::commit();

            $transaction->load('entries.account');

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => "Transaction #{$transaction->id} created",
                'undo_id' => $transaction->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);

        $transaction->load('entries.account');

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        // Store original for undo
        $original = $transaction->load('entries')->toArray();

        $validated = $request->validate([
            'date' => 'sometimes|date',
            'memo' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'entries' => 'sometimes|array|min:2',
            'entries.*.account_id' => 'required_with:entries|exists:accounts,id',
            'entries.*.debit' => 'nullable|numeric|min:0',
            'entries.*.credit' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Update transaction fields
            $transaction->update([
                'date' => $validated['date'] ?? $transaction->date,
                'memo' => $validated['memo'] ?? $transaction->memo,
                'tags' => $validated['tags'] ?? $transaction->tags,
            ]);

            // Update entries if provided
            if (isset($validated['entries'])) {
                // Validate balance
                $totalDebit = collect($validated['entries'])->sum('debit');
                $totalCredit = collect($validated['entries'])->sum('credit');

                if (abs($totalDebit - $totalCredit) > 0.01) {
                    throw new \Exception('Transaction must be balanced');
                }

                // Delete old entries and create new
                $transaction->entries()->delete();

                foreach ($validated['entries'] as $entry) {
                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'account_id' => $entry['account_id'],
                        'debit' => $entry['debit'] ?? null,
                        'credit' => $entry['credit'] ?? null,
                    ]);
                }
            }

            // Store undo action
            UndoAction::create([
                'user_id' => $request->user()->id,
                'type' => 'update',
                'entity' => 'transaction',
                'entity_id' => $transaction->id,
                'data' => [
                    'before' => $original,
                    'after' => $transaction->load('entries')->toArray(),
                ],
            ]);

            DB::commit();

            $transaction->load('entries.account');

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => "Transaction #{$transaction->id} updated",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        // Store for undo
        $data = $transaction->load('entries')->toArray();

        DB::beginTransaction();
        try {
            $id = $transaction->id;

            // Store undo action BEFORE deleting
            $undoAction = UndoAction::create([
                'user_id' => $transaction->user_id,
                'type' => 'delete',
                'entity' => 'transaction',
                'entity_id' => $id,
                'data' => $data,
            ]);

            $transaction->entries()->delete();
            $transaction->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Transaction #{$id} deleted",
                'undo_id' => $undoAction->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function undo(Request $request, $actionId)
    {
        $action = UndoAction::where('user_id', $request->user()->id)
            ->findOrFail($actionId);

        DB::beginTransaction();
        try {
            switch ($action->type) {
                case 'create':
                    // Undo create = delete
                    $transaction = Transaction::find($action->entity_id);
                    if ($transaction) {
                        $transaction->entries()->delete();
                        $transaction->delete();
                    }
                    $message = "Transaction #{$action->entity_id} creation undone";
                    break;

                case 'delete':
                    // Undo delete = restore
                    $data = $action->data;
                    $transaction = Transaction::create([
                        'id' => $data['id'],
                        'user_id' => $data['user_id'],
                        'date' => $data['date'],
                        'memo' => $data['memo'],
                        'tags' => $data['tags'] ?? [],
                    ]);
                    foreach ($data['entries'] as $entry) {
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'account_id' => $entry['account_id'],
                            'debit' => $entry['debit'],
                            'credit' => $entry['credit'],
                        ]);
                    }
                    $message = "Transaction #{$action->entity_id} restored";
                    break;

                case 'update':
                    // Undo update = restore previous state
                    $transaction = Transaction::find($action->entity_id);
                    if ($transaction) {
                        $before = $action->data['before'];
                        $transaction->update([
                            'date' => $before['date'],
                            'memo' => $before['memo'],
                            'tags' => $before['tags'] ?? [],
                        ]);
                        $transaction->entries()->delete();
                        foreach ($before['entries'] as $entry) {
                            JournalEntry::create([
                                'transaction_id' => $transaction->id,
                                'account_id' => $entry['account_id'],
                                'debit' => $entry['debit'],
                                'credit' => $entry['credit'],
                            ]);
                        }
                    }
                    $message = "Transaction #{$action->entity_id} update undone";
                    break;
            }

            // Delete the undo action (one-time use)
            $action->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

### 11.4 Suggestion Controller

```php
// app/Http/Controllers/Api/SuggestionController.php

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function accounts(Request $request)
    {
        $query = $request->get('q', '');
        $contextFrom = $request->get('from'); // For smart suggestions

        $accounts = Account::where('user_id', $request->user()->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('slug', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($account) {
                return [
                    'value' => $account->slug,
                    'label' => $account->name,
                    'type' => $account->type,
                    'code' => $account->code,
                    'balance' => $account->calculateBalance(),
                ];
            });

        // If we have context (from account), boost relevant suggestions
        if ($contextFrom) {
            $fromAccount = Account::where('slug', $contextFrom)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($fromAccount) {
                // Find frequently paired accounts
                $frequentPairs = $this->getFrequentlyPairedAccounts(
                    $request->user()->id,
                    $fromAccount->id
                );

                // Boost scores for frequent pairs
                $accounts = $accounts->map(function ($acc) use ($frequentPairs) {
                    $acc['score'] = $frequentPairs[$acc['value']] ?? 0;
                    return $acc;
                })->sortByDesc('score')->values();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function tags(Request $request)
    {
        $query = $request->get('q', '');

        // Get all unique tags from user's transactions
        $tags = Transaction::where('user_id', $request->user()->id)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->filter(function ($tag) use ($query) {
                return stripos($tag, $query) !== false;
            })
            ->take(10)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $tags->map(fn($tag) => [
                'value' => $tag,
                'label' => "#{$tag}",
            ]),
        ]);
    }

    public function memos(Request $request)
    {
        $query = $request->get('q', '');

        $memos = Transaction::where('user_id', $request->user()->id)
            ->whereNotNull('memo')
            ->where('memo', 'like', "%{$query}%")
            ->distinct()
            ->pluck('memo')
            ->take(10);

        return response()->json([
            'success' => true,
            'data' => $memos->map(fn($memo) => [
                'value' => $memo,
                'label' => $memo,
            ]),
        ]);
    }

    private function getFrequentlyPairedAccounts(int $userId, int $accountId): array
    {
        // Find accounts that frequently appear in the same transaction
        $pairs = \DB::table('journal_entries as je1')
            ->join('journal_entries as je2', 'je1.transaction_id', '=', 'je2.transaction_id')
            ->join('transactions as t', 't.id', '=', 'je1.transaction_id')
            ->join('accounts as a', 'a.id', '=', 'je2.account_id')
            ->where('t.user_id', $userId)
            ->where('je1.account_id', $accountId)
            ->where('je2.account_id', '!=', $accountId)
            ->select('a.slug', \DB::raw('COUNT(*) as count'))
            ->groupBy('a.slug')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'slug')
            ->toArray();

        return $pairs;
    }
}
```

---

## 12. State Management

### 12.1 Terminal Store

```typescript
// src/stores/terminal.ts
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
    UIMode,
    OutputBlock,
    DialogConfig,
    AutocompleteState
} from '@/types/tui'
import { useCommandParser } from '@/composables/useCommandParser'
import { useCommandExecutor } from '@/composables/useCommandExecutor'

export const useTerminalStore = defineStore('terminal', () => {
    // ======================
    // STATE
    // ======================

    const mode = ref<UIMode>('command')
    const currentInput = ref('')
    const outputHistory = ref<OutputBlock[]>([])
    const activeDialog = ref<DialogConfig | null>(null)
    const commandHistory = ref<string[]>([])
    const historyIndex = ref(-1)

    const autocomplete = ref<AutocompleteState>({
        active: false,
        suggestions: [],
        selectedIndex: 0,
        loading: false,
        query: '',
        context: null,
    })

    // ======================
    // GETTERS
    // ======================

    const contextHints = computed(() => {
        // Return context-appropriate keyboard hints
        switch (mode.value) {
            case 'command':
                if (autocomplete.value.active) {
                    return ['Tab accept', '↑↓ navigate', 'Esc dismiss']
                }
                return ['Enter execute', '↑↓ history', 'Ctrl+P palette']
            case 'browse':
                return ['j/k navigate', 'Enter select', 'q back']
            case 'dialog':
                return ['Enter confirm', 'Esc cancel']
            case 'form':
                return ['Tab next', 'Ctrl+Enter submit', 'Esc cancel']
            default:
                return []
        }
    })

    // ======================
    // ACTIONS
    // ======================

    function setMode(newMode: UIMode) {
        mode.value = newMode
    }

    function addOutput(block: Omit<OutputBlock, 'id' | 'timestamp'>) {
        outputHistory.value.push({
            ...block,
            id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
            timestamp: new Date(),
        })

        // Limit history size
        if (outputHistory.value.length > 500) {
            outputHistory.value = outputHistory.value.slice(-500)
        }
    }

    function clearOutput() {
        outputHistory.value = []
    }

    async function executeCommand(input: string) {
        if (!input.trim()) return

        // Add command to output
        addOutput({
            type: 'command',
            content: { type: 'text', text: input },
        })

        // Add to history
        commandHistory.value.unshift(input)
        if (commandHistory.value.length > 100) {
            commandHistory.value = commandHistory.value.slice(0, 100)
        }
        historyIndex.value = -1

        // Clear input
        currentInput.value = ''

        // Parse and execute
        const { parse } = useCommandParser()
        const { execute } = useCommandExecutor()

        const parsed = parse(input, input.length)

        // Show loading
        const loadingId = `loading-${Date.now()}`
        addOutput({
            type: 'loading',
            content: { type: 'text', text: 'Processing...' },
        })

        try {
            const result = await execute(parsed)

            // Remove loading
            outputHistory.value = outputHistory.value.filter(
                b => b.id !== loadingId
            )

            // Add result
            if (result) {
                addOutput(result)
            }
        } catch (error: any) {
            // Remove loading
            outputHistory.value = outputHistory.value.filter(
                b => b.id !== loadingId
            )

            addOutput({
                type: 'error',
                content: { type: 'text', text: error.message || 'An error occurred' },
            })
        }
    }

    function navigateHistory(direction: 'up' | 'down') {
        if (commandHistory.value.length === 0) return

        if (direction === 'up') {
            if (historyIndex.value < commandHistory.value.length - 1) {
                historyIndex.value++
                currentInput.value = commandHistory.value[historyIndex.value]
            }
        } else {
            if (historyIndex.value > 0) {
                historyIndex.value--
                currentInput.value = commandHistory.value[historyIndex.value]
            } else if (historyIndex.value === 0) {
                historyIndex.value = -1
                currentInput.value = ''
            }
        }
    }

    // Dialog management
    function openDialog(config: DialogConfig) {
        activeDialog.value = config
        mode.value = 'dialog'
    }

    function closeDialog() {
        activeDialog.value = null
        mode.value = 'command'
    }

    // Autocomplete management
    function acceptSuggestion(suggestion: { value: string }) {
        // Insert suggestion at cursor position
        // This is simplified - real implementation needs cursor tracking
        currentInput.value = currentInput.value.replace(
            autocomplete.value.query,
            suggestion.value
        )

        autocomplete.value.active = false
        autocomplete.value.suggestions = []
    }

    // Special modes
    function togglePalette() {
        if (mode.value === 'palette') {
            mode.value = 'command'
        } else {
            mode.value = 'palette'
        }
    }

    function openTransactionForm() {
        mode.value = 'form'
        // The form component will handle the rest
    }

    // ======================
    // PERSISTENCE
    // ======================

    // Load from localStorage on init
    function loadPersistedState() {
        try {
            const saved = localStorage.getItem('tui-command-history')
            if (saved) {
                commandHistory.value = JSON.parse(saved)
            }
        } catch (e) {
            console.error('Failed to load command history:', e)
        }
    }

    // Save to localStorage
    function persistState() {
        try {
            localStorage.setItem(
                'tui-command-history',
                JSON.stringify(commandHistory.value.slice(0, 100))
            )
        } catch (e) {
            console.error('Failed to save command history:', e)
        }
    }

    // Initialize
    loadPersistedState()

    return {
        // State
        mode,
        currentInput,
        outputHistory,
        activeDialog,
        autocomplete,
        commandHistory,

        // Getters
        contextHints,

        // Actions
        setMode,
        addOutput,
        clearOutput,
        executeCommand,
        navigateHistory,
        openDialog,
        closeDialog,
        acceptSuggestion,
        togglePalette,
        openTransactionForm,
        persistState,
    }
})
```

### 12.2 Accounts Store

```typescript
// src/stores/accounts.ts
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Account } from '@/types/tui'
import axios from 'axios'

export const useAccountsStore = defineStore('accounts', () => {
    // ======================
    // STATE
    // ======================

    const accounts = ref<Account[]>([])
    const loaded = ref(false)
    const loading = ref(false)
    const error = ref<string | null>(null)

    // ======================
    // GETTERS
    // ======================

    const accountsByType = computed(() => {
        const grouped: Record<string, Account[]> = {
            asset: [],
            liability: [],
            equity: [],
            revenue: [],
            expense: [],
        }

        for (const account of accounts.value) {
            grouped[account.type]?.push(account)
        }

        return grouped
    })

    const accountsMap = computed(() => {
        const map = new Map<string, Account>()
        for (const account of accounts.value) {
            map.set(account.slug, account)
        }
        return map
    })

    // ======================
    // ACTIONS
    // ======================

    async function fetchAccounts() {
        if (loading.value) return

        loading.value = true
        error.value = null

        try {
            const response = await axios.get('/api/accounts')
            accounts.value = response.data.data
            loaded.value = true
        } catch (e: any) {
            error.value = e.response?.data?.error || 'Failed to fetch accounts'
            throw e
        } finally {
            loading.value = false
        }
    }

    async function createAccount(data: Partial<Account>) {
        const response = await axios.post('/api/accounts', data)
        const newAccount = response.data.data
        accounts.value.push(newAccount)
        return newAccount
    }

    async function updateAccount(slug: string, data: Partial<Account>) {
        const account = getBySlug(slug)
        if (!account) throw new Error('Account not found')

        const response = await axios.put(`/api/accounts/${account.id}`, data)
        const updated = response.data.data

        const index = accounts.value.findIndex(a => a.id === account.id)
        if (index !== -1) {
            accounts.value[index] = updated
        }

        return updated
    }

    async function deleteAccount(slug: string) {
        const account = getBySlug(slug)
        if (!account) throw new Error('Account not found')

        await axios.delete(`/api/accounts/${account.id}`)
        accounts.value = accounts.value.filter(a => a.id !== account.id)
    }

    function getBySlug(slug: string): Account | undefined {
        return accountsMap.value.get(slug)
    }

    function getById(id: number): Account | undefined {
        return accounts.value.find(a => a.id === id)
    }

    function search(query: string): Account[] {
        const lower = query.toLowerCase()
        return accounts.value.filter(a =>
            a.name.toLowerCase().includes(lower) ||
            a.slug.toLowerCase().includes(lower) ||
            (a.code && a.code.includes(query))
        )
    }

    return {
        // State
        accounts,
        loaded,
        loading,
        error,

        // Getters
        accountsByType,
        accountsMap,

        // Actions
        fetchAccounts,
        createAccount,
        updateAccount,
        deleteAccount,
        getBySlug,
        getById,
        search,
    }
})
```

### 12.3 Commands Store

```typescript
// src/stores/commands.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'

interface UsageData {
    accounts: Record<string, number>
    params: Record<string, Record<string, number>>
    tags: Record<string, number>
}

export const useCommandsStore = defineStore('commands', () => {
    // ======================
    // STATE
    // ======================

    const history = ref<string[]>([])
    const usage = ref<UsageData>({
        accounts: {},
        params: {},
        tags: {},
    })

    // ======================
    // ACTIONS
    // ======================

    function recordCommand(command: string) {
        history.value.unshift(command)
        if (history.value.length > 100) {
            history.value = history.value.slice(0, 100)
        }
        persistHistory()
    }

    function recordAccountUsage(slug: string) {
        usage.value.accounts[slug] = (usage.value.accounts[slug] || 0) + 1
        persistUsage()
    }

    function recordParamValue(param: string, value: string) {
        if (!usage.value.params[param]) {
            usage.value.params[param] = {}
        }
        usage.value.params[param][value] =
            (usage.value.params[param][value] || 0) + 1
        persistUsage()
    }

    function recordTag(tag: string) {
        usage.value.tags[tag] = (usage.value.tags[tag] || 0) + 1
        persistUsage()
    }

    function getAccountUsageCount(slug: string): number {
        return usage.value.accounts[slug] || 0
    }

    function getRecentParamValues(param: string): string[] {
        const values = usage.value.params[param] || {}
        return Object.entries(values)
            .sort((a, b) => b[1] - a[1])
            .map(([value]) => value)
            .slice(0, 10)
    }

    function getRecentTags(): string[] {
        return Object.entries(usage.value.tags)
            .sort((a, b) => b[1] - a[1])
            .map(([tag]) => tag)
            .slice(0, 20)
    }

    // ======================
    // PERSISTENCE
    // ======================

    function loadState() {
        try {
            const savedHistory = localStorage.getItem('tui-cmd-history')
            if (savedHistory) {
                history.value = JSON.parse(savedHistory)
            }

            const savedUsage = localStorage.getItem('tui-cmd-usage')
            if (savedUsage) {
                usage.value = JSON.parse(savedUsage)
            }
        } catch (e) {
            console.error('Failed to load command store:', e)
        }
    }

    function persistHistory() {
        try {
            localStorage.setItem('tui-cmd-history', JSON.stringify(history.value))
        } catch (e) {
            console.error('Failed to persist history:', e)
        }
    }

    function persistUsage() {
        try {
            localStorage.setItem('tui-cmd-usage', JSON.stringify(usage.value))
        } catch (e) {
            console.error('Failed to persist usage:', e)
        }
    }

    // Initialize
    loadState()

    return {
        history,
        usage,
        recordCommand,
        recordAccountUsage,
        recordParamValue,
        recordTag,
        getAccountUsageCount,
        getRecentParamValues,
        getRecentTags,
    }
})
```

---

## 13. Testing Checklist

Use this checklist to verify each feature works correctly.

### Phase 1: Foundation

- [ ] Terminal container renders and captures focus
- [ ] Blinking cursor displays correctly
- [ ] Typing inserts characters at cursor position
- [ ] Backspace deletes character before cursor
- [ ] Arrow keys move cursor left/right
- [ ] Home/End move cursor to start/end
- [ ] Ctrl+U clears line before cursor
- [ ] Ctrl+K clears line after cursor
- [ ] Ctrl+W deletes word before cursor
- [ ] Enter submits command
- [ ] Command appears in output history
- [ ] Output scrolls to bottom on new content
- [ ] Status bar shows current mode
- [ ] `help` command shows output
- [ ] `clear` command clears screen

### Phase 2: Intelligence

- [ ] Typing shows autocomplete dropdown
- [ ] Tab/Down moves to next suggestion
- [ ] Shift+Tab/Up moves to previous suggestion
- [ ] Enter/Tab accepts suggestion
- [ ] Escape dismisses dropdown
- [ ] Ghost text shows completion preview
- [ ] Account suggestions load from API
- [ ] Suggestions filter as you type
- [ ] Fuzzy matching works (e.g., "chk" matches "checking")
- [ ] Validation errors show inline
- [ ] Invalid param values highlighted

### Phase 3: Navigation

- [ ] `list tx` opens table view
- [ ] Mode changes to "browse"
- [ ] j/k moves selection up/down
- [ ] Enter on row triggers select action
- [ ] e on row triggers edit action
- [ ] d on row triggers delete (with confirm)
- [ ] / opens search within table
- [ ] q returns to command mode
- [ ] Ctrl+P opens command palette
- [ ] Palette filters as you type
- [ ] Selecting palette item executes command

### Phase 4: Transaction Entry

- [ ] Ctrl+N opens transaction form
- [ ] Date field accepts shortcuts (t, y, -N)
- [ ] Tab moves between fields
- [ ] Account fields show autocomplete
- [ ] Amount fields accept math (100+25)
- [ ] Balance indicator shows correct status
- [ ] Green check when balanced
- [ ] Red X when unbalanced
- [ ] Ctrl+Enter submits form
- [ ] Escape cancels form
- [ ] Transaction created on submit
- [ ] Success message appears

### Phase 5: Polish

- [ ] Ctrl+Z undoes last action
- [ ] Undo works for create/update/delete
- [ ] Up arrow recalls previous command
- [ ] Down arrow goes to next command
- [ ] !! repeats last command
- [ ] Search with filters works
- [ ] Multiple concurrent API calls don't break UI
- [ ] Loading spinners appear during API calls
- [ ] Errors show user-friendly messages
- [ ] Keyboard shortcuts shown in status bar

### Dialog System

- [ ] Confirm dialog opens on destructive action
- [ ] y/Enter confirms
- [ ] n/Escape cancels
- [ ] Alert dialog shows messages
- [ ] Form dialog collects input
- [ ] Dialog backdrop dims screen
- [ ] Clicking backdrop closes dialog

### Backend Integration

- [ ] Accounts load on startup
- [ ] Transactions list with pagination
- [ ] Create transaction persists
- [ ] Edit transaction persists
- [ ] Delete transaction persists
- [ ] Undo creates/deletes/updates work
- [ ] Validation errors from server display
- [ ] Auth errors redirect to login

---

## Quick Reference Card

Print this for your team:

```
╔════════════════════════════════════════════════════════════════╗
║                    LEDGER TUI - QUICK REFERENCE                ║
╠════════════════════════════════════════════════════════════════╣
║  GLOBAL                                                        ║
║    Ctrl+P      Command palette                                 ║
║    Ctrl+N      New transaction                                 ║
║    Ctrl+L      Clear screen                                    ║
║    Ctrl+Z      Undo                                            ║
║                                                                ║
║  COMMAND MODE                                                  ║
║    Enter       Execute command                                 ║
║    Tab         Autocomplete / accept suggestion                ║
║    ↑/↓         Navigate history / suggestions                  ║
║    Ctrl+U      Clear line                                      ║
║    Ctrl+W      Delete word                                     ║
║                                                                ║
║  BROWSE MODE (tables)                                          ║
║    j/k         Move down/up                                    ║
║    Enter       Select/view item                                ║
║    e           Edit item                                       ║
║    d           Delete item                                     ║
║    /           Search                                          ║
║    q           Back to command                                 ║
║                                                                ║
║  FORMS                                                         ║
║    Tab         Next field                                      ║
║    Shift+Tab   Previous field                                  ║
║    Ctrl+Enter  Submit                                          ║
║    Escape      Cancel                                          ║
║                                                                ║
║  COMMANDS                                                      ║
║    add tx from:<acc> to:<acc> amt:<$> [memo:<text>]           ║
║    list tx [--limit:N] [--from:date] [--to:date]              ║
║    list accounts [--type:TYPE]                                 ║
║    edit tx <id>                                                ║
║    delete tx <id>                                              ║
║    report balance                                              ║
║    report income [--from:date] [--to:date]                    ║
║    help [command]                                              ║
║    clear                                                       ║
║                                                                ║
║  DATE SHORTCUTS                                                ║
║    t           Today                                           ║
║    y           Yesterday                                       ║
║    -N          N days ago                                      ║
╚════════════════════════════════════════════════════════════════╝
```

---

This completes the detailed implementation guide. Each component is self-contained with clear responsibilities. Start with Phase 1, get it working end-to-end, then layer on intelligence and polish.
