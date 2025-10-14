# VendorPicker

## Description
A specialized picker component for selecting vendors. Built on top of EntityPicker with vendor-specific defaults and configurations. Provides an intuitive interface for browsing and selecting vendors with their contact information, category details, and payment metrics.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | null | Selected vendor ID |
| vendors | Vendor[] | Yes | - | Array of vendors to display |
| optionLabel | string | No | 'name' | Field to use as display label |
| optionValue | string | No | 'id' | Field to use as value |
| optionDisabled | (vendor: Vendor) => boolean | No | - | Function to determine if option is disabled |
| placeholder | string | No | 'Select a vendor...' | Placeholder text when no selection |
| filterPlaceholder | string | No | 'Search vendors...' | Filter input placeholder |
| filterFields | string[] | No | ['name', 'email', 'phone', 'category'] | Fields to search when filtering |
| showClear | boolean | No | true | Show clear button |
| disabled | boolean | No | false | Disable the picker |
| loading | boolean | No | false | Show loading state |
| error | string | No | - | Error message to display |
| showBalance | boolean | No | true | Show vendor balance display |
| showStats | boolean | No | false | Show vendor statistics |
| allowCreate | boolean | No | true | Show create vendor button |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| update:modelValue | (value: number \| string \| null) => void | Emitted when selection changes |
| change | (vendor: Vendor \| null) => void | Emitted when selection changes with full vendor object |
| filter | (event: Event) => void | Emitted when filter is applied |
| show | () => void | Emitted when dropdown is shown |
| hide | () => void | Emitted when dropdown is hidden |
| create-vendor | () => void | Emitted when create vendor button is clicked |
| view-vendor | (vendor: Vendor) => void | Emitted when view vendor action is clicked |

## Usage Examples

### Basic Usage
```vue
<template>
  <VendorPicker
    v-model="form.vendor_id"
    :vendors="vendors"
    @change="onVendorSelected"
  />
</template>

<script setup>
import { ref } from 'vue'
import VendorPicker from '@/Components/UI/Forms/VendorPicker.vue'

const form = ref({
  vendor_id: null
})

const vendors = ref([
  {
    id: 1,
    name: 'Office Supplies Co',
    email: 'orders@officesupplies.com',
    phone: '+1-555-0123',
    category: 'Office Supplies',
    website: 'https://officesupplies.com',
    status: 'active',
    bill_count: 24,
    total_paid: 15000,
    currency: 'USD'
  }
])

const onVendorSelected = (vendor) => {
  console.log('Selected vendor:', vendor)
}
</script>
```

### In a Bill Creation Context
```vue
<template>
  <div class="space-y-4">
    <label class="block text-sm font-medium text-gray-700">
      Vendor
    </label>
    
    <VendorPicker
      v-model="bill.vendor_id"
      :vendors="vendors"
      :error="bill.errors.vendor_id"
      :disabled="isProcessing"
      placeholder="Select a vendor..."
      @change="onVendorChange"
    />
    
    <div v-if="selectedVendor" class="p-4 bg-purple-50 rounded-lg border border-purple-200">
      <div class="flex justify-between items-start">
        <div>
          <h3 class="font-medium text-purple-900">{{ selectedVendor.name }}</h3>
          <p class="text-sm text-purple-700">{{ selectedVendor.category }}</p>
          <p class="text-xs text-purple-600 mt-1">{{ selectedVendor.email }} " {{ selectedVendor.phone }}</p>
        </div>
        <div class="text-right">
          <p class="text-sm font-medium text-purple-900">{{ selectedVendor.bill_count }} bills</p>
          <p class="text-xs text-purple-600">Total paid: {{ formatCurrency(selectedVendor.total_paid) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  bill: Object,
  vendors: Array,
  isProcessing: Boolean
})

const emit = defineEmits(['vendor-change'])

const selectedVendor = computed(() => {
  return props.vendors.find(v => v.id === props.bill.vendor_id)
})

const onVendorChange = (vendor) => {
  emit('vendor-change', vendor)
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}
</script>
```

### With Payment Statistics
```vue
<template>
  <VendorPicker
    v-model="selectedVendorId"
    :vendors="vendors"
    :showStats="true"
    :showBalance="true"
    @view-vendor="viewVendorDetails"
  />
</template>

<script setup>
import { ref } from 'vue'

const viewVendorDetails = (vendor) => {
  router.visit(`/vendors/${vendor.id}`)
}
</script>
```

### In a Expense Report Context
```vue
<template>
  <div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-lg font-semibold mb-4">New Expense Report</h2>
    
    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Vendor
        </label>
        <VendorPicker
          v-model="expense.vendor_id"
          :vendors="vendors"
          :filterFields="['name', 'category']"
          placeholder="Select or search for a vendor..."
          @change="loadVendorDetails"
        />
      </div>
      
      <div v-if="selectedVendor" class="border-t pt-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-500">Category:</span>
            <span class="ml-2 font-medium">{{ selectedVendor.category }}</span>
          </div>
          <div>
            <span class="text-gray-500">Payment Terms:</span>
            <span class="ml-2 font-medium">{{ selectedVendor.payment_terms || 'Net 30' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  expense: Object,
  vendors: Array
})

const selectedVendor = computed(() => {
  return props.vendors.find(v => v.id === props.expense.vendor_id)
})

const loadVendorDetails = (vendor) => {
  // Load additional vendor details if needed
  console.log('Loading details for:', vendor.name)
}
</script>
```

## Features
- **Vendor-specific defaults**: Pre-configured for vendor entities with category and payment information
- **Category and website display**: Shows category and website in the extra info section
- **Payment metrics**: Displays bill count and total paid amount in statistics
- **Contact information**: Shows email and phone in subtitle
- **Status indicators**: Visual status badges for vendor state
- **Avatar generation**: Creates colored initials avatars when no image is available
- **Quick actions**: Direct link to view vendor details
- **Search functionality**: Filter by name, email, phone, or category
- **Create new**: Built-in option to add new vendors

## Vendor Interface
```typescript
interface Vendor {
  id?: number
  vendor_id?: number
  name: string
  email?: string
  phone?: string
  status?: string
  category?: string
  website?: string
  avatar?: string
  bill_count?: number
  total_paid?: number
  currency?: string
  [key: string]: any
}
```

## Default Configuration
- Entity Type: 'vendor'
- Option Label: 'name'
- Option Value: 'id'
- Filter Fields: ['name', 'email', 'phone', 'category']
- Default Icon: 'pi pi-truck'
- Header Title: 'Select Vendor'
- Create Button: 'New Vendor'

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
- This component is a wrapper around EntityPicker with vendor-specific configuration
- Uses id as the default value field but can be configured to use vendor_id
- The component automatically handles avatar color generation based on vendor name
- Statistics section shows bill count and total paid amount when enabled
- Category and website information appears in the extra info section by default for vendors
- Particularly useful in accounting and expense management contexts