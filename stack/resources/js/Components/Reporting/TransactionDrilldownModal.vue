<template>
  <div class="space-y-6">
    <!-- Modal Header -->
    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Transaction Drilldown
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Account: {{ accountCode }} | Period: {{ formatDateRange(dateRange.start, dateRange.end) }}
        </p>
      </div>
      <div class="flex items-center space-x-2">
        <Button
          icon="pi pi-filter"
          severity="secondary"
          text
          size="small"
          @click="showFilters = !showFilters"
          v-tooltip="'Filters'"
        />
        <Button
          icon="pi pi-download"
          severity="secondary"
          text
          size="small"
          @click="exportTransactions"
          v-tooltip="'Export'"
        />
        <Button
          icon="pi pi-times"
          severity="secondary"
          text
          size="small"
          @click="$emit('close')"
          v-tooltip="'Close'"
        />
      </div>
    </div>

    <!-- Filters Section -->
    <div v-if="showFilters" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Date Range Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Date Range
          </label>
          <Calendar
            v-model="filterDateRange"
            selectionMode="range"
            :manualInput="false"
            showIcon
            placeholder="Filter by date range"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <!-- Counterparty Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Counterparty
          </label>
          <Dropdown
            v-model="selectedCounterparty"
            :options="counterparties"
            optionLabel="name"
            optionValue="id"
            placeholder="All counterparties"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <!-- Amount Range Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Amount Range
          </label>
          <div class="flex items-center space-x-2">
            <input
              v-model.number="minAmount"
              type="number"
              placeholder="Min"
              class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
              @change="applyFilters"
            />
            <span class="text-gray-500">-</span>
            <input
              v-model.number="maxAmount"
              type="number"
              placeholder="Max"
              class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
              @change="applyFilters"
            />
          </div>
        </div>
      </div>

      <!-- Search and Reset -->
      <div class="flex items-center justify-between">
        <div class="flex-1 max-w-md">
          <div class="relative">
            <i class="pi pi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search descriptions..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
              @input="applyFilters"
            />
          </div>
        </div>
        
        <div class="flex items-center space-x-2">
          <Button
            label="Reset"
            severity="secondary"
            size="small"
            @click="resetFilters"
          />
          <Button
            label="Apply"
            size="small"
            @click="applyFilters"
          />
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
        <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">Total Transactions</div>
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-2">
          {{ summary.total_transactions || 0 }}
        </div>
      </div>
      
      <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded">
        <div class="text-sm text-green-600 dark:text-green-400 font-medium">Total Debits</div>
        <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">
          {{ formatCurrency(summary.total_debits || 0, currency) }}
        </div>
      </div>
      
      <div class="text-center p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded">
        <div class="text-sm text-orange-600 dark:text-orange-400 font-medium">Total Credits</div>
        <div class="text-2xl font-bold text-orange-600 dark:text-orange-400 mt-2">
          {{ formatCurrency(summary.total_credits || 0, currency) }}
        </div>
      </div>
      
      <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded">
        <div class="text-sm text-purple-600 dark:text-purple-400 font-medium">Net Balance</div>
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-2">
          {{ formatCurrency(summary.net_amount || 0, currency) }}
        </div>
      </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      <DataTable
        :value="filteredTransactions"
        :paginator="true"
        :rows="20"
        :stripedRows="true"
        :showGridlines="false"
        class="p-datatable-sm"
        :loading="loading"
        :sortField="sortField"
        :sortOrder="sortOrder"
        @sort="onSort"
        responsiveLayout="scroll"
      >
        <!-- Entry Date -->
        <Column field="entry_date" header="Date" style="min-width: 120px" sortable>
          <template #body="{ data }">
            <span class="text-gray-900 dark:text-white">
              {{ formatDate(data.entry_date) }}
            </span>
          </template>
        </Column>

        <!-- Entry Number -->
        <Column field="entry_number" header="Entry #" style="min-width: 100px" sortable>
          <template #body="{ data }">
            <span class="font-medium text-blue-600 dark:text-blue-400">
              #{{ data.entry_number }}
            </span>
          </template>
        </Column>

        <!-- Description -->
        <Column field="description" header="Description" style="min-width: 300px">
          <template #body="{ data }">
            <div>
              <div class="font-medium text-gray-900 dark:text-white">
                {{ data.entry_description || data.description }}
              </div>
              <div v-if="data.line_description && data.line_description !== data.entry_description" 
                   class="text-sm text-gray-500 dark:text-gray-400">
                {{ data.line_description }}
              </div>
            </div>
          </template>
        </Column>

        <!-- Counterparty -->
        <Column field="counterparty_name" header="Counterparty" style="min-width: 150px">
          <template #body="{ data }">
            <span v-if="data.counterparty_name" class="text-gray-700 dark:text-gray-300">
              {{ data.counterparty_name }}
            </span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </Column>

        <!-- Amount -->
        <Column field="amount" header="Amount" style="min-width: 120px" class="text-right" sortable>
          <template #body="{ data }">
            <div class="flex flex-col items-end">
              <span class="font-medium" :class="data.amount >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ formatCurrency(Math.abs(data.amount), currency) }}
              </span>
              <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ data.amount >= 0 ? 'Debit' : 'Credit' }}
              </span>
            </div>
          </template>
        </Column>

        <!-- Currency -->
        <Column field="currency_symbol" header="Currency" style="min-width: 80px" class="text-center">
          <template #body="{ data }">
            <span class="text-gray-700 dark:text-gray-300">
              {{ data.currency_symbol || 'USD' }}
            </span>
          </template>
        </Column>

        <!-- Reference -->
        <Column field="reference_number" header="Reference" style="min-width: 120px">
          <template #body="{ data }">
            <div v-if="data.reference_number" class="flex items-center space-x-2">
              <span class="text-gray-700 dark:text-gray-300">
                {{ data.reference_number }}
              </span>
              <Tag
                v-if="data.reference_type"
                :value="data.reference_type"
                severity="secondary"
                size="small"
              />
            </div>
            <span v-else class="text-gray-400">-</span>
          </template>
        </Column>

        <!-- Actions -->
        <Column style="min-width: 100px" class="text-center">
          <template #body="{ data }">
            <div class="flex items-center justify-center space-x-1">
              <Button
                icon="pi pi-external-link"
                severity="secondary"
                text
                size="small"
                @click="viewJournalEntry(data.journal_entry_id)"
                v-tooltip="'View journal entry'"
              />
              <Button
                v-if="data.document_reference_id"
                icon="pi pi-file"
                severity="secondary"
                text
                size="small"
                @click="viewDocument(data.document_reference_id)"
                v-tooltip="'View document'"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Running Balance Chart -->
    <div v-if="showBalanceChart" class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <div class="flex items-center justify-between mb-4">
        <h4 class="text-md font-medium text-gray-900 dark:text-white">Running Balance</h4>
        <Button
          :label="showBalanceChart ? 'Hide Chart' : 'Show Chart'"
          icon="pi pi-chart-line"
          severity="secondary"
          text
          size="small"
          @click="showBalanceChart = !showBalanceChart"
        />
      </div>
      
      <div v-if="showBalanceChart" class="h-64">
        <Chart
          type="line"
          :data="balanceChartData"
          :options="balanceChartOptions"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useReportingStatements } from '@/services/reportingStatements'

