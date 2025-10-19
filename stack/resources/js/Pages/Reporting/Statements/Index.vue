<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Financial Statements
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Generate income statement, balance sheet, cash flow, and trial balance reports
          </p>
        </div>
        
        <div class="flex items-center space-x-3">
          <!-- Generate Report Button -->
          <Button
            :loading="isGenerating"
            icon="pi pi-file-pdf"
            label="Generate Report"
            @click="showGenerateDialog = true"
          />
          
          <!-- Settings Button -->
          <Button
            icon="pi pi-cog"
            severity="secondary"
            text
            @click="showSettings = true"
          />
        </div>
      </div>

      <!-- Alert for Errors -->
      <Message
        v-if="error"
        severity="error"
        :closable="true"
        @close="error = null"
      >
        {{ error }}
      </Message>

      <!-- Report Generation Form -->
      <Card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
        <template #title>
          Report Parameters
        </template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Report Type -->
            <div>
              <label for="report-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Report Type
              </label>
              <Dropdown
                id="report-type"
                v-model="selectedReportType"
                :options="reportTypes"
                optionLabel="name"
                optionValue="type"
                placeholder="Select report type"
                class="w-full"
                @change="onReportTypeChange"
              />
            </div>

            <!-- Date Range -->
            <div>
              <label for="date-range" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Date Range
              </label>
              <Calendar
                id="date-range"
                v-model="dateRange"
                selectionMode="range"
                :manualInput="false"
                showIcon
                placeholder="Select date range"
                class="w-full"
                @change="onDateRangeChange"
              />
            </div>

            <!-- Comparison Period -->
            <div>
              <label for="comparison" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Comparison
              </label>
              <Dropdown
                id="comparison"
                v-model="selectedComparison"
                :options="comparisonOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Select comparison"
                class="w-full"
                @change="onComparisonChange"
              />
            </div>

            <!-- Currency -->
            <div>
              <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Currency
              </label>
              <Dropdown
                id="currency"
                v-model="selectedCurrency"
                :options="currencyOptions"
                optionLabel="name"
                optionValue="code"
                placeholder="Select currency"
                class="w-full"
                @change="onCurrencyChange"
              />
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end mt-6 space-x-3">
            <Button
              label="Preview"
              icon="pi pi-eye"
              severity="secondary"
              @click="handlePreview"
              :disabled="!canGenerate"
            />
            <Button
              label="Generate"
              icon="pi pi-cog"
              @click="handleGenerate"
              :loading="isGenerating"
              :disabled="!canGenerate"
            />
          </div>
        </template>
      </Card>

      <!-- Budget KPIs Section -->
      <div v-if="budgetKpisData && selectedReportType === 'income_statement'" class="space-y-6">
        <Card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <template #title>
            Budget Variance Analysis
          </template>
          <template #content>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <!-- Budget Variance Card -->
              <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                <div class="text-center">
                  <div class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    {{ formatCurrency(budgetKpisData.budget_variance?.total_variance || 0, selectedCurrency) }}
                  </div>
                  <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Budget Variance</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ budgetKpisData.budget_variance?.variance_percentage?.toFixed(1) || '0.0' }}% variance
                  </div>
                </div>
              </div>

              <!-- Revenue Variance -->
              <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                <div class="text-center">
                  <div class="text-lg font-semibold text-green-700 dark:text-green-400 mb-2">
                    {{ formatCurrency(budgetKpisData.budget_variance?.revenue_variance || 0, selectedCurrency) }}
                  </div>
                  <div class="text-sm font-medium text-green-600 dark:text-green-300">Revenue Variance</div>
                  <div class="text-xs text-green-500 dark:text-green-400 mt-1">
                    {{ budgetKpisData.budget_variance?.revenue_variance_percentage?.toFixed(1) || '0.0' }}%
                  </div>
                </div>
              </div>

              <!-- Expense Variance -->
              <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
                <div class="text-center">
                  <div class="text-lg font-semibold text-red-700 dark:text-red-400 mb-2">
                    {{ formatCurrency(budgetKpisData.budget_variance?.expense_variance || 0, selectedCurrency) }}
                  </div>
                  <div class="text-sm font-medium text-red-600 dark:text-red-300">Expense Variance</div>
                  <div class="text-xs text-red-500 dark:text-red-400 mt-1">
                    {{ budgetKpisData.budget_variance?.expense_variance_percentage?.toFixed(1) || '0.0' }}%
                  </div>
                </div>
              </div>

              <!-- Budget Adherence -->
              <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                <div class="text-center">
                  <div class="text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2">
                    {{ budgetKpisData.budget_variance?.budget_adherence?.toFixed(1) || '0.0' }}%
                  </div>
                  <div class="text-sm font-medium text-blue-600 dark:text-blue-300">Budget Adherence</div>
                  <div class="text-xs text-blue-500 dark:text-blue-400 mt-1">
                    Higher is better (0-100%)
                  </div>
                </div>
              </div>
            </div>

            <!-- Detailed Variance Breakdown -->
            <div class="mt-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Variance Breakdown by Category</h3>
              <div class="space-y-3">
                <div 
                  v-for="category in budgetKpisData.budget_variance?.category_variances || []" 
                  :key="category.category"
                  class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                >
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white">{{ category.category }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      Budget: {{ formatCurrency(category.budget_amount, selectedCurrency) }} | 
                      Actual: {{ formatCurrency(category.actual_amount, selectedCurrency) }}
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="font-semibold" :class="category.variance_amount >= 0 ? 'text-green-600' : 'text-red-600'">
                      {{ formatCurrency(category.variance_amount, selectedCurrency) }}
                    </div>
                    <div class="text-sm" :class="category.variance_percentage >= 0 ? 'text-green-600' : 'text-red-600'">
                      {{ category.variance_percentage.toFixed(1) }}%
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Preview Section -->
      <div v-if="previewData" class="space-y-6">
        <Card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <template #title>
            Preview: {{ getReportTypeName(selectedReportType) }}
          </template>
          <template #content>
            <div class="space-y-4">
              <!-- Preview Loading State -->
              <div v-if="previewLoading" class="text-center py-8">
                <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="8" />
                <p class="mt-4 text-gray-600 dark:text-gray-400">Generating preview...</p>
              </div>

              <!-- Preview Content -->
              <div v-else-if="previewData" class="space-y-6">
                <!-- Income Statement Preview -->
                <IncomeStatementPreview
                  v-if="selectedReportType === 'income_statement'"
                  :data="previewData"
                  :currency="selectedCurrency"
                  @drilldown="handleDrilldown"
                />

                <!-- Balance Sheet Preview -->
                <BalanceSheetPreview
                  v-else-if="selectedReportType === 'balance_sheet'"
                  :data="previewData"
                  :currency="selectedCurrency"
                  @drilldown="handleDrilldown"
                />

                <!-- Cash Flow Preview -->
                <CashFlowPreview
                  v-else-if="selectedReportType === 'cash_flow'"
                  :data="previewData"
                  :currency="selectedCurrency"
                  @drilldown="handleDrilldown"
                />

                <!-- Trial Balance Preview -->
                <TrialBalancePreview
                  v-else-if="selectedReportType === 'trial_balance'"
                  :data="previewData"
                  :currency="selectedCurrency"
                  @drilldown="handleDrilldown"
                />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Recent Reports -->
      <Card v-if="recentReports.length > 0" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
        <template #title>
          Recent Reports
        </template>
        <template #content>
          <DataTable
            :value="recentReports"
            :paginator="recentReports.length > 10"
            :rows="10"
            :stripedRows="true"
            :showGridlines="false"
            class="p-datatable-sm"
            :loading="loadingReports"
          >
            <Column field="report_type" header="Type">
              <template #body="{ data }">
                <span class="capitalize">{{ data.report_type?.replace('_', ' ') || 'Unknown' }}</span>
              </template>
            </Column>
            <Column field="created_at" header="Generated">
              <template #body="{ data }">
                {{ formatDateTime(data.created_at) }}
              </template>
            </Column>
            <Column field="status" header="Status">
              <template #body="{ data }">
                <Tag
                  :value="data.status"
                  :severity="getStatusSeverity(data.status)"
                />
              </template>
            </Column>
            <Column field="file_size" header="Size">
              <template #body="{ data }">
                {{ formatFileSize(data.file_size) }}
              </template>
            </Column>
            <Column header="Actions">
              <template #body="{ data }">
                <div class="flex items-center space-x-2">
                  <Button
                    icon="pi pi-eye"
                    severity="secondary"
                    text
                    size="small"
                    @click="handleViewReport(data)"
                    :disabled="data.status !== 'generated'"
                    v-tooltip="'View'"
                  />
                  <Button
                    icon="pi pi-download"
                    severity="secondary"
                    text
                    size="small"
                    @click="handleDownloadReport(data)"
                    :disabled="data.status !== 'generated'"
                    v-tooltip="'Download'"
                  />
                  <Button
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    size="small"
                    @click="handleDeleteReport(data)"
                    v-tooltip="'Delete'"
                  />
                </div>
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>

      <!-- Empty State -->
      <div v-else-if="!loading && !previewData && !error" class="text-center py-12">
        <i class="pi pi-file-text text-6xl text-gray-400 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
          No Reports Generated
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-4">
          Select report parameters and generate your first financial statement.
        </p>
      </div>
    </div>

    <!-- Transaction Drilldown Modal -->
    <Dialog
      v-model:visible="showDrilldownModal"
      modal
      :header="drilldownModalTitle"
      :style="{ width: '90vw', maxWidth: '1200px' }"
      :maximizable="true"
    >
      <TransactionDrilldownModal
        v-if="showDrilldownModal"
        :accountId="drilldownAccountId"
        :accountCode="drilldownAccountCode"
        :dateRange="drilldownDateRange"
        :currency="selectedCurrency"
        @close="showDrilldownModal = false"
      />
    </Dialog>

    <!-- Settings Dialog -->
    <Dialog
      v-model:visible="showSettings"
      modal
      header="Report Settings"
      :style="{ width: '450px' }"
    >
      <div class="space-y-4">
        <div>
          <label for="export-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Default Export Format
          </label>
          <Dropdown
            id="export-format"
            v-model="defaultExportFormat"
            :options="exportFormatOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>

        <div>
          <label for="include-zero-balances" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Include Zero Balances
          </label>
          <div class="flex items-center">
            <ToggleSwitch
              id="include-zero-balances"
              v-model="includeZeroBalances"
            />
            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
              Show accounts with zero balances in reports
            </span>
          </div>
        </div>

        <div>
          <label for="auto-refresh" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Auto-refresh Reports
          </label>
          <div class="flex items-center">
            <ToggleSwitch
              id="auto-refresh"
              v-model="autoRefreshReports"
            />
            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
              Automatically refresh report list every 30 seconds
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          label="Close"
          severity="secondary"
          @click="showSettings = false"
        />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useReportingStatements } from '@/services/reportingStatements'
