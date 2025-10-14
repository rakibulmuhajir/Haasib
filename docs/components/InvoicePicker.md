# InvoicePicker

## Description
A specialized picker component for selecting invoices. Built on top of EntityPicker with invoice-specific defaults and configurations. Provides an intuitive interface for browsing and selecting invoices with their customer information, dates, amounts, and payment status.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | null | Selected invoice ID |
| invoices | Invoice[] | Yes | - | Array of invoices to display |
| optionLabel | string | No | 'invoice_number' | Field to use as display label |
| optionValue | string | No | 'invoice_id' | Field to use as value |
| optionDisabled | (invoice: Invoice) => boolean | No | - | Function to determine if option is disabled |
| placeholder | string | No | 'Select an invoice...' | Placeholder text when no selection |
| filterPlaceholder | string | No | 'Search invoices...' | Filter input placeholder |
| filterFields | string[] | No | ['invoice_number', 'customer_name', 'notes'] | Fields to search when filtering |
| showClear | boolean | No | true | Show clear button |
| disabled | boolean | No | false | Disable the picker |
| loading | boolean | No | false | Show loading state |
| error | string | No | - | Error message to display |
| showBalance | boolean | No | true | Show invoice balance display |
| showStats | boolean | No | true | Show invoice statistics |
| allowCreate | boolean | No | true | Show create invoice button |
| customerFilter | number \| null | No | null | Filter invoices by customer ID |
| statusFilter | string \| null | No | null | Filter invoices by status |
| currencyFilter | number \| null | No | null | Filter invoices by currency ID |
| dateRangeFilter | { start: string; end: string } \| null | No | null | Filter invoices by date range |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| update:modelValue | (value: number \| string \| null) => void | Emitted when selection changes |
| change | (invoice: Invoice \| null) => void | Emitted when selection changes with full invoice object |
| filter | (event: Event) => void | Emitted when filter is applied |
| show | () => void | Emitted when dropdown is shown |
| hide | () => void | Emitted when dropdown is hidden |
| create-invoice | () => void | Emitted when create invoice button is clicked |
| view-invoice | (invoice: Invoice) => void | Emitted when view invoice action is clicked |

## Usage Examples

### Basic Usage
```vue
<template>
  <InvoicePicker
    v-model="form.invoice_id"
    :invoices="invoices"
    @change="onInvoiceSelected"
  />
</template>

<script setup>
import { ref } from 'vue'
import InvoicePicker from '@/Components/UI/Forms/InvoicePicker.vue'

const form = ref({
  invoice_id: null
})

const invoices = ref([
  {
    id: 1,
    invoice_id: 1001,
    invoice_number: 'INV-2024-001',
    customer_name: 'Acme Corp',
    invoice_date: '2024-01-15',
    due_date: '2024-02-15',
    total_amount: 1500,
    balance_due: 1500,
    status: 'pending',
    currency: { code: 'USD', symbol: '$' }
  }
])

const onInvoiceSelected = (invoice) => {
  console.log('Selected invoice:', invoice)
}
</script>
```

