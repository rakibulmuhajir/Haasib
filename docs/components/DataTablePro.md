# DataTablePro

## Description
An advanced data table component with filtering, sorting, virtual scrolling, and comprehensive data manipulation capabilities. Built on top of PrimeVue DataTable with enhanced features.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| columns | Array<Column> | Yes | - | Column configuration |
| value | Array<any> | Yes | - | Data to display |
| loading | boolean | No | false | Loading state |
| filters | Filters | No | {} | Active filters |
| selection | any | No | null | Selected row(s) |
| selectionMode | 'single' \| 'multiple' \| null | No | null | Selection mode |
| dataKey | string | No | 'id' | Unique identifier field |
| lazy | boolean | No | false | Enable lazy loading |
| totalRecords | number | No | 0 | Total records for lazy loading |
| rows | number | No | 10 | Rows per page |
| sortField | string | No | - | Default sort field |
| sortOrder | number | No | 1 | Default sort order (1: asc, -1: desc) |

### Column Type
```typescript
interface Column {
  field: string
  header: string
  filter?: FilterConfig
  sortable?: boolean
  style?: string
  bodyStyle?: string
  headerStyle?: string
  width?: string
  hidden?: boolean
}
```

### FilterConfig Type
```typescript
interface FilterConfig {
  type: 'text' \| 'number' \| 'date' \| 'select' \| 'multiselect'
  options?: Array<{ label: string; value: any }>
  matchMode?: 'equals' \| 'contains' \| 'between' \| 'in'
}
```

## Slots
| Slot Name | Description | Props |
|-----------|-------------|-------|
| default | Default slot for additional content | - |
| header | Table header content | - |
| footer | Table footer content | - |
| loading | Loading overlay content | - |
| empty | Empty state content | - |
| paginator | Custom paginator | - |
| column-{field} | Custom column template | { data: any, index: number } |
| filter-{field} | Custom filter template | { filter: any, filterCallback: Function } |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| @filter | (filters: Filters) => void | When filters change |
| @sort | (event: SortEvent) => void | When sorting changes |
| @page | (event: PageEvent) => void | When pagination changes |
| @selection-change | (selection: any) => void | When selection changes |
| @row-click | (event: RowClickEvent) => void | When row is clicked |
| @row-select | (event: { originalEvent: Event, data: any, index: number }) => void | When row is selected |
| @row-unselect | (event: { originalEvent: Event, data: any, index: number }) => void | When row is unselected |

## Usage Examples

### Basic Usage
```vue
<template>
  <DataTablePro
    :columns="columns"
    :value="customers"
    :loading="loading"
    dataKey="customer_id"
    @filter="onFilter"
    @sort="onSort"
  >
    <template #column-status="{ data }">
      <StatusBadge :value="data.status" />
    </template>
  </DataTablePro>
</template>

<script setup>
import { ref } from 'vue'

const columns = ref([
  { field: 'name', header: 'Name', filter: { type: 'text' }, sortable: true },
  { field: 'email', header: 'Email', filter: { type: 'text' }, sortable: true },
  { field: 'status', header: 'Status', filter: { type: 'select', options: statusOptions } },
  { field: 'balance', header: 'Balance', filter: { type: 'number' }, sortable: true }
])

const customers = ref([])
const loading = ref(false)
</script>
```

### With Selection and Custom Filters
```vue
<template>
  <DataTablePro
    :columns="columns"
    :value="invoices"
    :loading="loading"
    v-model:selection="selectedInvoice"
    selectionMode="single"
    dataKey="invoice_id"
    :rows="20"
    @row-select="onInvoiceSelect"
  >
    <template #header>
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-medium">Invoices</h3>
        <Button label="New Invoice" icon="pi pi-plus" />
      </div>
    </template>
    
    <template #column-amount="{ data }">
      <BalanceDisplay :amount="data.total_amount" :currency="data.currency" />
    </template>
    
    <template #column-actions="{ data }">
      <div class="flex gap-2">
        <Button icon="pi pi-eye" class="p-button-text p-button-sm" @click="viewInvoice(data)" />
        <Button icon="pi pi-edit" class="p-button-text p-button-sm" @click="editInvoice(data)" />
      </div>
    </template>
  </DataTablePro>
</template>
```

## Features
- **Advanced Filtering**: Text, number, date, select, and multiselect filters
- **Virtual Scrolling**: Efficient rendering of large datasets
- **Column Management**: Show/hide columns, reorder columns
- **Export Options**: CSV, PDF export capabilities
- **Responsive Design**: Mobile-friendly with horizontal scroll
- **State Management**: Persists filters, sort, and pagination
- **Performance Optimized**: Lazy loading and virtualization

## Filter Types
- **Text**: Contains, starts with, equals
- **Number**: Equals, not equals, between, greater than, less than
- **Date**: Equals, before, after, between
- **Select**: Single value selection
- **Multiselect**: Multiple value selection

## Accessibility
- Full keyboard navigation
- ARIA labels and roles
- Screen reader announcements for sorting/filtering
- High contrast support

## Styling
- Custom CSS variables for theming
- Responsive breakpoints
- Consistent spacing and typography
- Hover states and transitions

## Dependencies
- PrimeVue DataTable (base component)
- PrimeVue Column, ColumnGroup
- Custom FilterMenu component
- Custom Paginator component

## Testing
Test scenarios to cover:
- Sorting functionality
- Filter application and clearing
- Pagination behavior
- Row selection (single/multiple)
- Virtual scrolling performance
- Export functionality
- Responsive behavior
- Keyboard navigation
- Screen reader compatibility

## Performance Considerations
- Use virtual scrolling for datasets > 1000 rows
- Enable lazy loading for server-side operations
- Debounce filter inputs for better performance
- Use appropriate row sizes for virtualization

## Notes
- Always provide a unique dataKey for proper row identification
- Consider the performance impact of complex custom templates
- Use the loading state during data operations
- Implement proper error handling for async operations