<template>
  <div class="payment-audit-timeline">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
          Payment Audit Timeline
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Complete audit trail for payment operations and reconciliation
        </p>
      </div>
      
      <div class="flex gap-3">
        <Button
          icon="pi pi-refresh"
          label="Refresh"
          @click="refreshAuditTrail"
          :loading="loading"
          severity="secondary"
        />
        <Button
          icon="pi pi-download"
          label="Export"
          @click="exportAuditData"
          severity="outline"
        />
      </div>
    </div>

    <!-- Filters Section -->
    <Card class="mb-6">
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="flex flex-col gap-2">
            <label for="date-range" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Date Range
            </label>
            <Calendar
              id="date-range"
              v-model="dateRange"
              selectionMode="range"
              :manualInput="false"
              dateFormat="yy-mm-dd"
              placeholder="Select date range"
              class="w-full"
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="action-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Action Type
            </label>
            <Dropdown
              id="action-filter"
              v-model="selectedAction"
              :options="actionTypes"
              optionLabel="label"
              optionValue="value"
              placeholder="All Actions"
              class="w-full"
              showClear
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="actor-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Actor Type
            </label>
            <Dropdown
              id="actor-filter"
              v-model="selectedActorType"
              :options="actorTypes"
              optionLabel="label"
              optionValue="value"
              placeholder="All Actors"
              class="w-full"
              showClear
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="payment-search" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Payment Search
            </label>
            <IconField iconPosition="left">
              <InputIcon class="pi pi-search" />
              <InputText
                id="payment-search"
                v-model="searchQuery"
                placeholder="Search by payment # or customer"
                class="w-full"
              />
            </IconField>
          </div>
        </div>

        <div class="flex justify-end mt-4 gap-2">
          <Button
            label="Clear Filters"
            @click="clearFilters"
            severity="secondary"
            size="small"
          />
          <Button
            label="Apply Filters"
            @click="applyFilters"
            :loading="loading"
            size="small"
          />
        </div>
      </template>
    </Card>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg">
              <i class="pi pi-history text-blue-600 dark:text-blue-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Events</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ auditMetrics.total_audit_events || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-green-100 dark:bg-green-900 rounded-lg">
              <i class="pi pi-check-circle text-green-600 dark:text-green-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Payments Created</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ auditMetrics.payment_created_events || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-lg">
              <i class="pi pi-link text-purple-600 dark:text-purple-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Allocations</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ auditMetrics.payment_allocated_events || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #content>
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-lg">
              <i class="pi pi-bank text-orange-600 dark:text-orange-400"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Bank Reconciliations</p>
              <p class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ auditMetrics.bank_reconciled_events || 0 }}
              </p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Audit Timeline -->
    <Card>
      <template #title>
        <span class="flex items-center gap-2">
          <i class="pi pi-clock"></i>
          Audit Events Timeline
        </span>
      </template>
      
      <template #content>
        <div v-if="loading" class="flex justify-center py-8">
          <ProgressSpinner />
        </div>

        <div v-else-if="auditTrail.length === 0" class="text-center py-8">
          <i class="pi pi-info-circle text-4xl text-gray-400 mb-3"></i>
          <p class="text-gray-500 dark:text-gray-400">
            No audit events found matching the current filters.
          </p>
        </div>

        <div v-else class="space-y-4">
          <!-- Timeline Events -->
          <div
            v-for="event in auditTrail"
            :key="event.id"
            class="relative pl-8 pb-4 border-l-2 border-gray-200 dark:border-gray-700 last:border-l-0"
          >
            <!-- Timeline Marker -->
            <div
              class="absolute left-0 top-2 w-4 h-4 -translate-x-1/2 rounded-full border-2 border-white dark:border-gray-900"
              :class="getEventMarkerClass(event.action)"
            />

            <!-- Event Content -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
              <div class="flex items-start justify-between mb-2">
                <div class="flex items-center gap-3">
                  <i :class="getEventIcon(event.action)" class="text-lg"></i>
                  <div>
                    <h4 class="font-medium text-gray-900 dark:text-white">
                      {{ getEventTitle(event.action) }}
                    </h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ formatDateTime(event.timestamp) }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <Tag
                    :value="event.actor_type"
                    :severity="getActorTypeSeverity(event.actor_type)"
                    size="small"
                  />
                  <Button
                    icon="pi pi-eye"
                    size="small"
                    text
                    rounded
                    @click="showEventDetails(event)"
                  />
                </div>
              </div>

              <!-- Event Summary -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <div v-if="event.payment_details?.payment_number">
                  <span class="text-gray-500 dark:text-gray-400">Payment:</span>
                  <span class="font-medium text-gray-900 dark:text-white ml-1">
                    {{ event.payment_details.payment_number }}
                  </span>
                </div>

                <div v-if="event.payment_details?.entity_name">
                  <span class="text-gray-500 dark:text-gray-400">Entity:</span>
                  <span class="font-medium text-gray-900 dark:text-white ml-1">
                    {{ event.payment_details.entity_name }}
                  </span>
                </div>

                <div v-if="event.payment_details?.amount">
                  <span class="text-gray-500 dark:text-gray-400">Amount:</span>
                  <span class="font-medium text-gray-900 dark:text-white ml-1">
                    ${{ formatCurrency(event.payment_details.amount) }}
                  </span>
                </div>
              </div>

              <!-- Expandable Details -->
              <div v-if="expandedEvents.includes(event.id)" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <h5 class="font-medium text-gray-900 dark:text-white mb-3">Event Details</h5>
                <div class="bg-white dark:bg-gray-900 rounded p-3 space-y-2">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div>
                      <span class="text-gray-500 dark:text-gray-400">Event ID:</span>
                      <span class="font-mono text-xs text-gray-900 dark:text-white ml-1">
                        {{ event.id }}
                      </span>
                    </div>
                    <div>
                      <span class="text-gray-500 dark:text-gray-400">Actor ID:</span>
                      <span class="font-mono text-xs text-gray-900 dark:text-white ml-1">
                        {{ event.actor_id }}
                      </span>
                    </div>
                  </div>

                  <!-- Metadata Display -->
                  <div v-if="event.metadata && Object.keys(event.metadata).length > 0">
                    <h6 class="font-medium text-gray-900 dark:text-white mb-2">Metadata</h6>
                    <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">{{ JSON.stringify(event.metadata, null, 2) }}</pre>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="flex justify-center mt-6">
          <Paginator
            :rows="pagination.per_page"
            :totalRecords="pagination.total"
            :first="(pagination.current_page - 1) * pagination.per_page"
            @page="onPageChange"
          />
        </div>
      </template>
    </Card>

    <!-- Event Details Dialog -->
    <Dialog
      v-model:visible="showDetailsDialog"
      modal
      header="Audit Event Details"
      :style="{ width: '60vw' }"
      :maximizable="true"
    >
      <div v-if="selectedEvent" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Event ID
            </label>
            <p class="font-mono text-sm text-gray-900 dark:text-white">
              {{ selectedEvent.id }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Action
            </label>
            <Tag :value="selectedEvent.action" severity="info" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Timestamp
            </label>
            <p class="text-sm text-gray-900 dark:text-white">
              {{ formatDateTime(selectedEvent.timestamp) }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Actor
            </label>
            <p class="text-sm text-gray-900 dark:text-white">
              {{ selectedEvent.actor_type }} ({{ selectedEvent.actor_id }})
            </p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Metadata
          </label>
          <div class="bg-gray-100 dark:bg-gray-800 rounded p-3">
            <pre class="text-sm overflow-x-auto">{{ JSON.stringify(selectedEvent.metadata, null, 2) }}</pre>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          label="Close"
          @click="showDetailsDialog = false"
          severity="secondary"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { format } from 'date-fns'

// Props
const props = defineProps({
  paymentId: {
    type: String,
    default: null
  }
})

// Composition
const toast = useToast()

// State
const loading = ref(false)
const auditTrail = ref([])
const auditMetrics = ref({})
const expandedEvents = ref([])
const showDetailsDialog = ref(false)
const selectedEvent = ref(null)

// Filters
const dateRange = ref(null)
const selectedAction = ref(null)
const selectedActorType = ref(null)
const searchQuery = ref('')

// Pagination
const pagination = reactive({
  current_page: 1,
  per_page: 20,
  total: 0
})

// Options
const actionTypes = [
  { label: 'Payment Created', value: 'payment_created' },
  { label: 'Payment Allocated', value: 'payment_allocated' },
  { label: 'Payment Reversed', value: 'payment_reversed' },
  { label: 'Allocation Reversed', value: 'allocation_reversed' },
  { label: 'Bank Reconciled', value: 'bank_reconciled' },
  { label: 'Payment Audited', value: 'payment_audited' }
]

const actorTypes = [
  { label: 'User', value: 'user' },
  { label: 'System', value: 'system' },
  { label: 'API', value: 'api' }
]

// Computed
const hasActiveFilters = computed(() => {
  return dateRange.value || 
         selectedAction.value || 
         selectedActorType.value || 
         searchQuery.value
})

// Methods
const loadAuditTrail = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: pagination.current_page,
      limit: pagination.per_page
    })

    if (dateRange.value) {
      params.append('start_date', dateRange.value[0])
      params.append('end_date', dateRange.value[1])
    }

    if (selectedAction.value) {
      params.append('actions', selectedAction.value)
    }

    if (selectedActorType.value) {
      params.append('actor_types', selectedActorType.value)
    }

    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }

    const endpoint = props.paymentId 
      ? `/api/accounting/payments/audit/${props.paymentId}?${params}`
      : `/api/accounting/payments/audit?${params}`

    const response = await fetch(endpoint, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load audit trail')
    }

    const data = await response.json()

    if (props.paymentId) {
      auditTrail.value = data.audit_trail
    } else {
      auditTrail.value = data.audit_trail
      pagination.total = data.pagination.total
      pagination.current_page = data.pagination.current_page
      pagination.per_page = data.pagination.per_page
    }
  } catch (error) {
    console.error('Error loading audit trail:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load audit trail data',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const loadAuditMetrics = async () => {
  try {
    const params = new URLSearchParams({
      start_date: dateRange.value?.[0] || getDefaultStartDate(),
      end_date: dateRange.value?.[1] || getDefaultEndDate()
    })

    const response = await fetch(`/api/accounting/payments/audit/metrics?${params}`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load audit metrics')
    }

    const data = await response.json()
    auditMetrics.value = data.metrics
  } catch (error) {
    console.error('Error loading audit metrics:', error)
  }
}

const refreshAuditTrail = async () => {
  await Promise.all([
    loadAuditTrail(),
    loadAuditMetrics()
  ])
}

const applyFilters = async () => {
  pagination.current_page = 1
  await refreshAuditTrail()
}

const clearFilters = () => {
  dateRange.value = null
  selectedAction.value = null
  selectedActorType.value = null
  searchQuery.value = ''
  pagination.current_page = 1
  refreshAuditTrail()
}

const onPageChange = (event) => {
  pagination.current_page = event.page + 1
  pagination.per_page = event.rows
  loadAuditTrail()
}

const showEventDetails = (event) => {
  selectedEvent.value = event
  showDetailsDialog.value = true
}

const exportAuditData = () => {
  // Implementation for exporting audit data
  const dataStr = JSON.stringify(auditTrail.value, null, 2)
  const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr)
  
  const exportFileDefaultName = `audit-trail-${format(new Date(), 'yyyy-MM-dd')}.json`
  
  const linkElement = document.createElement('a')
  linkElement.setAttribute('href', dataUri)
  linkElement.setAttribute('download', exportFileDefaultName)
  linkElement.click()
}

// Helper Methods
const getEventMarkerClass = (action) => {
  const classes = {
    'payment_created': 'bg-green-500',
    'payment_allocated': 'bg-blue-500',
    'payment_reversed': 'bg-red-500',
    'allocation_reversed': 'bg-orange-500',
    'bank_reconciled': 'bg-purple-500',
    'payment_audited': 'bg-gray-500'
  }
  return classes[action] || 'bg-gray-500'
}

const getEventIcon = (action) => {
  const icons = {
    'payment_created': 'pi pi-plus-circle text-green-600 dark:text-green-400',
    'payment_allocated': 'pi pi-link text-blue-600 dark:text-blue-400',
    'payment_reversed': 'pi pi-replay text-red-600 dark:text-red-400',
    'allocation_reversed': 'pi pi-undo text-orange-600 dark:text-orange-400',
    'bank_reconciled': 'pi pi-bank text-purple-600 dark:text-purple-400',
    'payment_audited': 'pi pi-eye text-gray-600 dark:text-gray-400'
  }
  return icons[action] || 'pi pi-info-circle text-gray-600 dark:text-gray-400'
}

const getEventTitle = (action) => {
  const titles = {
    'payment_created': 'Payment Created',
    'payment_allocated': 'Payment Allocated',
    'payment_reversed': 'Payment Reversed',
    'allocation_reversed': 'Allocation Reversed',
    'bank_reconciled': 'Bank Reconciled',
    'payment_audited': 'Payment Audited'
  }
  return titles[action] || action
}

const getActorTypeSeverity = (actorType) => {
  const severities = {
    'user': 'info',
    'system': 'warning',
    'api': 'success'
  }
  return severities[actorType] || 'secondary'
}

const formatDateTime = (timestamp) => {
  try {
    return format(new Date(timestamp), 'MMM dd, yyyy HH:mm:ss')
  } catch {
    return timestamp
  }
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

const getDefaultStartDate = () => {
  return format(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000), 'yyyy-MM-dd')
}

const getDefaultEndDate = () => {
  return format(new Date(), 'yyyy-MM-dd')
}

// Lifecycle
onMounted(() => {
  refreshAuditTrail()
})
</script>

<style scoped>
.payment-audit-timeline {
  @apply space-y-6;
}

/* Timeline styles */
.timeline-marker {
  @apply absolute left-0 top-2 w-4 h-4 -translate-x-1/2 rounded-full border-2 border-white dark:border-gray-900;
}

/* Custom scrollbar for metadata */
.overflow-x-auto {
  scrollbar-width: thin;
  scrollbar-color: rgb(156 163 175) transparent;
}

.overflow-x-auto::-webkit-scrollbar {
  height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background-color: rgb(156 163 175);
  border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background-color: rgb(107 114 128);
}
</style>