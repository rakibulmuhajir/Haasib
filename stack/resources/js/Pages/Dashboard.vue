<script setup>
import { ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { usePageActions } from '@/composables/usePageActions'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'

const page = usePage()
const user = page.props.auth?.user
const { actions } = usePageActions()

// Get dashboard data from props
const metrics = ref(page.props.metrics)
const company = ref(page.props.company)
const error = ref(page.props.error)
const lastUpdated = ref(page.props.lastUpdated)
const loading = ref(false)

// Define page actions for the dashboard
const dashboardActions = [
  {
    key: 'new-invoice',
    label: 'New Invoice',
    icon: 'pi pi-plus',
    severity: 'success',
    routeName: 'invoices.create'
  },
  {
    key: 'new-customer',
    label: 'New Customer',
    icon: 'pi pi-plus',
    severity: 'info',
    routeName: 'customers.create'
  },
  {
    key: 'refresh-metrics',
    label: 'Refresh',
    icon: 'pi pi-refresh',
    severity: 'secondary',
    action: refreshMetrics
  }
]

// Define quick links for the dashboard
const quickLinks = [
  {
    label: 'Manage Invoices',
    url: '/invoices',
    icon: 'pi pi-file'
  },
  {
    label: 'Customer Management',
    url: '/customers',
    icon: 'pi pi-users'
  },
  {
    label: 'Accounting Ledger',
    url: '/ledger',
    icon: 'pi pi-book'
  },
  {
    label: 'Financial Reports',
    url: '/reporting/dashboard',
    icon: 'pi pi-chart-bar'
  },
  {
    label: 'Company Settings',
    url: '/companies/' + company.value?.id + '/settings',
    icon: 'pi pi-cog'
  }
]

// Set page actions
actions.value = dashboardActions

// Format currency
const formatCurrency = (amount, currency = 'USD') => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

// Format percentage
const formatPercentage = (value) => {
  return `${value > 0 ? '+' : ''}${value.toFixed(1)}%`
}

// Get trend icon and color
const getTrendClass = (value) => {
  if (value > 0) return 'text-green-600'
  if (value < 0) return 'text-red-600'
  return 'text-gray-600'
}

const getTrendIcon = (value) => {
  if (value > 0) return 'pi pi-arrow-up'
  if (value < 0) return 'pi pi-arrow-down'
  return 'pi pi-minus'
}

// Refresh dashboard metrics
async function refreshMetrics() {
  loading.value = true
  try {
    const response = await fetch('/api/dashboard/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      metrics.value = data.metrics
      lastUpdated.value = data.lastUpdated
    }
  } catch (error) {
    console.error('Failed to refresh metrics:', error)
  } finally {
    loading.value = false
  }
}
</script>


<template>
  <LayoutShell>
    <template #default>
      <!-- Universal Page Header -->
      <UniversalPageHeader
        title="Dashboard"
        :description="metrics ? `Welcome back, ${user?.name || 'User'}!` : (error || 'Loading...')"
        :subDescription="company ? `Viewing metrics for ${company.name}` : 'Select a company to view metrics'"
        :default-actions="dashboardActions"
        :show-search="false"
      />

      <!-- Error State -->
      <div v-if="error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                Dashboard Metrics Unavailable
              </h3>
              <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <p>{{ error }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Dashboard Content -->
      <div v-else-if="metrics" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Key Metrics Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Cash Balance -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-wallet text-green-600 text-2xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                      Cash Balance
                    </dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                      {{ formatCurrency(metrics.cash_balance, company?.currency) }}
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Outstanding Invoices -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-file-invoice-dollar text-blue-600 text-2xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                      Outstanding Invoices
                    </dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                      {{ formatCurrency(metrics.outstanding_invoices.outstanding_amount, company?.currency) }}
                    </dd>
                  </dl>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ metrics.outstanding_invoices.count }} invoices
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
              <div class="text-sm">
                <Link href="/invoices" class="font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400">
                  View invoices →
                </Link>
              </div>
            </div>
          </div>

          <!-- Monthly Revenue -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-chart-line text-green-600 text-2xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                      Monthly Revenue
                    </dt>
                    <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                      {{ formatCurrency(metrics.monthly_revenue.total, company?.currency) }}
                    </dd>
                  </dl>
                  <div class="flex items-center text-sm" :class="getTrendClass(metrics.monthly_revenue.growth_vs_last_month)">
                    <i :class="getTrendIcon(metrics.monthly_revenue.growth_vs_last_month)" class="mr-1"></i>
                    {{ formatPercentage(metrics.monthly_revenue.growth_vs_last_month) }} vs last month
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Net Income -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i :class="metrics.net_income.amount >= 0 ? 'fas fa-chart-pie text-green-600' : 'fas fa-chart-pie text-red-600'" class="text-2xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                      Net Income (Monthly)
                    </dt>
                    <dd class="text-2xl font-semibold" :class="metrics.net_income.amount >= 0 ? 'text-green-600' : 'text-red-600'">
                      {{ formatCurrency(metrics.net_income.amount, company?.currency) }}
                    </dd>
                  </dl>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ metrics.net_income.profit_margin.toFixed(1) }}% profit margin
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Additional Metrics Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
          <!-- Customers Stats -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Customers</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Total Customers</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ metrics.total_customers }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Collection Rate</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ metrics.collection_rate.toFixed(1) }}%</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Avg Days Outstanding</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ metrics.outstanding_invoices.average_days_outstanding }} days</span>
                </div>
              </div>
              <div class="mt-4">
                <Link href="/customers" class="text-sm font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400">
                  Manage customers →
                </Link>
              </div>
            </div>
          </div>

          <!-- Financial Health -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Financial Health</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Accounts Receivable</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ formatCurrency(metrics.accounts_receivable, company?.currency) }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Accounts Payable</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ formatCurrency(metrics.accounts_payable, company?.currency) }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Monthly Expenses</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ formatCurrency(metrics.monthly_expenses.total, company?.currency) }}
                  </span>
                </div>
              </div>
              <div class="mt-4">
                <Link href="/ledger/reports" class="text-sm font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400">
                  View financial reports →
                </Link>
              </div>
            </div>
          </div>

          <!-- Overdue Invoices Alert -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                Overdue Invoices
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Overdue Amount</span>
                  <span class="text-sm font-medium text-red-600">
                    {{ formatCurrency(metrics.overdue_invoices.overdue_amount, company?.currency) }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">Overdue Count</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ metrics.overdue_invoices.count }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400">% of Receivables</span>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ metrics.overdue_invoices.percentage_of_receivables.toFixed(1) }}%
                  </span>
                </div>
              </div>
              <div class="mt-4">
                <Link href="/invoices?status=overdue" class="text-sm font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400">
                  View overdue invoices →
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Customers and Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Top Customers -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Top Customers by Revenue</h3>
              <div v-if="metrics.top_customers.length > 0" class="space-y-3">
                <div v-for="(customer, index) in metrics.top_customers" :key="customer.name" class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-sm font-medium text-blue-600 dark:text-blue-300">
                      {{ index + 1 }}
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ customer.name }}</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ customer.invoice_count }} invoices</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ formatCurrency(customer.revenue, company?.currency) }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                No customer data available
              </div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Activity</h3>
              <div v-if="metrics.recent_activity.length > 0" class="space-y-3">
                <div v-for="activity in metrics.recent_activity" :key="activity.date + activity.description" class="flex items-center justify-between">
                  <div class="flex items-center">
                    <i :class="activity.type === 'invoice' ? 'fas fa-file-invoice text-blue-500' : 'fas fa-credit-card text-green-500'" class="w-4 h-4"></i>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ activity.description }}</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ activity.date }}</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ formatCurrency(activity.amount, company?.currency) }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                No recent activity
              </div>
            </div>
          </div>
        </div>

        <!-- Last Updated -->
        <div class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
          <p v-if="loading">Refreshing metrics...</p>
          <p v-else-if="lastUpdated">Last updated: {{ new Date(lastUpdated).toLocaleString() }}</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center">
          <div class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-spinner fa-spin mr-2"></i>
            Loading dashboard metrics...
          </div>
        </div>
      </div>
    </template>
  </LayoutShell>
</template>