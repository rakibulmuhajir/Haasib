<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import PrimeCard from 'primevue/card'
import PrimeButton from 'primevue/button'
import PrimeDialog from 'primevue/dialog'
import PrimeCheckbox from 'primevue/checkbox'
import PrimeProgressbar from 'primevue/progressbar'
import PrimeTag from 'primevue/tag'
import PrimeBadge from 'primevue/badge'
import PrimeMessage from 'primevue/message'
import PrimeColumn from 'primevue/column'
import PrimeDataTable from 'primevue/datatable'

interface Props {
  periodId: string
  periodClose?: any
  permissions: {
    can_view_reports: boolean
    can_generate_reports: boolean
    can_download_reports: boolean
    can_manage_reports: boolean
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const emit = defineEmits<{
  'reports-generated': [reports: any]
  'reports-error': [error: string]
}>()

// Local state
const isGenerating = ref(false)
const reportGenerationStatus = ref<any>(null)
const reports = ref<any[]>([])
const availableReportTypes = ref<any>({})
const showGenerateDialog = ref(false)
const selectedReportTypes = ref<string[]>([])
const pollingInterval = ref<NodeJS.Timeout | null>(null)

// Computed properties
const hasReports = computed(() => reports.value.length > 0)
const isProcessing = computed(() => 
  reportGenerationStatus.value?.status === 'processing'
)
const completedReports = computed(() => 
  reports.value.filter(report => report.status === 'completed')
)
const processingReports = computed(() => 
  reports.value.filter(report => report.status === 'processing')
)
const failedReports = computed(() => 
  reports.value.filter(report => report.status === 'failed')
)

const selectedReportTypesCount = computed(() => selectedReportTypes.value.length)

// Methods
async function loadReports() {
  if (!props.periodId) return

  try {
    const response = await fetch(`/api/v1/ledger/periods/${props.periodId}/close/reports`)
    if (!response.ok) throw new Error('Failed to load reports')
    
    const data = await response.json()
    reports.value = data.reports || []
    reportGenerationStatus.value = data.current_report || null
    availableReportTypes.value = data.available_report_types || {}
  } catch (error: any) {
    console.error('Failed to load reports:', error)
    emit('reports-error', 'Failed to load reports: ' + error.message)
  }
}

async function generateReports() {
  if (selectedReportTypes.value.length === 0) return

  isGenerating.value = true
  
  try {
    const response = await fetch(`/api/v1/ledger/periods/${props.periodId}/close/reports`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        report_types: selectedReportTypes.value
      })
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.error || 'Failed to generate reports')
    }

    const data = await response.json()
    reportGenerationStatus.value = data
    
    // Close dialog and start polling for status
    showGenerateDialog.value = false
    startPolling()
    
    emit('reports-generated', data)
    
    // Refresh reports list
    await loadReports()
    
  } catch (error: any) {
    console.error('Failed to generate reports:', error)
    emit('reports-error', error.message || 'Failed to generate reports')
  } finally {
    isGenerating.value = false
  }
}

async function downloadReport(reportType: string, report?: any) {
  try {
    const url = `/api/v1/ledger/periods/${props.periodId}/close/reports/download/${reportType}`
    
    // Create download link
    const link = document.createElement('a')
    link.href = url
    link.download = ''
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    
  } catch (error: any) {
    console.error('Failed to download report:', error)
    emit('reports-error', 'Failed to download report: ' + error.message)
  }
}

