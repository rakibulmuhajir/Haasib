<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

const page = usePage()
const user = page.props.auth?.user
const balanceSheet = ref(page.props.balanceSheet)
const error = ref(page.props.error)
const filters = ref(page.props.filters || {})
const loading = ref(false)

// Filter state
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

// Format date
const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString()
}

// Apply filters and refresh data
async function applyFilters() {
  loading.value = true
  try {
    const params = new URLSearchParams({
      date_to: dateTo.value,
    })

    const response = await fetch(`/ledger/reports/balance-sheet/data?${params}`)
    if (response.ok) {
      const data = await response.json()
      balanceSheet.value = data
      error.value = null
    } else {
      error.value = 'Failed to load balance sheet data'
    }
  } catch (err) {
    error.value = 'Network error occurred'
  } finally {
    loading.value = false
  }
}

// Export to CSV
function exportToCSV() {
  if (!balanceSheet.value?.sections) return

  let csvContent = "Balance Sheet Report\n"
  csvContent += `As of: ${formatDate(balanceSheet.value.as_of_date)}\n`
  csvContent += `Company: ${balanceSheet.value.company_name}\n`
  csvContent += `Currency: ${balanceSheet.value.currency}\n\n`

  // Assets section
  csvContent += "ASSETS\n"
  csvContent += "Account Number,Account Name,Balance\n"
  balanceSheet.value.sections.assets.accounts.forEach(account => {
    csvContent += `${account.account_number},"${account.account_name}",${account.balance.toFixed(2)}\n`
  })
  csvContent += `Total Assets,,${balanceSheet.value.sections.assets.total.toFixed(2)}\n\n`

  // Liabilities section
  csvContent += "LIABILITIES\n"
  csvContent += "Account Number,Account Name,Balance\n"
  balanceSheet.value.sections.liabilities.accounts.forEach(account => {
    csvContent += `${account.account_number},"${account.account_name}",${account.balance.toFixed(2)}\n`
  })
  csvContent += `Total Liabilities,,${balanceSheet.value.sections.liabilities.total.toFixed(2)}\n\n`

  // Equity section
  csvContent += "EQUITY\n"
  csvContent += "Account Number,Account Name,Balance\n"
  balanceSheet.value.sections.equity.accounts.forEach(account => {
    const prefix = account.is_calculated ? '*' : ''
    csvContent += `${account.account_number},"${prefix}${account.account_name}",${account.balance.toFixed(2)}\n`
  })
  csvContent += `Total Equity,,${balanceSheet.value.sections.equity.total.toFixed(2)}\n\n`

  csvContent += `Total Liabilities and Equity,,${balanceSheet.value.summary.total_liabilities_and_equity.toFixed(2)}\n`

  const blob = new Blob([csvContent], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `balance-sheet-${formatDate(new Date())}.csv`
  a.click()
  window.URL.revokeObjectURL(url)
}

// Print report
function printReport() {
  window.print()
}

onMounted(() => {
  if (!balanceSheet.value && !error.value) {
    applyFilters()
  }
})
</script>

<template>
  <LayoutShell>
    <template #default>
      <!-- Page Header -->
      <UniversalPageHeader
        title="Balance Sheet"
        description="View company assets, liabilities, and equity positions"
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
                Error Loading Balance Sheet
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
            Loading balance sheet...
          </div>
        </div>
      </div>

      <!-- Balance Sheet Content -->
      <div v-else-if="balanceSheet" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                As of Date
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
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Balance Sheet</h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">{{ balanceSheet.company_name }}</p>
            <p class="text-gray-500 dark:text-gray-400">As of {{ formatDate(balanceSheet.as_of_date) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Generated: {{ formatDate(balanceSheet.generated_at) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Currency: {{ balanceSheet.currency }}</p>
          </div>

          <!-- Balance Check -->
          <div :class="balanceSheet.summary.is_balanced ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'" 
               class="border rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <i :class="balanceSheet.summary.is_balanced ? 'fas fa-check-circle text-emerald-600 dark:text-emerald-400' : 'fas fa-exclamation-triangle text-red-600 dark:text-red-400'" class="text-2xl mr-3"></i>
                <div>
                  <p :class="balanceSheet.summary.is_balanced ? 'text-emerald-800 dark:text-emerald-200' : 'text-red-800 dark:text-red-200'" class="font-medium">
                    {{ balanceSheet.summary.is_balanced ? 'Balance Sheet is Balanced' : 'Balance Sheet is Out of Balance' }}
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Assets: {{ formatCurrency(balanceSheet.summary.total_assets, balanceSheet.currency) }}
                    = Liabilities & Equity: {{ formatCurrency(balanceSheet.summary.total_liabilities_and_equity, balanceSheet.currency) }}
                  </p>
                </div>
              </div>
              <div v-if="!balanceSheet.summary.is_balanced" class="text-right">
                <p class="text-lg font-semibold text-red-600 dark:text-red-400">
                  Difference: {{ formatCurrency(balanceSheet.summary.difference, balanceSheet.currency) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Balance Sheet Sections -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Assets Section -->
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="bg-blue-600 text-white px-6 py-3">
              <h2 class="text-lg font-semibold">ASSETS</h2>
            </div>
            <div class="p-4">
              <div class="space-y-3">
                <div v-for="account in balanceSheet.sections.assets.accounts" :key="account.id" class="flex justify-between">
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ account.account_name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ account.account_number }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ formatCurrency(account.balance, balanceSheet.currency) }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between">
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">Total Assets</p>
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(balanceSheet.sections.assets.total, balanceSheet.currency) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Liabilities Section -->
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="bg-yellow-600 text-white px-6 py-3">
              <h2 class="text-lg font-semibold">LIABILITIES</h2>
            </div>
            <div class="p-4">
              <div class="space-y-3">
                <div v-for="account in balanceSheet.sections.liabilities.accounts" :key="account.id" class="flex justify-between">
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ account.account_name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ account.account_number }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ formatCurrency(account.balance, balanceSheet.currency) }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between">
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">Total Liabilities</p>
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(balanceSheet.sections.liabilities.total, balanceSheet.currency) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Equity Section -->
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="bg-green-600 text-white px-6 py-3">
              <h2 class="text-lg font-semibold">EQUITY</h2>
            </div>
            <div class="p-4">
              <div class="space-y-3">
                <div v-for="account in balanceSheet.sections.equity.accounts" :key="'eq-' + (account.id || 'calculated')" class="flex justify-between">
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ account.account_name }}
                      <span v-if="account.is_calculated" class="text-xs text-gray-500 ml-1">(calculated)</span>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ account.account_number }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ formatCurrency(account.balance, balanceSheet.currency) }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between">
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">Total Equity</p>
                  <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(balanceSheet.sections.equity.total, balanceSheet.currency) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Summary Section -->
        <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Summary</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Assets</p>
              <p class="text-xl font-bold text-blue-600 dark:text-blue-400">
                {{ formatCurrency(balanceSheet.summary.total_assets, balanceSheet.currency) }}
              </p>
            </div>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Total Liabilities</p>
              <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">
                {{ formatCurrency(balanceSheet.summary.total_liabilities, balanceSheet.currency) }}
              </p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-green-600 dark:text-green-400">Total Equity</p>
              <p class="text-xl font-bold text-green-600 dark:text-green-400">
                {{ formatCurrency(balanceSheet.summary.total_equity, balanceSheet.currency) }}
              </p>
            </div>
            <div :class="balanceSheet.summary.is_balanced ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'" class="rounded-lg p-4">
              <p :class="balanceSheet.summary.is_balanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-sm font-medium">
                Balance Check
              </p>
              <p :class="balanceSheet.summary.is_balanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-xl font-bold">
                {{ balanceSheet.summary.is_balanced ? 'Balanced' : 'Out of Balance' }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </LayoutShell>
</template>