const props = defineProps({
  accountId: {
    type: String,
    required: true
  },
  accountCode: {
    type: String,
    required: true
  },
  dateRange: {
    type: Object,
    required: true
  },
  currency: {
    type: String,
    default: 'USD'
  }
})

defineEmits(['close'])

const { getTransactionDrilldown } = useReportingStatements()

// State
const loading = ref(false)
const transactions = ref([])
const summary = ref({})
const counterparties = ref([])
const showFilters = ref(false)
const showBalanceChart = ref(false)

// Filter State
const searchTerm = ref('')
const filterDateRange = ref(null)
const selectedCounterparty = ref(null)
const minAmount = ref(null)
const maxAmount = ref(null)

// Sort State
const sortField = ref('entry_date')
const sortOrder = ref(-1) // -1 for descending

// Computed
const filteredTransactions = computed(() => {
  let filtered = transactions.value

  if (searchTerm.value) {
    const search = searchTerm.value.toLowerCase()
    filtered = filtered.filter(t => 
      t.description?.toLowerCase().includes(search) ||
      t.entry_description?.toLowerCase().includes(search) ||
      t.line_description?.toLowerCase().includes(search) ||
      t.reference_number?.toLowerCase().includes(search)
    )
  }

  if (filterDateRange.value && filterDateRange.value.length === 2) {
    const [start, end] = filterDateRange.value
    filtered = filtered.filter(t => {
      const date = new Date(t.entry_date)
      return date >= start && date <= end
    })
  }

  if (selectedCounterparty.value) {
    filtered = filtered.filter(t => t.counterparty_id === selectedCounterparty.value)
  }

  if (minAmount.value !== null) {
    filtered = filtered.filter(t => Math.abs(t.amount) >= minAmount.value)
  }

  if (maxAmount.value !== null) {
    filtered = filtered.filter(t => Math.abs(t.amount) <= maxAmount.value)
  }

  // Apply sorting
  return filtered.sort((a, b) => {
    let aVal = a[sortField.value]
    let bVal = b[sortField.value]

    if (aVal === null || aVal === undefined) return 1
    if (bVal === null || bVal === undefined) return -1

    if (typeof aVal === 'string') aVal = aVal.toLowerCase()
    if (typeof bVal === 'string') bVal = bVal.toLowerCase()

    if (aVal < bVal) return sortOrder.value
    if (aVal > bVal) return -sortOrder.value
    return 0
  })
})

