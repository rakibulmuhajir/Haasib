<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Financial Dashboard
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Real-time financial metrics and KPIs
          </p>
        </div>
        
        <div class="flex items-center space-x-3">
          <!-- Date Range Selector -->
          <Calendar
            v-model="dateRange"
            selectionMode="range"
            :manualInput="false"
            showIcon
            placeholder="Select date range"
            class="w-64"
            @change="onDateRangeChange"
          />
          
          <!-- Layout Selector -->
          <Dropdown
            v-model="selectedLayoutId"
            :options="layoutOptions"
            optionLabel="name"
            optionValue="layout_id"
            placeholder="Select Layout"
            class="w-48"
            @change="onLayoutChange"
          />
          
          <!-- Refresh Button -->
          <Button
            :loading="isRefreshing"
            icon="pi pi-refresh"
            label="Refresh"
            @click="handleRefresh"
            :disabled="!selectedLayoutId"
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

      <!-- Loading State -->
      <div v-if="loading" class="space-y-6">
        <!-- KPI Cards Loading Skeleton -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="i in 4" :key="i" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <Skeleton width="100%" height="20px" class="mb-3" />
            <Skeleton width="60%" height="32px" class="mb-2" />
            <Skeleton width="40%" height="16px" />
          </div>
        </div>
        
        <!-- Charts Loading Skeleton -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div v-for="i in 2" :key="i" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <Skeleton width="100%" height="24px" class="mb-4" />
            <Skeleton width="100%" height="300px" />
          </div>
        </div>
      </div>

      <!-- Dashboard Content -->
      <div v-else-if="dashboardData" class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div
            v-for="(total, index) in dashboardData.totals"
            :key="index"
            class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                  {{ total.label }}
                </p>
                <div class="mt-2 flex items-baseline">
                  <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(total.value, selectedCurrency) }}
                  </p>
                </div>
              </div>
              <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <i :class="getSummaryIcon(total.label)" class="text-xl text-gray-600 dark:text-gray-300"></i>
              </div>
            </div>
            <div v-if="total.trend_percent !== null" class="mt-4 flex items-center text-sm">
              <span
                :class="total.direction === 'up' ? 'text-green-600' : total.direction === 'down' ? 'text-red-600' : 'text-gray-500'"
                class="flex items-center"
              >
                <i
                  :class="total.direction === 'up' ? 'pi pi-arrow-up' : total.direction === 'down' ? 'pi pi-arrow-down' : 'pi pi-minus'"
                  class="mr-1"
                ></i>
                {{ Math.abs(total.trend_percent).toFixed(1) }}%
              </span>
              <span class="text-gray-500 dark:text-gray-400 ml-2">vs last period</span>
            </div>
          </div>
        </div>

        <!-- Currency & Comparison Controls -->
        <div class="flex flex-wrap items-center gap-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center space-x-3">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Currency:</label>
            <Dropdown
              v-model="selectedCurrency"
              :options="currencyOptions"
              optionLabel="name"
              optionValue="code"
              class="w-36"
              @change="onCurrencyChange"
            />
          </div>
          
          <div class="flex items-center space-x-3">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Comparison:</label>
            <Dropdown
              v-model="selectedComparison"
              :options="comparisonOptions"
              optionLabel="label"
              optionValue="value"
              class="w-40"
              @change="onComparisonChange"
            />
          </div>

          <div class="flex items-center space-x-3">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Aging Buckets:</label>
            <MultiSelect
              v-model="selectedAgingBuckets"
              :options="agingBucketOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Select buckets"
              class="w-48"
              @change="onAgingBucketsChange"
            />
          </div>
        </div>

        <!-- Aging KPIs Section -->
        <div v-if="agingKpisData" class="space-y-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Aging Analysis</h2>
          
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Receivables Aging -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
              <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Accounts Receivable Aging</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                  As of {{ formatDate(agingKpisData.as_of_date) }} • {{ agingKpisData.currency }}
                </p>
              </div>
              <div class="p-6">
                <div class="space-y-4">
                  <div v-for="bucket in agingKpisData.receivables_aging.buckets" :key="bucket.label" class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                      <div 
                        :class="`w-4 h-4 rounded-full bg-${bucket.color}-500`"
                      ></div>
                      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ bucket.label }}</span>
                    </div>
                    <div class="text-right">
                      <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ formatCurrency(bucket.amount, selectedCurrency) }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ bucket.count }} invoices ({{ bucket.percentage.toFixed(1) }}%)
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Receivables</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">
                      {{ formatCurrency(agingKpisData.receivables_aging.total, selectedCurrency) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payables Aging -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
              <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Accounts Payable Aging</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                  As of {{ formatDate(agingKpisData.as_of_date) }} • {{ agingKpisData.currency }}
                </p>
              </div>
              <div class="p-6">
                <div class="space-y-4">
                  <div v-for="bucket in agingKpisData.payables_aging.buckets" :key="bucket.label" class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                      <div 
                        :class="`w-4 h-4 rounded-full bg-${bucket.color}-500`"
                      ></div>
                      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ bucket.label }}</span>
                    </div>
                    <div class="text-right">
                      <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ formatCurrency(bucket.amount, selectedCurrency) }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ bucket.count }} bills ({{ bucket.percentage.toFixed(1) }}%)
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Payables</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">
                      {{ formatCurrency(agingKpisData.payables_aging.total, selectedCurrency) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Aging Metrics Summary -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
              <div class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ agingKpisData.aging_metrics.aging_health_score?.toFixed(1) || 'N/A' }}%
                </div>
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mt-1">Aging Health Score</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Higher is better (0-100%)
                </div>
              </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
              <div class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ agingKpisData.aging_metrics.collection_effectiveness?.toFixed(1) || 'N/A' }}%
                </div>
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mt-1">Collection Effectiveness</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  How well you're collecting receivables
                </div>
              </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
              <div class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ agingKpisData.aging_metrics.average_days_to_pay?.toFixed(0) || 'N/A' }}
                </div>
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mt-1">Avg Days to Pay</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Average time to collect payments
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Dashboard Cards Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
          <div
            v-for="card in dashboardData.cards"
            :key="card.card_id"
            class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700"
          >
            <!-- Card Header -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ card.title }}
                </h3>
                <Button
                  icon="pi pi-external-link"
                  severity="secondary"
                  text
                  size="small"
                  @click="handleCardDrilldown(card)"
                  v-if="card.drilldown_url"
                />
              </div>
            </div>

            <!-- Card Content -->
            <div class="p-4">
              <!-- KPI Card -->
              <div v-if="card.type === 'kpi'" class="text-center">
                <div class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                  {{ formatValue(card.data.value, card.data.format, card.data.currency) }}
                </div>
                <div v-if="card.comparison" class="flex items-center justify-center space-x-4 text-sm">
                  <div class="flex items-center">
                    <span class="text-gray-500 dark:text-gray-400">Previous:</span>
                    <span class="ml-1 font-medium">
                      {{ formatValue(card.comparison.previous_value, card.data.format, card.data.currency) }}
                    </span>
                  </div>
                  <div class="flex items-center">
                    <i
                      :class="card.comparison.trend === 'up' ? 'pi pi-arrow-up text-green-500' : 'pi pi-arrow-down text-red-500'"
                      class="mr-1"
                    ></i>
                    <span :class="card.comparison.trend === 'up' ? 'text-green-500' : 'text-red-500'">
                      {{ Math.abs(card.comparison.variance_percent).toFixed(1) }}%
                    </span>
                  </div>
                </div>
              </div>

              <!-- Chart Card -->
              <div v-else-if="card.type === 'chart'" class="space-y-4">
                <Chart
                  :type="card.data.chart_type"
                  :data="card.data"
                  :options="getChartOptions(card.data.chart_type)"
                  class="w-full"
                  style="height: 250px"
                />
              </div>

              <!-- Table Card -->
              <div v-else-if="card.type === 'table'" class="space-y-4">
                <DataTable
                  :value="card.data"
                  :paginator="card.data.length > 5"
                  :rows="5"
                  :stripedRows="true"
                  :showGridlines="false"
                  class="p-datatable-sm"
                >
                  <Column
                    v-for="column in card.columns"
                    :key="column"
                    :field="column.toLowerCase().replace(' ', '_')"
                    :header="column"
                  />
                </DataTable>
              </div>

              <!-- Default/Stat Card -->
              <div v-else class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ formatValue(card.data.value, card.data.format) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Last Refreshed Info -->
        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
          <div>
            Last refreshed: {{ formatDateTime(dashboardData.refreshed_at) }}
          </div>
          <div class="flex items-center space-x-4">
            <span>Cache TTL: {{ cacheTtl }}s</span>
            <Button
              label="Force Refresh"
              size="small"
              severity="secondary"
              @click="handleForceRefresh"
            />
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && !error" class="text-center py-12">
        <i class="pi pi-chart-bar text-6xl text-gray-400 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
          No Dashboard Layout Selected
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-4">
          Select a dashboard layout from the dropdown to view financial metrics.
        </p>
        <Button
          label="Create Layout"
          icon="pi pi-plus"
          @click="showCreateLayout = true"
        />
      </div>
    </div>

    <!-- Settings Dialog -->
    <Dialog
      v-model:visible="showSettings"
      modal
      header="Dashboard Settings"
      :style="{ width: '450px' }"
    >
      <div class="space-y-4">
        <div>
          <label for="auto-refresh" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Auto Refresh
          </label>
          <div class="mt-1">
            <ToggleSwitch
              id="auto-refresh"
              v-model="autoRefresh"
              @change="toggleAutoRefresh"
            />
            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
              Refresh dashboard automatically every {{ autoRefreshInterval }}s
            </span>
          </div>
        </div>

        <div>
          <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Display Currency
          </label>
          <Dropdown
            id="currency"
            v-model="selectedCurrency"
            :options="currencyOptions"
            optionLabel="name"
            optionValue="code"
            class="w-full mt-1"
            @change="onCurrencyChange"
          />
        </div>

        <div>
          <label for="comparison" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Comparison Period
          </label>
          <Dropdown
            id="comparison"
            v-model="selectedComparison"
            :options="comparisonOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full mt-1"
            @change="onComparisonChange"
          />
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

    <!-- Create Layout Dialog -->
    <Dialog
      v-model:visible="showCreateLayout"
      modal
      header="Create Dashboard Layout"
      :style="{ width: '600px' }"
    >
      <div class="space-y-4">
        <Message severity="info">
          Dashboard layouts allow you to customize which KPIs and charts appear on your dashboard.
          This feature is coming soon!
        </Message>
      </div>

      <template #footer>
        <Button
          label="Cancel"
          severity="secondary"
          @click="showCreateLayout = false"
        />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useReportingDashboard } from '@/services/reportingDashboard'
