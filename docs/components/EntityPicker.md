# EntityPicker

## Description
A generic, configurable picker component for selecting entities (customers, companies, users, vendors, etc.). Built with PrimeVue Select and provides extensive customization options for displaying entity information including avatars, status badges, balances, and statistics.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | null | Selected entity ID |
| entities | Entity[] | Yes | - | Array of entities to display |
| entityType | 'customer' \| 'company' \| 'user' \| 'vendor' \| 'custom' | No | 'custom' | Type of entity for default configuration |
| optionLabel | string | No | 'name' | Field to use as display label |
| optionValue | string | No | 'id' | Field to use as value |
| optionDisabled | (entity: Entity) => boolean | No | - | Function to determine if option is disabled |
| placeholder | string | No | 'Select an item...' | Placeholder text when no selection |
| filterPlaceholder | string | No | 'Search items...' | Filter input placeholder |
| filterFields | string[] | No | ['name', 'email', 'code'] | Fields to search when filtering |
| showClear | boolean | No | true | Show clear button |
| disabled | boolean | No | false | Disable the picker |
| loading | boolean | No | false | Show loading state |
| error | string | No | - | Error message to display |
| avatarField | string | No | - | Field name for avatar URL |
| avatarTypeField | string | No | - | Field name for avatar type ('image' or 'icon') |
| iconField | string | No | - | Field name for icon class |
| defaultIcon | string | No | 'pi pi-user' | Default icon class |
| statusField | string | No | - | Field name for status value |
| showStatus | boolean | No | true | Show status badge |
| subtitleFields | string[] | No | [] | Fields to display in subtitle |
| showSubtitle | boolean | No | true | Show subtitle section |
| extraInfoFields | string[] | No | [] | Fields to display as extra info |
| showExtraInfo | boolean | No | false | Show extra info section |
| balanceField | string | No | - | Field name for balance amount |
| currencyField | string | No | 'currency_code' | Field name for currency code |
| defaultCurrency | string | No | 'USD' | Default currency code |
| showBalance | boolean | No | true | Show balance display |
| badgeField | string | No | - | Field name for badge value |
| badgeSeverity | 'success' \| 'info' \| 'warn' \| 'danger' \| 'secondary' | No | 'secondary' | Badge severity |
| showBadge | boolean | No | false | Show badge display |
| showActions | boolean | No | true | Show view action button |
| actionIcon | string | No | 'pi pi-external-link' | Action button icon |
| actionTitle | string | No | 'View details' | Action button title |
| headerTitle | string | No | 'Select Item' | Header title text |
| allowCreate | boolean | No | true | Show create button in header |
| createButtonLabel | string | No | 'New Item' | Create button label |
| createFirstButtonLabel | string | No | 'Create Your First Item' | Create first button label |
| emptyIcon | string | No | 'pi pi-search' | Empty state icon |
| entityNamePlural | string | No | 'items' | Plural entity name for empty state |
| statsFields | StatsField[] | No | [] | Fields to display in stats section |
| showStats | boolean | No | false | Show stats section |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| update:modelValue | (value: number \| string \| null) => void | Emitted when selection changes |
| change | (entity: Entity \| null) => void | Emitted when selection changes with full entity object |
| filter | (event: Event) => void | Emitted when filter is applied |
| show | () => void | Emitted when dropdown is shown |
| hide | () => void | Emitted when dropdown is hidden |
| create-entity | () => void | Emitted when create button is clicked |
| view-entity | (entity: Entity) => void | Emitted when view action is clicked |

## Usage Examples

### Basic Usage
```vue
<template>
  <EntityPicker
    v-model="selectedCustomerId"
    :entities="customers"
    entity-type="customer"
    @change="onCustomerChange"
  />
</template>

<script setup>
import { ref } from 'vue'
import EntityPicker from '@/Components/UI/Forms/EntityPicker.vue'

const selectedCustomerId = ref(null)
const customers = ref([
  { id: 1, name: 'John Doe', email: 'john@example.com', status: 'active' },
  { id: 2, name: 'Jane Smith', email: 'jane@example.com', status: 'inactive' }
])

const onCustomerChange = (customer) => {
  console.log('Selected customer:', customer)
}
</script>
```

### Advanced Usage with Custom Configuration
```vue
<template>
  <EntityPicker
    v-model="selectedCompanyId"
    :entities="companies"
    :optionLabel="'company_name'"
    :optionValue="'company_id'"
    :subtitleFields="['industry', 'location']"
    :extraInfoFields="['employee_count', 'annual_revenue']"
    :statsFields="[
      { key: 'project_count', label: 'Projects', type: 'number' },
      { key: 'total_value', label: 'Total Value', type: 'currency' }
    ]"
    :showStats="true"
    :showBalance="true"
    balanceField="account_balance"
    currencyField="currency"
    @view-entity="viewCompany"
  />
</template>
```

### Custom Entity Type
```vue
<template>
  <EntityPicker
    v-model="selectedProductId"
    :entities="products"
    entity-type="custom"
    :optionLabel="'product_name'"
    :optionValue="'sku'"
    :subtitleFields="['category', 'brand']"
    :extraInfoFields="['weight', 'dimensions']"
    :badgeField="'stock_status'"
    :badgeSeverity="'success'"
    :showBadge="true"
    :headerTitle="'Select Product'"
    :createButtonLabel="'Add Product'"
    emptyIcon="pi pi-box"
    entityNamePlural="products"
  />
</template>
```

## Features
- **Generic Design**: Works with any entity type through flexible configuration
- **Rich Display Options**: Shows avatars, status badges, subtitles, and extra info
- **Built-in Entity Types**: Pre-configured defaults for customer, company, user, and vendor
- **Balance Display**: Integrated BalanceDisplay component for monetary values
- **Statistics Section**: Configurable stats display with number and currency formatting
- **Search & Filter**: Built-in search with customizable filter fields
- **Create Integration**: Built-in create button for adding new entities
- **View Actions**: Quick access to view entity details
- **Empty States**: Customizable empty state messaging and actions
- **Accessibility**: Full keyboard navigation and screen reader support

## Entity Type Defaults

### Customer
- Subtitle fields: email, phone
- Extra info fields: customer_type
- Stats fields: invoice_count, outstanding_balance
- Icon: pi pi-users

### Company
- Subtitle fields: email, phone
- Extra info fields: industry, website
- Stats fields: employee_count, annual_revenue
- Icon: pi pi-building

### User
- Subtitle fields: email, role
- Extra info fields: department
- Stats fields: task_count, project_count
- Icon: pi pi-users

### Vendor
- Subtitle fields: email, phone
- Extra info fields: category, website
- Stats fields: bill_count, total_paid
- Icon: pi pi-truck

## Dependencies
- PrimeVue Select component
- PrimeVue Button component
- PrimeVue Tag component
- StatusBadge component
- BalanceDisplay component

## Styling
The component uses PrimeVue's styling system with Tailwind CSS:
- `.entity-picker` - Main container class
- Custom styling for option layouts
- Responsive design with proper spacing
- Hover states on options

## Methods
The component exposes the following methods through template refs:
- `show()` - Open the dropdown
- `hide()` - Close the dropdown
- `focus()` - Focus the input element

## Notes
- The component uses the new PrimeVue 4 Select component (renamed from Dropdown)
- Avatar generation supports both images and initials with color coding
- Stats fields support both number and currency types with proper formatting
- The component maintains backward compatibility with existing picker implementations