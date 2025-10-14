# Ledger Components

This directory contains reusable Vue components for Ledger functionality throughout the application.

## Components Overview

### LedgerEntriesTable
**Location**: `/resources/js/Components/Ledger/LedgerEntriesTable.vue`

A comprehensive table component for displaying journal entries with filtering, sorting, and bulk operations.

#### Features
- Configurable columns with filtering and sorting capabilities
- Bulk actions (post, void) with permission controls
- Status badge display with appropriate severity colors
- Action buttons for view, edit, post, void, and print operations
- Active filter chips with clear functionality
- Row selection for bulk operations
- Responsive design with stack layout on mobile

#### Props
```typescript
interface Props {
  entries: any           // Paginated journal entries data
  filters: any           // Initial filter state
  routeName: string      // Base route name for navigation
  permissions?: {
    view?: boolean
    create?: boolean
    edit?: boolean
    delete?: boolean
  }
  showActions?: boolean
  bulkActions?: {
    post?: boolean
    void?: boolean
  }
  customColumns?: Array<{
    field: string
    header: string
    filter?: any
    style?: string
  }>
}
```

#### Usage Example
```vue
<LedgerEntriesTable
  :entries="entries"
  :filters="filters"
  routeName="ledger"
  :permissions="{
    view: canView,
    create: canCreate,
    edit: canEdit,
    delete: canDelete
  }"
  :bulkActions="{
    post: canEdit,
    void: canDelete
  }"
/>
```

---

### JournalEntryForm
**Location**: `/resources/js/Components/Ledger/JournalEntryForm.vue`

A form component for creating and editing double-entry journal entries with real-time balance validation.

#### Features
- Dynamic journal line management (add/remove lines)
- Real-time balance calculation and validation
- Auto-balance helper for quick entry balancing
- Account selection with code and name display
- Date picker with format validation
- Optional reference number field
- Form validation using Inertia.js
- Configurable permissions and submit methods

#### Props
```typescript
interface Props {
  initialData?: {
    description?: string
    reference?: string
    date?: string
    lines?: JournalLine[]
  }
  accounts: Account[]     // Available ledger accounts
  routeName: string       // Base route name
  submitRoute: string     // Route for form submission
  method?: 'post' | 'put'
  permissions?: {
    create?: boolean
    edit?: boolean
  }
  title?: string
  subtitle?: string
  showHeader?: boolean
}
```

#### Events
- `submit`: Emitted when form is submitted
- `cancel`: Emitted when cancel is clicked
- `success`: Emitted on successful submission

#### Usage Example
```vue
<JournalEntryForm
  :accounts="accounts"
  routeName="ledger"
  submitRoute="route('ledger.store')"
  :permissions="{
    create: canCreate,
    edit: canCreate
  }"
  @success="handleSuccess"
  @cancel="handleCancel"
/>
```

---

### JournalEntrySummary
**Location**: `/resources/js/Components/Ledger/JournalEntrySummary.vue`

A summary display component showing journal entry details, totals, and available actions.

#### Features
- Entry details display (status, date, reference, description)
- Posted/created timestamps with user attribution
- Totals calculation with balance validation
- Status-specific action cards:
  - Draft: Post entry action
  - Posted: Void entry action
  - Voided: Void reason display
- Configurable permission-based action visibility

#### Props
```typescript
interface Props {
  entry: JournalEntry     // Journal entry data
  permissions?: {
    post?: boolean
    void?: boolean
  }
  showActions?: boolean
}
```

#### Events
- `post`: Emitted when post action is triggered
- `void`: Emitted when void action is triggered

#### Usage Example
```vue
<JournalEntrySummary
  :entry="entry"
  :permissions="{
    post: canPost,
    void: canVoid
  }"
  @post="handlePost"
  @void="handleVoid"
/>
```

---

### LinesTable
**Location**: `/resources/js/Components/Ledger/LinesTable.vue`

A table component for displaying individual journal entry lines with account information and amounts.

#### Features
- Grid layout with account codes and names
- Debit/credit amount display with color coding
- Optional account type display
- Configurable line numbering
- Click handlers for line interactions
- Running totals with balance indicator
- Empty state handling

#### Props
```typescript
interface Props {
  lines: JournalLine[]    // Array of journal lines
  showAccountType?: boolean
  showLineNumbers?: boolean
  currency?: string
}
```

#### Events
- `lineClick`: Emitted when a line is clicked

#### Usage Example
```vue
<LinesTable
  :lines="entry.journal_lines"
  :showAccountType="true"
  :showLineNumbers="true"
  @lineClick="handleLineClick"
/>
```

---

### LedgerAccountsFilters
**Location**: `/resources/js/Components/Ledger/LedgerAccountsFilters.vue`

A filter component for ledger accounts with search, type filtering, and status filtering.

#### Features
- Search input with debounced filtering
- Account type dropdown (Asset, Liability, Equity, Revenue, Expense)
- Status filter (All, Active Only, Inactive Only)
- Active filter chips with individual clear capability
- Clear all filters functionality
- Configurable auto-apply behavior
- URL integration for filter persistence

#### Props
```typescript
interface Props {
  initialFilters?: Partial<AccountFilters>
  routeName?: string
  autoApply?: boolean
}
```

#### Events
- `filtersChange`: Emitted when filters change
- `apply`: Emitted when filters are applied
- `clear`: Emitted when filters are cleared

#### Usage Example
```vue
<LedgerAccountsFilters
  :initialFilters="filters"
  routeName="ledger.accounts.index"
  :autoApply="true"
  @apply="handleFiltersApply"
/>
```

## Styling Conventions

All components follow these styling patterns:
- Use PrimeVue components for consistency
- Support dark mode with `dark:` variants
- Rounded corners (0.75rem for cards, 0.5rem for inputs)
- Consistent spacing using Tailwind utilities
- Hover states and transitions for interactive elements
- Responsive design with mobile-first approach

## TypeScript Interfaces

Key interfaces used across components:

```typescript
interface JournalEntry {
  id: number
  reference?: string
  date: string
  description: string
  status: 'draft' | 'posted' | 'void'
  total_debit: number
  total_credit: number
  posted_at?: string
  posted_by?: any
  created_at: string
  created_by?: any
  metadata?: {
    void_reason?: string
  }
}

interface JournalLine {
  id: number
  line_number: number
  description: string
  debit_amount: number
  credit_amount: number
  ledger_account: {
    id: string
    code: string
    name: string
    type: string
  }
}

interface Account {
  id: string
  code: string
  name: string
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'
  normal_balance: 'debit' | 'credit'
  active: boolean
  system_account: boolean
  description?: string
}
```

## Best Practices

1. **Permissions**: Always pass permission props to control UI visibility
2. **Loading States**: Use the built-in loading states of forms and tables
3. **Error Handling**: Components emit events for parent to handle actions
4. **Accessibility**: All interactive elements have proper ARIA labels
5. **Performance**: Components use computed properties for efficient updates
6. **Consistency**: Follow the established patterns when extending components

## Future Enhancements

Potential improvements to consider:
- Export functionality for table data
- Advanced filtering with date ranges
- Bulk editing capabilities
- Line item templates for common entries
- Account search with hierarchical display
- Import/export for journal entries
- Audit trail viewing
- Multi-currency support