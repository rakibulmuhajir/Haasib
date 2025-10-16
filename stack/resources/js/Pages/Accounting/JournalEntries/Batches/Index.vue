<template>
  <div class="journal-batches-page">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
          Journal Batches
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Manage and process journal entry batches for approval and posting
        </p>
      </div>
      
      <div class="flex gap-3">
        <Button
          icon="pi pi-plus"
          label="Create Batch"
          @click="showCreateDialog = true"
          severity="primary"
        />
        <Button
          icon="pi pi-refresh"
          label="Refresh"
          @click="refreshData"
          :loading="loading"
          severity="secondary"
        />
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Batches</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ statistics.total_batches || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ getPeriodLabel() }}
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full">
              <i class="pi pi-folder text-blue-600 dark:text-blue-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
              <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                {{ statistics.pending_batches || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Need approval
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-orange-100 dark:bg-orange-900 rounded-full">
              <i class="pi pi-clock text-orange-600 dark:text-orange-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Posted</p>
              <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ statistics.posted_batches || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Processed
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full">
              <i class="pi pi-check-circle text-green-600 dark:text-green-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Entries</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ statistics.total_entries_in_batches || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Across all batches
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full">
              <i class="pi pi-file text-purple-600 dark:text-purple-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Status
            </label>
            <Dropdown
              v-model="filters.status"
              :options="statusOptions"
              placeholder="All statuses"
              optionLabel="label"
              optionValue="value"
              @change="applyFilters"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Min Entries
            </label>
            <InputNumber
              v-model="filters.min_entries"
              placeholder="Min entries"
              @change="applyFilters"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Max Entries
            </label>
            <InputNumber
              v-model="filters.max_entries"
              placeholder="Max entries"
              @change="applyFilters"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Search
            </label>
            <IconField>
              <InputIcon>
                <i class="pi pi-search" />
              </InputIcon>
              <InputText
                v-model="filters.search"
                placeholder="Search batches..."
                @keyup.enter="applyFilters"
              />
            </IconField>
          </div>
        </div>
      </template>
    </Card>

    <!-- Batches Table -->
    <Card>
      <template #header>
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Journal Batches
          </h3>
          <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ batches.total }} total
          </div>
        </div>
      </template>
      
      <template #content>
        <DataTable
          :value="batches.data"
          :loading="loading"
          stripedRows
          scrollable
          scrollHeight="600px"
          v-model:filters="tableFilters"
          filterDisplay="menu"
          :paginator="true"
          :rows="25"
          :rowsPerPageOptions="[10, 25, 50, 100]"
          @row-click="viewBatch"
        >
          <template #header>
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold">Journal Batches</span>
              <div class="flex gap-2">
                <Button
                  icon="pi pi-filter-slash"
                  label="Clear Filters"
                  @click="clearFilters"
                  severity="secondary"
                  size="small"
                />
              </div>
            </div>
          </template>

          <Column field="name" header="Name" sortable style="min-width: 200px">
            <template #body="{ data }">
              <div>
                <div class="font-medium text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                  {{ data.name }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400" v-if="data.description">
                  {{ truncateText(data.description, 50) }}
                </div>
              </div>
            </template>
          </Column>

          <Column field="status" header="Status" sortable style="min-width: 120px">
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="getStatusSeverity(data.status)"
                class="text-xs"
              />
            </template>
          </Column>

          <Column field="total_entries" header="Entries" sortable style="min-width: 100px">
            <template #body="{ data }">
              <span class="font-mono text-sm">{{ data.total_entries }}</span>
            </template>
          </Column>

          <Column field="statistics.total_amount" header="Total Amount" style="min-width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-sm">
                {{ formatCurrency(data.statistics?.total_amount || 0) }}
              </span>
            </template>
          </Column>

          <Column field="created_at" header="Created" sortable style="min-width: 140px">
            <template #body="{ data }">
              <span class="text-sm">{{ formatDate(data.created_at) }}</span>
            </template>
          </Column>

          <Column field="updated_at" header="Updated" sortable style="min-width: 140px">
            <template #body="{ data }">
              <span class="text-sm">{{ formatDate(data.updated_at) }}</span>
            </template>
          </Column>

          <Column header="Actions" style="min-width: 200px">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  icon="pi pi-eye"
                  size="small"
                  severity="secondary"
                  @click="viewBatch($event, data)"
                  v-tooltip="'View Batch'"
                />
                
                <Button
                  v-if="data.statistics?.can_approve"
                  icon="pi pi-check"
                  size="small"
                  severity="success"
                  @click="approveBatch($event, data)"
                  v-tooltip="'Approve Batch'"
                />
                
                <Button
                  v-if="data.statistics?.can_post"
                  icon="pi pi-upload"
                  size="small"
                  severity="primary"
                  @click="postBatch($event, data)"
                  v-tooltip="'Post Batch'"
                />
                
                <Button
                  v-if="data.statistics?.can_edit"
                  icon="pi pi-pencil"
                  size="small"
                  severity="secondary"
                  @click="editBatch($event, data)"
                  v-tooltip="'Edit Batch'"
                />
                
                <Button
                  v-if="data.statistics?.can_delete"
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  @click="deleteBatch($event, data)"
                  v-tooltip="'Delete Batch'"
                />
              </div>
            </template>
          </Column>
        </DataTable>

        <Paginator
          v-model:first="batches.from"
          :rows="batches.per_page"
          :totalRecords="batches.total"
          @page="onPageChange"
        />
      </template>
    </Card>

    <!-- Create Batch Dialog -->
    <Dialog
      v-model:visible="showCreateDialog"
      header="Create Journal Batch"
      :style="{ width: '600px' }"
      :modal="true"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Batch Name
          </label>
          <InputText
            v-model="createForm.name"
            placeholder="Enter batch name"
            :class="{ 'p-invalid': createErrors.name }"
          />
          <small class="p-error" v-if="createErrors.name">{{ createErrors.name }}</small>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Description
          </label>
          <Textarea
            v-model="createForm.description"
            placeholder="Enter batch description (optional)"
            rows="3"
          />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Select Journal Entries
          </label>
          <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
            <div class="flex justify-between items-center mb-2">
              <span class="text-sm font-medium">Available Entries (Draft/Approved)</span>
              <Button
                icon="pi pi-refresh"
                size="small"
                severity="secondary"
                @click="loadAvailableEntries"
                :loading="loadingEntries"
              />
            </div>
            <div v-if="availableEntries.length > 0" class="space-y-2 max-h-48 overflow-y-auto">
              <div
                v-for="entry in availableEntries"
                :key="entry.id"
                class="flex items-center p-2 bg-white dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600"
              >
                <Checkbox
                  v-model="createForm.journal_entry_ids"
                  :value="entry.id"
                  :binary="true"
                />
                <div class="ml-3 flex-1">
                  <div class="font-medium text-sm">{{ entry.description }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ entry.reference }} • {{ formatDate(entry.date) }} • Status: {{ entry.status }}
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
              No available entries found
            </div>
          </div>
          <small class="p-error" v-if="createErrors.journal_entry_ids">{{ createErrors.journal_entry_ids }}</small>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Cancel"
            severity="secondary"
            @click="showCreateDialog = false"
          />
          <Button
            label="Create Batch"
            @click="createBatch"
            :loading="creating"
          />
        </div>
      </template>
    </Dialog>

    <!-- Toast -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'

