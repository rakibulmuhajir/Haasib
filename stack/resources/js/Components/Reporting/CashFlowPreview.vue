<template>
  <div class="space-y-6">
    <!-- Report Header -->
    <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Cash Flow Statement
        </h3>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          {{ formatDateRange(data.period?.start, data.period?.end) }}
        </div>
      </div>
    </div>

    <!-- Cash Flow Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded">
        <div class="text-sm text-green-600 dark:text-green-400 font-medium">Net Cash Flow</div>
        <div class="text-2xl font-bold mt-2" :class="netCashFlow >= 0 ? 'text-green-600' : 'text-red-600'">
          {{ formatCurrency(netCashFlow, currency) }}
        </div>
        <div v-if="data.comparison?.net_cash_flow_variance" class="text-sm mt-1">
          <span :class="data.comparison.net_cash_flow_variance >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ data.comparison.net_cash_flow_variance >= 0 ? '+' : '' }}{{ data.comparison.net_cash_flow_variance.toFixed(1) }}%
          </span>
        </div>
      </div>
      
      <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
        <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">Opening Balance</div>
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-2">
          {{ formatCurrency(data.opening_balance || 0, currency) }}
        </div>
      </div>
      
      <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded">
        <div class="text-sm text-purple-600 dark:text-purple-400 font-medium">Closing Balance</div>
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-2">
          {{ formatCurrency(data.closing_balance || 0, currency) }}
        </div>
      </div>
    </div>

    <!-- Operating Activities -->
    <div class="space-y-3">
      <h4 class="text-md font-medium text-gray-900 dark:text-white flex items-center">
        <i class="pi pi-sync mr-2 text-blue-500"></i>
        Cash Flow from Operating Activities
      </h4>
      
      <div class="ml-6 space-y-2">
        <div
          v-for="item in data.operating_activities?.items || []"
          :key="item.account_code"
          class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
          @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
        >
          <div class="flex items-center space-x-2">
            <i class="pi pi-arrow-right text-gray-400"></i>
            <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
          </div>
          <div class="text-right">
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.amount, currency) }}
            </span>
            <div v-if="item.variance_percent !== undefined" class="text-xs">
              <span :class="item.variance_percent >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ item.variance_percent >= 0 ? '+' : '' }}{{ item.variance_percent.toFixed(1) }}%
              </span>
            </div>
          </div>
        </div>
        
        <!-- Net Cash from Operations -->
        <div class="flex items-center justify-between py-3 px-3 bg-blue-50 dark:bg-blue-900/20 rounded font-semibold">
          <span>Net Cash from Operating Activities</span>
          <div class="text-right">
            <div>{{ formatCurrency(data.operating_activities?.net || 0, currency) }}</div>
            <div v-if="data.operating_activities?.variance_percent !== undefined" class="text-sm">
              <span :class="data.operating_activities.variance_percent >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ data.operating_activities.variance_percent >= 0 ? '+' : '' }}{{ data.operating_activities.variance_percent.toFixed(1) }}%
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Investing Activities -->
    <div class="space-y-3">
      <h4 class="text-md font-medium text-gray-900 dark:text-white flex items-center">
        <i class="pi pi-chart-line mr-2 text-green-500"></i>
        Cash Flow from Investing Activities
      </h4>
      
      <div class="ml-6 space-y-2">
        <div
          v-for="item in data.investing_activities?.items || []"
          :key="item.account_code"
          class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
          @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
        >
          <div class="flex items-center space-x-2">
            <i class="pi pi-arrow-right text-gray-400"></i>
            <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
          </div>
          <div class="text-right">
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.amount, currency) }}
            </span>
            <div v-if="item.variance_percent !== undefined" class="text-xs">
              <span :class="item.variance_percent <= 0 ? 'text-green-600' : 'text-red-600'">
                {{ item.variance_percent <= 0 ? '' : '+' }}{{ item.variance_percent.toFixed(1) }}%
              </span>
            </div>
          </div>
        </div>
        
        <!-- Net Cash from Investing -->
        <div class="flex items-center justify-between py-3 px-3 bg-green-50 dark:bg-green-900/20 rounded font-semibold">
          <span>Net Cash from Investing Activities</span>
          <div class="text-right">
            <div>{{ formatCurrency(data.investing_activities?.net || 0, currency) }}</div>
            <div v-if="data.investing_activities?.variance_percent !== undefined" class="text-sm">
              <span :class="data.investing_activities.variance_percent <= 0 ? 'text-green-600' : 'text-red-600'">
                {{ data.investing_activities.variance_percent <= 0 ? '' : '+' }}{{ data.investing_activities.variance_percent.toFixed(1) }}%
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Financing Activities -->
    <div class="space-y-3">
      <h4 class="text-md font-medium text-gray-900 dark:text-white flex items-center">
        <i class="pi pi-dollar mr-2 text-purple-500"></i>
        Cash Flow from Financing Activities
      </h4>
      
      <div class="ml-6 space-y-2">
        <div
          v-for="item in data.financing_activities?.items || []"
          :key="item.account_code"
          class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
          @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
        >
          <div class="flex items-center space-x-2">
            <i class="pi pi-arrow-right text-gray-400"></i>
            <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
          </div>
          <div class="text-right">
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.amount, currency) }}
            </span>
            <div v-if="item.variance_percent !== undefined" class="text-xs">
              <span :class="item.variance_percent >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ item.variance_percent >= 0 ? '+' : '' }}{{ item.variance_percent.toFixed(1) }}%
              </span>
            </div>
          </div>
        </div>
        
        <!-- Net Cash from Financing -->
        <div class="flex items-center justify-between py-3 px-3 bg-purple-50 dark:bg-purple-900/20 rounded font-semibold">
          <span>Net Cash from Financing Activities</span>
          <div class="text-right">
            <div>{{ formatCurrency(data.financing_activities?.net || 0, currency) }}</div>
            <div v-if="data.financing_activities?.variance_percent !== undefined" class="text-sm">
              <span :class="data.financing_activities.variance_percent >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ data.financing_activities.variance_percent >= 0 ? '+' : '' }}{{ data.financing_activities.variance_percent.toFixed(1) }}%
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reconciliation -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Cash Reconciliation</h4>
      
      <div class="space-y-2">
        <div class="flex items-center justify-between py-2 px-3">
          <span class="text-gray-700 dark:text-gray-300">Opening Cash Balance</span>
          <span class="font-medium text-gray-900 dark:text-white">
            {{ formatCurrency(data.opening_balance || 0, currency) }}
          </span>
        </div>
        
        <div class="flex items-center justify-between py-2 px-3">
          <span class="text-gray-700 dark:text-gray-300">Net Cash Flow</span>
          <span class="font-medium" :class="netCashFlow >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatCurrency(netCashFlow, currency) }}
          </span>
        </div>
        
        <div class="flex items-center justify-between py-3 px-3 bg-gray-50 dark:bg-gray-700 rounded font-semibold">
          <span>Closing Cash Balance</span>
          <span>{{ formatCurrency(data.closing_balance || 0, currency) }}</span>
        </div>
      </div>
    </div>

    <!-- Cash Flow Metrics -->
    <div v-if="data.metrics" class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Cash Flow Metrics</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Operating Cash Flow</div>
          <div class="text-lg font-semibold">
            <span :class="(data.operating_activities?.net || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ formatCurrency(data.operating_activities?.net || 0, currency) }}
            </span>
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Free Cash Flow</div>
          <div class="text-lg font-semibold">
            <span :class="(data.metrics?.free_cash_flow || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ formatCurrency(data.metrics?.free_cash_flow || 0, currency) }}
            </span>
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Cash Conversion Ratio</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.metrics?.cash_conversion_ratio || 0).toFixed(2) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Days Sales Outstanding</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.metrics?.days_sales_outstanding || 0).toFixed(0) }} days
          </div>
        </div>
      </div>
    </div>

    <!-- Comparison Section -->
    <div v-if="data.comparison" class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Period Comparison</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Current Operating CF</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ formatCurrency(data.comparison.current_operating_cf || 0, currency) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Previous Operating CF</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ formatCurrency(data.comparison.previous_operating_cf || 0, currency) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Operating CF Growth</div>
          <div class="text-lg font-semibold">
            <span :class="data.comparison.operating_cf_growth >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ data.comparison.operating_cf_growth >= 0 ? '+' : '' }}{{ (data.comparison.operating_cf_growth || 0).toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: {
    type: Object,
    required: true
  },
  currency: {
    type: String,
    default: 'USD'
  }
})

defineEmits(['drilldown'])

// Computed
const netCashFlow = computed(() => {
  const operating = props.data.operating_activities?.net || 0
  const investing = props.data.investing_activities?.net || 0
  const financing = props.data.financing_activities?.net || 0
  return operating + investing + financing
})

// Methods
const formatCurrency = (value, currency = 'USD') => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(value)
}

const formatDateRange = (startDate, endDate) => {
  if (!startDate || !endDate) return ''
  
  const start = new Date(startDate).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
  
  const end = new Date(endDate).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
  
  return `${start} - ${end}`
}
</script>