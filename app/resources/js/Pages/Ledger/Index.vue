<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, reactive, onUnmounted } from 'vue'
import { format } from 'date-fns'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import DataTablePro from '@/Components/DataTablePro.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Badge from 'primevue/badge'
import Card from 'primevue/card'
import { FilterMatchMode } from '@primevue/core/api'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useToast } from 'primevue/usetoast'

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

const props = defineProps({
  entries: Object,
  filters: Object,
})

const toasty = useToast()
const { setActions, clearActions } = usePageActions()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Ledger', url: '/ledger', icon: 'book' },
])

// DataTablePro columns definition
const columns = [
  { field: 'reference', header: 'Reference', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 120px' },
  { field: 'date', header: 'Date', filter: { type: 'date', matchMode: FilterMatchMode.DATE_IS }, style: 'width: 120px' },
  { field: 'description', header: 'Description', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'min-width: 300px' },
  { field: 'total_debit', header: 'Total Debit', filter: { type: 'number', matchMode: FilterMatchMode.EQUALS }, style: 'width: 120px' },
  { field: 'total_credit', header: 'Total Credit', filter: { type: 'number', matchMode: FilterMatchMode.EQUALS }, style: 'width: 120px' },
  { field: 'status', header: 'Status', filter: { type: 'select', options: [{label:'Draft', value:'draft'},{label:'Posted', value:'posted'},{label:'Void', value:'void'}] }, style: 'width: 120px' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 140px; text-align: center' },
]

// Use the useDataTable composable
const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'ledger.index',
  filterLookups: {
    status: {
      options: [{label:'Draft', value:'draft'},{label:'Posted', value:'posted'},{label:'Void', value:'void'}],
      labelField: 'label',
      valueField: 'value'
    }
  }
})

// Format functions
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

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
const canView = computed(() => 
  usePage().props.auth.permissions?.['ledger.view'] ?? false
)
const canCreate = computed(() => 
  usePage().props.auth.permissions?.['ledger.create'] ?? false
)
const canEdit = computed(() => 
  usePage().props.auth.permissions?.['ledger.edit'] ?? false
)
const canDelete = computed(() => 
  usePage().props.auth.permissions?.['ledger.delete'] ?? false
)

// Bulk operations
async function bulkPost() {
  if (!table.selectedRows.value.length) return
  if (!confirm(`Post ${table.selectedRows.value.length} selected journal entries?`)) return
  
  await router.post(route('ledger.bulk'), { 
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
    }
  })
}

async function bulkVoid() {
  if (!table.selectedRows.value.length) return
  if (!confirm(`Void ${table.selectedRows.value.length} selected journal entries? This action cannot be undone.`)) return
  
  await router.post(route('ledger.bulk'), { 
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
    }
  })
}

// Void entry method
const voidEntry = (entryId: string) => {
  if (confirm('Are you sure you want to void this journal entry?')) {
    router.post(route('ledger.void', entryId))
  }
}

// Page Actions
setActions([
  { key: 'create', label: 'New Journal Entry', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('ledger.create')), disabled: () => !canCreate.value },
  { key: 'post', label: 'Post Selected', icon: 'pi pi-check', severity: 'success', disabled: () => table.selectedRows.value.length === 0, click: bulkPost },
  { key: 'void', label: 'Void Selected', icon: 'pi pi-ban', severity: 'danger', disabled: () => table.selectedRows.value.length === 0, click: bulkVoid },
  { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => table.fetchData() },
])

onUnmounted(() => clearActions())
</script>

<template>
  <Head title="Journal Entries" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Ledger" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
      </div>
    </template>

    <div class="space-y-6">
      <PageHeader
        title="Journal Entries"
        subtitle="View and manage your accounting journal entries"
        :maxActions="5"
      >
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="table.filterForm.search"
              placeholder="Search journal entries..."
              class="w-64"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Journal Entries Table -->
      <Card>
        <template #content>
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
                {{ formatCurrency(data.total_debit) }}
              </span>
            </template>

            <template #cell-total_credit="{ data }">
              <span class="font-medium text-red-600 dark:text-red-400">
                {{ formatCurrency(data.total_credit) }}
              </span>
            </template>

            <template #cell-status="{ data }">
              <Badge 
                :value="getStatusBadge(data.status).value"
                :severity="getStatusBadge(data.status).severity"
                size="small"
              />
            </template>

            <template #cell-actions="{ data }">
              <div class="flex items-center justify-center gap-2">
                <!-- View -->
                <Link :href="route('ledger.show', data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/20 transition-all duration-200 transform hover:scale-105"
                    title="View details"
                  >
                    <i class="fas fa-eye text-blue-600 dark:text-blue-400"></i>
                  </button>
                </Link>
                
                <!-- Edit -->
                <Link v-if="canEdit && data.status === 'draft'" :href="route('ledger.edit', data.id)">
                  <button
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-green-100 dark:hover:bg-green-900/20 transition-all duration-200 transform hover:scale-105"
                    title="Edit entry"
                  >
                    <i class="fas fa-edit text-green-600 dark:text-green-400"></i>
                  </button>
                </Link>
                
                <!-- Post -->
                <button
                  v-if="canEdit && data.status === 'draft'"
                  @click="router.post(route('ledger.post', data.id))"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Post entry"
                >
                  <i class="fas fa-check text-purple-600 dark:text-purple-400"></i>
                </button>
                
                <!-- Void -->
                <button
                  v-if="canDelete && data.status !== 'void'"
                  @click="voidEntry(data.id)"
                  class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-red-100 dark:hover:bg-red-900/20 transition-all duration-200 transform hover:scale-105"
                  title="Void entry"
                >
                  <i class="fas fa-ban text-red-600 dark:text-red-400"></i>
                </button>
                
                <!-- Print -->
                <Link :href="route('ledger.print', data.id)" target="_blank">
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
      </Card>
    </div>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>