// PrimeVue Components
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Paginator from 'primevue/paginator'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Toast from 'primevue/toast'

const props = defineProps({
  batches: Object,
  statistics: Object,
  filters: Object,
})

const toast = useToast()

const loading = ref(false)
const loadingEntries = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)

const tableFilters = ref({})
const availableEntries = ref([])

const filters = ref({
  status: props.filters?.status || null,
  min_entries: props.filters?.min_entries || null,
  max_entries: props.filters?.max_entries || null,
  search: props.filters?.search || null,
})

const createForm = ref({
  name: '',
  description: '',
  journal_entry_ids: [],
})

const createErrors = ref({})

const statusOptions = [
  { label: 'All Statuses', value: null },
  { label: 'Pending', value: 'pending' },
  { label: 'Approved', value: 'approved' },
  { label: 'Posted', value: 'posted' },
  { label: 'Void', value: 'void' },
]

// Computed Properties
const getPeriodLabel = () => {
  return 'All time'
}

// Methods
const getStatusSeverity = (status) => {
  const severityMap = {
    'pending': 'warning',
    'approved': 'info',
    'posted': 'success',
    'void': 'danger',
    'draft': 'secondary',
  }
  return severityMap[status] || 'secondary'
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const truncateText = (text, length) => {
  if (!text) return ''
  return text.length > length ? text.substring(0, length) + '...' : text
}

const applyFilters = () => {
  loading.value = true
  
  const params = new URLSearchParams()
  
  if (filters.value.status) {
    params.append('status', filters.value.status)
  }
  if (filters.value.min_entries) {
    params.append('min_entries', filters.value.min_entries)
  }
  if (filters.value.max_entries) {
    params.append('max_entries', filters.value.max_entries)
  }
  if (filters.value.search) {
    params.append('search', filters.value.search)
  }
  
  router.get(
    route('journal-batches.index'),
    Object.fromEntries(params),
    {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => {
        loading.value = false
      },
    }
  )
}

const clearFilters = () => {
  filters.value = {
    status: null,
    min_entries: null,
    max_entries: null,
    search: null,
  }
  applyFilters()
}

const refreshData = () => {
  router.reload({ only: ['batches', 'statistics'] })
}

const onPageChange = (event) => {
  const params = new URLSearchParams(window.location.search)
  params.set('page', event.page + 1)
  params.set('per_page', event.rows)
  
  router.get(
    route('journal-batches.index'),
    Object.fromEntries(params),
    {
      preserveState: true,
      preserveScroll: false,
    }
  )
}

const loadAvailableEntries = async () => {
  loadingEntries.value = true
  
  try {
    const response = await fetch('/api/ledger/journal-entries?status=draft,status=approved&per_page=100', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    })
    
    const data = await response.json()
    availableEntries.value = data.data || []
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load available entries',
      life: 3000,
    })
  } finally {
    loadingEntries.value = false
  }
}