### In a Payment Application Context
```vue
<template>
  <div class="space-y-4">
    <label class="block text-sm font-medium text-gray-700">
      Apply Payment to Invoice
    </label>
    
    <InvoicePicker
      v-model="payment.invoice_id"
      :invoices="outstandingInvoices"
      :option-disabled="invoice => invoice.balance_due <= 0"
      :customer-filter="selectedCustomerId"
      :error="payment.errors.invoice_id"
      placeholder="Select an outstanding invoice..."
      @change="onInvoiceChange"
    />
    
    <div v-if="selectedInvoice" class="p-4 bg-blue-50 rounded-lg border border-blue-200">
      <div class="flex justify-between items-start">
        <div>
          <h3 class="font-medium text-blue-900">{{ selectedInvoice.invoice_number }}</h3>
          <p class="text-sm text-blue-700">
            {{ selectedInvoice.customer_name }} • Due: {{ formatDate(selectedInvoice.due_date) }}
          </p>
        </div>
        <div class="text-right">
          <p class="text-sm font-medium text-blue-900">Amount Due</p>
          <p class="text-lg font-bold text-blue-900">
            {{ formatCurrency(selectedInvoice.balance_due, selectedInvoice.currency.code) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  payment: Object,
  invoices: Array,
  selectedCustomerId: Number
})

const emit = defineEmits(['invoice-change'])

const outstandingInvoices = computed(() => {
  return props.invoices.filter(inv => inv.balance_due > 0)
})

const selectedInvoice = computed(() => {
  return outstandingInvoices.value.find(inv => inv.invoice_id === props.payment.invoice_id)
})

const onInvoiceChange = (invoice) => {
  emit('invoice-change', invoice)
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString()
}

const formatCurrency = (amount, currency) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD'
  }).format(amount)
}
</script>
```

### With Advanced Filtering
```vue
<template>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
      <select v-model="statusFilter" class="w-full rounded-md border-gray-300">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="overdue">Overdue</option>
        <option value="paid">Paid</option>
      </select>
    </div>
    
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
      <input 
        v-model="dateRange.start" 
        type="date" 
        class="w-full rounded-md border-gray-300"
      />
    </div>
    
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
      <input 
        v-model="dateRange.end" 
        type="date" 
        class="w-full rounded-md border-gray-300"
      />
    </div>
  </div>
  
  <InvoicePicker
    v-model="selectedInvoiceId"
    :invoices="invoices"
    :status-filter="statusFilter"
    :date-range-filter="dateRange"
    :show-stats="true"
    @view-invoice="viewInvoiceDetails"
  />
</template>

<script setup>
import { ref } from 'vue'

const statusFilter = ref('')
const dateRange = ref({
  start: '',
  end: ''
})

const viewInvoiceDetails = (invoice) => {
  router.visit(`/invoices/${invoice.invoice_id}`)
}
</script>
```

## Features
- **Invoice-specific defaults**: Pre-configured for invoice entities with date and amount display
- **Balance and status indicators**: Visual indicators for payment status and overdue invoices
- **Customer information**: Shows customer name alongside invoice details
- **Advanced filtering**: Support for customer, status, currency, and date range filters
- **Date display**: Shows both invoice date and due date in the extra info section
- **Currency formatting**: Properly formats amounts with their respective currencies
- **Quick actions**: Direct link to view invoice details
- **Real-time search**: Filter by invoice number, customer name, or notes

## Invoice Interface
```typescript
interface Invoice {
  id?: number
  invoice_id?: number
  customer_id?: number
  invoice_number: string
  invoice_date: string
  due_date?: string
  total_amount: number
  balance_due: number
  status?: string
  currency?: { id: number; code: string; symbol: string }
  currency_id?: number
  notes?: string
  customer_name?: string
  overdue?: boolean
  [key: string]: any
}
```

## Default Configuration
- Entity Type: 'invoice'
- Option Label: 'invoice_number'
- Option Value: 'invoice_id'
- Filter Fields: ['invoice_number', 'customer_name', 'notes']
- Default Icon: 'pi pi-file-invoice'
- Header Title: 'Select Invoice'
- Create Button: 'New Invoice'

## Dependencies
- EntityPicker component
- PrimeVue components (indirectly through EntityPicker)
- StatusBadge component
- BalanceDisplay component

## Methods
The component exposes the following methods through template refs:
- `show()` - Open the dropdown
- `hide()` - Close the dropdown
- `focus()` - Focus the input element

## Notes
- This component is a wrapper around EntityPicker with invoice-specific configuration
- Uses invoice_id as the default value field but can be configured to use id
- The component automatically handles status color coding for balance amounts
- Date filtering uses the invoice_date field for range comparisons
- Particularly useful in payment application, credit memo creation, and reporting contexts