async function deleteReport(reportId: string) {
  if (!confirm('Are you sure you want to delete this report?')) return

  try {
    const response = await fetch(`/api/v1/ledger/periods/${props.periodId}/close/reports/${reportId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.error || 'Failed to delete report')
    }

    // Refresh reports list
    await loadReports()
    
  } catch (error: any) {
    console.error('Failed to delete report:', error)
    emit('reports-error', 'Failed to delete report: ' + error.message)
  }
}

function openGenerateDialog() {
  selectedReportTypes.value = []
  showGenerateDialog.value = true
}

function getReportTypeName(reportType: string): string {
  const reportTypeMap: Record<string, string> = {
    'income_statement': 'Income Statement',
    'balance_sheet': 'Balance Sheet',
    'cash_flow': 'Cash Flow Statement',
    'trial_balance': 'Trial Balance',
    'interim_trial_balance': 'Interim Trial Balance',
    'final_statements': 'Final Financial Statements',
    'management_reports': 'Management Reports',
    'tax_reports': 'Tax Reports'
  }
  return reportTypeMap[reportType] || reportType
}

function getReportTypeDescription(reportType: string): string {
  const descriptions: Record<string, string> = {
    'income_statement': 'Revenue, expenses, and net income for the period',
    'balance_sheet': 'Assets, liabilities, and equity at period end',
    'cash_flow': 'Operating, investing, and financing activities',
    'trial_balance': 'Account balances with debits and credits',
    'interim_trial_balance': 'Current trial balance for review periods',
    'final_statements': 'Complete set of audited financial statements',
    'management_reports': 'Detailed operational and analytical reports',
    'tax_reports': 'Tax-specific financial data and schedules'
  }
  return descriptions[reportType] || ''
}

function getStatusSeverity(status: string): 'success' | 'info' | 'warning' | 'danger' | 'secondary' {
  switch (status) {
    case 'completed': return 'success'
    case 'processing': return 'info'
    case 'failed': return 'danger'
    case 'cancelled': return 'warning'
    default: return 'secondary'
  }
}

function getStatusText(status: string): string {
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleString()
}

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function startPolling() {
  // Clear existing interval
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
  }

  // Poll every 5 seconds
  pollingInterval.value = setInterval(async () => {
    if (isProcessing.value) {
      try {
        const response = await fetch(`/api/v1/ledger/periods/${props.periodId}/close/reports/status`)
        if (response.ok) {
          const data = await response.json()
          reportGenerationStatus.value = data
          
          // If processing is complete, stop polling and refresh reports
          if (data.status === 'completed' || data.status === 'failed') {
            stopPolling()
            await loadReports()
          }
        }
      } catch (error) {
        console.error('Failed to poll report status:', error)
      }
    } else {
      stopPolling()
    }
  }, 5000)
}

function stopPolling() {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

// Lifecycle
onMounted(() => {
  loadReports()
})

onUnmounted(() => {
  stopPolling()
})

// Watch for period changes
watch(() => props.periodId, (newPeriodId) => {
  if (newPeriodId) {
    loadReports()
  }
}, { immediate: true })
</script>

<template>
  <div class="space-y-6">
    <!-- Reports Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
      <PrimeCard>
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-blue-600">{{ reports.length }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Reports</div>
          </div>
        </template>
      </PrimeCard>

      <PrimeCard>
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ completedReports.length }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
          </div>
        </template>
      </PrimeCard>

      <PrimeCard>
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-amber-600">{{ processingReports.length }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Processing</div>
          </div>
        </template>
      </PrimeCard>

      <PrimeCard>
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-red-600">{{ failedReports.length }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Failed</div>
          </div>
        </template>
      </PrimeCard>
    </div>

    <!-- Current Generation Status -->
    <PrimeCard v-if="isProcessing">
      <template #header>
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold">Generating Reports</h3>
          <PrimeTag severity="info" value="Processing" />
        </div>
      </template>
      <template #content>
        <div class="space-y-4">
          <div class="flex items-center space-x-3">
            <i class="pi pi-spin pi-spinner text-blue-600"></i>
            <span class="text-gray-700 dark:text-gray-300">
              Generating {{ reportGenerationStatus?.report_types?.join(', ') || 'reports' }}...
            </span>
          </div>
          <PrimeProgressbar mode="indeterminate" />
          <p class="text-sm text-gray-600 dark:text-gray-400">
            This may take a few moments. Please wait while we generate your reports.
          </p>
        </div>
      </template>
    </PrimeCard>

    <!-- Actions -->
    <div class="flex space-x-3">
      <PrimeButton
        v-if="permissions.can_generate_reports"
        label="Generate Reports"
        icon="pi pi-file-plus"
        @click="openGenerateDialog"
        :loading="isGenerating"
        severity="primary"
      />
      <PrimeButton
        label="Refresh"
        icon="pi pi-refresh"
        @click="loadReports"
        :loading="loading"
        severity="secondary"
      />
    </div>

    <!-- Reports List -->
    <PrimeCard v-if="hasReports">
      <template #header>
        <h3 class="text-lg font-semibold">Generated Reports</h3>
      </template>
      <template #content>
        <PrimeDataTable
          :value="reports"
          :paginator="true"
          :rows="10"
          stripedRows
          responsiveLayout="scroll"
        >
          <PrimeColumn field="report_types" header="Reports">
            <template #body="{ data }">
              <div class="space-y-1">
                <PrimeTag
                  v-for="reportType in data.report_types"
                  :key="reportType"
                  :value="getReportTypeName(reportType)"
                  severity="secondary"
                  class="mr-1 mb-1"
                />
              </div>
            </template>
          </PrimeColumn>
          
          <PrimeColumn field="status" header="Status">
            <template #body="{ data }">
              <PrimeTag
                :value="getStatusText(data.status)"
                :severity="getStatusSeverity(data.status)"
              />
            </template>
          </PrimeColumn>
          
          <PrimeColumn field="requested_at" header="Requested">
            <template #body="{ data }">
              <span class="text-sm">{{ formatDate(data.requested_at) }}</span>
            </template>
          </PrimeColumn>
          
          <PrimeColumn field="generated_at" header="Generated">
            <template #body="{ data }">
              <span v-if="data.generated_at" class="text-sm">
                {{ formatDate(data.generated_at) }}
              </span>
              <span v-else class="text-sm text-gray-500">-</span>
            </template>
          </PrimeColumn>
          
          <PrimeColumn header="Actions">
            <template #body="{ data }">
              <div class="flex space-x-2">
                <PrimeButton
                  v-for="reportType in data.report_types"
                  :key="reportType"
                  icon="pi pi-download"
                  size="small"
                  severity="secondary"
                  @click="downloadReport(reportType, data)"
                  :disabled="data.status !== 'completed' || !permissions.can_download_reports"
                  v-tooltip="`Download ${getReportTypeName(reportType)}`"
                />
                <PrimeButton
                  v-if="permissions.can_manage_reports"
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  @click="deleteReport(data.id)"
                  v-tooltip="'Delete Report'"
                />
              </div>
            </template>
          </PrimeColumn>
        </PrimeDataTable>
      </template>
    </PrimeCard>

    <!-- No Reports State -->
    <PrimeCard v-if="!hasReports && !isProcessing">
      <template #content>
        <div class="text-center py-8">
          <i class="pi pi-file text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
          <p class="text-gray-500 dark:text-gray-400 mb-4">
            No reports have been generated for this period close yet.
          </p>
          <PrimeButton
            v-if="permissions.can_generate_reports"
            label="Generate Your First Report"
            icon="pi pi-file-plus"
            @click="openGenerateDialog"
            severity="primary"
          />
        </div>
      </template>
    </PrimeCard>

    <!-- Generate Reports Dialog -->
    <PrimeDialog
      v-model:visible="showGenerateDialog"
      modal
      header="Generate Period Close Reports"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <div class="space-y-4">
        <PrimeMessage
          severity="info"
          :closable="false"
          class="mb-4"
        >
          Select the reports you want to generate for this period close. Reports are generated asynchronously and may take a few moments to complete.
        </PrimeMessage>

        <div class="space-y-3">
          <div
            v-for="(reportInfo, reportType) in availableReportTypes"
            :key="reportType"
            class="flex items-start space-x-3 p-3 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800"
            :class="{
              'opacity-50 cursor-not-allowed': !reportInfo.available,
              'border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800': reportInfo.warning
            }"
          >
            <PrimeCheckbox
              v-model="selectedReportTypes"
              :value="reportType"
              :disabled="!reportInfo.available"
              :inputId="reportType"
            />
            <div class="flex-1">
              <label
                :for="reportType"
                class="block text-sm font-medium text-gray-900 dark:text-white mb-1"
                :class="{ 'text-gray-500': !reportInfo.available }"
              >
                {{ reportInfo.label }}
              </label>
              <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                {{ reportInfo.description }}
              </p>
              <div v-if="reportInfo.warning" class="text-xs text-amber-600 dark:text-amber-400">
                <i class="pi pi-exclamation-triangle mr-1"></i>
                {{ reportInfo.warning }}
              </div>
              <div v-else-if="!reportInfo.available" class="text-xs text-gray-500">
                {{ reportInfo.unavailable_reason || 'Not available for current period status' }}
              </div>
            </div>
          </div>
        </div>

        <div v-if="selectedReportTypesCount > 0" class="text-sm text-gray-600 dark:text-gray-400">
          Selected {{ selectedReportTypesCount }} report{{ selectedReportTypesCount !== 1 ? 's' : '' }}
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between">
          <PrimeButton
            label="Cancel"
            @click="showGenerateDialog = false"
            severity="secondary"
          />
          <PrimeButton
            label="Generate Reports"
            icon="pi pi-file-plus"
            @click="generateReports"
            :loading="isGenerating"
            :disabled="selectedReportTypesCount === 0"
            severity="primary"
          />
        </div>
      </template>
    </PrimeDialog>
  </div>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-tbody > tr > td) {
  padding: 0.75rem 1rem;
}

:deep(.p-tag) {
  font-size: 0.75rem;
}

:deep(.p-checkbox) {
  align-items: flex-start;
  padding-top: 2px;
}
</style>