import AppLayout from '@/Layouts/AuthenticatedLayout.vue'

const toast = useToast()
const page = usePage()
const { 
  fetchDashboard, 
  refreshDashboard, 
  invalidateCache,
  getDashboardLayouts 
} = useReportingDashboard()

// State
const loading = ref(false)
const isRefreshing = ref(false)
const error = ref(null)
const dashboardData = ref(null)
const cacheTtl = ref(5)
const agingKpisData = ref(null)
const loadingAgingKpis = ref(false)

// Dashboard Settings
const selectedLayoutId = ref(null)
const selectedCurrency = ref('USD')
const selectedComparison = ref('prior_period')
const dateRange = ref(null)
const autoRefresh = ref(false)
const autoRefreshInterval = ref(30)
const selectedAgingBuckets = ref([30, 60, 90, 120])
let refreshTimer = null

// UI State
const showSettings = ref(false)
const showCreateLayout = ref(false)

// Options
const layoutOptions = ref([])
const currencyOptions = ref([
  { name: 'US Dollar', code: 'USD' },
  { name: 'Euro', code: 'EUR' },
  { name: 'British Pound', code: 'GBP' },
  { name: 'Canadian Dollar', code: 'CAD' },
  { name: 'Australian Dollar', code: 'AUD' },
])

