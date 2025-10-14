# PageHeader

## Description
A standardized page header component with title, subtitle, and action slots. Provides consistent page heading structure across the application.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| title | string | Yes | - | The main title of the page |
| subtitle | string | No | - | Optional subtitle/description |
| maxActions | number | No | 3 | Maximum number of actions to show before dropdown |

## Slots
| Slot Name | Description | Props |
|-----------|-------------|-------|
| default | Content below title/subtitle | - |
| actions-left | Actions aligned to the left | - |
| actions-right | Actions aligned to the right | - |

## Usage Examples

### Basic Usage
```vue
<template>
  <PageHeader title="Customers" subtitle="Manage your customer database">
    <template #actions-right>
      <Button label="Add Customer" icon="pi pi-plus" @click="createCustomer" />
    </template>
  </PageHeader>
</template>
```

### With Left and Right Actions
```vue
<template>
  <PageHeader 
    title="Invoices" 
    subtitle="View and manage all invoices"
    :max-actions="4"
  >
    <template #actions-left>
      <Button label="Export" icon="pi pi-download" class="p-button-outlined" />
    </template>
    <template #actions-right>
      <Button label="New Invoice" icon="pi pi-plus" @click="createInvoice" />
      <Button label="Settings" icon="pi pi-cog" class="p-button-text" />
    </template>
  </PageHeader>
</template>
```

## Features
- Responsive design that stacks actions on mobile
- Automatic overflow handling for actions (moves to dropdown when exceeding maxActions)
- Consistent spacing and typography
- Breadcrumb integration point

## Accessibility
- Uses semantic heading structure (h1)
- Proper ARIA labeling for action buttons
- Keyboard navigation support

## Styling
- Uses CSS variables for theming
- Responsive breakpoints at 768px and 1024px
- Consistent with application design system

## Dependencies
- PrimeVue Button component
- Custom PageActions component for overflow handling

## Testing
Test scenarios to cover:
- Title and subtitle display
- Action button rendering
- Overflow dropdown behavior
- Responsive layout changes
- Accessibility attributes

## Notes
- Always provide a clear, concise title
- Use subtitles for additional context when needed
- Consider the most important actions for primary placement
- The component handles action overflow automatically