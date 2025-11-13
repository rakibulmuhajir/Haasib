<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

const page = usePage()
const user = page.props.auth?.user
const trialBalance = ref(page.props.trialBalance)
const error = ref(page.props.error)
const filters = ref(page.props.filters || {})
const loading = ref(false)

// Filter state
const dateFrom = ref(filters.value.date_from || '')
const dateTo = ref(filters.value.date_to || new Date().toISOString().split('T')[0])
const includeZeroBalances = ref(filters.value.include_zero_balances || false)

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
      date_from: dateFrom.value,
      date_to: dateTo.value,
      include_zero_balances: includeZeroBalances.value,
    })

    const response = await fetch(`/ledger/reports/trial-balance/data?${params}`)
    if (response.ok) {
      const data = await response.json()
      trialBalance.value = data
      error.value = null
    } else {
      error.value = 'Failed to load trial balance data'
    }
  } catch (err) {
    error.value = 'Network error occurred'
  } finally {
    loading.value = false
  }
}

// Export to CSV
function exportToCSV() {
  if (!trialBalance.value?.accounts?.length) return

  const headers = ['Account Number', 'Account Name', 'Account Type', 'Debits', 'Credits', 'Balance']
  const rows = trialBalance.value.accounts.map(account => [
    account.account_number,
    account.account_name,
    account.account_type,
    account.debit.toFixed(2),
    account.credit.toFixed(2),
    account.balance.toFixed(2)
  ])

  const csvContent = [headers, ...rows]
    .map(row => row.map(cell => `"${cell}"`).join(','))
    .join('\n')

  const blob = new Blob([csvContent], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `trial-balance-${formatDate(new Date())}.csv`
  a.click()
  window.URL.revokeObjectURL(url)
}

// Get account type CSS class
const getAccountTypeClass = (accountType) => {
  const classes = {
    'Asset': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'Liability': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'Equity': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'Revenue': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    'Expense': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
  }
  return classes[accountType] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

// Print report
function printReport() {
  window.print()
}

onMounted(() => {
  if (!trialBalance.value && !error.value) {
    applyFilters()
  }
})
</script>

<template>
  <LayoutShell>
    <template #default>
      <!-- Page Header -->
      <UniversalPageHeader
        title="Trial Balance"
        description="View account balances and verify debits equal credits"
        :default-actions="[
          { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', action: applyFilters },
          { key: 'export', label: 'Export CSV', icon: 'pi pi-download', action: exportToCSV },
          { key: 'print', label: 'Print', icon: 'pi pi-print', action: printReport }
        ]"
        :show-search="false"
      />

      <template v-if="error">
        <!-- Error State -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-red-400"></i>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                  Error Loading Trial Balance
                </h3>
                <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                  <p>{{ error }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
      <template v-else-if="loading">
        <!-- Loading State -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="text-center">
            <div class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
              <i class="fas fa-spinner fa-spin mr-2"></i>
              Loading trial balance...
            </div>
          </div>
        </div>
      </template>
      <template v-else-if="trialBalance">
        <!-- Trial Balance Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-4">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
              <div class="flex items-center">
                <input
                  v-model="includeZeroBalances"
                  type="checkbox"
                  id="include-zero-balances"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                />
                <label for="include-zero-balances" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                  Include zero balances
                </label>
              </div>
            </div>
            <div class="flex items-end">
              <button
                @click="applyFilters"
                :disabled="loading"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                <i :class="loading ? 'fas fa-spinner fa-spin' : 'fas fa-search'" class="mr-2"></i>
                Apply Filters
              </button>
            </div>
          </div>
        </div>

        <!-- Report Header -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-6">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Trial Balance</h1>
              <p class="text-gray-600 dark:text-gray-400">{{ trialBalance.company_name }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Period: {{ trialBalance.period.date_from ? formatDate(trialBalance.period.date_from) : 'Beginning' }} to {{ formatDate(trialBalance.period.date_to) }}
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Generated: {{ formatDate(trialBalance.generated_at) }}
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Currency: {{ trialBalance.currency }}
              </p>
            </div>
          </div>

          <!-- Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Accounts</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ trialBalance.summary.account_count }}</p>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Debits</p>
              <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ formatCurrency(trialBalance.summary.total_debits, trialBalance.currency) }}
              </p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
              <p class="text-sm font-medium text-green-600 dark:text-green-400">Total Credits</p>
              <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ formatCurrency(trialBalance.summary.total_credits, trialBalance.currency) }}
              </p>
            </div>
            <div :class="trialBalance.summary.is_balanced ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'" class="rounded-lg p-4">
              <p :class="trialBalance.summary.is_balanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-sm font-medium">
                {{ trialBalance.summary.is_balanced ? 'Balanced' : 'Out of Balance' }}
              </p>
              <p :class="trialBalance.summary.is_balanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-2xl font-bold">
                {{ formatCurrency(trialBalance.summary.total_difference, trialBalance.currency) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Trial Balance Table -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Account Number
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Account Name
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Account Type
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Debits
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Credits
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Balance
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="account in trialBalance.accounts" :key="account.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    {{ account.account_number }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    {{ account.account_name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                          :class="getAccountTypeClass(account.account_type)">
                      {{ account.account_type }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                    {{ formatCurrency(account.debit, trialBalance.currency) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                    {{ formatCurrency(account.credit, trialBalance.currency) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right"
                      :class="account.balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                    {{ formatCurrency(account.balance, trialBalance.currency) }}
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th colspan="3" class="px-6 py-4 text-left text-sm font-medium text-gray-900 dark:text-white">
                    Totals
                  </th>
                  <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">
                    {{ formatCurrency(trialBalance.summary.total_debits, trialBalance.currency) }}
                  </td>
                  <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">
                    {{ formatCurrency(trialBalance.summary.total_credits, trialBalance.currency) }}
                  </td>
                  <td class="px-6 py-4 text-right text-sm font-medium"
                      :class="trialBalance.summary.is_balanced ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                    {{ formatCurrency(trialBalance.summary.total_difference, trialBalance.currency) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
      </template>
      <template v-else>
        <!-- No Data State -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 text-center">
          <i class="fas fa-chart-bar text-gray-400 text-5xl mb-4"></i>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Trial Balance Data</h3>
          <p class="text-gray-600 dark:text-gray-400">
            There are no journal entries for the selected period.
          </p>
        </div>
      </template>
    </template>
  </LayoutShell>
</template>
