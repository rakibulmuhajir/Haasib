# Development Guide

This guide provides best practices and patterns for developing with our reusable component library.

## Working with Reusable Components

### Component Philosophy

Our components are designed to be:
- **Configurable**: Use props to customize behavior and appearance
- **Composable**: Combine components to build complex UIs
- **Accessible**: Follow WCAG guidelines for keyboard navigation and screen readers
- **Consistent**: Maintain visual and behavioral consistency across the application

### General Usage Patterns

#### 1. Props Configuration
Always pass required props and use sensible defaults:

```vue
<!-- ✅ Good: Explicit prop usage -->
<LedgerEntriesTable
  :entries="entries"
  :filters="filters"
  routeName="ledger"
  :permissions="permissions"
/>

<!-- ❌ Avoid: Missing required props -->
<LedgerEntriesTable />
```

#### 2. Event Handling
Components emit events for actions that require parent handling:

```vue
<JournalEntryForm
  :accounts="accounts"
  routeName="ledger"
  submitRoute="submitRoute"
  @success="handleSuccess"
  @cancel="handleCancel"
/>
```

#### 3. Permission-Based UI
Always control component visibility based on user permissions:

```vue
<template>
  <JournalEntrySummary
    :entry="entry"
    :permissions="{
      post: canPost,
      void: canVoid
    }"
  />
</template>

<script setup>
const canPost = computed(() => 
  page.props.auth.permissions?.['ledger.post'] ?? false
)
</script>
```

## Ledger Components Usage

### Journal Entry Workflow

#### Creating Entries
Use the `JournalEntryForm` component for creating and editing journal entries:

```vue
<template>
  <JournalEntryForm
    :accounts="accounts"
    routeName="ledger"
    :submitRoute="route('ledger.store')"
    :method="'post'"
    :permissions="{
      create: canCreate
    }"
    @success="onSuccess"
    @cancel="onCancel"
  />
</template>
```

Key features:
- Dynamic line management
- Real-time balance validation
- Auto-balance helper
- Account selection with search

#### Displaying Entries
Use `JournalEntrySummary` and `LinesTable` for entry display:

```vue
<template>
  <!-- Summary cards -->
  <JournalEntrySummary
    :entry="entry"
    :permissions="permissions"
    @post="handlePost"
    @void="handleVoid"
  />
  
  <!-- Lines breakdown -->
  <LinesTable
    :lines="entry.journal_lines"
    @lineClick="handleLineClick"
  />
</template>
```

#### Listing Entries
Use `LedgerEntriesTable` for entry listings:

```vue
<template>
  <LedgerEntriesTable
    :entries="entries"
    :filters="filters"
    routeName="ledger"
    :permissions="permissions"
    :bulkActions="{
      post: canEdit,
      void: canDelete
    }"
  />
</template>
```

### Account Filtering

Use `LedgerAccountsFilters` for account listings:

```vue
<template>
  <LedgerAccountsFilters
    :initialFilters="initialFilters"
    routeName="ledger.accounts.index"
    :autoApply="true"
    @apply="handleFiltersApply"
  />
</template>
```

## Form Components Patterns

### Entity Pickers

All picker components follow a similar pattern:

```vue
<template>
  <CustomerPicker
    v-model="selectedCustomer"
    :filters="{
      active: true,
      company: currentCompany
    }"
    :showBalance="true"
    :showStats="true"
    @change="onCustomerChange"
  />
</template>
```

### Form Validation

Components integrate with Inertia.js's form validation:

```vue
<template>
  <JournalEntryForm
    :initialData="{
      description: 'Test Entry',
      date: new Date().toISOString().split('T')[0]
    }"
    :errors="errors"
    @submit="handleSubmit"
  />
</template>
```

## Best Practices

### 1. Component Composition

Combine components to build complex views:

```vue
<template>
  <Card>
    <template #title>Journal Entry Details</template>
    <template #content>
      <JournalEntrySummary :entry="entry" />
      <Divider />
      <LinesTable :lines="entry.journal_lines" />
    </template>
  </Card>
</template>
```