import AppLayout from '@/Layouts/AuthenticatedLayout.vue'

// Import preview components
import IncomeStatementPreview from '@/components/Reporting/IncomeStatementPreview.vue'
import BalanceSheetPreview from '@/components/Reporting/BalanceSheetPreview.vue'
import CashFlowPreview from '@/components/Reporting/CashFlowPreview.vue'
import TrialBalancePreview from '@/components/Reporting/TrialBalancePreview.vue'
import TransactionDrilldownModal from '@/components/Reporting/TransactionDrilldownModal.vue'

const toast = useToast()
const page = usePage()
const { 
  generateReport,
  previewReport,
  getReports,
  deleteReport,
  downloadReport
} = useReportingStatements()

// State
const loading = ref(false)
const isGenerating = ref(false)
const previewLoading = ref(false)
const loadingReports = ref(false)
const loadingBudgetKpis = ref(false)
const error = ref(null)
const previewData = ref(null)
const recentReports = ref([])
const budgetKpisData = ref(null)

// Form State
const selectedReportType = ref('income_statement')
const dateRange = ref(null)
const selectedComparison = ref('prior_period')
const selectedCurrency = ref('USD')

// UI State
const showGenerateDialog = ref(false)
const showSettings = ref(false)
const showDrilldownModal = ref(false)

