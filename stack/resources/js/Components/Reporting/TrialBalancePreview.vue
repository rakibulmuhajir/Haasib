<template>
  <div class="space-y-6">
    <!-- Report Header -->
    <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Trial Balance
        </h3>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          As of {{ formatDate(data.as_of_date) }}
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="text-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
        <div class="text-sm text-gray-500 dark:text-gray-400">Total Accounts</div>
        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-2">
          {{ data.summary?.total_accounts || 0 }}
        </div>
      </div>
      
      <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
        <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">Total Debits</div>
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-2">
          {{ formatCurrency(data.summary?.total_debits || 0, currency) }}
        </div>
      </div>
      
      <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded">
        <div class="text-sm text-green-600 dark:text-green-400 font-medium">Total Credits</div>
        <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">
          {{ formatCurrency(data.summary?.total_credits || 0, currency) }}
        </div>
      </div>
      
      <div class="text-center p-4" :class="isBalanced ? 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'">
        <div class="text-sm font-medium" :class="isBalanced ? 'text-purple-600 dark:text-purple-400' : 'text-red-600 dark:text-red-400'">
          Balance Status
        </div>
        <div class="text-lg font-bold mt-2" :class="isBalanced ? 'text-purple-600' : 'text-red-600'">
          {{ isBalanced ? 'Balanced' : 'Out of Balance' }}
        </div>
      </div>
    </div>

    <!-- Filter and Search -->
    <div class="flex items-center justify-between space-x-4">
      <div class="flex items-center space-x-4 flex-1">
        <!-- Search -->
        <div class="relative flex-1 max-w-md">
          <i class="pi pi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Search accounts..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
          />
        </div>
        
        <!-- Account Type Filter -->
        <Dropdown
          v-model="selectedAccountType"
          :options="accountTypes"
          optionLabel="label"
          optionValue="value"
          placeholder="All Types"
          class="w-48"
          @change="filterAccounts"
        />
        
        <!-- Account Category Filter -->
        <Dropdown
          v-model="selectedCategory"
          :options="categories"
          optionLabel="label"
          optionValue="value"
          placeholder="All Categories"
          class="w-48"
          @change="filterAccounts"
        />
      </div>
      
      <div class="flex items-center space-x-2">
        <Button
          icon="pi pi-file-export"
          severity="secondary"
          text
          @click="exportData"
          v-tooltip="'Export to CSV'"
        />
        <Button
          icon="pi pi-refresh"
          severity="secondary"
          text
          @click="refreshData"
          v-tooltip="'Refresh'"
        />
      </div>
    </div>

    <!-- Trial Balance Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      <DataTable
        :value="filteredAccounts"
        :paginator="true"
        :rows="25"
        :stripedRows="true"
        :showGridlines="false"
        class="p-datatable-sm"
        :loading="loading"
        :globalFilterFields="['account_code', 'account_name', 'account_type', 'account_category']"
        :globalFilter="searchTerm"
        responsiveLayout="scroll"
      >
        <!-- Account Code -->
        <Column field="account_code" header="Code" style="min-width: 100px">
          <template #body="{ data }">
            <span class="font-medium text-gray-900 dark:text-white">{{ data.account_code }}</span>
          </template>
        </Column>

        <!-- Account Name -->
        <Column field="account_name" header="Account Name" style="min-width: 250px">
          <template #body="{ data }">
            <div class="flex items-center space-x-2">
              <button
                @click="$emit('drilldown', data.account_id, data.account_code, data.account_name)"
                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                v-tooltip="'View transactions'"
              >
                <i class="pi pi-arrow-right"></i>
              </button>
              <span class="font-medium text-gray-900 dark:text-white">{{ data.account_name }}</span>
            </div>
          </template>
        </Column>

        <!-- Account Type -->
        <Column field="account_type" header="Type" style="min-width: 120px">
          <template #body="{ data }">
            <Tag
              :value="data.account_type"
              :severity="getAccountTypeSeverity(data.account_type)"
            />
          </template>
        </Column>

        <!-- Account Category -->
        <Column field="account_category" header="Category" style="min-width: 150px">
          <template #body="{ data }">
            <span class="text-gray-700 dark:text-gray-300">{{ data.account_category }}</span>
          </template>
        </Column>

        <!-- Debit Balance -->
        <Column field="debit_balance" header="Debit" style="min-width: 150px" class="text-right">
          <template #body="{ data }">
            <span v-if="data.debit_balance > 0" class="font-medium text-blue-600 dark:text-blue-400">
              {{ formatCurrency(data.debit_balance, currency) }}
            </span>
            <span v-else class="text-gray-400">-</span>
          </template>
          <template #footer>
            <div class="font-bold text-blue-600 dark:text-blue-400">
              {{ formatCurrency(totalDebits, currency) }}
            </div>
          </template>
        </Column>

        <!-- Credit Balance -->
        <Column field="credit_balance" header="Credit" style="min-width: 150px" class="text-right">
          <template #body="{ data }">
            <span v-if="data.credit_balance > 0" class="font-medium text-green-600 dark:text-green-400">
              {{ formatCurrency(data.credit_balance, currency) }}
            </span>
            <span v-else class="text-gray-400">-</span>
          </template>
          <template #footer>
            <div class="font-bold text-green-600 dark:text-green-400">
              {{ formatCurrency(totalCredits, currency) }}
            </div>
          </template>
        </Column>

        <!-- Balance -->
        <Column field="balance" header="Balance" style="min-width: 150px" class="text-right">
          <template #body="{ data }">
            <div class="flex flex-col items-end">
              <span class="font-medium" :class="data.balance >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'">
                {{ formatCurrency(Math.abs(data.balance), currency) }}
              </span>
              <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ data.balance >= 0 ? 'Debit' : 'Credit' }}
              </span>
            </div>
          </template>
        </Column>

        <!-- Variance -->
        <Column field="variance_amount" header="Variance" style="min-width: 150px" class="text-right" v-if="showVariance">
          <template #body="{ data }">
            <div v-if="data.variance_amount !== undefined" class="flex flex-col items-end">
              <span class="font-medium" :class="getVarianceClass(data.variance_amount)">
                {{ formatCurrency(Math.abs(data.variance_amount), currency) }}
              </span>
              <span class="text-xs" :class="getVarianceClass(data.variance_percent)">
                {{ data.variance_percent >= 0 ? '+' : '' }}{{ data.variance_percent?.toFixed(1) || 0 }}%
              </span>
            </div>
          </template>
        </Column>

        <!-- Status -->
        <Column field="status" header="Status" style="min-width: 100px" class="text-center">
          <template #body="{ data }">
            <Tag
              :value="data.status || 'Active'"
              :severity="data.status === 'Inactive' ? 'danger' : 'success'"
              size="small"
            />
          </template>
        </Column>

        <!-- Actions -->
        <Column style="min-width: 100px" class="text-center">
          <template #body="{ data }">
            <div class="flex items-center justify-center space-x-1">
              <Button
                icon="pi pi-eye"
                severity="secondary"
                text
                size="small"
                @click="$emit('drilldown', data.account_id, data.account_code, data.account_name)"
                v-tooltip="'View transactions'"
              />
              <Button
                icon="pi pi-chart-line"
                severity="secondary"
                text
                size="small"
                @click="viewAccountTrend(data)"
                v-tooltip="'View trend'"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Variance Analysis -->
    <div v-if="showVariance && data.variance_analysis" class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Variance Analysis</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
          <div class="text-sm text-yellow-600 dark:text-yellow-400 font-medium">Accounts with Variance</div>
          <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-2">
            {{ data.variance_analysis?.variance_count || 0 }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ ((data.variance_analysis?.variance_count / (data.summary?.total_accounts || 1)) * 100).toFixed(1) }}% of total
          </div>
        </div>
        
        <div class="text-center p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded">
          <div class="text-sm text-orange-600 dark:text-orange-400 font-medium">Total Variance</div>
          <div class="text-2xl font-bold text-orange-600 dark:text-orange-400 mt-2">
            {{ formatCurrency(data.variance_analysis?.total_variance || 0, currency) }}
          </div>
        </div>
        
        <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded">
          <div class="text-sm text-red-600 dark:text-red-400 font-medium">Max Variance %</div>
          <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-2">
            {{ (data.variance_analysis?.max_variance_percent || 0).toFixed(1) }}%
          </div>
        </div>
      </div>
    </div>

    <!-- Account Type Summary -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Summary by Account Type</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="summary in accountTypeSummary" :key="summary.type" 
             class="p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ summary.type }}</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ summary.count }} accounts
          </div>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600 dark:text-gray-400">Debits:</span>
              <span class="font-medium text-blue-600 dark:text-blue-400">
                {{ formatCurrency(summary.debits, currency) }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600 dark:text-gray-400">Credits:</span>
              <span class="font-medium text-green-600 dark:text-green-400">
                {{ formatCurrency(summary.credits, currency) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  data: {
    type: Object,
    required: true
  },
  currency: {
    type: String,
    default: 'USD'
  },
  showVariance: {
    type: Boolean,
    default: true
  }
})

defineEmits(['drilldown'])

// State
const searchTerm = ref('')
const selectedAccountType = ref(null)
const selectedCategory = ref(null)
const loading = ref(false)

const accountTypes = ref([
  { label: 'All Types', value: null },
  { label: 'Assets', value: 'asset' },
  { label: 'Liabilities', value: 'liability' },
  { label: 'Equity', value: 'equity' },
  { label: 'Revenue', value: 'revenue' },
  { label: 'Expenses', value: 'expense' },
])

const categories = ref([
  { label: 'All Categories', value: null },
  { label: 'Current Assets', value: 'current_assets' },
  { label: 'Non-Current Assets', value: 'non_current_assets' },
  { label: 'Current Liabilities', value: 'current_liabilities' },
  { label: 'Non-Current Liabilities', value: 'non_current_liabilities' },
])

// Computed
const isBalanced = computed(() => {
  const totalDebits = props.data.summary?.total_debits || 0
  const totalCredits = props.data.summary?.total_credits || 0
  return Math.abs(totalDebits - totalCredits) < 0.01
})

const filteredAccounts = computed(() => {
  let accounts = props.data.accounts || []
  
  if (selectedAccountType.value) {
    accounts = accounts.filter(account => account.account_type === selectedAccountType.value)
  }
  
  if (selectedCategory.value) {
    accounts = accounts.filter(account => account.account_category === selectedCategory.value)
  }
  
  if (searchTerm.value) {
    const searchLower = searchTerm.value.toLowerCase()
    accounts = accounts.filter(account => 
      account.account_code.toLowerCase().includes(searchLower) ||
      account.account_name.toLowerCase().includes(searchLower) ||
      account.account_type.toLowerCase().includes(searchLower) ||
      account.account_category?.toLowerCase().includes(searchLower)
    )
  }
  
  return accounts
})

const totalDebits = computed(() => {
  return filteredAccounts.value.reduce((sum, account) => sum + (account.debit_balance || 0), 0)
})

const totalCredits = computed(() => {
  return filteredAccounts.value.reduce((sum, account) => sum + (account.credit_balance || 0), 0)
})

const accountTypeSummary = computed(() => {
  const summary = {}
  
  filteredAccounts.value.forEach(account => {
    const type = account.account_type
    if (!summary[type]) {
      summary[type] = {
        type: type.charAt(0).toUpperCase() + type.slice(1),
        count: 0,
        debits: 0,
        credits: 0
      }
    }
    
    summary[type].count++
    summary[type].debits += account.debit_balance || 0
    summary[type].credits += account.credit_balance || 0
  })
  
  return Object.values(summary)
})

// Methods
const getAccountTypeSeverity = (type) => {
  switch (type) {
    case 'asset': return 'info'
    case 'liability': return 'warning'
    case 'equity': return 'success'
    case 'revenue': return 'primary'
    case 'expense': return 'danger'
    default: return 'secondary'
  }
}

const getVarianceClass = (variance) => {
  if (Math.abs(variance) < 0.01) return 'text-gray-500'
  if (variance > 0) return 'text-green-600'
  return 'text-red-600'
}

const formatCurrency = (value, currency = 'USD') => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(value)
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const filterAccounts = () => {
  // Filtering is handled by computed property
}

const exportData = () => {
  // Export functionality
  const csvData = filteredAccounts.value.map(account => ({
    'Account Code': account.account_code,
    'Account Name': account.account_name,
    'Type': account.account_type,
    'Category': account.account_category,
    'Debit': account.debit_balance || 0,
    'Credit': account.credit_balance || 0,
    'Balance': account.balance || 0,
  }))
  
  // Convert to CSV and download
  const csv = convertToCSV(csvData)
  downloadCSV(csv, 'trial-balance.csv')
}

const convertToCSV = (data) => {
  if (!data.length) return ''
  
  const headers = Object.keys(data[0])
  const csvHeaders = headers.join(',')
  
  const csvRows = data.map(row => 
    headers.map(header => {
      const value = row[header]
      return typeof value === 'string' && value.includes(',') 
        ? `"${value}"` 
        : value
    }).join(',')
  )
  
  return [csvHeaders, ...csvRows].join('\n')
}

const downloadCSV = (csv, filename) => {
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  window.URL.revokeObjectURL(url)
}

const refreshData = () => {
  loading.value = true
  // Simulate refresh
  setTimeout(() => {
    loading.value = false
  }, 1000)
}

const viewAccountTrend = (account) => {
  // Open trend analysis modal or navigate to trend page
  console.log('View trend for account:', account)
}
</script>