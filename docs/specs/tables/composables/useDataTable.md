# useDataTable Composable Specification

## Overview

`useDataTable` is a powerful composable that provides comprehensive data table functionality for Laravel/Inertia.js applications. It handles filtering, sorting, pagination, and data fetching with support for complex DSL (Domain Specific Language) filters.

## Features

- **Server-side data fetching** with automatic parameter building
- **DSL filter system** for complex filter rules with AND/OR logic
- **Active filter chips** with clear functionality
- **Multi-column sorting** with direction control
- **Pagination** with customizable page sizes
- **Bulk operations** support
- **TypeScript support** with full type safety
- **Reactive state management** using Vue 3 Composition API

## API Reference

### Parameters

```typescript
interface UseDataTableOptions {
  columns: ColumnConfig[];           // Column configuration
  initialFilters: Record<string, any>; // Initial filter values
  routeName: string;                // Laravel route name for data fetching
  filterLookups?: Record<string, {   // Filter option lookups
    options: any[];
    labelField?: string;
    valueField?: string;
  }>;
}
```

### Return Value

```typescript
interface UseDataTableReturn {
  // State
  tableFilters: Ref<Record<string, any>>;    // Internal filter state
  selectedRows: Ref<any[]>;                   // Selected row data
  filterForm: InertiaForm;                   // Form with filter/sort/pagination
  activeFilters: ComputedRef<FilterChip[]>;   // Active filters for display
  
  // Methods
  fetchData: (extraParams?: Record<string, any>) => void;
  onPage: (event: any) => void;
  onSort: (event: any) => void;
  onFilter: (event: any) => void;
  clearFilters: () => void;
  clearTableFilterField: (filters: any, field: string) => void;
}
```

## Usage Examples

### Basic Usage

```typescript
const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'customers.index'
});
```

### With Filter Lookups

```typescript
const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'customers.index',
  filterLookups: {
    is_active: {
      options: [
        { label: 'Active', value: '1' },
        { label: 'Inactive', value: '0' }
      ],
      labelField: 'label',
      valueField: 'value'
    },
    country_id: {
      options: props.countries,
      labelField: 'name',
      valueField: 'id'
    }
  }
});
```

### In Vue Component

```vue
<template>
  <div>
    <!-- Active Filter Chips -->
    <div v-if="table.activeFilters.value.length">
      <span v-for="filter in table.activeFilters.value" :key="filter.key">
        {{ filter.display }}
        <button @click="clearFilter(filter)">×</button>
      </span>
    </div>
    
    <!-- Search Input -->
    <InputText 
      v-model="table.filterForm.search" 
      placeholder="Search..."
      @keyup.enter="table.fetchData()"
    />
    
    <!-- Data Table -->
    <DataTablePro
      :value="data"
      :loading="loading"
      v-model:filters="table.tableFilters.value"
      v-model:selection="table.selectedRows.value"
      @page="table.onPage"
      @sort="table.onSort"
      @filter="table.onFilter"
    />
  </div>
</template>

<script setup>
const table = useDataTable({
  columns: [
    { field: 'name', header: 'Name', filter: { type: 'text' } },
    { field: 'email', header: 'Email', filter: { type: 'text' } },
    { field: 'created_at', header: 'Created', filter: { type: 'date' } }
  ],
  initialFilters: { search: '' },
  routeName: 'users.index'
});
</script>
```

## DSL Filter System

The composable supports complex filter rules using a DSL structure:

```typescript
// Example DSL filter
{
  condition: 'AND',
  rules: [
    { field: 'name', operator: 'contains', value: 'john' },
    { field: 'status', operator: 'equals', value: 'active' },
    {
      condition: 'OR',
      rules: [
        { field: 'created_at', operator: 'after', value: '2024-01-01' },
        { field: 'created_at', operator: 'before', value: '2024-12-31' }
      ]
    }
  ]
}
```

## Active Filter Chips

The `activeFilters` computed property generates user-friendly filter chips:

```typescript
interface FilterChip {
  key: string;        // Filter field identifier
  field: string;      // Field name for clearing
  display: string;    // User-readable display text
}
```

## Integration with Laravel Backend

The composable expects the backend to handle:

1. **Search parameter**: `?search=query` for global search
2. **DSL filters**: `?filters={"rules":[...]}` for complex filters
3. **Sorting**: `?sort_by=field&sort_direction=asc|desc`
4. **Pagination**: `?page=2&per_page=15`

Example Laravel controller method:

```php
public function index(Request $request)
{
    $query = Model::query();
    
    // Apply search
    if ($request->filled('search')) {
        $query->where('name', 'like', "%{$request->search}%");
    }
    
    // Apply DSL filters
    if ($request->filled('filters')) {
        $filters = json_decode($request->filters, true);
        $query = FilterBuilder::apply($query, $filters);
    }
    
    // Apply sorting
    $query->orderBy(
        $request->input('sort_by', 'created_at'),
        $request->input('sort_direction', 'desc')
    );
    
    return $query->paginate($request->input('per_page', 15));
}
```

## Best Practices

1. **Always provide initialFilters** even if empty
2. **Use TypeScript interfaces** for column configurations
3. **Implement proper error handling** for failed requests
4. **Debounce search inputs** to prevent excessive API calls
5. **Use filter lookups** for select filters with dynamic options
6. **Clear selections** after bulk operations
7. **Preserve scroll position** during navigation
8. **Use loading states** during data fetching

## Dependencies

- Vue 3 Composition API
- Inertia.js
- PrimeVue Core API
- Custom filter utilities (`@/Utils/filters`)

## Related Components

- DataTablePro: The table component that pairs with this composable
- useDeleteConfirmation: For delete operations with confirmation dialogs
- usePageActions: For page-level action buttons