const balanceChartData = computed(() => {
  let runningBalance = 0
  const balanceData = transactions.value.map(t => {
    runningBalance += t.amount || 0
    return {
      x: t.entry_date,
      y: runningBalance
    }
  })

  return {
    labels: balanceData.map(d => formatDate(d.x, true)),
    datasets: [{
      label: 'Running Balance',
      data: balanceData.map(d => d.y),
      borderColor: 'rgb(59, 130, 246)',
      backgroundColor: 'rgba(59, 130, 246, 0.1)',
      fill: true,
      tension: 0.1
    }]
  }
})

const balanceChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: function(value) {
          return formatCurrency(value, props.currency, true)
        }
      }
    }
  }
}))

// Methods
const loadTransactions = async () => {
  if (!props.accountId) return

  loading.value = true
  try {
    const response = await getTransactionDrilldown({
      account_id: props.accountId,
      date_from: props.dateRange.start,
      date_to: props.dateRange.end,
      include_running_balances: true
    })

    transactions.value = response.data.transactions || []
    summary.value = response.data.summary || {}
    
    // Extract unique counterparties
    const uniqueCounterparties = [...new Map(
      transactions.value
        .filter(t => t.counterparty_id && t.counterparty_name)
        .map(t => [t.counterparty_id, { id: t.counterparty_id, name: t.counterparty_name }])
    ).values()]
    
    counterparties.value = [{ id: null, name: 'All Counterparties' }, ...uniqueCounterparties]
  } catch (error) {
    console.error('Failed to load transactions:', error)
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  // Filters are applied via computed property
}

const resetFilters = () => {
  searchTerm.value = ''
  filterDateRange.value = null
  selectedCounterparty.value = null
  minAmount.value = null
  maxAmount.value = null
}

const onSort = (event) => {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
}

const viewJournalEntry = (entryId) => {
  // Navigate to journal entry detail page
  window.open(`/ledger/journal/entries/${entryId}`, '_blank')
}

const viewDocument = (documentId) => {
  // Open document viewer
  window.open(`/documents/${documentId}`, '_blank')
}

const exportTransactions = () => {
  const csvData = filteredTransactions.value.map(t => ({
    'Date': formatDate(t.entry_date),
    'Entry Number': t.entry_number,
    'Description': t.description || t.entry_description,
    'Counterparty': t.counterparty_name || '',
    'Amount': t.amount || 0,
    'Currency': t.currency_symbol || 'USD',
    'Reference': t.reference_number || '',
    'Reference Type': t.reference_type || '',
  }))

  const csv = convertToCSV(csvData)
  downloadCSV(csv, `transactions-${props.accountCode}.csv`)
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

const formatDate = (date, short = false) => {
  if (!date) return ''
  
  const dateObj = new Date(date)
  
  if (short) {
    return dateObj.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric'
    })
  }
  
  return dateObj.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatDateRange = (startDate, endDate) => {
  if (!startDate || !endDate) return ''
  return `${formatDate(startDate)} - ${formatDate(endDate)}`
}

const formatCurrency = (value, currency = 'USD', compact = false) => {
  if (compact && Math.abs(value) >= 1000000) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value / 1000000) + 'M'
  }
  
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(value)
}

// Lifecycle
onMounted(() => {
  loadTransactions()
})

// Watchers
watch([() => props.accountId, () => props.dateRange], () => {
  loadTransactions()
}, { deep: true })
</script>