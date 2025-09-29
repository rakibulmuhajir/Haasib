# LineItemEditor

## Description
A comprehensive line item editor component for invoices and quotes. Provides a full-featured interface for adding, editing, and removing line items with real-time calculation of totals, support for discounts (fixed or percentage), and tax rates.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | LineItem[] | Yes | - | Array of line items to edit |
| currency | string | No | 'USD' | Currency code for formatting |
| taxRates | TaxRate[] | No | [] | Available tax rates for selection |
| locale | string | No | 'en-US' | Locale for number formatting |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| update:modelValue | (items: LineItem[]) => void | Emitted when line items change |
| item-added | (item: LineItem) => void | Emitted when a new line item is added |
| item-removed | (item: LineItem, index: number) => void | Emitted when a line item is removed |
| item-updated | (item: LineItem, index: number) => void | Emitted when a line item is updated |

## Usage Examples

### Basic Usage
```vue
<template>
  <div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-lg font-semibold mb-4">Invoice Items</h2>
    <LineItemEditor
      v-model="form.items"
      :tax-rates="taxRates"
      currency="USD"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import LineItemEditor from '@/Components/Invoicing/LineItemEditor.vue'

const form = ref({
  items: [
    {
      id: 1,
      name: 'Web Development Services',
      description: '40 hours of development work',
      quantity: 40,
      unit_price: 75,
      discount: 0,
      tax_rate_id: 1
    }
  ]
})

const taxRates = ref([
  { id: 1, name: 'Standard', rate: 10 },
  { id: 2, name: 'Reduced', rate: 5 },
  { id: 3, name: 'Zero Rated', rate: 0 }
])
</script>
```

### In an Invoice Creation Context
```vue
<template>
  <form @submit.prevent="submit">
    <!-- Customer and other invoice details -->
    
    <!-- Line Items Section -->
    <div class="mt-8">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-medium text-gray-900">Invoice Items</h3>
        <div class="text-sm text-gray-500">
          Total: {{ formatCurrency(grandTotal, form.currency) }}
        </div>
      </div>
      
      <LineItemEditor
        v-model="form.items"
        :tax-rates="props.taxRates"
        :currency="selectedCurrency?.code || 'USD'"
        @item-added="onItemAdded"
        @item-removed="onItemRemoved"
      />
    </div>
    
    <!-- Submit button -->
  </form>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  taxRates: Array,
  currencies: Array
})

const form = useForm({
  customer_id: null,
  currency_id: null,
  items: [
    {
      id: Date.now(),
      name: '',
      description: '',
      quantity: 1,
      unit_price: 0,
      discount: 0,
      discount_type: 'percentage',
      tax_rate_id: null
    }
  ]
})

const selectedCurrency = computed(() => {
  return props.currencies.find(c => c.id === form.currency_id)
})

const grandTotal = computed(() => {
  // The LineItemEditor calculates totals, but you might need them in the parent
  return form.items.reduce((total, item) => {
    const lineTotal = item.quantity * item.unit_price
    const discount = item.discount_type === 'percentage' 
      ? lineTotal * item.discount / 100 
      : Math.min(item.discount, lineTotal)
    const taxRate = props.taxRates.find(t => t.id === item.tax_rate_id)
    const tax = taxRate ? (lineTotal - discount) * taxRate.rate / 100 : 0
    return total + lineTotal - discount + tax
  }, 0)
})

const onItemAdded = (item) => {
  // Track item additions for analytics or validation
  console.log('Item added:', item)
}

const onItemRemoved = (item, index) => {
  // Track item removals
  console.log('Item removed:', item, 'at index:', index)
}
</script>
```

### With Custom Validation
```vue
<template>
  <LineItemEditor
    v-model="items"
    :tax-rates="taxRates"
    :currency="currency"
    @update:model-value="validateItems"
  />
  
  <div v-if="validationError" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
    <p class="text-sm text-red-600">{{ validationError }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const items = ref([])
const validationError = ref('')

const validateItems = (newItems) => {
  // Reset error
  validationError.value = ''
  
  // Validate each item
  for (const item of newItems) {
    if (!item.name.trim()) {
      validationError.value = 'All items must have a name'
      return
    }
    
    if (item.quantity <= 0) {
      validationError.value = 'Quantity must be greater than 0'
      return
    }
    
    if (item.unit_price < 0) {
      validationError.value = 'Unit price cannot be negative'
      return
    }
    
    if (item.discount < 0) {
      validationError.value = 'Discount cannot be negative'
      return
    }
  }
  
  // Additional business logic validation
  if (newItems.length === 0) {
    validationError.value = 'At least one item is required'
  }
}
</script>
```

## Features
- **Full CRUD Operations**: Add, edit, and remove line items
- **Real-time Calculations**: Automatic calculation of line totals, subtotal, discounts, tax, and grand total
- **Flexible Discounts**: Support for both fixed amount and percentage-based discounts
- **Tax Rate Support**: Integration with configurable tax rates
- **Responsive Design**: Works well on all screen sizes with proper scrolling
- **Keyboard Friendly**: Tab navigation between fields
- **Empty State**: Helpful empty state with call-to-action
- **Currency Formatting**: Automatic formatting based on locale and currency

## LineItem Interface
```typescript
interface LineItem {
  id?: number | string  // Temporary ID for new items
  name: string         // Item name or service description
  description?: string // Detailed description
  quantity: number     // Number of units
  unit_price: number   // Price per unit
  discount: number     // Discount amount or percentage
  discount_type?: 'fixed' | 'percentage'  // How discount is applied
  tax_rate_id?: number | null  // Selected tax rate
  tax_amount?: number  // Calculated tax amount
}
```

## TaxRate Interface
```typescript
interface TaxRate {
  id: number
  name: string    // e.g., "Standard", "Reduced", "Zero Rated"
  rate: number   // Percentage rate (e.g., 10 for 10%)
}
```

## Computed Properties
The component provides several computed properties for display:

- **subtotal**: Sum of all line items (quantity × unit price)
- **totalDiscount**: Sum of all discounts across line items
- **totalTax**: Sum of all taxes across line items
- **total**: Final total (subtotal - discounts + taxes)

## Styling
The component uses Tailwind CSS classes for styling:
- Responsive table layout
- Hover states on rows
- Clear visual hierarchy with proper spacing
- Action buttons with appropriate sizing
- Color-coded totals (green for discounts)

## Dependencies
- PrimeVue InputText, Textarea, InputNumber, Dropdown, Button
- Vue 3 Composition API
- Tailwind CSS for styling

## Accessibility
- Semantic table structure
- Proper labels for all inputs
- Keyboard navigation support
- Clear visual feedback for interactive elements

## Performance Considerations
- Efficient re-rendering with Vue's reactivity system
- Debounced calculations to prevent excessive updates
- Lazy rendering of tax rate dropdown options

## Notes
- The component manages its own state internally while emitting updates to the parent
- All monetary values are stored as numbers (not formatted strings)
- Discount type can be toggled between percentage and fixed amount
- Tax calculations are performed based on the discounted subtotal
- The component is designed to work with both new invoices and editing existing ones