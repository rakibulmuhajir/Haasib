<template>
  <LayoutShell>
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Dashboard"
      description="Comprehensive dashboard and analytics"
      subDescription="Monitor your business performance and key metrics"
      :show-search="false"
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <!-- Left Column - Main Content -->
      <div class="main-content">
        <div class="payment-reporting-dashboard">

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Payments</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ formatNumber(reportMetrics.total_payments) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ getPeriodLabel() }}
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full">
              <i class="pi pi-money-bill text-blue-600 dark:text-blue-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Allocated</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                ${{ formatCurrency(reportMetrics.total_amount_allocated) }}
              </p>
              <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                {{ reportMetrics.allocation_rate }}% allocated
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
              <p class="text-sm text-gray-500 dark:text-gray-400">Unallocated Cash</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                ${{ formatCurrency(reportMetrics.total_unallocated_cash) }}
              </p>
              <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                Requires attention
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-orange-100 dark:bg-orange-900 rounded-full">
              <i class="pi pi-exclamation-triangle text-orange-600 dark:text-orange-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Reconciliation Rate</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ reportMetrics.reconciliation_rate }}%
              </p>
              <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                Bank matched
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full">
              <i class="pi pi-bank text-purple-600 dark:text-purple-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Payment Methods Chart -->
      <Card>
        <template #title>
          <span class="flex items-center gap-2">
            <i class="pi pi-chart-pie"></i>
            Payment Methods Distribution
          </span>
        </template>
        <template #content>
          <div class="relative h-80">
            <canvas ref="paymentMethodsChart" id="payment-methods-chart"></canvas>
          </div>
        </template>
      </Card>

      <!-- Daily Activity Chart -->
      <Card>
        <template #title>
          <span class="flex items-center gap-2">
            <i class="pi pi-chart-line"></i>
            Daily Payment Activity
          </span>
        </template>
        <template #content>
          <div class="relative h-80">
            <canvas ref="dailyActivityChart" id="daily-activity-chart"></canvas>
          </div>
        </template>
      </Card>
    </div>

    <!-- Audit Actions Chart -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <Card>
        <template #title>
          <span class="flex items-center gap-2">
            <i class="pi pi-chart-bar"></i>
            Audit Actions Breakdown
          </span>
        </template>
        <template #content>
          <div class="relative h-80">
            <canvas ref="auditActionsChart" id="audit-actions-chart"></canvas>
          </div>
        </template>
      </Card>

      <!-- Allocation Trends -->
      <Card>
        <template #title>
          <span class="flex items-center gap-2">
            <i class="pi pi-chart-area"></i>
            Allocation Trends
          </span>
        </template>
        <template #content>
          <div class="relative h-80">
            <canvas ref="allocationTrendsChart" id="allocation-trends-chart"></canvas>
          </div>
        </template>
      </Card>
    </div>

    <!-- Recent Activity Table -->
    <Card>
      <template #title>
        <span class="flex items-center gap-2">
          <i class="pi pi-history"></i>
          Recent Payment Activity
        </span>
      </template>
      
      <template #content>
        <DataTable
          :value="recentActivity"
          :loading="loadingActivity"
          paginator
          :rows="10"
          :totalRecords="totalActivityRecords"
          :lazy="true"
          @page="onActivityPageChange"
          dataKey="id"
          :globalFilterFields="['payment_number', 'entity_name', 'action']"
          filterDisplay="menu"
          :filters="activityFilters"
          responsiveLayout="scroll"
        >
          <template #empty>
            <div class="text-center py-4">
              <i class="pi pi-info-circle text-2xl text-gray-400"></i>
              <p class="text-gray-500 dark:text-gray-400 mt-2">No recent activity found</p>
            </div>
          </template>

          <Column field="timestamp" header="Time" sortable>
            <template #body="{ data }">
              <span class="text-sm">{{ formatDateTime(data.timestamp) }}</span>
            </template>
          </Column>

          <Column field="action" header="Action" sortable>
            <template #body="{ data }">
              <Tag 
                :value="getActionLabel(data.action)"
                :severity="getActionSeverity(data.action)"
                size="small"
              />
            </template>
          </Column>

          <Column field="payment_number" header="Payment #" sortable>
            <template #body="{ data }">
              <span class="font-mono text-sm">{{ data.payment_number }}</span>
            </template>
          </Column>

          <Column field="entity_name" header="Entity" sortable>
            <template #body="{ data }">
              <span class="text-sm">{{ data.entity_name || 'N/A' }}</span>
            </template>
          </Column>

          <Column field="amount" header="Amount" sortable>
            <template #body="{ data }">
              <span class="font-semibold">${{ formatCurrency(data.amount) }}</span>
            </template>
          </Column>

          <Column field="actor_type" header="Actor" sortable>
            <template #body="{ data }">
              <Tag 
                :value="data.actor_type"
                :severity="getActorTypeSeverity(data.actor_type)"
                size="small"
              />
            </template>
          </Column>

          <Column header="Actions" :exportable="false">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-eye"
                  size="small"
                  text
                  rounded
                  @click="viewActivityDetails(data)"
                />
                <Button
                  icon="pi pi-external-link"
                  size="small"
                  text
                  rounded
                  @click="navigateToPayment(data.payment_id)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Report Generation Dialog -->
    <Dialog
      v-model:visible="showReportDialog"
      modal
      header="Generate Payment Report"
      :style="{ width: '50vw' }"
    >
      <div class="space-y-4">
        <div>
          <label for="report-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Report Format
          </label>
          <Dropdown
            id="report-format"
            v-model="reportFormat"
            :options="reportFormats"
            optionLabel="label"
            optionValue="value"
            placeholder="Select format"
            class="w-full"
          />
        </div>

        <div>
          <label for="report-period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Report Period
          </label>
          <Calendar
            id="report-period"
            v-model="reportPeriod"
            selectionMode="range"
            :manualInput="false"
            dateFormat="yy-mm-dd"
            placeholder="Select date range"
            class="w-full"
          />
        </div>

        <div>
          <label for="report-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Report Type
          </label>
          <Dropdown
            id="report-type"
            v-model="reportType"
            :options="reportTypes"
            optionLabel="label"
            optionValue="value"
            placeholder="Select report type"
            class="w-full"
          />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox
            id="include-audit"
            v-model="includeAuditTrail"
            binary
          />
          <label for="include-audit" class="text-sm text-gray-700 dark:text-gray-300">
            Include audit trail
          </label>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox
            id="include-metrics"
            v-model="includeMetrics"
            binary
          />
          <label for="include-metrics" class="text-sm text-gray-700 dark:text-gray-300">
            Include performance metrics
          </label>
        </div>
      </div>

      <template #footer>
        <Button
          label="Cancel"
          @click="showReportDialog = false"
          severity="secondary"
        />
        <Button
          label="Generate"
          @click="downloadReport"
          :loading="generatingReport"
          severity="primary"
        />
      </template>
    </Dialog>

    <!-- Configuration Dialog -->
    <Dialog
      v-model:visible="showConfigDialog"
      modal
      header="Dashboard Configuration"
      :style="{ width: '40vw' }"
    >
      <div class="space-y-4">
        <div>
          <label for="refresh-interval" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Auto Refresh Interval (minutes)
          </label>
          <InputNumber
            id="refresh-interval"
            v-model="refreshInterval"
            :min="1"
            :max="60"
            class="w-full"
          />
        </div>

        <div>
          <label for="default-period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Default Report Period
          </label>
          <Dropdown
            id="default-period"
            v-model="defaultPeriod"
            :options="periodOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox
            id="show-charts"
            v-model="showCharts"
            binary
          />
          <label for="show-charts" class="text-sm text-gray-700 dark:text-gray-300">
            Show charts on dashboard
          </label>
        </div>
      </div>

      <template #footer>
        <Button
          label="Cancel"
          @click="showConfigDialog = false"
          severity="secondary"
        />
        <Button
          label="Save Changes"
          @click="saveConfiguration"
          severity="primary"
        />
      </template>
    </Dialog>
      </div>
    </div>

      <!-- Right Column - Quick Links -->
      <div class="sidebar-content">
        <QuickLinks 
          :links="quickLinks" 
          title="Payment Actions"
        />
      </div>
    </div>
  </LayoutShell>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, nextTick } from 'vue'
