<template>
  <div class="space-y-6">
    <!-- Report Header -->
    <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Balance Sheet
        </h3>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          As of {{ formatDate(data.as_of_date) }}
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Assets Section -->
      <div class="space-y-4">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
          Assets
        </h4>
        
        <!-- Current Assets -->
        <div class="space-y-2">
          <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Assets</h5>
          
          <div
            v-for="item in data.assets?.current?.items || []"
            :key="item.account_code"
            class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
            @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
          >
            <div class="flex items-center space-x-2">
              <i class="pi pi-arrow-right text-gray-400"></i>
              <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
            </div>
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.balance, currency) }}
            </span>
          </div>
          
          <div class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-700 rounded text-sm font-medium">
            <span>Total Current Assets</span>
            <span>{{ formatCurrency(data.assets?.current?.total || 0, currency) }}</span>
          </div>
        </div>

        <!-- Non-Current Assets -->
        <div class="space-y-2">
          <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300">Non-Current Assets</h5>
          
          <div
            v-for="item in data.assets?.non_current?.items || []"
            :key="item.account_code"
            class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
            @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
          >
            <div class="flex items-center space-x-2">
              <i class="pi pi-arrow-right text-gray-400"></i>
              <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
            </div>
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.balance, currency) }}
            </span>
          </div>
          
          <div class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-700 rounded text-sm font-medium">
            <span>Total Non-Current Assets</span>
            <span>{{ formatCurrency(data.assets?.non_current?.total || 0, currency) }}</span>
          </div>
        </div>

        <!-- Total Assets -->
        <div class="flex items-center justify-between py-3 px-3 bg-blue-50 dark:bg-blue-900/20 rounded font-semibold">
          <span>Total Assets</span>
          <span>{{ formatCurrency(data.assets?.total || 0, currency) }}</span>
        </div>
      </div>

      <!-- Liabilities & Equity Section -->
      <div class="space-y-4">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
          Liabilities & Equity
        </h4>
        
        <!-- Current Liabilities -->
        <div class="space-y-2">
          <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Liabilities</h5>
          
          <div
            v-for="item in data.liabilities?.current?.items || []"
            :key="item.account_code"
            class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
            @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
          >
            <div class="flex items-center space-x-2">
              <i class="pi pi-arrow-right text-gray-400"></i>
              <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
            </div>
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.balance, currency) }}
            </span>
          </div>
          
          <div class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-700 rounded text-sm font-medium">
            <span>Total Current Liabilities</span>
            <span>{{ formatCurrency(data.liabilities?.current?.total || 0, currency) }}</span>
          </div>
        </div>

        <!-- Non-Current Liabilities -->
        <div class="space-y-2">
          <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300">Non-Current Liabilities</h5>
          
          <div
            v-for="item in data.liabilities?.non_current?.items || []"
            :key="item.account_code"
            class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
            @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
          >
            <div class="flex items-center space-x-2">
              <i class="pi pi-arrow-right text-gray-400"></i>
              <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
            </div>
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.balance, currency) }}
            </span>
          </div>
          
          <div class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-700 rounded text-sm font-medium">
            <span>Total Non-Current Liabilities</span>
            <span>{{ formatCurrency(data.liabilities?.non_current?.total || 0, currency) }}</span>
          </div>
        </div>

        <!-- Equity -->
        <div class="space-y-2">
          <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300">Equity</h5>
          
          <div
            v-for="item in data.equity?.items || []"
            :key="item.account_code"
            class="flex items-center justify-between py-2 px-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer text-sm"
            @click="$emit('drilldown', item.account_id, item.account_code, item.account_name)"
          >
            <div class="flex items-center space-x-2">
              <i class="pi pi-arrow-right text-gray-400"></i>
              <span class="text-gray-700 dark:text-gray-300">{{ item.account_name }}</span>
            </div>
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(item.balance, currency) }}
            </span>
          </div>
          
          <div class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-700 rounded text-sm font-medium">
            <span>Total Equity</span>
            <span>{{ formatCurrency(data.equity?.total || 0, currency) }}</span>
          </div>
        </div>

        <!-- Total Liabilities & Equity -->
        <div class="flex items-center justify-between py-3 px-3 bg-blue-50 dark:bg-blue-900/20 rounded font-semibold">
          <span>Total Liabilities & Equity</span>
          <span>{{ formatCurrency(data.liabilities_equity_total || 0, currency) }}</span>
        </div>
      </div>
    </div>

    <!-- Balance Check -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <div class="flex items-center justify-center p-4" :class="isBalanced ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
        <div class="text-center">
          <i :class="isBalanced ? 'pi pi-check-circle text-green-600' : 'pi pi-times-circle text-red-600'" class="text-2xl mb-2"></i>
          <div class="font-semibold" :class="isBalanced ? 'text-green-600' : 'text-red-600'">
            {{ isBalanced ? 'Balance Sheet is Balanced' : 'Balance Sheet Out of Balance' }}
          </div>
          <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Difference: {{ formatCurrency(Math.abs(balanceDifference), currency) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Financial Ratios -->
    <div v-if="data.ratios" class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Key Financial Ratios</h4>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Current Ratio</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.ratios.current_ratio || 0).toFixed(2) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Quick Ratio</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.ratios.quick_ratio || 0).toFixed(2) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Debt to Equity</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.ratios.debt_to_equity || 0).toFixed(2) }}
          </div>
        </div>
        
        <div class="text-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Equity Ratio</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ (data.ratios.equity_ratio || 0).toFixed(1) }}%
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
            {{ formatCurrency(data.comparison.current_assets || 0, currency) }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Total Assets</div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Previous Period</div>
          <div class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ formatCurrency(data.comparison.previous_assets || 0, currency) }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Total Assets</div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
          <div class="text-sm text-gray-500 dark:text-gray-400">Asset Growth</div>
          <div class="text-lg font-semibold">
            <span :class="data.comparison.asset_growth >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ data.comparison.asset_growth >= 0 ? '+' : '' }}{{ (data.comparison.asset_growth || 0).toFixed(1) }}%
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
const isBalanced = computed(() => {
  const assetsTotal = props.data.assets?.total || 0
  const liabilitiesEquityTotal = props.data.liabilities_equity_total || 0
  return Math.abs(assetsTotal - liabilitiesEquityTotal) < 0.01 // Allow for small rounding differences
})

const balanceDifference = computed(() => {
  const assetsTotal = props.data.assets?.total || 0
  const liabilitiesEquityTotal = props.data.liabilities_equity_total || 0
  return assetsTotal - liabilitiesEquityTotal
})

// Methods
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
</script>