const createBatch = async () => {
  createErrors.value = {}
  
  // Validate
  if (!createForm.value.name.trim()) {
    createErrors.value.name = 'Batch name is required'
  }
  
  if (createForm.value.journal_entry_ids.length === 0) {
    createErrors.value.journal_entry_ids = 'At least one journal entry must be selected'
  }
  
  if (Object.keys(createErrors.value).length > 0) {
    return
  }
  
  creating.value = true
  
  try {
    const response = await fetch('/api/ledger/journal-batches', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
      body: JSON.stringify(createForm.value),
    })
    
    const data = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Journal batch created successfully',
        life: 3000,
      })
      
      showCreateDialog.value = false
      createForm.value = {
        name: '',
        description: '',
        journal_entry_ids: [],
      }
      
      refreshData()
    } else {
      throw new Error(data.message || 'Failed to create batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  } finally {
    creating.value = false
  }
}

const viewBatch = (event, data) => {
  router.get(route('journal-batches.show', data.id))
}

const editBatch = (event, data) => {
  router.get(route('journal-batches.edit', data.id))
}

const approveBatch = async (event, data) => {
  try {
    const response = await fetch(`/api/ledger/journal-batches/${data.id}/approve`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Batch approved successfully',
        life: 3000,
      })
      
      refreshData()
    } else {
      throw new Error(result.message || 'Failed to approve batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  }
}

const postBatch = async (event, data) => {
  if (!confirm('Are you sure you want to post this batch? This will post all journal entries in the batch.')) {
    return
  }
  
  try {
    const response = await fetch(`/api/ledger/journal-batches/${data.id}/post`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Batch posted successfully',
        life: 3000,
      })
      
      refreshData()
    } else {
      throw new Error(result.message || 'Failed to post batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  }
}

const deleteBatch = async (event, data) => {
  if (!confirm('Are you sure you want to delete this batch? This action cannot be undone.')) {
    return
  }
  
  try {
    const response = await fetch(`/api/ledger/journal-batches/${data.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
    })
    
    const result = await response.json()
    
    if (response.ok) {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Batch deleted successfully',
        life: 3000,
      })
      
      refreshData()
    } else {
      throw new Error(result.message || 'Failed to delete batch')
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message,
      life: 3000,
    })
  }
}

// Lifecycle
onMounted(() => {
  // Initialize any required data
})
</script>

<style scoped>
.journal-batches-page {
  @apply space-y-6;
}

.metric-card {
  @apply transition-all duration-200 hover:shadow-lg;
}

.metric-card:hover {
  @apply transform scale-105;
}
</style>