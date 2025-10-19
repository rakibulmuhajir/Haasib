<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Report Schedules
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage automated report generation and delivery schedules
          </p>
        </div>
        
        <div class="flex items-center space-x-3">
          <!-- Statistics Button -->
          <Button
            icon="pi pi-chart-bar"
            label="Statistics"
            severity="secondary"
            @click="showStatisticsDialog = true"
          />
          
          <!-- Create Button -->
          <Button
            icon="pi pi-plus"
            label="Create Schedule"
            @click="showCreateDialog = true"
          />
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Schedules</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ statistics.total_schedules || 0 }}
                </p>
              </div>
              <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900">
                <i class="pi pi-calendar text-blue-600 dark:text-blue-400"></i>
              </div>
            </div>
          </template>
        </Card>

        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Active</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                  {{ statistics.active_schedules || 0 }}
                </p>
              </div>
              <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900">
                <i class="pi pi-play text-green-600 dark:text-green-400"></i>
              </div>
            </div>
          </template>
        </Card>

        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Paused</p>
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                  {{ statistics.paused_schedules || 0 }}
                </p>
              </div>
              <div class="flex items-center justify-center w-12 h-12 rounded-full bg-yellow-100 dark:bg-yellow-900">
                <i class="pi pi-pause text-yellow-600 dark:text-yellow-400"></i>
              </div>
            </div>
          </template>
        </Card>

        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Running Today</p>
                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                  {{ statistics.running_today || 0 }}
                </p>
              </div>
              <div class="flex items-center justify-center w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900">
                <i class="pi pi-clock text-purple-600 dark:text-purple-400"></i>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Filters -->
      <Card>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Search
              </label>
              <InputText
                v-model="filters.search"
                placeholder="Search schedules..."
                class="w-full"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Status
              </label>
              <Dropdown
                v-model="filters.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All Status"
                class="w-full"
                showClear
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Frequency
              </label>
              <Dropdown
                v-model="filters.frequency"
                :options="frequencyOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All Frequencies"
                class="w-full"
                showClear
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Template
              </label>
              <Dropdown
                v-model="filters.templateId"
                :options="templateOptions"
                optionLabel="name"
                optionValue="template_id"
                placeholder="All Templates"
                class="w-full"
                showClear
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Schedules Table -->
      <Card>
        <template #content>
          <div v-if="loading" class="flex justify-center py-12">
            <ProgressSpinner />
          </div>
          
          <div v-else-if="error" class="text-center py-12">
            <i class="pi pi-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400">{{ error }}</p>
            <Button
              label="Retry"
              icon="pi pi-refresh"
              @click="loadSchedules"
              class="mt-4"
            />
          </div>
          
          <div v-else-if="filteredSchedules.length === 0" class="text-center py-12">
            <i class="pi pi-calendar text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400">
              {{ hasActiveFilters ? 'No schedules found matching your filters.' : 'No schedules created yet.' }}
            </p>
            <Button
              label="Create First Schedule"
              icon="pi pi-plus"
              @click="showCreateDialog = true"
              class="mt-4"
              v-if="!hasActiveFilters"
            />
          </div>

          <DataTable
            v-else
            :value="filteredSchedules"
            :paginator="true"
            :rows="10"
            :stripedRows="true"
            :showGridlines="false"
            class="p-datatable-sm"
            responsiveLayout="scroll"
          >
            <!-- Name -->
            <Column field="name" header="Name" :sortable="true">
              <template #body="{ data }">
                <div class="flex items-center space-x-3">
                  <div class="flex items-center justify-center w-8 h-8 rounded-full"
                       :class="getStatusColor(data.status)">
                    <i :class="getStatusIcon(data.status)" class="text-white text-sm"></i>
                  </div>
                  <div>
                    <p class="font-medium text-gray-900 dark:text-white">{{ data.name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ data.template_name }}</p>
                  </div>
                </div>
              </template>
            </Column>

            <!-- Frequency -->
            <Column field="frequency" header="Frequency" :sortable="true">
              <template #body="{ data }">
                <Badge
                  :value="formatFrequency(data.frequency)"
                  :severity="getFrequencySeverity(data.frequency)"
                  size="small"
                />
              </template>
            </Column>

            <!-- Status -->
            <Column field="status" header="Status" :sortable="true">
              <template #body="{ data }">
                <Badge
                  :value="formatStatus(data.status)"
                  :severity="getStatusSeverity(data.status)"
                />
              </template>
            </Column>

            <!-- Next Run -->
            <Column field="next_run_at" header="Next Run" :sortable="true">
              <template #body="{ data }">
                <div v-if="data.status === 'active'">
                  <p class="text-sm font-medium">{{ formatDateTime(data.next_run_at) }}</p>
                  <p class="text-xs text-gray-500">{{ getTimeUntil(data.next_run_at) }}</p>
                </div>
                <span v-else class="text-gray-500">Not scheduled</span>
              </template>
            </Column>

            <!-- Last Run -->
            <Column field="last_run_at" header="Last Run" :sortable="true">
              <template #body="{ data }">
                <div v-if="data.last_run_at">
                  <p class="text-sm">{{ formatDateTime(data.last_run_at) }}</p>
                  <p class="text-xs text-gray-500">{{ getRelativeTime(data.last_run_at) }}</p>
                </div>
                <span v-else class="text-gray-500">Never run</span>
              </template>
            </Column>

            <!-- Actions -->
            <Column header="Actions" :exportable="false">
              <template #body="{ data }">
                <div class="flex items-center space-x-2">
                  <Button
                    :icon="data.status === 'active' ? 'pi pi-pause' : 'pi pi-play'"
                    size="small"
                    :severity="data.status === 'active' ? 'secondary' : 'success'"
                    @click="toggleScheduleStatus(data)"
                    v-tooltip="data.status === 'active' ? 'Pause' : 'Resume'"
                  />
                  
                  <Button
                    icon="pi pi-refresh"
                    size="small"
                    severity="secondary"
                    @click="triggerSchedule(data)"
                    v-tooltip="'Run Now'"
                    :disabled="data.status === 'running'"
                  />
                  
                  <Button
                    icon="pi pi-history"
                    size="small"
                    severity="info"
                    @click="viewDeliveryLogs(data)"
                    v-tooltip="'Delivery Logs'"
                  />
                  
                  <Button
                    icon="pi pi-pencil"
                    size="small"
                    @click="editSchedule(data)"
                    v-tooltip="'Edit'"
                  />
                  
                  <Button
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    @click="confirmDelete(data)"
                    v-tooltip="'Delete'"
                  />
                </div>
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>

      <!-- Create/Edit Dialog -->
      <Dialog
        v-model:visible="showCreateDialog"
        :header="editingSchedule ? 'Edit Schedule' : 'Create Schedule'"
        :modal="true"
        :style="{ width: '90vw', maxWidth: '1000px' }"
        :breakpoints="{ '960px': '100vw' }"
      >
        <ScheduleForm
          :schedule="editingSchedule"
          :templates="templates"
          :loading="saving"
          @save="handleSave"
          @cancel="handleCancel"
        />
      </Dialog>

      <!-- Statistics Dialog -->
      <Dialog
        v-model:visible="showStatisticsDialog"
        header="Schedule Statistics"
        :modal="true"
        :style="{ width: '80vw', maxWidth: '900px' }"
        :breakpoints="{ '960px': '100vw' }"
      >
        <ScheduleStatistics
          :statistics="statistics"
          @close="showStatisticsDialog = false"
        />
      </Dialog>

      <!-- Delivery Logs Dialog -->
      <Dialog
        v-model:visible="showDeliveryLogsDialog"
        :header="`Delivery Logs: ${selectedSchedule?.name}`"
        :modal="true"
        :style="{ width: '95vw', maxWidth: '1200px' }"
        :breakpoints="{ '960px': '100vw' }"
      >
        <DeliveryLogs
          :schedule="selectedSchedule"
          @close="showDeliveryLogsDialog = false"
        />
      </Dialog>

      <!-- Delete Confirmation -->
      <ConfirmDialog />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import AppLayout from '@/Layouts/AuthenticatedLayout.vue'