### 2. Loading States

Components handle their own loading states:

```vue
<template>
  <LedgerEntriesTable
    :entries="{
      data: entries,
      loading: $page.props.loading,
      total: entries.total
    }"
  />
</template>
```

### 3. Error Handling

Let components handle errors and emit events:

```vue
<template>
  <JournalEntryForm
    @error="showErrorToast"
    @success="showSuccessToast"
  />
</template>
```

### 4. Responsive Design

All components are mobile-responsive by default:

```vue
<!-- Components automatically adapt to screen size -->
<LedgerEntriesTable />
```

## Testing Components

### Unit Testing

Test component behavior with props and events:

```vue
<script setup>
import { mount } from '@vue/test-utils'
import LedgerEntriesTable from './LedgerEntriesTable.vue'

test('emits post event when bulk post clicked', async () => {
  const wrapper = mount(LedgerEntriesTable, {
    props: {
      entries: mockEntries,
      permissions: { edit: true }
    }
  })
  
  await wrapper.find('[data-testid="bulk-post"]').trigger('click')
  expect(wrapper.emitted('bulkPost')).toBeTruthy()
})
</script>
```

### Accessibility Testing

Ensure components are accessible:

```javascript
// Check keyboard navigation
await tab() // Navigate through interactive elements
await expect(document.activeElement).toBe(expectedElement)

// Check ARIA attributes
expect(wrapper.find('button').attributes('aria-label')).toBeDefined()
```

## Performance Considerations

### 1. Lazy Loading

Use lazy loading for heavy components:

```vue
<script setup>
const JournalEntryForm = defineAsyncComponent(() => 
  import('@/Components/Ledger/JournalEntryForm.vue')
)
</script>
```

### 2. Virtual Scrolling

Large tables use virtual scrolling:

```vue
<LedgerEntriesTable
  :entries="largeDataSet"
  :virtualScroll="true"
  scrollHeight="500px"
/>
```

### 3. Memoization

Use computed properties for expensive calculations:

```vue
<script setup>
const formattedEntries = computed(() => 
  entries.map(formatEntry)
)
</script>
```

## Common Patterns

### 1. Master-Detail Views

```vue
<template>
  <!-- Master list -->
  <LedgerEntriesTable
    :entries="entries"
    @rowClick="showDetail"
  />
  
  <!-- Detail view -->
  <Dialog v-model:visible="showDetailDialog">
    <JournalEntrySummary 
      v-if="selectedEntry"
      :entry="selectedEntry"
    />
  </Dialog>
</template>
```

### 2. Wizard Forms

```vue
<template>
  <Steps :activeIndex="currentStep">
    <StepPanel :index="0">
      <JournalEntryForm
        ref="entryForm"
        @next="currentStep++"
      />
    </StepPanel>
    <StepPanel :index="1">
      <ReviewPanel :entry="formData" />
    </StepPanel>
  </Steps>
</template>
```

### 3. Filtered Lists

```vue
<template>
  <LedgerAccountsFilters @apply="applyFilters" />
  <LedgerEntriesTable 
    :entries="filteredEntries" 
    :filters="activeFilters"
  />
</template>
```

## Troubleshooting

### Common Issues

1. **Props Not Updating**: Ensure you're using `v-model` or `:key` for reactivity
2. **Events Not Firing**: Check event names and parameter matching
3. **Styling Issues**: Verify CSS specificity and component styles
4. **Permission Errors**: Double-check permission props

### Debug Tools

Use Vue DevTools to inspect:
- Component props and events
- Reactive data flow
- Performance metrics

## Batch Processing Components

### Batches Management Interface

The batch processing system includes a comprehensive Vue.js interface for managing payment batches:

#### Component Structure
```vue
<template>
  <Batches>
    <BatchUpload @upload="handleUpload" />
    <BatchList 
      :batches="batches" 
      @refresh="loadBatches"
      @view="showBatchDetails"
    />
    <BatchDetails 
      v-if="selectedBatch"
      :batch="selectedBatch"
      @retry="handleRetry"
      @download="downloadReport"
    />
  </Batches>
</template>
```

