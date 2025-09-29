# CompanyPicker

## Description
A specialized picker component for selecting companies. Built on top of EntityPicker with company-specific defaults and configurations. Provides an intuitive interface for browsing and selecting companies with their contact information, industry details, and financial metrics.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | null | Selected company ID |
| companies | Company[] | Yes | - | Array of companies to display |
| optionLabel | string | No | 'name' | Field to use as display label |
| optionValue | string | No | 'id' | Field to use as value |
| optionDisabled | (company: Company) => boolean | No | - | Function to determine if option is disabled |
| placeholder | string | No | 'Select a company...' | Placeholder text when no selection |
| filterPlaceholder | string | No | 'Search companies...' | Filter input placeholder |
| filterFields | string[] | No | ['name', 'email', 'phone', 'industry'] | Fields to search when filtering |
| showClear | boolean | No | true | Show clear button |
| disabled | boolean | No | false | Disable the picker |
| loading | boolean | No | false | Show loading state |
| error | string | No | - | Error message to display |
| showBalance | boolean | No | true | Show company balance display |
| showStats | boolean | No | false | Show company statistics |
| allowCreate | boolean | No | true | Show create company button |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| update:modelValue | (value: number \| string \| null) => void | Emitted when selection changes |
| change | (company: Company \| null) => void | Emitted when selection changes with full company object |
| filter | (event: Event) => void | Emitted when filter is applied |
| show | () => void | Emitted when dropdown is shown |
| hide | () => void | Emitted when dropdown is hidden |
| create-company | () => void | Emitted when create company button is clicked |
| view-company | (company: Company) => void | Emitted when view company action is clicked |

## Usage Examples

### Basic Usage
```vue
<template>
  <CompanyPicker
    v-model="form.company_id"
    :companies="companies"
    @change="onCompanySelected"
  />
</template>

<script setup>
import { ref } from 'vue'
import CompanyPicker from '@/Components/UI/Forms/CompanyPicker.vue'

const form = ref({
  company_id: null
})

const companies = ref([
  {
    id: 1,
    name: 'Tech Solutions Inc',
    email: 'contact@techsolutions.com',
    phone: '+1-555-0123',
    industry: 'Technology',
    website: 'https://techsolutions.com',
    employee_count: 150,
    annual_revenue: 5000000,
    status: 'active'
  }
])

const onCompanySelected = (company) => {
  console.log('Selected company:', company)
}
</script>
```

### With Statistics and Enhanced Display
```vue
<template>
  <CompanyPicker
    v-model="selectedCompanyId"
    :companies="companies"
    :showStats="true"
    :showBalance="true"
    balanceField="account_balance"
    @view-company="navigateToCompany"
  />
</template>

<script setup>
import { ref } from 'vue'

const navigateToCompany = (company) => {
  router.visit(`/companies/${company.id}`)
}
</script>
```

### In a Project Management Context
```vue
<template>
  <div class="space-y-4">
    <label class="block text-sm font-medium text-gray-700">
      Client Company
    </label>
    
    <CompanyPicker
      v-model="project.company_id"
      :companies="companies"
      :error="project.errors.company_id"
      :disabled="isProjectLocked"
      placeholder="Select a client company..."
      @change="onCompanyChange"
    />
    
    <div v-if="selectedCompany" class="p-4 bg-blue-50 rounded-lg">
      <h3 class="font-medium text-blue-900">{{ selectedCompany.name }}</h3>
      <p class="text-sm text-blue-700">{{ selectedCompany.industry }} " {{ selectedCompany.employee_count }} employees</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  project: Object,
  companies: Array,
  isProjectLocked: Boolean
})

const emit = defineEmits(['company-change'])

const selectedCompany = computed(() => {
  return props.companies.find(c => c.id === props.project.company_id)
})

const onCompanyChange = (company) => {
  emit('company-change', company)
}
</script>
```

## Features
- **Company-specific defaults**: Pre-configured for company entities with appropriate display fields
- **Industry and website display**: Shows industry and website in the extra info section
- **Financial metrics**: Displays employee count and annual revenue in statistics
- **Contact information**: Shows email and phone in subtitle
- **Status indicators**: Visual status badges for company state
- **Avatar generation**: Creates colored initials avatars when no image is available
- **Quick actions**: Direct link to view company details
- **Search functionality**: Filter by name, email, phone, or industry
- **Create new**: Built-in option to add new companies

## Company Interface
```typescript
interface Company {
  id?: number
  company_id?: number
  name: string
  email?: string
  phone?: string
  status?: string
  industry?: string
  website?: string
  avatar?: string
  employee_count?: number
  annual_revenue?: number
  currency?: string
  [key: string]: any
}
```

## Default Configuration
- Entity Type: 'company'
- Option Label: 'name'
- Option Value: 'id'
- Filter Fields: ['name', 'email', 'phone', 'industry']
- Default Icon: 'pi pi-building'
- Header Title: 'Select Company'
- Create Button: 'New Company'

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
- This component is a wrapper around EntityPicker with company-specific configuration
- Uses id as the default value field but can be configured to use company_id
- The component automatically handles avatar color generation based on company name
- Statistics section shows employee count and annual revenue when enabled
- Industry and website information appears in the extra info section by default for companies