import ScheduleForm from '@/Components/Reporting/Schedules/ScheduleForm.vue'
import ScheduleStatistics from '@/Components/Reporting/Schedules/ScheduleStatistics.vue'
import DeliveryLogs from '@/Components/Reporting/Schedules/DeliveryLogs.vue'

const toast = useToast()
const confirm = useConfirm()

// State
const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const schedules = ref([])
const templates = ref([])
const statistics = ref({})

// Dialogs
const showCreateDialog = ref(false)
const showStatisticsDialog = ref(false)
const showDeliveryLogsDialog = ref(false)
const editingSchedule = ref(null)
const selectedSchedule = ref(null)

// Filters
const filters = ref({
  search: '',
  status: null,
  frequency: null,
  templateId: null
})

// Options
const statusOptions = ref([
  { label: 'Active', value: 'active' },
  { label: 'Paused', value: 'paused' },
  { label: 'Running', value: 'running' }
])

const frequencyOptions = ref([
  { label: 'Daily', value: 'daily' },
  { label: 'Weekly', value: 'weekly' },
  { label: 'Monthly', value: 'monthly' },
  { label: 'Quarterly', value: 'quarterly' },
  { label: 'Yearly', value: 'yearly' },
  { label: 'Custom', value: 'custom' }
])

// Computed
const templateOptions = computed(() => templates.value)

