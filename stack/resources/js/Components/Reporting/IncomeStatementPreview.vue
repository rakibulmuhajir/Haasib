<template>
  <div class="space-y-6">
    <!-- Report Header -->
    <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Income Statement
        </h3>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          {{ formatDateRange(data.period?.start, data.period?.end) }}
        </div>
      </div>
    </div>

    <!-- Revenue Section -->
    <div class="space-y-3">
      <h4 class="text-md font-medium text-gray-900 dark:text-white">Revenue</h4>
      
      <div
        v-for="item in data.revenue?.items || []"
        :key="item.account_code"
        class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer"
        @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
      >
        <div class="flex items-center space-x-3">
          <i class="pi pi-arrow-right text-gray-400"></i>
          <div>
            <span class="font-medium text-gray-900 dark:text-white">{{ item.account_name }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ item.account_code }}</span>
          </div>
        </div>
        <div class="text-right">
          <div class="font-medium text-gray-900 dark:text-white">
            {{ formatCurrency(item.amount, currency) }}
          </div>
          <div v-if="item.variance_percent !== undefined" class="text-sm">
            <span
              :class="item.variance_percent >= 0 ? 'text-green-600' : 'text-red-600'"
            >
              {{ item.variance_percent >= 0 ? '+' : '' }}{{ item.variance_percent.toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Total Revenue -->
      <div class="flex items-center justify-between py-3 px-3 bg-gray-50 dark:bg-gray-700 rounded font-semibold">
        <span>Total Revenue</span>
        <div class="text-right">
          <div>{{ formatCurrency(data.revenue?.total || 0, currency) }}</div>
          <div v-if="data.revenue?.variance_percent !== undefined" class="text-sm">
            <span
              :class="data.revenue.variance_percent >= 0 ? 'text-green-600' : 'text-red-600'"
            >
              {{ data.revenue.variance_percent >= 0 ? '+' : '' }}{{ data.revenue.variance_percent.toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Expenses Section -->
    <div class="space-y-3">
      <h4 class="text-md font-medium text-gray-900 dark:text-white">Expenses</h4>
      
      <div
        v-for="item in data.expenses?.items || []"
        :key="item.account_code"
        class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer"
        @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
      >
        <div class="flex items-center space-x-3">
          <i class="pi pi-arrow-right text-gray-400"></i>
          <div>
            <span class="font-medium text-gray-900 dark:text-white">{{ item.account_name }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ item.account_code }}</span>
          </div>
        </div>
        <div class="text-right">
          <div class="font-medium text-gray-900 dark:text-white">
            {{ formatCurrency(Math.abs(item.amount), currency) }}
          </div>
          <div v-if="item.variance_percent !== undefined" class="text-sm">
            <span
              :class="item.variance_percent <= 0 ? 'text-green-600' : 'text-red-600'"
            >
              {{ item.variance_percent <= 0 ? '' : '+' }}{{ item.variance_percent.toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Total Expenses -->
      <div class="flex items-center justify-between py-3 px-3 bg-gray-50 dark:bg-gray-700 rounded font-semibold">
        <span>Total Expenses</span>
        <div class="text-right">
          <div>{{ formatCurrency(Math.abs(data.expenses?.total || 0), currency) }}</div>
          <div v-if="data.expenses?.variance_percent !== undefined" class="text-sm">
            <span
              :class="data.expenses.variance_percent <= 0 ? 'text-green-600' : 'text-red-600'"
            >
              {{ data.expenses.variance_percent <= 0 ? '' : '+' }}{{ data.expenses.variance_percent.toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Net Income Section -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <div class="flex items-center justify-between py-3 px-3 bg-blue-50 dark:bg-blue-900/20 rounded font-bold text-lg">
        <span>Net Income</span>
        <div class="text-right">
          <div :class="data.net_income >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatCurrency(data.net_income || 0, currency) }}
          </div>
          <div v-if="data.net_income_variance_percent !== undefined" class="text-sm">
            <span
              :class="data.net_income_variance_percent >= 0 ? 'text-green-600' : 'text-red-600'"
            >
              {{ data.net_income_variance_percent >= 0 ? '+' : '' }}{{ data.net_income_variance_percent.toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Comparison Section -->
    <div v-if="data.comparison" class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Period Comparison</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Current Period</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ formatCurrency(data.comparison.current_revenue || 0, currency) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Previous Period</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ formatCurrency(data.comparison.previous_revenue || 0, currency) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Variance</div>
          <div class="text-lg font-semibold">
            <span :class="data.comparison.revenue_variance >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ data.comparison.revenue_variance >= 0 ? '+' : '' }}{{ data.comparison.revenue_variance?.toFixed(1) || 0 }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Key Metrics -->
    <div v-if="data.metrics" class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Key Metrics</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Gross Margin</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.metrics.gross_margin_percent || 0).toFixed(1) }}%
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Operating Margin</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.metrics.operating_margin_percent || 0).toFixed(1) }}%
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Net Margin</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.metrics.net_margin_percent || 0).toFixed(1) }}%
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Revenue Growth</div>
          <div class="text-lg font-semibold">
            <span :class="data.metrics.revenue_growth_percent >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ data.metrics.revenue_growth_percent >= 0 ? '+' : '' }}{{ (data.metrics.revenue_growth_percent || 0).toFixed(1) }}%
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
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