const comparisonOptions = ref([
  { label: 'Previous Period', value: 'prior_period' },
  { label: 'Previous Year', value: 'prior_year' },
  { label: 'None', value: 'none' },
])

const agingBucketOptions = ref([
  { label: '30 Days', value: 30 },
  { label: '60 Days', value: 60 },
  { label: '90 Days', value: 90 },
  { label: '120 Days', value: 120 },
  { label: '180 Days', value: 180 },
])

// Computed
const currentCompanyId = computed(() => page.props.auth.user?.current_company_id)

// Methods
const loadDashboard = async () => {
  if (!selectedLayoutId.value || !currentCompanyId.value) return

  loading.value = true
  error.value = null

  try {
    const params = {
      layout_id: selectedLayoutId.value,
      currency: selectedCurrency.value,
      comparison: selectedComparison.value,
    }

    if (dateRange.value) {
      params.date_range = {
        start: dateRange.value[0]?.toISOString().split('T')[0],
        end: dateRange.value[1]?.toISOString().split('T')[0],
      }
    }

    const response = await fetchDashboard(params)
    dashboardData.value = response.data
    cacheTtl = response.headers.get('X-Cache-TTL') || 5

    toast.add({
      severity: 'success',
      summary: 'Dashboard Loaded',
      detail: 'Financial metrics loaded successfully',
      life: 3000,
    })
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load dashboard data'
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.value,
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

const handleRefresh = async () => {
  if (!selectedLayoutId.value) return

  isRefreshing.value = true
  try {
    const params = {
      layout_id: selectedLayoutId.value,
      invalidate_cache: true,
      priority: 'normal',
      async: true,
      currency: selectedCurrency.value,
      comparison: selectedComparison.value,
    }

    await refreshDashboard(params)
    
    toast.add({
      severity: 'info',
      summary: 'Refresh Started',
      detail: 'Dashboard refresh has been queued',
      life: 3000,
    })

    // Poll for completion
    setTimeout(() => {
      loadDashboard()
    }, 5000)
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Refresh Failed',
      detail: err.response?.data?.message || 'Failed to refresh dashboard',
      life: 5000,
    })
  } finally {
    isRefreshing.value = false
  }
}

