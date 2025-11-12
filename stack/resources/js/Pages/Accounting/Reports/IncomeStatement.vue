<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

const page = usePage()
const user = page.props.auth?.user
const incomeStatement = ref(page.props.incomeStatement)
const error = ref(page.props.error)
const filters = ref(page.props.filters || {})
const loading = ref(false)

// Filter state
const dateFrom = ref(filters.value.date_from || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const dateTo = ref(filters.value.date_to || new Date().toISOString().split('T')[0])

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
  return `${value >= 0 ? '+' : ''}${value.toFixed(2)}%`
}

// Format date
const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString()
}

// Apply filters and refresh data
async function applyFilters() {
  loading.value = true
  try {
    const params = new URLSearchParams({
      date_from: dateFrom.value,
      date_to: dateTo.value,
    })

    const response = await fetch(`/ledger/reports/income-statement/data?${params}`)
    if (response.ok) {
      const data = await response.json()
      incomeStatement.value = data
      error.value = null
    } else {
      error.value = 'Failed to load income statement data'
    }
  } catch (err) {
    error.value = 'Network error occurred'
  } finally {
    loading.value = false
  }
}

// Export to CSV
function exportToCSV() {
  if (!incomeStatement.value?.sections) return

  let csvContent = "Income Statement Report\n"
  csvContent += `Period: ${formatDate(incomeStatement.value.period.date_from)} to ${formatDate(incomeStatement.value.period.date_to)}\n`
  csvContent += `Company: ${incomeStatement.value.company_name}\n`
  csvContent += `Currency: ${incomeStatement.value.currency}\n\n`

  // Revenue section
  csvContent += "REVENUE\n"
  csvContent += "Account Number,Account Name,Category,Amount\n"
  incomeStatement.value.sections.revenues.accounts.forEach(account => {
    csvContent += `${account.account_number},"${account.account_name}","${account.account_category}",${account.total.toFixed(2)}\n`
  })
  csvContent += `Total Revenue,,${incomeStatement.value.sections.revenues.total.toFixed(2)}\n\n`

  // Expense section
  csvContent += "EXPENSES\n"
  csvContent += "Account Number,Account Name,Category,Amount\n"
  incomeStatement.value.sections.expenses.accounts.forEach(account => {
    csvContent += `${account.account_number},"${account.account_name}","${account.account_category}",${account.total.toFixed(2)}\n`
  })
  csvContent += `Total Expenses,,${incomeStatement.value.sections.expenses.total.toFixed(2)}\n\n`

  // Summary
  csvContent += "SUMMARY\n"
  csvContent += `Gross Profit,,${incomeStatement.value.summary.gross_profit.toFixed(2)}\n`
  csvContent += `Net Income,,${incomeStatement.value.summary.net_income.toFixed(2)}\n`
  csvContent += `Profit Margin,,${incomeStatement.value.summary.profit_margin.toFixed(2)}%\n`

  const blob = new Blob([csvContent], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `income-statement-${formatDate(new Date())}.csv`
  a.click()
  window.URL.revokeObjectURL(url)
}

// Print report
function printReport() {
  window.print()
}

onMounted(() => {
  if (!incomeStatement.value && !error.value) {
    applyFilters()
  }
})
</script>

<template>
  <LayoutShell>
    <template #default>
      <!-- Page Header -->
      <UniversalPageHeader
        title="Income Statement"
        description="View company revenue, expenses, and profitability"
        :default-actions="[
          { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', action: applyFilters },
          { key: 'export', label: 'Export CSV', icon: 'pi pi-download', action: exportToCSV },
          { key: 'print', label: 'Print', icon: 'pi pi-print', action: printReport }
        ]"
        :show-search="false"
      />

      <!-- Error State -->
      <div v-if="error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-exclamation-triangle text-red-400"></i>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                Error Loading Income Statement
              </h3>
              <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                <p>{{ error }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center">
          <div class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
            <i class="fas fa-spinner fa-spin mr-2"></i>
            Loading income statement...
          </div>
        </div>
      </div>

      <!-- Income Statement Content -->
      <div v-else-if="incomeStatement" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Date From
              </label>
              <input
                v-model="dateFrom"
                type="date"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Date To
              </label>
              <input
                v-model="dateTo"
                type="date"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
              />
            </div>
            <div class="flex items-end">
              <button
                @click="applyFilters"
                :disabled="loading"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                <i :class="loading ? 'fas fa-spinner fa-spin' : 'fas fa-search'" class="mr-2"></i>
                Update Report
              </button>
            </div>
          </div>
        </div>

        <!-- Report Header -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-6">
          <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Income Statement</h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">{{ incomeStatement.company_name }}</p>
            <p class="text-gray-500 dark:text-gray-400">
              Period: {{ formatDate(incomeStatement.period.date_from) }} to {{ formatDate(incomeStatement.period.date_to) }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Generated: {{ formatDate(incomeStatement.generated_at) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Currency: {{ incomeStatement.currency }}</p>
          </div>

          <!-- Profitability Summary -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-green-600 dark:text-green-400">Total Revenue</p>
              <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ formatCurrency(incomeStatement.summary.total_revenue, incomeStatement.currency) }}
              </p>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-red-600 dark:text-red-400">Total Expenses</p>
              <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                {{ formatCurrency(incomeStatement.summary.total_expenses, incomeStatement.currency) }}
              </p>
            </div>
            <div :class="incomeStatement.summary.is_profitable ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'" class="rounded-lg p-4">
              <p :class="incomeStatement.summary.is_profitable ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-sm font-medium">
                Net Income
              </p>
              <p :class="incomeStatement.summary.is_profitable ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-2xl font-bold">
                {{ formatCurrency(incomeStatement.summary.net_income, incomeStatement.currency) }}
              </p>
              <p :class="incomeStatement.summary.is_profitable ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-sm">
                {{ incomeStatement.summary.is_profitable ? 'Profitable' : 'Loss' }}
              </p>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Profit Margin</p>
              <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                {{ formatPercentage(incomeStatement.summary.profit_margin) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Revenue Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="bg-green-600 text-white px-6 py-3">
              <h2 class="text-lg font-semibold">REVENUE</h2>
            </div>
            <div class="p-4">
              <div v-if="incomeStatement.sections.revenues.accounts.length > 0" class="space-y-3">
                <div v-for="account in incomeStatement.sections.revenues.accounts" :key="account.id" class="flex justify-between">
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ account.account_name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ account.account_number }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ account.account_category }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-green-600 dark:text-green-400">
                      {{ formatCurrency(account.total, incomeStatement.currency) }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-4 text-gray-500 dark:text-gray-400">
                No revenue accounts found for this period
              </div>
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between">
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">Total Revenue</p>
                  <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                    {{ formatCurrency(incomeStatement.sections.revenues.total, incomeStatement.currency) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Expense Section -->
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="bg-red-600 text-white px-6 py-3">
              <h2 class="text-lg font-semibold">EXPENSES</h2>
            </div>
            <div class="p-4">
              <div v-if="incomeStatement.sections.expenses.accounts.length > 0" class="space-y-3">
                <div v-for="account in incomeStatement.sections.expenses.accounts" :key="account.id" class="flex justify-between">
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ account.account_name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ account.account_number }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ account.account_category }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-red-600 dark:text-red-400">
                      {{ formatCurrency(account.total, incomeStatement.currency) }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-4 text-gray-500 dark:text-gray-400">
                No expense accounts found for this period
              </div>
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between">
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">Total Expenses</p>
                  <p class="text-lg font-semibold text-red-600 dark:text-red-400">
                    {{ formatCurrency(incomeStatement.sections.expenses.total, incomeStatement.currency) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Summary Section -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Income Statement Summary</h3>
          <div class="space-y-4">
            <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Revenue</span>
              <span class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ formatCurrency(incomeStatement.summary.total_revenue, incomeStatement.currency) }}
              </span>
            </div>
            
            <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Expenses</span>
              <span class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ formatCurrency(incomeStatement.summary.total_expenses, incomeStatement.currency) }}
              </span>
            </div>

            <div class="flex justify-between py-2">
              <span class="text-lg font-medium text-gray-700 dark:text-gray-300">Net Income (Loss)</span>
              <span :class="incomeStatement.summary.is_profitable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-lg font-bold">
                {{ formatCurrency(incomeStatement.summary.net_income, incomeStatement.currency) }}
              </span>
            </div>

            <div class="flex justify-between py-2 pt-4 border-t border-gray-200 dark:border-gray-700">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Profit Margin</span>
              <span :class="incomeStatement.summary.is_profitable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-sm font-bold">
                {{ formatPercentage(incomeStatement.summary.profit_margin) }}
              </span>
            </div>
          </div>

          <!-- Profitability Indicator -->
          <div class="mt-6 p-4 rounded-lg" :class="incomeStatement.summary.is_profitable ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 border'">
            <div class="flex items-center">
              <i :class="incomeStatement.summary.is_profitable ? 'fas fa-chart-line text-emerald-600 dark:text-emerald-400' : 'fas fa-chart-line-down text-red-600 dark:text-red-400'" class="text-2xl mr-3"></i>
              <div>
                <p :class="incomeStatement.summary.is_profitable ? 'text-emerald-800 dark:text-emerald-200' : 'text-red-800 dark:text-red-200'" class="font-medium">
                  {{ incomeStatement.summary.is_profitable ? 'Profitable Period' : 'Loss Period' }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  {{ incomeStatement.summary.is_profitable ? 'The company generated profit during this period' : 'The company incurred losses during this period' }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </LayoutShell>
</template>