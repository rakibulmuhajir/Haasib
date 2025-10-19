<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-12">
      <i class="pi pi-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
      <p class="text-gray-600 dark:text-gray-400">{{ error }}</p>
      <Button
        label="Retry"
        icon="pi pi-refresh"
        @click="loadDeliveryLogs"
        class="mt-4"
      />
    </div>

    <!-- Delivery Logs Content -->
    <div v-else class="space-y-6">
      <!-- Summary Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Deliveries</p>
              <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                {{ summary.total || 0 }}
              </p>
            </div>
            <i class="pi pi-send text-blue-600 dark:text-blue-400 text-xl"></i>
          </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-green-600 dark:text-green-400">Successful</p>
              <p class="text-2xl font-bold text-green-900 dark:text-green-100">
                {{ summary.sent || 0 }}
              </p>
            </div>
            <i class="pi pi-check text-green-600 dark:text-green-400 text-xl"></i>
          </div>
        </div>

        <div class="bg-red-50 dark:bg-red-900 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-red-600 dark:text-red-400">Failed</p>
              <p class="text-2xl font-bold text-red-900 dark:text-red-100">
                {{ summary.failed || 0 }}
              </p>
            </div>
            <i class="pi pi-times text-red-600 dark:text-red-400 text-xl"></i>
          </div>
        </div>

        <div class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Pending</p>
              <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">
                {{ summary.pending || 0 }}
              </p>
            </div>
            <i class="pi pi-clock text-yellow-600 dark:text-yellow-400 text-xl"></i>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <Card>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                @change="filterLogs"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Channel
              </label>
              <Dropdown
                v-model="filters.channel"
                :options="channelOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All Channels"
                class="w-full"
                showClear
                @change="filterLogs"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Date Range
              </label>
              <Calendar
                v-model="filters.dateRange"
                selectionMode="range"
                :manualInput="false"
                showIcon
                placeholder="Select date range"
                class="w-full"
                @change="filterLogs"
              />
            </div>
            
            <div class="flex items-end">
              <Button
                icon="pi pi-refresh"
                label="Refresh"
                severity="secondary"
                @click="loadDeliveryLogs"
                :loading="loading"
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Delivery Logs Table -->
      <Card>
        <template #content>
          <div v-if="filteredLogs.length === 0" class="text-center py-12">
            <i class="pi pi-history text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400">
              {{ hasActiveFilters ? 'No delivery logs found matching your filters.' : 'No delivery logs found.' }}
            </p>
          </div>

          <DataTable
            v-else
            :value="filteredLogs"
            :paginator="true"
            :rows="10"
            :stripedRows="true"
            :showGridlines="false"
            class="p-datatable-sm"
            responsiveLayout="scroll"
            :expandedRowIcon="true"
            v-model:expandedRows="expandedRows"
          >
            <!-- Expand/Collapse -->
            <Column :expander="true" headerStyle="width: 3rem" />
            
            <!-- Report Info -->
            <Column field="report_name" header="Report" :sortable="true">
              <template #body="{ data }">
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">{{ data.report_name }}</p>
                  <p class="text-sm text-gray-500 dark:text-gray-400">{{ data.report_type }}</p>
                </div>
              </template>
            </Column>

            <!-- Channel -->
            <Column field="channel" header="Channel" :sortable="true">
              <template #body="{ data }">
                <Badge
                  :value="formatChannel(data.channel)"
                  :severity="getChannelSeverity(data.channel)"
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

            <!-- Sent At -->
            <Column field="sent_at" header="Sent At" :sortable="true">
              <template #body="{ data }">
                <div v-if="data.sent_at">
                  <p class="text-sm">{{ formatDateTime(data.sent_at) }}</p>
                  <p class="text-xs text-gray-500">{{ getRelativeTime(data.sent_at) }}</p>
                </div>
                <span v-else class="text-gray-500">Not sent</span>
              </template>
            </Column>

            <!-- Actions -->
            <Column header="Actions" :exportable="false">
              <template #body="{ data }">
                <div class="flex items-center space-x-2">
                  <Button
                    icon="pi pi-eye"
                    size="small"
                    severity="secondary"
                    @click="viewDetails(data)"
                    v-tooltip="'View Details'"
                  />
                  
                  <Button
                    v-if="data.status === 'failed'"
                    icon="pi pi-refresh"
                    size="small"
                    severity="warning"
                    @click="retryDelivery(data)"
                    v-tooltip="'Retry Delivery'"
                  />
                </div>
              </template>
            </Column>

            <!-- Row Expansion -->
            <template #expansion="{ data }">
              <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Delivery Details</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <!-- Target Information -->
                  <div>
                    <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Target</h5>
                    <div class="bg-white dark:bg-gray-900 rounded p-3">
                      <pre class="text-sm whitespace-pre-wrap">{{ formatTarget(data.target) }}</pre>
                    </div>
                  </div>
                  
                  <!-- Status Information -->
                  <div>
                    <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Status Information</h5>
                    <div class="space-y-2">
                      <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Created:</span>
                        <span class="text-sm">{{ formatDateTime(data.created_at) }}</span>
                      </div>
                      <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Attempted:</span>
                        <span class="text-sm">{{ data.attempt_count || 1 }} times</span>
                      </div>
                      <div v-if="data.last_attempt_at" class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Last Attempt:</span>
                        <span class="text-sm">{{ formatDateTime(data.last_attempt_at) }}</span>
                      </div>
                      <div v-if="data.next_retry_at" class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Next Retry:</span>
                        <span class="text-sm">{{ formatDateTime(data.next_retry_at) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Error Information -->
                <div v-if="data.status === 'failed' && data.failure_reason" class="mt-4">
                  <h5 class="text-sm font-medium text-red-600 dark:text-red-400 mb-2">Error Details</h5>
                  <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded p-3">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ data.failure_reason }}</p>
                  </div>
                </div>
              </div>
            </template>
          </DataTable>
        </template>
      </Card>
    </div>

    <!-- Details Dialog -->
    <Dialog
      v-model:visible="showDetailsDialog"
      header="Delivery Details"
      :modal="true"
      :style="{ width: '80vw', maxWidth: '900px' }"
      :breakpoints="{ '960px': '100vw' }"
    >
      <DeliveryDetails
        v-if="selectedDelivery"
        :delivery="selectedDelivery"
        @close="showDetailsDialog = false"
      />
    </Dialog>

    <!-- Actions -->
    <div class="flex justify-end space-x-3">
      <Button
        label="Close"
        severity="secondary"
        @click="$emit('close')"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DeliveryDetails from './DeliveryDetails.vue'

defineProps({
  schedule: {
    type: Object,
    required: true
  }
})

defineEmits(['close'])

const toast = useToast()

// State
const loading = ref(false)
const error = ref(null)
const deliveryLogs = ref([])
const expandedRows = ref([])
const summary = ref({})
const selectedDelivery = ref(null)
const showDetailsDialog = ref(false)

// Filters
const filters = ref({
  status: null,
  channel: null,
  dateRange: null
})

// Options
const statusOptions = ref([
  { label: 'Sent', value: 'sent' },
  { label: 'Failed', value: 'failed' },
  { label: 'Pending', value: 'pending' }
])

const channelOptions = ref([
  { label: 'Email', value: 'email' },
  { label: 'SFTP', value: 'sftp' },
  { label: 'Webhook', value: 'webhook' },
  { label: 'In-App', value: 'in_app' }
])

// Computed
const filteredLogs = computed(() => {
  let filtered = deliveryLogs.value

  if (filters.value.status) {
    filtered = filtered.filter(log => log.status === filters.value.status)
  }

  if (filters.value.channel) {
    filtered = filtered.filter(log => log.channel === filters.value.channel)
  }

  if (filters.value.dateRange && filters.value.dateRange.length === 2) {
    const [startDate, endDate] = filters.value.dateRange
    filtered = filtered.filter(log => {
      const logDate = new Date(log.created_at)
      return logDate >= startDate && logDate <= endDate
    })
  }

  return filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

const hasActiveFilters = computed(() => {
  return filters.value.status ||
         filters.value.channel ||
         (filters.value.dateRange && filters.value.dateRange.length === 2)
})

// Methods
const loadDeliveryLogs = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await fetch(`/api/reporting/schedules/${props.schedule.schedule_id}/deliveries`)
    if (!response.ok) throw new Error('Failed to load delivery logs')
    
    const data = await response.json()
    deliveryLogs.value = data.deliveries || []
    summary.value = data.summary || {}
  } catch (err) {
    error.value = err.message
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load delivery logs',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const filterLogs = () => {
  // Filters are reactive through computed property
}

const viewDetails = (delivery) => {
  selectedDelivery.value = delivery
  showDetailsDialog.value = true
}

const retryDelivery = async (delivery) => {
  try {
    const response = await fetch(`/api/reporting/deliveries/${delivery.delivery_id}/retry`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })

    if (!response.ok) throw new Error('Failed to retry delivery')

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Delivery retry initiated',
      life: 3000
    })

    await loadDeliveryLogs()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: err.message || 'Failed to retry delivery',
      life: 3000
    })
  }
}

// Utility Functions
const formatStatus = (status) => {
  const statusMap = {
    sent: 'Sent',
    failed: 'Failed',
    pending: 'Pending'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status) => {
  switch (status) {
    case 'sent': return 'success'
    case 'failed': return 'danger'
    case 'pending': return 'warning'
    default: return 'secondary'
  }
}

const formatChannel = (channel) => {
  const channelMap = {
    email: 'Email',
    sftp: 'SFTP',
    webhook: 'Webhook',
    in_app: 'In-App'
  }
  return channelMap[channel] || channel
}

const getChannelSeverity = (channel) => {
  switch (channel) {
    case 'email': return 'info'
    case 'sftp': return 'warning'
    case 'webhook': return 'success'
    case 'in_app': return 'secondary'
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

const formatTarget = (target) => {
  if (!target) return 'No target configured'
  
  try {
    if (typeof target === 'string') {
      return JSON.stringify(JSON.parse(target), null, 2)
    }
    return JSON.stringify(target, null, 2)
  } catch {
    return target
  }
}

// Lifecycle
onMounted(() => {
  loadDeliveryLogs()
})
</script>