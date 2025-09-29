# CustomerPicker

## Description
A specialized picker component for selecting customers. Built on top of EntityPicker with customer-specific defaults and configurations. Provides an intuitive interface for browsing and selecting customers with their contact information, status, and outstanding balances.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | - | Selected customer ID |
| customers | Array<Customer> | Yes | - | Array of customer objects |
| optionLabel | string | No | 'name' | Field to use as display label |
| optionValue | string | No | 'customer_id' | Field to use as value |
| optionDisabled | (customer: Customer) => boolean | No | - | Function to disable options |
| placeholder | string | No | 'Select a customer...' | Placeholder text |
| filterPlaceholder | string | No | 'Search customers...' | Filter input placeholder |
| filterFields | string[] | No | ['name', 'email', 'phone'] | Fields to search in |
| showClear | boolean | No | true | Show clear button |
| disabled | boolean | No | false | Disable the component |
| loading | boolean | No | false | Loading state |
| error | string | No | - | Error message to display |
| showStatus | boolean | No | true | Show customer status badge |
| showBalance | boolean | No | true | Show customer balance |
| showExtraInfo | boolean | No | false | Show customer ID and type |
| showActions | boolean | No | true | Show action buttons |
| showStats | boolean | No | false | Show customer statistics |
| allowCreate | boolean | No | true | Allow creating new customers |
| defaultCurrency | string | No | 'USD' | Default currency code |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| @update:modelValue | (value: number \| string \| null) => void | When selection changes |
| @change | (customer: Customer \| null) => void | When customer is selected |
| @filter | (event: Event) => void | When filter is applied |
| @show | () => void | When dropdown is shown |
| @hide | () => void | When dropdown is hidden |
| @create-customer | () => void | When create customer is clicked |
| @view-customer | (customer: Customer) => void | When view customer is clicked |

## Customer Interface
```typescript
interface Customer {
  customer_id?: number
  id?: number
  name: string
  email?: string
  phone?: string
  status?: string
  type?: string
  avatar?: string
  balance?: number
  currency?: string
  outstanding_balance?: number
  invoice_count?: number
  [key: string]: any
}
```

## Usage Examples

### Basic Usage
```vue
<template>
  <CustomerPicker
    v-model="form.customer_id"
    :customers="customers"
    @change="onCustomerChange"
  />
</template>

<script setup>
import { ref } from 'vue'

const form = ref({
  customer_id: null
})

const customers = ref([
  { customer_id: 1, name: 'Acme Corp', email: 'contact@acme.com' },
  { customer_id: 2, name: 'Tech Solutions', email: 'info@tech.com' }
])

const onCustomerChange = (customer) => {
  console.log('Selected customer:', customer)
}
</script>
```

### With All Features
```vue
<template>
  <CustomerPicker
    v-model="selectedCustomerId"
    :customers="customers"
    :loading="loading"
    :error="errors.customer_id"
    showBalance
    showStats
    showExtraInfo
    @create-customer="openCustomerModal"
    @view-customer="viewCustomerDetails"
  />
</template>
```

### In a Form with Validation
```vue
<template>
  <div class="space-y-4">
    <CustomerPicker
      v-model="form.customer_id"
      :customers="customers"
      :error="form.errors.customer_id"
      :disabled="loading"
      @change="loadCustomerInvoices"
    />
    
    <!-- Customer details show when selected -->
    <div v-if="selectedCustomer" class="p-4 bg-gray-50 rounded-lg">
      <CustomerInfoDisplay :customer="selectedCustomer" variant="detailed" />
    </div>
  </div>
</template>
```

## Features
- **Rich Customer Display**: Shows avatar, name, email, phone, and status
- **Advanced Search**: Multi-field filtering with customizable fields
- **Balance Display**: Shows customer balance with proper formatting
- **Status Indicators**: Visual status badges for customer state
- **Inline Actions**: Quick access to view customer details
- **Create Customer**: Option to create new customer from picker
- **Statistics**: Optional display of customer metrics
- **Avatar Generation**: Automatic avatar from initials with consistent colors
- **Error Handling**: Clear error message display
- **Loading States**: Visual feedback during operations
- **Accessibility**: Full keyboard navigation and ARIA support

## Avatar Generation
- Uses customer initials when no avatar image is provided
- Consistent color generation based on customer name
- Fallback to gray for names that can't be processed

## Search/Filter Features
- Searches across multiple fields (name, email, phone by default)
- Real-time filtering as user types
- Customizable filter fields
- Empty states with call-to-action

## Accessibility
- Semantic HTML structure
- ARIA labels and descriptions
- Keyboard navigation support
- Screen reader compatibility
- High contrast support

## Styling
- Uses CSS custom properties for theming
- Responsive design considerations
- Consistent spacing and typography
- Hover states and transitions
- Loading and disabled states

## Dependencies
- EntityPicker component
- PrimeVue components (indirectly through EntityPicker)
- StatusBadge component
- BalanceDisplay component

## Performance Considerations
- Efficient filtering algorithm
- Minimal re-renders
- Lazy loading ready for large datasets
- Optimized avatar generation

## Testing
Test scenarios to cover:
- Customer selection and clearing
- Search/filter functionality
- Error state display
- Loading state behavior
- Keyboard navigation
- Screen reader compatibility
- Action button functionality
- Custom field configurations
- Disabled state behavior

## Methods Exposed
- `show()` - Programmatically open the dropdown
- `hide()` - Programmatically close the dropdown
- `focus()` - Focus the input element

## Notes
- This component is a wrapper around EntityPicker with customer-specific configuration
- Maintains backward compatibility with existing implementations
- Uses customer_id as the default value field but can be configured to use id
- The component automatically handles avatar color generation based on customer name
- Statistics section shows invoice count and outstanding balance when enabled

## Future Enhancements
- Virtual scrolling for large datasets
- Recently used customers section
- Customer grouping/folders
- Advanced search filters (status, type, etc.)
- Bulk customer operations
- Customer merge/split functionality