const filteredSchedules = computed(() => {
  let filtered = schedules.value

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    filtered = filtered.filter(schedule =>
      schedule.name.toLowerCase().includes(search) ||
      schedule.template_name?.toLowerCase().includes(search)
    )
  }

  if (filters.value.status) {
    filtered = filtered.filter(schedule => schedule.status === filters.value.status)
  }

  if (filters.value.frequency) {
    filtered = filtered.filter(schedule => schedule.frequency === filters.value.frequency)
  }

  if (filters.value.templateId) {
    filtered = filtered.filter(schedule => schedule.template_id === filters.value.templateId)
  }

  return filtered
})

const hasActiveFilters = computed(() => {
  return filters.value.search ||
         filters.value.status ||
         filters.value.frequency ||
         filters.value.templateId
})

// Methods
const loadSchedules = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await fetch('/api/reporting/schedules')
    if (!response.ok) throw new Error('Failed to load schedules')
    
    const data = await response.json()
    schedules.value = data.data || data
  } catch (err) {
    error.value = err.message
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load schedules',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const loadTemplates = async () => {
  try {
    const response = await fetch('/api/reporting/templates')
    if (!response.ok) throw new Error('Failed to load templates')
    
    const data = await response.json()
    templates.value = data.data || data
  } catch (err) {
    console.error('Failed to load templates:', err)
  }
}

const loadStatistics = async () => {
  try {
    const response = await fetch('/api/reporting/schedules/statistics')
    if (!response.ok) throw new Error('Failed to load statistics')
    
    const data = await response.json()
    statistics.value = data
  } catch (err) {
    console.error('Failed to load statistics:', err)
  }
}

const refreshData = async () => {
  await Promise.all([
    loadSchedules(),
    loadStatistics()
  ])
}

const editSchedule = (schedule) => {
  editingSchedule.value = { ...schedule }
  showCreateDialog.value = true
}

