# Dynamic Components Documentation

## CollapsibleFilter Component

A versatile, collapsible filter component that can be used across different pages with consistent behavior.

### Usage

```vue
<template>
  <CollapsibleFilter 
    ref="filterRef"
    title="Filters"
    :default-collapsed="false"
    @clear="clearFilters"
  >
    <template #default="{ setActiveFiltersCount }">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <InputText
          v-model="filters.search"
          placeholder="Search..."
          @input="() => setActiveFiltersCount(activeFiltersCount)"
        />
        
        <Select
          v-model="filters.category"
          :options="categoryOptions"
          placeholder="Select Category"
          show-clear
        />
      </div>
      
      <div class="flex gap-2 mt-4">
        <Button @click="applyFilters" label="Apply" />
        <Button @click="clearFilters" label="Clear" severity="secondary" />
      </div>
    </template>
  </CollapsibleFilter>
</template>
```

### Props

- `title` (String, default: 'Filters'): Title displayed in the header
- `defaultCollapsed` (Boolean, default: false): Initial collapse state
- `persistent` (Boolean, default: true): Whether to remember state

### Events

- `@toggle`: Emitted when filter is collapsed/expanded
- `@clear`: Emitted when clear button is clicked

### Methods

- `collapse()`: Programmatically collapse the filter
- `expand()`: Programmatically expand the filter
- `setActiveFiltersCount(count)`: Update the active filter count badge

## ActionPanel Component

A dynamic action panel that generates buttons based on data type and selection state.

### Usage

```vue
<template>
  <ActionPanel
    :data="companies"
    :data-type="'companies'"
    :selected-items="selectedItems"
    :show-bulk-actions="true"
    :max-visible="4"
    @action="handleAction"
    @bulk-action="handleBulkAction"
  />
</template>
```

### Props

- `data` (Array): Current data being displayed
- `dataType` (String): Type of data ('companies', 'invoices', 'reports', etc.)
- `selectedItems` (Array): Currently selected items for bulk actions
- `showBulkActions` (Boolean, default: true): Whether to show bulk action controls
- `maxVisible` (Number, default: 4): Maximum visible actions before "More" button
- `availableActions` (Array): Custom actions to add to the default ones

### Events

- `@action`: Emitted when a regular action is clicked
- `@bulk-action`: Emitted when a bulk action is clicked

### Supported Data Types

#### Companies
- New Company
- Import
- Export
- Bulk Delete
- Bulk Edit (placeholder)

#### Invoices
- New Invoice
- Import
- Export
- Batch Pay
- Bulk Delete
- Bulk Edit (placeholder)

#### Reports
- Generate Report
- Schedule
- Export
- Bulk Delete
- Bulk Edit (placeholder)

#### Default (fallback)
- New Item
- Export
- Bulk Delete
- Bulk Edit (placeholder)

## Implementation Example

Here's a complete example of how to implement both components (note: this replaces the old bulk actions bar):

```vue
<script setup>
import { ref, computed, watch } from 'vue'
import CollapsibleFilter from '@/Components/CollapsibleFilter.vue'
import ActionPanel from '@/Components/ActionPanel.vue'
import { useBulkSelection } from '@/composables/useBulkSelection'

// Filter state
const filterRef = ref()
const filters = ref({
  search: '',
  category: null,
  status: null
})

// Bulk selection
const {
  selectedItems,
  selectedCount,
  hasSelection,
  clearSelection
} = useBulkSelection([], 'items')

// Data
const items = ref([])

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0
  if (filters.value.search) count++
  if (filters.value.category) count++
  if (filters.value.status) count++
  return count
})

// Update filter component
watch(activeFiltersCount, (count) => {
  if (filterRef.value) {
    filterRef.value.setActiveFiltersCount(count)
  }
})

// Action handlers
const handleAction = (action) => {
  console.log('Action:', action)
  // Handle different actions
}

const handleBulkAction = (action) => {
  console.log('Bulk action:', action)
  // Handle bulk actions
}

// Filter methods
const clearFilters = () => {
  filters.value = {
    search: '',
    category: null,
    status: null
  }
}

const applyFilters = () => {
  // Apply filter logic
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <PageHeader title="Items" subtitle="Manage your items" />

    <!-- Collapsible Filters -->
    <CollapsibleFilter 
      ref="filterRef"
      title="Filters"
      :default-collapsed="false"
      @clear="clearFilters"
    >
      <template #default="{ setActiveFiltersCount }">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <InputText
            v-model="filters.search"
            placeholder="Search items..."
            @input="() => setActiveFiltersCount(activeFiltersCount)"
          />
          
          <Select
            v-model="filters.category"
            :options="categoryOptions"
            placeholder="Select Category"
            show-clear
          />
        </div>
        
        <div class="flex gap-2 mt-4">
          <Button @click="applyFilters" label="Apply Filters" />
          <Button @click="clearFilters" label="Clear" severity="secondary" />
        </div>
      </template>
    </CollapsibleFilter>

    <!-- Dynamic Action Panel -->
    <ActionPanel
      :data="items"
      :data-type="'companies'"
      :selected-items="selectedItems"
      :show-bulk-actions="true"
      :max-visible="4"
      @action="handleAction"
      @bulk-action="handleBulkAction"
    />

    <!-- Data Table -->
    <DataTable :value="items" :selection="selectedItems" @selection-change="selectedItems = $event">
      <!-- Table columns -->
    </DataTable>
  </div>
</template>
```

## Features

### CollapsibleFilter
- ✅ Smooth animations
- ✅ Active filter count badge
- ✅ Auto-expand when filters are applied
- ✅ Keyboard accessible
- ✅ Responsive design
- ✅ Dark mode support
- ✅ Clear button when filters are active

### ActionPanel
- ✅ Dynamic action generation based on data type
- ✅ Bulk action controls
- ✅ Selection state management
- ✅ Responsive design with overflow menu
- ✅ Keyboard accessible
- ✅ Dark mode support
- ✅ Customizable actions
- ✅ Auto-hiding "More" button when not needed