#### Key Features
- **File Upload**: Drag-and-drop CSV file upload with validation
- **Real-time Progress**: Live status updates with percentage completion
- **Error Reporting**: Detailed error display with row-by-row validation
- **Batch Actions**: Retry failed batches, download reports, view details
- **Responsive Design**: Mobile-optimized interface

#### Usage Patterns

**File Upload Component:**
```vue
<BatchUpload
  :max-file-size="10240"
  accepted-types=".csv,.txt"
  @validation-error="showValidationError"
  @upload-success="onBatchCreated"
/>
```

**Batch Status Display:**
```vue
<BatchStatus
  :batch="batch"
  :show-progress="true"
  :auto-refresh="batch.isProcessing"
  @status-changed="handleStatusChange"
/>
```

**Error Details Component:**
```vue
<BatchErrors
  :errors="batch.metadata.processing_errors"
  :show-row-numbers="true"
  @retry-row="retrySpecificRow"
/>
```

#### State Management with Pinia

```javascript
// stores/batches.js
export const useBatchesStore = defineStore('batches', {
  state: () => ({
    batches: [],
    selectedBatch: null,
    loading: false,
    filters: {
      status: null,
      sourceType: null,
      dateRange: null
    }
  }),
  
  actions: {
    async loadBatches() {
      this.loading = true
      try {
        const response = await api.get('/accounting/payment-batches', {
          params: this.filters
        })
        this.batches = response.data.data
      } finally {
        this.loading = false
      }
    },
    
    async createBatch(formData) {
      const response = await api.post('/accounting/payment-batches', formData)
      await this.loadBatches()
      return response.data
    },
    
    async retryBatch(batchId) {
      await api.post(`/accounting/payment-batches/${batchId}/retry`)
      await this.loadBatches()
    }
  }
})
```

#### Real-time Updates

Use polling or WebSocket for real-time batch status updates:

```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const selectedBatch = ref(null)
let pollInterval = null

const startPolling = () => {
  pollInterval = setInterval(async () => {
    if (selectedBatch.value?.isProcessing) {
      await updateBatchStatus(selectedBatch.value.id)
    }
  }, 2000) // Poll every 2 seconds
}

onMounted(() => startPolling())
onUnmounted(() => clearInterval(pollInterval))
</script>
```

#### Error Handling

Implement comprehensive error handling:

```vue
<template>
  <BatchUpload @error="handleUploadError" />
  <ErrorDisplay 
    v-if="error"
    :error="error"
    @dismiss="clearError"
  />
</template>

<script setup>
const error = ref(null)

const handleUploadError = (errorData) => {
  error.value = {
    type: 'validation',
    message: errorData.message,
    details: errorData.errors
  }
}
</script>
```

#### Testing Batch Components

```javascript
// tests/components/BatchUpload.spec.js
import { mount } from '@vue/test-utils'
import BatchUpload from '@/components/BatchUpload.vue'

describe('BatchUpload', () => {
  test('validates CSV file format', async () => {
    const wrapper = mount(BatchUpload)
    const file = new File(['invalid content'], 'test.txt', {
      type: 'text/plain'
    })
    
    await wrapper.vm.handleFile(file)
    
    expect(wrapper.emitted('validation-error')).toBeTruthy()
    expect(wrapper.vm.errorMessage).toContain('Invalid CSV format')
  })
  
  test('emits upload-success with batch data', async () => {
    const wrapper = mount(BatchUpload)
    const validFile = new File(['valid,csv,data'], 'payments.csv')
    
    await wrapper.vm.handleFile(validFile)
    
    expect(wrapper.emitted('upload-success')).toBeTruthy()
  })
})
```

## Contributing

When adding new components:

1. Follow the established patterns
2. Include TypeScript definitions
3. Write comprehensive documentation
4. Add unit tests
5. Ensure accessibility compliance
6. Update the component index
7. Test batch processing workflows end-to-end
8. Verify real-time updates work correctly

See [Component Documentation Template](./docs/components/README.md) for documentation guidelines.