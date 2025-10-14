<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { format } from 'date-fns'
import DataTablePro from '@/Components/DataTablePro.vue'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import { FilterMatchMode } from '@primevue/core/api'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useToast } from 'primevue/usetoast'
import { useFormatting } from '@/composables/useFormatting'
import { useDeleteConfirmation } from '@/composables/useDeleteConfirmation'

interface JournalEntry {
  id: number
  reference: string
  date: string
  description: string
  total_debit: number
  total_credit: number
  status: 'draft' | 'posted' | 'void'
  journal_lines_count: number
}

interface Props {
  entries: any
  filters: any
  routeName: string
  permissions?: {
    view?: boolean
    create?: boolean
    edit?: boolean
    delete?: boolean
  }
  showActions?: boolean
  bulkActions?: {
    post?: boolean
    void?: boolean
  }
  customColumns?: Array<{
    field: string
    header: string
    filter?: any
    style?: string
  }>
}

const props = withDefaults(defineProps<Props>(), {
  showActions: true,
  bulkActions: () => ({ post: true, void: true }),
  permissions: () => ({ view: true, create: true, edit: true, delete: true }),
  customColumns: () => []
})

const emit = defineEmits<{
  refresh: []
}>()

const toasty = useToast()
const { confirmDelete } = useDeleteConfirmation()