import { useToast } from 'primevue/usetoast'
import { format } from 'date-fns'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import { usePageActions } from '@/composables/usePageActions'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

// Composition
const toast = useToast()
const { actions } = usePageActions()

// Define page actions
const pageActions = [
  {
    key: 'generate-report',
    label: 'Generate Report',
    icon: 'pi pi-file-pdf',
    severity: 'primary',
    action: () => generateReport()
  },
  {
    key: 'configure',
    label: 'Configure',
    icon: 'pi pi-cog',
    severity: 'secondary',
    action: () => showConfigDialog.value = true
  }
]

// Define quick links for the payments page
const quickLinks = [
  {
    label: 'Generate Payment Report',
    url: '#',
    icon: 'pi pi-file-pdf',
    action: () => generateReport()
  },
  {
    label: 'Payment Batches',
    url: '/payments/batches',
    icon: 'pi pi-database'
  },
  {
    label: 'Payment Reversals',
    url: '/payments/reversals',
    icon: 'pi pi-undo'
  },
  {
    label: 'Audit Timeline',
    url: '/payments/audit',
    icon: 'pi pi-history'
  }
]

// Set page actions
actions.value = pageActions

// State
const loading = ref(false)
const loadingActivity = ref(false)
const generatingReport = ref(false)
const recentActivity = ref([])
const totalActivityRecords = ref(0)
const reportMetrics = ref({})
const showReportDialog = ref(false)
const showConfigDialog = ref(false)