// Drilldown State
const drilldownModalTitle = ref('')
const drilldownAccountId = ref(null)
const drilldownAccountCode = ref(null)
const drilldownDateRange = ref(null)

// Settings
const defaultExportFormat = ref('json')
const includeZeroBalances = ref(false)
const autoRefreshReports = ref(false)
let refreshTimer = null

// Options
const reportTypes = ref([
  { type: 'income_statement', name: 'Income Statement' },
  { type: 'balance_sheet', name: 'Balance Sheet' },
  { type: 'cash_flow', name: 'Cash Flow Statement' },
  { type: 'trial_balance', name: 'Trial Balance' },
])

const comparisonOptions = ref([
  { label: 'Previous Period', value: 'prior_period' },
  { label: 'Previous Year', value: 'prior_year' },
  { label: 'None', value: 'none' },
])

const currencyOptions = ref([
  { name: 'US Dollar', code: 'USD' },
  { name: 'Euro', code: 'EUR' },
  { name: 'British Pound', code: 'GBP' },
  { name: 'Canadian Dollar', code: 'CAD' },
  { name: 'Australian Dollar', code: 'AUD' },
])

const exportFormatOptions = ref([
  { label: 'JSON', value: 'json' },
  { label: 'PDF', value: 'pdf' },
  { label: 'CSV', value: 'csv' },
])