const handleForceRefresh = async () => {
  try {
    await invalidateCache({ layout_id: selectedLayoutId.value })
    await loadDashboard()
    
    toast.add({
      severity: 'success',
      summary: 'Cache Cleared',
      detail: 'Dashboard cache has been invalidated',
      life: 3000,
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to invalidate cache',
      life: 5000,
    })
  }
}

const handleCardDrilldown = (card) => {
  if (card.drilldown_url) {
    router.visit(card.drilldown_url)
  }
}

const loadLayouts = async () => {
  try {
    const layouts = await getDashboardLayouts()
    layoutOptions.value = layouts.data
    
    // Select default layout if available
    const defaultLayout = layouts.data.find(l => l.is_default)
    if (defaultLayout && !selectedLayoutId.value) {
      selectedLayoutId.value = defaultLayout.layout_id
    }
  } catch (err) {
    console.error('Failed to load layouts:', err)
  }
}

const toggleAutoRefresh = () => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
  }

  if (autoRefresh.value) {
    refreshTimer = setInterval(() => {
      handleRefresh()
    }, autoRefreshInterval.value * 1000)
  }
}

// Event Handlers
const onLayoutChange = () => {
  loadDashboard()
}

const onCurrencyChange = () => {
  loadDashboard()
  loadAgingKpis()
}

const onComparisonChange = () => {
  loadDashboard()
}

const onDateRangeChange = () => {
  loadDashboard()
  loadAgingKpis()
}

const loadAgingKpis = async () => {
  if (!currentCompanyId.value) return

  loadingAgingKpis.value = true
  try {
    const params = new URLSearchParams({
      currency: selectedCurrency.value,
      aging_buckets: JSON.stringify(selectedAgingBuckets.value),
      include_summary: 'true',
    })

    const response = await fetch(`/api/reporting/kpis/aging?${params}`, {
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
    agingKpisData.value = data
  } catch (err) {
    console.error('Failed to load aging KPIs:', err)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load aging KPIs',
      life: 3000,
    })
  } finally {
    loadingAgingKpis.value = false
  }
}

const onAgingBucketsChange = () => {
  loadAgingKpis()
}

// Utility Functions
const formatCurrency = (value, currency = 'USD') => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(value)
}

const formatValue = (value, format, currency = 'USD') => {
  switch (format) {
    case 'currency':
      return formatCurrency(value, currency)
    case 'percentage':
      return `${value.toFixed(1)}%`
    case 'days':
      return `${Math.round(value)} days`
    default:
      return new Intl.NumberFormat().format(value)
  }
}

const formatDateTime = (dateTime) => {
  return new Date(dateTime).toLocaleString()
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const getSummaryIcon = (label) => {
  const iconMap = {
    'Total Revenue': 'pi pi-dollar',
    'Total Expenses': 'pi pi-minus-circle',
    'Net Profit': 'pi pi-chart-line',
    'Cash Balance': 'pi pi-wallet',
  }
  return iconMap[label] || 'pi pi-info-circle'
}

const getChartOptions = (chartType) => {
  const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
      },
    },
  }

  switch (chartType) {
    case 'line':
      return {
        ...baseOptions,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
      }
    case 'bar':
      return {
        ...baseOptions,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
      }
    default:
      return baseOptions
  }
}

// Lifecycle
onMounted(async () => {
  await loadLayouts()
  if (selectedLayoutId.value) {
    await loadDashboard()
  }
  await loadAgingKpis()
})

onUnmounted(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }
})

// Watchers
watch(selectedLayoutId, () => {
  if (selectedLayoutId.value) {
    loadDashboard()
  }
})
</script>