// Charts
const paymentMethodsChart = ref(null)
const dailyActivityChart = ref(null)
const auditActionsChart = ref(null)
const allocationTrendsChart = ref(null)

// Chart instances
let charts = {}

// Configuration
const refreshInterval = ref(5)
const defaultPeriod = ref('30')
const showCharts = ref(true)

// Report Generation
const reportFormat = ref('json')
const reportPeriod = ref(null)
const reportType = ref('allocation')
const includeAuditTrail = ref(true)
const includeMetrics = ref(true)

// Filters
const activityFilters = reactive({})

// Options
const reportFormats = [
  { label: 'JSON', value: 'json' },
  { label: 'CSV', value: 'csv' },
  { label: 'PDF', value: 'pdf' }
]

const reportTypes = [
  { label: 'Allocation Report', value: 'allocation' },
  { label: 'Audit Trail', value: 'audit' },
  { label: 'Reconciliation', value: 'reconciliation' },
  { label: 'Comprehensive', value: 'comprehensive' }
]

const periodOptions = [
  { label: 'Last 7 days', value: '7' },
  { label: 'Last 30 days', value: '30' },
  { label: 'Last 90 days', value: '90' },
  { label: 'Last year', value: '365' }
]

// Auto refresh
let refreshTimer = null

// Methods
const loadDashboardData = async () => {
  loading.value = true
  try {
    await Promise.all([
      loadReportMetrics(),
      loadRecentActivity(),
      loadChartData()
    ])
  } catch (error) {
    console.error('Error loading dashboard data:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load dashboard data',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const loadReportMetrics = async () => {
  try {
    const response = await fetch(`/api/accounting/payments/audit/metrics?start_date=${getStartDate()}&end_date=${getEndDate()}`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load metrics')
    }

    const data = await response.json()
    
    // Transform metrics for dashboard
    reportMetrics.value = {
      total_payments: data.metrics.total_audit_events || 0,
      total_amount_allocated: data.metrics.action_counts?.payment_allocated_events || 0,
      total_unallocated_cash: data.metrics.action_counts?.payment_created_events - (data.metrics.action_counts?.payment_allocated_events || 0),
      allocation_rate: calculateAllocationRate(data.metrics),
      reconciliation_rate: data.metrics.reconciliation_metrics?.reconciliation_rate || 0
    }
  } catch (error) {
    console.error('Error loading metrics:', error)
  }
}

const loadRecentActivity = async () => {
  loadingActivity.value = true
  try {
    const params = new URLSearchParams({
      page: 1,
      limit: 10,
      sort_by: 'timestamp',
      sort_direction: 'desc'
    })

    const response = await fetch(`/api/accounting/payments/audit?${params}`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load recent activity')
    }

    const data = await response.json()
    recentActivity.value = data.audit_trail
    totalActivityRecords.value = data.pagination.total
  } catch (error) {
    console.error('Error loading recent activity:', error)
  } finally {
    loadingActivity.value = false
  }
}

const loadChartData = async () => {
  try {
    const response = await fetch(`/api/accounting/payments/audit/metrics?start_date=${getStartDate()}&end_date=${getEndDate()}`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load chart data')
    }

    const data = await response.json()
    
    await nextTick()
    createCharts(data.metrics)
  } catch (error) {
    console.error('Error loading chart data:', error)
  }
}

const createCharts = (metrics) => {
  if (!showCharts.value) return

  // Destroy existing charts
  Object.values(charts).forEach(chart => {
    if (chart) chart.destroy()
  })

  // Payment Methods Chart
  if (paymentMethodsChart.value && metrics.payment_method_distribution) {
    charts.paymentMethods = new Chart(paymentMethodsChart.value, {
      type: 'doughnut',
      data: {
        labels: Object.keys(metrics.payment_method_distribution),
        datasets: [{
          data: Object.values(metrics.payment_method_distribution),
          backgroundColor: [
            '#10B981',
            '#3B82F6',
            '#F59E0B',
            '#EF4444',
            '#8B5CF6'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    })
  }

  // Daily Activity Chart
  if (dailyActivityChart.value && metrics.daily_activity) {
    charts.dailyActivity = new Chart(dailyActivityChart.value, {
      type: 'line',
      data: {
        labels: metrics.daily_activity.map(item => item.date),
        datasets: [{
          label: 'Daily Activity',
          data: metrics.daily_activity.map(item => item.count),
          borderColor: '#3B82F6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    })
  }

  // Audit Actions Chart
  if (auditActionsChart.value && metrics.action_counts) {
    charts.auditActions = new Chart(auditActionsChart.value, {
      type: 'bar',
      data: {
        labels: Object.keys(metrics.action_counts),
        datasets: [{
          label: 'Count',
          data: Object.values(metrics.action_counts),
          backgroundColor: [
            '#10B981',
            '#3B82F6',
            '#F59E0B',
            '#EF4444',
            '#8B5CF6'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    })
  }
}

const onActivityPageChange = (event) => {
  const params = new URLSearchParams({
    page: event.page + 1,
    limit: event.rows,
    sort_by: 'timestamp',
    sort_direction: 'desc'
  })

  // Load page data
  fetch(`/api/accounting/payments/audit?${params}`, {
    headers: {
      'X-Company-Id': localStorage.getItem('companyId') || '',
      'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
    }
  })
  .then(response => response.json())
  .then(data => {
    recentActivity.value = data.audit_trail
    totalActivityRecords.value = data.pagination.total
  })
  .catch(error => {
    console.error('Error loading activity page:', error)
  })
}

const generateReport = () => {
  showReportDialog.value = true
}

const downloadReport = async () => {
  generatingReport.value = true
  try {
    const params = new URLSearchParams({
      format: reportFormat.value,
      type: reportType.value,
      include_audit: includeAuditTrail.value,
      include_metrics: includeMetrics.value
    })

    if (reportPeriod.value) {
      params.append('start_date', reportPeriod.value[0])
      params.append('end_date', reportPeriod.value[1])
    }

    const response = await fetch(`/api/accounting/payments/reports/generate?${params}`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to generate report')
    }

    // Handle file download
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `payment-report-${format(new Date(), 'yyyy-MM-dd')}.${reportFormat.value}`
    a.click()
    window.URL.revokeObjectURL(url)

    showReportDialog.value = false
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Report generated successfully',
      life: 3000
    })
  } catch (error) {
    console.error('Error generating report:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to generate report',
      life: 3000
    })
  } finally {
    generatingReport.value = false
  }
}

const saveConfiguration = () => {
  // Save configuration to localStorage or backend
  localStorage.setItem('dashboard-config', JSON.stringify({
    refreshInterval: refreshInterval.value,
    defaultPeriod: defaultPeriod.value,
    showCharts: showCharts.value
  }))

  showConfigDialog.value = false
  toast.add({
    severity: 'success',
    summary: 'Success',
    detail: 'Configuration saved',
    life: 3000
  })

  // Restart auto refresh with new interval
  setupAutoRefresh()
}

const viewActivityDetails = (activity) => {
  // Navigate to detailed activity view or show modal
  console.log('View activity details:', activity)
}

const navigateToPayment = (paymentId) => {
  // Navigate to payment details page
  console.log('Navigate to payment:', paymentId)
}

const setupAutoRefresh = () => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }

  if (refreshInterval.value > 0) {
    refreshTimer = setInterval(() => {
      loadDashboardData()
    }, refreshInterval.value * 60 * 1000)
  }
}

// Helper Methods
const getStartDate = () => {
  const days = parseInt(defaultPeriod.value)
  return format(new Date(Date.now() - days * 24 * 60 * 60 * 1000), 'yyyy-MM-dd')
}

const getEndDate = () => {
  return format(new Date(), 'yyyy-MM-dd')
}

const getPeriodLabel = () => {
  const labels = {
    '7': 'Last 7 days',
    '30': 'Last 30 days',
    '90': 'Last 90 days',
    '365': 'Last year'
  }
  return labels[defaultPeriod.value] || 'Custom period'
}

const calculateAllocationRate = (metrics) => {
  const created = metrics.action_counts?.payment_created_events || 0
  const allocated = metrics.action_counts?.payment_allocated_events || 0
  return created > 0 ? Math.round((allocated / created) * 100) : 0
}

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num)
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

const formatDateTime = (timestamp) => {
  try {
    return format(new Date(timestamp), 'MMM dd, HH:mm')
  } catch {
    return timestamp
  }
}

const getActionLabel = (action) => {
  const labels = {
    'payment_created': 'Created',
    'payment_allocated': 'Allocated',
    'payment_reversed': 'Reversed',
    'allocation_reversed': 'Allocation Reversed',
    'bank_reconciled': 'Reconciled',
    'payment_audited': 'Audited'
  }
  return labels[action] || action
}

const getActionSeverity = (action) => {
  const severities = {
    'payment_created': 'success',
    'payment_allocated': 'info',
    'payment_reversed': 'danger',
    'allocation_reversed': 'warning',
    'bank_reconciled': 'success',
    'payment_audited': 'secondary'
  }
  return severities[action] || 'secondary'
}

const getActorTypeSeverity = (actorType) => {
  const severities = {
    'user': 'info',
    'system': 'warning',
    'api': 'success'
  }
  return severities[actorType] || 'secondary'
}

// Lifecycle
onMounted(() => {
  // Load configuration
  const savedConfig = localStorage.getItem('dashboard-config')
  if (savedConfig) {
    const config = JSON.parse(savedConfig)
    refreshInterval.value = config.refreshInterval || 5
    defaultPeriod.value = config.defaultPeriod || '30'
    showCharts.value = config.showCharts !== false
  }

  loadDashboardData()
  setupAutoRefresh()
})

onUnmounted(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }
  
  // Destroy charts
  Object.values(charts).forEach(chart => {
    if (chart) chart.destroy()
  })
})
</script>