// Default columns
const defaultColumns = [
  { field: 'reference', header: 'Reference', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 120px' },
  { field: 'date', header: 'Date', filter: { type: 'date', matchMode: FilterMatchMode.DATE_IS }, style: 'width: 120px' },
  { field: 'description', header: 'Description', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'min-width: 300px' },
  { field: 'total_debit', header: 'Total Debit', filter: { type: 'number', matchMode: FilterMatchMode.EQUALS }, style: 'width: 120px' },
  { field: 'total_credit', header: 'Total Credit', filter: { type: 'number', matchMode: FilterMatchMode.EQUALS }, style: 'width: 120px' },
  { field: 'status', header: 'Status', filter: { type: 'select', options: [{label:'Draft', value:'draft'},{label:'Posted', value:'posted'},{label:'Void', value:'void'}] }, style: 'width: 120px' }
]

// Add actions column if enabled
if (props.showActions) {
  defaultColumns.push({ field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 140px; text-align: center' })
}

// Merge custom columns with defaults
const columns = computed(() => {
  if (!props.customColumns.length) return defaultColumns
  
  // This is a simple merge - in a real implementation, you might want more sophisticated merging logic
  return [...defaultColumns.filter(col => !props.customColumns.some(custom => custom.field === col.field)), ...props.customColumns]
})

// Use the useDataTable composable
const table = useDataTable({
  columns: columns.value,
  initialFilters: props.filters,
  routeName: props.routeName,
  filterLookups: {
    status: {
      options: [{label:'Draft', value:'draft'},{label:'Posted', value:'posted'},{label:'Void', value:'void'}],
      labelField: 'label',
      valueField: 'value'
    }
  }
})

// Get formatting utilities
const { formatMoney } = useFormatting()

// Date formatting with consistent format
const formatDate = (dateString: string) => {
  return format(new Date(dateString), 'MMM dd, yyyy')
}

const getStatusBadge = (status: string) => {
  const variants = {
    draft: 'info',
    posted: 'success',
    void: 'danger'
  }
  
  return {
    severity: variants[status] || 'secondary',
    value: status.charAt(0).toUpperCase() + status.slice(1)
  }
}

// Permissions
const canView = computed(() => props.permissions?.view ?? true)
const canCreate = computed(() => props.permissions?.create ?? true)
const canEdit = computed(() => props.permissions?.edit ?? true)
const canDelete = computed(() => props.permissions?.delete ?? true)

// Bulk operations
async function bulkPost() {
  if (!table.selectedRows.value.length) return
  const confirmed = await confirmDelete({
    title: 'Post Journal Entries',
    message: `Post ${table.selectedRows.value.length} selected journal entries?`,
    confirmText: 'Post Entries',
    type: 'warning'
  })
  
  if (!confirmed) return
  
  await router.post(route(`${props.routeName}.bulk`), { 
    action: 'post', 
    entry_ids: table.selectedRows.value.map((r: JournalEntry) => r.id) 
  }, { 
    preserveState: true, 
    preserveScroll: true,
    onSuccess: () => {
      toasty.add({
        severity: 'success',
        summary: 'Success',
        detail: `${table.selectedRows.value.length} journal entries posted successfully`,
        life: 3000
      })
      table.selectedRows.value = []
      emit('refresh')
    }
  })
}

async function bulkVoid() {
  if (!table.selectedRows.value.length) return
  const confirmed = await confirmDelete({
    title: 'Void Journal Entries',
    message: `Void ${table.selectedRows.value.length} selected journal entries? This action cannot be undone.`,
    confirmText: 'Void Entries',
    type: 'danger'
  })
  
  if (!confirmed) return
  
  await router.post(route(`${props.routeName}.bulk`), { 
    action: 'void', 
    entry_ids: table.selectedRows.value.map((r: JournalEntry) => r.id) 
  }, { 
    preserveState: true, 
    preserveScroll: true,
    onSuccess: () => {
      toasty.add({
        severity: 'success',
        summary: 'Success',
        detail: `${table.selectedRows.value.length} journal entries voided successfully`,
        life: 3000
      })
      table.selectedRows.value = []
      emit('refresh')
    }
  })
}

// Void entry method
const voidEntry = async (entryId: string) => {
  const confirmed = await confirmDelete({
    title: 'Void Journal Entry',
    message: 'Are you sure you want to void this journal entry? This action cannot be undone.',
    confirmText: 'Void Entry',
    type: 'danger'
  })
  
  if (confirmed) {
    router.post(route(`${props.routeName}.void`, entryId), {}, {
      onSuccess: () => {
        toasty.add({
          severity: 'success',
          summary: 'Success',
          detail: 'Journal entry voided successfully',
          life: 3000
        })
        emit('refresh')
      }
    })
  }
}

// Page Actions
const { setActions, clearActions } = usePageActions()

const actions = [
  { key: 'create', label: 'New Journal Entry', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route(`${props.routeName}.create`)), disabled: () => !canCreate.value }
]

if (props.bulkActions.post) {
  actions.push({ key: 'post', label: 'Post Selected', icon: 'pi pi-check', severity: 'success', disabled: () => table.selectedRows.value.length === 0, click: bulkPost })
}

if (props.bulkActions.void) {
  actions.push({ key: 'void', label: 'Void Selected', icon: 'pi pi-ban', severity: 'danger', disabled: () => table.selectedRows.value.length === 0, click: bulkVoid })
}

actions.push({ key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => table.fetchData() })

setActions(actions)

// Expose table instance for external access
defineExpose({
  table,
  refresh: () => table.fetchData()
})
</script>

<template>
  <!-- Active Filters Chips -->
  <div v-if="table.activeFilters.value.length" class="flex flex-wrap items-center gap-2 mb-3">
    <span class="text-xs text-gray-500">Filters:</span>
    <span
      v-for="f in table.activeFilters.value"
      :key="f.key"
      class="inline-flex items-center text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded"
    >
      <span class="mr-1">{{ f.display }}</span>
      <button
        type="button"
        class="ml-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200"
        @click="table.clearTableFilterField(table.tableFilters.value, f.field)"
        aria-label="Clear filter"
      >
        ×
      </button>
    </span>
    <Button label="Clear all" size="small" text @click="table.clearFilters()" />
  </div>
  
  <DataTablePro
    :value="entries.data"
    :loading="entries.loading"
    :paginator="true"
    :rows="entries.per_page"
    :totalRecords="entries.total"
    :lazy="true"
    :sortField="table.filterForm.sort_by"
    :sortOrder="table.filterForm.sort_direction === 'asc' ? 1 : -1"
    :columns="columns"
    :virtualScroll="entries.total > 200"
    scrollHeight="500px"
    responsiveLayout="stack"
    breakpoint="960px"
    v-model:filters="table.tableFilters.value"
    v-model:selection="table.selectedRows.value"
    selectionMode="multiple"
    dataKey="id"
    :showSelectionColumn="true"
    @page="table.onPage"
    @sort="table.onSort"
    @filter="table.onFilter"
  >
    <template #cell-reference="{ data }">
      <span v-if="data.reference" class="font-mono text-sm font-medium">
        {{ data.reference }}
      </span>
      <span v-else class="text-gray-400">
        —
      </span>
    </template>

    <template #cell-date="{ data }">
      <span class="text-sm">{{ formatDate(data.date) }}</span>
    </template>

    <template #cell-description="{ data }">
      <div class="max-w-md">
        <div class="font-medium">{{ data.description }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
          {{ data.journal_lines_count || 0 }} lines
        </div>
      </div>
    </template>

    <template #cell-total_debit="{ data }">
      <span class="font-medium text-green-600 dark:text-green-400">
        {{ formatMoney(data.total_debit) }}
      </span>
    </template>

    <template #cell-total_credit="{ data }">
      <span class="font-medium text-red-600 dark:text-red-400">
        {{ formatMoney(data.total_credit) }}
      </span>
    </template>

    <template #cell-status="{ data }">
      <Badge 
        :value="getStatusBadge(data.status).value"
        :severity="getStatusBadge(data.status).severity"
        size="small"
      />
    </template>

    <template #cell-actions="{ data }" v-if="showActions">
      <div class="flex items-center justify-center gap-2">
        <!-- View -->
        <Link :href="route(`${routeName}.show`, data.id)">
          <button
            class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/20 transition-all duration-200 transform hover:scale-105"
            title="View details"
          >
            <i class="fas fa-eye text-blue-600 dark:text-blue-400"></i>
          </button>
        </Link>
        
        <!-- Edit -->
        <Link v-if="canEdit && data.status === 'draft'" :href="route(`${routeName}.edit`, data.id)">
          <button
            class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
            title="Edit entry"
          >
            <i class="fas fa-edit text-green-600 dark:text-green-400"></i>
          </button>
        </Link>
        
        <!-- Post -->
        <button
          v-if="canEdit && data.status === 'draft' && bulkActions.post"
          @click="router.post(route(`${routeName}.post`, data.id))"
          class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/20 transition-all duration-200 transform hover:scale-105"
          title="Post entry"
        >
          <i class="fas fa-check text-purple-600 dark:text-purple-400"></i>
        </button>
        
        <!-- Void -->
        <button
          v-if="canDelete && data.status !== 'void' && bulkActions.void"
          @click="voidEntry(data.id)"
          class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-red-100 dark:hover:bg-red-900/20 transition-all duration-200 transform hover:scale-105"
          title="Void entry"
        >
          <i class="fas fa-ban text-red-600 dark:text-red-400"></i>
        </button>
        
        <!-- Print -->
        <Link :href="route(`${routeName}.print`, data.id)" target="_blank">
          <button
            class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/20 transition-all duration-200 transform hover:scale-105"
            title="Print entry"
          >
            <i class="fas fa-print text-gray-600 dark:text-gray-400"></i>
          </button>
        </Link>
      </div>
    </template>

    <template #empty>
      <div class="text-center py-8">
        <i class="fas fa-book text-4xl text-gray-300 mb-3"></i>
        <p class="text-gray-500">No journal entries found</p>
        <p class="text-sm">Try adjusting your filters or create a new journal entry.</p>
      </div>
    </template>

    <template #footer>
      <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
        <span>
          Showing {{ entries.from }} to {{ entries.to }} of {{ entries.total }} entries
        </span>
        <span>
          Selected: {{ table.selectedRows.value.length }}
        </span>
      </div>
    </template>
  </DataTablePro>
</template>