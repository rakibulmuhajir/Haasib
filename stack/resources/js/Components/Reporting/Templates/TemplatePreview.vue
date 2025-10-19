<template>
  <div class="space-y-6">
    <!-- Template Information -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Template Name</h3>
          <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ template.name }}</p>
        </div>
        
        <div>
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Report Type</h3>
          <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatReportType(template.report_type) }}</p>
        </div>
        
        <div v-if="template.description">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</h3>
          <p class="text-gray-900 dark:text-white">{{ template.description }}</p>
        </div>
        
        <div>
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Visibility</h3>
          <Badge
            :value="formatVisibility(template.visibility)"
            :severity="getVisibilitySeverity(template.visibility)"
          />
        </div>
      </div>
    </div>

    <!-- Configuration Preview -->
    <div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Configuration Preview
      </h3>
      
      <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Basic Configuration -->
          <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Basic Settings</h4>
            <ul class="space-y-2">
              <li class="flex items-center">
                <i
                  :class="template.configuration?.include_header ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Include Header</span>
              </li>
              <li class="flex items-center">
                <i
                  :class="template.configuration?.include_footer ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Include Footer</span>
              </li>
              <li class="flex items-center">
                <i
                  :class="template.configuration?.include_charts ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Include Charts</span>
              </li>
              <li class="flex items-center">
                <i
                  :class="template.configuration?.include_zero_balances ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Include Zero Balances</span>
              </li>
            </ul>
          </div>

          <!-- Type-specific Configuration -->
          <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Advanced Settings</h4>
            <ul v-if="template.report_type === 'income_statement' || template.report_type === 'balance_sheet'" class="space-y-2">
              <li class="flex items-center">
                <i
                  :class="template.configuration?.group_by_account_type ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Group by Account Type</span>
              </li>
              <li v-if="template.report_type === 'income_statement'" class="flex items-center">
                <i
                  :class="template.configuration?.show_percentages ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Show Percentages</span>
              </li>
            </ul>

            <ul v-else-if="template.report_type === 'trial_balance'" class="space-y-2">
              <li class="flex items-center">
                <i
                  :class="template.configuration?.columns?.debit ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Debit Column</span>
              </li>
              <li class="flex items-center">
                <i
                  :class="template.configuration?.columns?.credit ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Credit Column</span>
              </li>
              <li class="flex items-center">
                <i
                  :class="template.configuration?.columns?.balance ? 'pi pi-check text-green-500' : 'pi pi-times text-red-500'"
                  class="mr-2"
                ></i>
                <span>Balance Column</span>
              </li>
            </ul>

            <div v-else-if="template.report_type === 'kpi_dashboard'" class="space-y-2">
              <div class="mb-2">
                <span class="text-sm font-medium">Layout Type:</span>
                <span class="ml-2 text-sm">{{ formatLayoutType(template.configuration?.layout_type) }}</span>
              </div>
              <div>
                <span class="text-sm font-medium block mb-1">KPI Metrics:</span>
                <div class="flex flex-wrap gap-1">
                  <Badge
                    v-for="metric in template.configuration?.kpi_metrics"
                    :key="metric"
                    :value="formatKpiMetric(metric)"
                    size="small"
                    severity="info"
                  />
                  <span v-if="!template.configuration?.kpi_metrics?.length" class="text-sm text-gray-500">
                    No metrics selected
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="text-sm text-gray-500">
              No advanced settings for this report type
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sample Report Preview -->
    <div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Sample Report Preview
      </h3>
      
      <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
        <!-- Sample Header -->
        <div v-if="template.configuration?.include_header" class="border-b border-gray-200 dark:border-gray-700 p-6">
          <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatReportType(template.report_type) }}
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
              {{ template.name }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
              Period: {{ formatDateRange() }}
            </p>
          </div>
        </div>

        <!-- Sample Content -->
        <div class="p-6">
          <!-- Income Statement Sample -->
          <div v-if="template.report_type === 'income_statement'" class="space-y-4">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="font-medium">Revenue</span>
                <span>$500,000</span>
              </div>
              <div v-if="template.configuration?.show_percentages" class="flex justify-between text-sm text-gray-600">
                <span>% of Revenue</span>
                <span>100.0%</span>
              </div>
            </div>
            <div class="border-t pt-2 space-y-2">
              <div class="flex justify-between">
                <span>Cost of Goods Sold</span>
                <span>($300,000)</span>
              </div>
              <div v-if="template.configuration?.show_percentages" class="flex justify-between text-sm text-gray-600">
                <span>% of Revenue</span>
                <span>60.0%</span>
              </div>
            </div>
            <div class="border-t pt-2 flex justify-between font-bold">
              <span>Gross Profit</span>
              <span>$200,000</span>
            </div>
            <div v-if="template.configuration?.show_percentages" class="flex justify-between text-sm text-gray-600">
              <span>% of Revenue</span>
              <span>40.0%</span>
            </div>
          </div>

          <!-- Balance Sheet Sample -->
          <div v-else-if="template.report_type === 'balance_sheet'" class="grid grid-cols-2 gap-8">
            <div>
              <h4 class="font-medium mb-3">Assets</h4>
              <div class="space-y-2">
                <div class="flex justify-between">
                  <span>Cash</span>
                  <span>$100,000</span>
                </div>
                <div class="flex justify-between">
                  <span>Accounts Receivable</span>
                  <span>$50,000</span>
                </div>
                <div class="border-t pt-2 flex justify-between font-bold">
                  <span>Total Assets</span>
                  <span>$150,000</span>
                </div>
              </div>
            </div>
            <div>
              <h4 class="font-medium mb-3">Liabilities & Equity</h4>
              <div class="space-y-2">
                <div class="flex justify-between">
                  <span>Accounts Payable</span>
                  <span>$30,000</span>
                </div>
                <div class="flex justify-between">
                  <span>Owner's Equity</span>
                  <span>$120,000</span>
                </div>
                <div class="border-t pt-2 flex justify-between font-bold">
                  <span>Total Liabilities & Equity</span>
                  <span>$150,000</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Trial Balance Sample -->
          <div v-else-if="template.report_type === 'trial_balance'">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b">
                  <th class="text-left py-2">Account</th>
                  <th v-if="template.configuration?.columns?.debit" class="text-right py-2">Debit</th>
                  <th v-if="template.configuration?.columns?.credit" class="text-right py-2">Credit</th>
                  <th v-if="template.configuration?.columns?.balance" class="text-right py-2">Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b">
                  <td class="py-2">Cash</td>
                  <td v-if="template.configuration?.columns?.debit" class="text-right">$100,000</td>
                  <td v-if="template.configuration?.columns?.credit" class="text-right">-</td>
                  <td v-if="template.configuration?.columns?.balance" class="text-right font-medium">$100,000</td>
                </tr>
                <tr class="border-b">
                  <td class="py-2">Accounts Receivable</td>
                  <td v-if="template.configuration?.columns?.debit" class="text-right">$50,000</td>
                  <td v-if="template.configuration?.columns?.credit" class="text-right">-</td>
                  <td v-if="template.configuration?.columns?.balance" class="text-right font-medium">$50,000</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- KPI Dashboard Sample -->
          <div v-else-if="template.report_type === 'kpi_dashboard'">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">$500,000</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Revenue</div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600">$75,000</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Net Income</div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-600">$100,000</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Cash Balance</div>
              </div>
            </div>
          </div>

          <!-- Default Sample for other types -->
          <div v-else class="text-center py-8 text-gray-500">
            <i class="pi pi-file-text text-4xl mb-4"></i>
            <p>Sample preview for {{ formatReportType(template.report_type) }}</p>
          </div>
        </div>

        <!-- Sample Footer -->
        <div v-if="template.configuration?.include_footer" class="border-t border-gray-200 dark:border-gray-700 p-4">
          <div class="text-center text-sm text-gray-500 dark:text-gray-400">
            Generated on {{ new Date().toLocaleDateString() }} using template: {{ template.name }}
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end space-x-3">
      <Button
        label="Close"
        severity="secondary"
        @click="$emit('close')"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineProps({
  template: {
    type: Object,
    required: true
  }
})

defineEmits(['close'])

// Utility Functions
const formatReportType = (type) => {
  const types = {
    income_statement: 'Income Statement',
    balance_sheet: 'Balance Sheet',
    cash_flow: 'Cash Flow Statement',
    trial_balance: 'Trial Balance',
    kpi_dashboard: 'KPI Dashboard'
  }
  return types[type] || type
}

const formatVisibility = (visibility) => {
  const visibilities = {
    public: 'Public',
    private: 'Private',
    role_based: 'Role-based'
  }
  return visibilities[visibility] || visibility
}

const getVisibilitySeverity = (visibility) => {
  switch (visibility) {
    case 'public': return 'success'
    case 'private': return 'danger'
    case 'role_based': return 'info'
    default: return 'secondary'
  }
}

const formatLayoutType = (type) => {
  const types = {
    grid: 'Grid Layout',
    cards: 'Card Layout',
    table: 'Table Layout'
  }
  return types[type] || type
}

const formatKpiMetric = (metric) => {
  const metrics = {
    total_revenue: 'Total Revenue',
    net_income: 'Net Income',
    gross_profit: 'Gross Profit',
    operating_income: 'Operating Income',
    ebitda: 'EBITDA',
    total_assets: 'Total Assets',
    total_liabilities: 'Total Liabilities',
    cash_balance: 'Cash Balance',
    accounts_receivable: 'Accounts Receivable',
    accounts_payable: 'Accounts Payable'
  }
  return metrics[metric] || metric
}

const formatDateRange = () => {
  const end = new Date()
  const start = new Date()
  start.setMonth(start.getMonth() - 1)
  
  return `${start.toLocaleDateString()} - ${end.toLocaleDateString()}`
}
</script>