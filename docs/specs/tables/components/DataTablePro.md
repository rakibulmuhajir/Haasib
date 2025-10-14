# DataTablePro Component Specification

## Overview
DataTablePro is an enhanced data table component built on PrimeVue's DataTable with additional features for Laravel/Inertia.js applications. It provides advanced filtering, sorting, pagination, and data management capabilities.

## Features

### Core Features
- **Lazy Loading**: Supports server-side data fetching
- **Pagination**: Built-in pagination with customizable rows per page
- **Sorting**: Multi-column sorting with server-side support
- **Selection**: Single and multi-row selection modes
- **Virtual Scrolling**: Efficient rendering for large datasets (>200 records)

### Advanced Filtering
- **DSL (Domain Specific Language) Filters**: Complex filter rules with AND/OR logic
- **Column Filters**: Individual filter controls per column
- **Date Range Filtering**: Date picker with range selection
- **Number Range Filtering**: Min/max number inputs
- **Text Search**: Contains, equals, starts with, ends with
- **Select Filters**: Dropdown options for categorical data
- **Active Filter Chips**: Visual display of active filters with clear functionality

### UI/UX Features
- **Responsive Design**: Column prioritization for different screen sizes
- **Loading States**: Built-in loading indicators
- **Empty States**: Custom empty state messages with call-to-action
- **Hover Effects**: Interactive feedback on rows and actions
- **Tooltips**: Helpful hints for actions and filters
- **Dark Mode Support**: Automatic dark/light theme adaptation

### Bulk Operations
- **Multi-select**: Checkbox selection with select all functionality
- **Bulk Actions**: Delete, enable/disable, export operations
- **Progress Feedback**: Loading states during bulk operations
- **Success/Error Notifications**: Toast notifications for operation results

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| value | Array | [] | Data to display in the table |
| loading | Boolean | false | Loading state indicator |
| paginator | Boolean | true | Enable pagination |
| rows | Number | 15 | Number of rows per page |
| totalRecords | Number | 0 | Total number of records |
| lazy | Boolean | true | Enable lazy loading |
| sortField | String | null | Current sort field |
| sortOrder | Number | null | Sort order (1 for asc, -1 for desc) |
| columns | Array | [] | Column configuration |
| filters | Object | {} | Filter state object |
| selection | Array | [] | Selected rows |
| selectionMode | String | null | 'single' or 'multiple' |
| dataKey | String | 'id' | Unique identifier for rows |
| showSelectionColumn | Boolean | true | Show selection checkbox column |
| virtualScroll | Boolean | false | Enable virtual scrolling |
| scrollHeight | String | null | Height for scrolling |
| responsiveLayout | String | 'scroll' | Responsive layout mode |
| breakpoint | String | '960px' | Breakpoint for responsive behavior |

## Events

| Event | Parameters | Description |
|-------|------------|-------------|
| page | { page: number, rows: number } | Fired when page changes |
| sort | { sortField: string, sortOrder: number } | Fired when sorting changes |
| filter | { filters: object } | Fired when filters change |
| selection-change | Array | Fired when selection changes |

## Column Configuration

Each column object supports the following properties:

```typescript
interface ColumnConfig {
  field: string;           // Data field to display
  header: string;          // Column header text
  sortable?: boolean;      // Enable sorting (default: false)
  filterable?: boolean;    // Enable filtering (default: true)
  filterField?: string;    // Custom filter field name
  filter?: {               // Filter configuration
    type: 'text' | 'date' | 'number' | 'select';
    matchMode: string;
    options?: Array<{label: string, value: any}>;
  };
  style?: string;          // CSS styles for column
  class?: string;          // CSS classes for column
  exportable?: boolean;    // Include in exports (default: true)
}
```

## Usage Examples

### Basic Usage
```vue
<DataTablePro
  :value="customers.data"
  :loading="customers.loading"
  :paginator="true"
  :rows="customers.per_page"
  :totalRecords="customers.total"
  :lazy="true"
  :columns="columns"
  @page="onPage"
  @sort="onSort"
  @filter="onFilter"
/>
```

### With Selection and Actions
```vue
<DataTablePro
  :value="data"
  selectionMode="multiple"
  v-model:selection="selectedRows"
  :showSelectionColumn="true"
  dataKey="id"
>
  <!-- Column definitions -->
  <Column field="name" header="Name" sortable />
  <Column field="email" header="Email" />
  <Column header="Actions" :exportable="false">
    <template #body="{ data }">
      <button @click="edit(data)">Edit</button>
      <button @click="delete(data)">Delete</button>
    </template>
  </Column>
</DataTablePro>
```

### With Advanced Filtering
```typescript
const columns = [
  { 
    field: 'created_at', 
    header: 'Date',
    filter: { 
      type: 'date', 
      matchMode: FilterMatchMode.DATE_BETWEEN 
    }
  },
  { 
    field: 'status', 
    header: 'Status',
    filter: { 
      type: 'select', 
      matchMode: FilterMatchMode.EQUALS,
      options: [
        { label: 'Active', value: 'active' },
        { label: 'Inactive', value: 'inactive' }
      ]
    }
  },
  { 
    field: 'balance', 
    header: 'Balance',
    filter: { 
      type: 'number', 
      matchMode: FilterMatchMode.BETWEEN 
    }
  }
];
```

## Integration with useDataTable Composable

The DataTablePro is designed to work seamlessly with the `useDataTable` composable:

```typescript
const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'customers.index',
  filterLookups: {
    status: {
      options: statusOptions,
      labelField: 'name',
      valueField: 'id'
    }
  }
});
```

## Best Practices

1. **Always use lazy loading** for large datasets
2. **Implement server-side filtering/sorting** for better performance
3. **Use virtual scrolling** for datasets with >200 records
4. **Provide meaningful empty states** with call-to-action buttons
5. **Use responsive column classes** to hide less important columns on mobile
6. **Implement proper error handling** with toast notifications
7. **Use the useDataTable composable** for consistent behavior across tables
8. **Add loading indicators** for better UX during data fetching
9. **Implement bulk operations** with confirmation dialogs
10. **Use DSL filters** for complex filtering requirements

## Dependencies

- PrimeVue DataTable
- PrimeVue Column
- PrimeVue Button
- PrimeVue InputText
- PrimeVue Dropdown
- PrimeVue Calendar
- PrimeVue Dialog
- PrimeVue Toast
- Font Awesome Icons
- Tailwind CSS

## Styling

The component uses Tailwind CSS classes for styling:
- Responsive grid system
- Dark mode support with `dark:` prefix
- Consistent spacing with gap utilities
- Hover states and transitions
- Loading animations

## Accessibility

- Semantic HTML structure
- ARIA labels for actions
- Keyboard navigation support
- Screen reader friendly
- High contrast color support