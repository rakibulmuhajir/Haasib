# Component Documentation Template

## Template Structure

Each component should be documented with the following sections:

```markdown
# [Component Name]

## Description
Brief description of what the component does and its purpose.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| propName | Type | Yes/No | defaultValue | Description of the prop |

## Slots
| Slot Name | Description | Props |
|-----------|-------------|-------|
| default | Default slot content | - |
| slotName | Description of slot | { slotProp: Type } |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| eventName | (payload: Type) => void | Description of when event is emitted |

## Usage Examples

### Basic Usage
```vue
<template>
  <ComponentName :prop="value" @event="handler">
    Content
  </ComponentName>
</template>

<script setup>
import { ref } from 'vue'

const value = ref('example')
const handler = (payload) => {
  console.log(payload)
}
</script>
```

### Advanced Usage
```vue
<!-- Example with all features -->
```

## Features
- Feature 1
- Feature 2
- Feature 3

## Accessibility
- ARIA attributes used
- Keyboard navigation support
- Screen reader considerations

## Styling
- CSS classes available
- CSS variables for customization
- Theme considerations

## Dependencies
- PrimeVue components used
- Other custom components
- External libraries

## Testing
- What scenarios are covered
- How to test the component
- Mock data examples

## Notes
Any additional information, limitations, or gotchas.
```

---

## Component Documentation Index

This file serves as an index for all component documentation.

## Layout Components
- [LayoutShell](./components/LayoutShell.md)
- [PageHeader](./components/PageHeader.md)
- [Breadcrumb](./components/Breadcrumb.md)

## Data Display Components
- [DataTablePro](./components/DataTablePro.md)
- [StatusBadge](./components/StatusBadge.md)
- [BalanceDisplay](./components/BalanceDisplay.md)
- [CustomerInfoDisplay](./components/CustomerInfoDisplay.md)
- [CountryDisplay](./components/CountryDisplay.md)

## Ledger Components
- [LedgerEntriesTable](./components/Ledger-Components.md#ledgerentriestable) - Journal entries table with filtering and bulk actions
- [JournalEntryForm](./components/Ledger-Components.md#journalentryform) - Double-entry journal form with balance validation
- [JournalEntrySummary](./components/Ledger-Components.md#journalentrysummary) - Entry details and totals display
- [LinesTable](./components/Ledger-Components.md#linestable) - Journal lines display with account information
- [LedgerAccountsFilters](./components/Ledger-Components.md#ledgeraccountsfilters) - Account filtering with search and type selection

## Form Components
- [EntityPicker](./components/EntityPicker.md) - Generic entity picker for customers, companies, users, vendors
- [CustomerPicker](./components/CustomerPicker.md) - Customer selection with balance and stats
- [CompanyPicker](./components/CompanyPicker.md) - Company selection with industry and revenue metrics
- [UserPicker](./components/UserPicker.md) - User selection with role and department
- [VendorPicker](./components/VendorPicker.md) - Vendor selection with category and payment history
- [InvoicePicker](./components/InvoicePicker.md) - Invoice selection with balance and due dates
- [InputText](./components/InputText.md)
- [Dropdown](./components/Dropdown.md)
- [Calendar](./components/Calendar.md)
- [InputNumber](./components/InputNumber.md)
- [Textarea](./components/Textarea.md)
- [RadioButton](./components/RadioButton.md)

## Action Components
- [Button](./components/Button.md)
- [PageActions](./components/PageActions.md)

## Dialog Components
- [Dialog](./components/Dialog.md)

## Navigation Components
- [Link](./components/Link.md)

## Layout Components
- [Card](./components/Card.md)
- [Fieldset](./components/Fieldset.md)
- [Divider](./components/Divider.md)

## Utility Components
- [InputGroup](./components/InputGroup.md)
- [InputGroupAddon](./components/InputGroupAddon.md)
- [OverlayBadge](./components/OverlayBadge.md)

### Documented Components ✅
- PageHeader - Comprehensive documentation with examples
- DataTablePro - Advanced table with filtering/sorting features  
- StatusBadge - Status indicator with color coding
- BalanceDisplay - Monetary value display with risk indicators
- CustomerInfoDisplay - Customer information with multiple variants
- EntityPicker - Generic entity picker with extensive customization
- CustomerPicker - Customer selection with balance and statistics
- CompanyPicker - Company selection with industry and metrics
- UserPicker - User selection with role and department
- VendorPicker - Vendor selection with category and payment history
- InvoicePicker - Invoice selection with balance and due dates
- LineItemEditor - Full-featured line item editor with real-time calculations
- LedgerEntriesTable - Journal entries table with bulk operations and filtering
- JournalEntryForm - Double-entry journal form with balance validation
- JournalEntrySummary - Entry details display with status-specific actions
- LinesTable - Journal lines display with account information
- LedgerAccountsFilters - Account filtering with search and type selection

### Pending Documentation
- LayoutShell, Breadcrumb, CountryDisplay
- All PrimeVue base components
- Remaining form and utility components

## Form Components
- [InputText](./components/InputText.md)
- [Dropdown](./components/Dropdown.md)
- [Calendar](./components/Calendar.md)
- [InputNumber](./components/InputNumber.md)
- [Textarea](./components/Textarea.md)
- [RadioButton](./components/RadioButton.md)

## Action Components
- [Button](./components/Button.md)
- [PageActions](./components/PageActions.md)

## Dialog Components
- [Dialog](./components/Dialog.md)

## Navigation Components
- [Link](./components/Link.md)

## Layout Components
- [Card](./components/Card.md)
- [Fieldset](./components/Fieldset.md)
- [Divider](./components/Divider.md)

## Utility Components
- [InputGroup](./components/InputGroup.md)
- [InputGroupAddon](./components/InputGroupAddon.md)
- [OverlayBadge](./components/OverlayBadge.md)