const toggleScheduleStatus = async (schedule) => {
  try {
    const action = schedule.status === 'active' ? 'pause' : 'resume'
    const response = await fetch(`/api/reporting/schedules/${schedule.schedule_id}/${action}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })

    if (!response.ok) throw new Error(`Failed to ${action} schedule`)

    const statusText = action === 'pause' ? 'paused' : 'resumed'
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: `Schedule ${statusText} successfully`,
      life: 3000
    })

    await refreshData()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: err.message || 'Failed to update schedule status',
      life: 3000
    })
  }
}

const triggerSchedule = async (schedule) => {
  try {
    const response = await fetch(`/api/reporting/schedules/${schedule.schedule_id}/trigger`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })

    if (!response.ok) throw new Error('Failed to trigger schedule')

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Schedule triggered successfully',
      life: 3000
    })

    // Show a message that it might take a moment
    setTimeout(() => {
      toast.add({
        severity: 'info',
        summary: 'Info',
        detail: 'Schedule is running. This may take a few moments...',
        life: 5000
      })
    }, 1000)

    await refreshData()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: err.message || 'Failed to trigger schedule',
      life: 3000
    })
  }
}

const viewDeliveryLogs = (schedule) => {
  selectedSchedule.value = schedule
  showDeliveryLogsDialog.value = true
}

const confirmDelete = (schedule) => {
  confirm.require({
    message: `Are you sure you want to delete "${schedule.name}"?`,
    header: 'Delete Schedule',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-secondary p-button-outlined',
    rejectLabel: 'Cancel',
    acceptLabel: 'Delete',
    acceptClass: 'p-button-danger',
    accept: () => deleteSchedule(schedule)
  })
}

const deleteSchedule = async (schedule) => {
  try {
    const response = await fetch(`/api/reporting/schedules/${schedule.schedule_id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })

    if (!response.ok) throw new Error('Failed to delete schedule')

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Schedule deleted successfully',
      life: 3000
    })

    await refreshData()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: err.message || 'Failed to delete schedule',
      life: 3000
    })
  }
}

const handleSave = async (scheduleData) => {
  saving.value = true

  try {
    const url = editingSchedule.value 
      ? `/api/reporting/schedules/${editingSchedule.value.schedule_id}`
      : '/api/reporting/schedules'
    
    const method = editingSchedule.value ? 'PUT' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(scheduleData)
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.message || 'Failed to save schedule')
    }

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: `Schedule ${editingSchedule.value ? 'updated' : 'created'} successfully`,
      life: 3000
    })

    showCreateDialog.value = false
    editingSchedule.value = null
    await refreshData()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: err.message || 'Failed to save schedule',
      life: 3000
    })
  } finally {
    saving.value = false
  }
}

const handleCancel = () => {
  showCreateDialog.value = false
  editingSchedule.value = null
}

// Utility Functions
const formatStatus = (status) => {
  const statusMap = {
    active: 'Active',
    paused: 'Paused',
    running: 'Running',
    completed: 'Completed',
    failed: 'Failed'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status) => {
  switch (status) {
    case 'active': return 'success'
    case 'paused': return 'warning'
    case 'running': return 'info'
    case 'failed': return 'danger'
    default: return 'secondary'
  }
}

const getStatusIcon = (status) => {
  switch (status) {
    case 'active': return 'pi pi-play'
    case 'paused': return 'pi pi-pause'
    case 'running': return 'pi pi-spin pi-spinner'
    case 'failed': return 'pi pi-times'
    default: return 'pi pi-question'
  }
}

const getStatusColor = (status) => {
  switch (status) {
    case 'active': return 'bg-green-500'
    case 'paused': return 'bg-yellow-500'
    case 'running': return 'bg-blue-500'
    case 'failed': return 'bg-red-500'
    default: return 'bg-gray-500'
  }
}

const formatFrequency = (frequency) => {
  const frequencyMap = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
    custom: 'Custom'
  }
  return frequencyMap[frequency] || frequency
}

const getFrequencySeverity = (frequency) => {
  switch (frequency) {
    case 'daily': return 'success'
    case 'weekly': return 'info'
    case 'monthly': return 'warning'
    case 'quarterly': return 'danger'
    case 'yearly': return 'secondary'
    default: return 'info'
  }
}

const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString()
}

const getRelativeTime = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  const now = new Date()
  const diff = now - date
  
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return date.toLocaleDateString()
}

const getTimeUntil = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  const now = new Date()
  const diff = date - now
  
  if (diff < 0) return 'Overdue'
  
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 60) return `In ${minutes}m`
  if (hours < 24) return `In ${hours}h`
  if (days < 7) return `In ${days}d`
  return `In ${Math.floor(days / 7)}w`
}

// Lifecycle
onMounted(() => {
  refreshData()
  loadTemplates()
})
</script>