// Computed
const currentCompanyId = computed(() => page.props.auth.user?.current_company_id)

const canGenerate = computed(() => {
  return selectedReportType.value && 
         dateRange.value && 
         dateRange.value.length === 2 && 
         currentCompanyId.value
})

// Methods
const loadRecentReports = async () => {
  if (!currentCompanyId.value) return

  loadingReports.value = true
  try {
    const response = await getReports({
      per_page: 20,
      report_type: selectedReportType.value
    })
    recentReports.value = response.data.data || []
  } catch (err) {
    console.error('Failed to load recent reports:', err)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load recent reports',
      life: 3000,
    })
  } finally {
    loadingReports.value = false
  }
}

const handlePreview = async () => {
  if (!canGenerate.value) return

  previewLoading.value = true
  error.value = null

  try {
    const params = getReportParams()
    const response = await previewReport(params)
    previewData.value = response.data.preview

    toast.add({
      severity: 'success',
      summary: 'Preview Generated',
      detail: 'Report preview loaded successfully',
      life: 3000,
    })
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to generate preview'
    toast.add({
      severity: 'error',
      summary: 'Preview Failed',
      detail: error.value,
      life: 5000,
    })
  } finally {
    previewLoading.value = false
  }
}

const handleGenerate = async () => {
  if (!canGenerate.value) return

  isGenerating.value = true
  error.value = null

  try {
    const params = {
      ...getReportParams(),
      export_format: defaultExportFormat.value,
      async: true,
      priority: 'normal',
      include_zero_balances: includeZeroBalances.value,
    }

    const response = await generateReport(params)
    
    toast.add({
      severity: 'info',
      summary: 'Report Generation Started',
      detail: 'Your report is being generated in the background',
      life: 3000,
    })

    // Refresh recent reports after a delay
    setTimeout(() => {
      loadRecentReports()
    }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to generate report'
    toast.add({
      severity: 'error',
      summary: 'Generation Failed',
      detail: error.value,
      life: 5000,
    })
  } finally {
    isGenerating.value = false
  }
}

const handleDrilldown = (accountId, accountCode, accountName) => {
  drilldownModalTitle.value = `Transaction Drilldown: ${accountName} (${accountCode})`
  drilldownAccountId.value = accountId
  drilldownAccountCode.value = accountCode
  drilldownDateRange.value = {
    start: dateRange.value[0]?.toISOString().split('T')[0],
    end: dateRange.value[1]?.toISOString().split('T')[0],
  }
  showDrilldownModal.value = true
}

const handleViewReport = (report) => {
  router.visit(`/reporting/statements/${report.report_id}`)
}

const handleDownloadReport = async (report) => {
  try {
    await downloadReport(report.report_id)
    toast.add({
      severity: 'success',
      summary: 'Download Started',
      detail: 'Report download started',
      life: 3000,
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Download Failed',
      detail: err.response?.data?.message || 'Failed to download report',
      life: 5000,
    })
  }
}

const handleDeleteReport = async (report) => {
  if (!confirm(`Are you sure you want to delete this ${report.report_type?.replace('_', ' ')} report?`)) {
    return
  }

  try {
    await deleteReport(report.report_id)
    await loadRecentReports()
    
    toast.add({
      severity: 'success',
      summary: 'Report Deleted',
      detail: 'Report has been deleted successfully',
      life: 3000,
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Delete Failed',
      detail: err.response?.data?.message || 'Failed to delete report',
      life: 5000,
    })
  }
}

const getReportParams = () => {
  return {
    report_type: selectedReportType.value,
    date_range: {
      start: dateRange.value[0]?.toISOString().split('T')[0],
      end: dateRange.value[1]?.toISOString().split('T')[0],
    },
    comparison: selectedComparison.value,
    currency: selectedCurrency.value,
  }
}

// Event Handlers
const onReportTypeChange = () => {
  previewData.value = null
  loadRecentReports()
  loadBudgetKpis()
}

const onDateRangeChange = () => {
  previewData.value = null
  loadBudgetKpis()
}

const onComparisonChange = () => {
  previewData.value = null
}

const onCurrencyChange = () => {
  previewData.value = null
  loadBudgetKpis()
}

const loadBudgetKpis = async () => {
  if (!currentCompanyId.value || !dateRange.value || dateRange.value.length !== 2) return

  if (selectedReportType.value !== 'income_statement') {
    budgetKpisData.value = null
    return
  }

  loadingBudgetKpis.value = true
  try {
    const params = new URLSearchParams({
      currency: selectedCurrency.value,
      date_range: JSON.stringify({
        start: dateRange.value[0]?.toISOString().split('T')[0],
        end: dateRange.value[1]?.toISOString().split('T')[0],
      }),
      include_variance: 'true',
    })

    const response = await fetch(`/api/reporting/kpis/budget?${params}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    })

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    const data = await response.json()
    budgetKpisData.value = data
  } catch (err) {
    console.error('Failed to load budget KPIs:', err)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load budget KPIs',
      life: 3000,
    })
  } finally {
    loadingBudgetKpis.value = false
  }
}

// Utility Functions
const getReportTypeName = (type) => {
  const reportType = reportTypes.value.find(rt => rt.type === type)
  return reportType ? reportType.name : type
}

const getStatusSeverity = (status) => {
  switch (status) {
    case 'generated': return 'success'
    case 'failed': return 'danger'
    case 'running': return 'info'
    case 'queued': return 'warning'
    default: return 'secondary'
  }
}

const formatDateTime = (dateTime) => {
  return new Date(dateTime).toLocaleString()
}

const formatFileSize = (bytes) => {
  if (!bytes) return 'N/A'
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`
}

const formatCurrency = (value, currency = 'USD') => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(value)
}

const toggleAutoRefresh = () => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
  }

  if (autoRefreshReports.value) {
    refreshTimer = setInterval(() => {
      loadRecentReports()
    }, 30000) // 30 seconds
  }
}

// Lifecycle
onMounted(async () => {
  await loadRecentReports()
  
  // Set default date range to current month
  const now = new Date()
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
  const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  dateRange.value = [startOfMonth, endOfMonth]
  
  // Load budget KPIs after date range is set
  await loadBudgetKpis()
})

onUnmounted(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }
})

// Watchers
watch(autoRefreshReports, toggleAutoRefresh)
watch(selectedReportType, loadRecentReports)
</script>