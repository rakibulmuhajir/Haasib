<template>
  <div class="space-y-6">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Basic Information -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Template Name *
          </label>
          <InputText
            v-model="form.name"
            placeholder="Enter template name"
            :class="{ 'p-invalid': errors.name }"
            class="w-full"
          />
          <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Report Type *
          </label>
          <Dropdown
            v-model="form.report_type"
            :options="reportTypeOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Select report type"
            :class="{ 'p-invalid': errors.report_type }"
            class="w-full"
            @change="onReportTypeChange"
          />
          <small v-if="errors.report_type" class="text-red-500">{{ errors.report_type }}</small>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Description
        </label>
        <Textarea
          v-model="form.description"
          placeholder="Enter template description"
          :rows="3"
          class="w-full"
        />
      </div>

      <!-- Visibility Settings -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Visibility *
          </label>
          <Dropdown
            v-model="form.visibility"
            :options="visibilityOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Select visibility"
            :class="{ 'p-invalid': errors.visibility }"
            class="w-full"
          />
          <small v-if="errors.visibility" class="text-red-500">{{ errors.visibility }}</small>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Position
          </label>
          <InputNumber
            v-model="form.position"
            :min="0"
            placeholder="Template position"
            class="w-full"
          />
        </div>
      </div>

      <!-- Configuration -->
      <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          Template Configuration
        </h3>
        
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
          <div class="space-y-4">
            <!-- Include Header -->
            <div class="flex items-center">
              <Checkbox
                v-model="form.configuration.include_header"
                binary
                inputId="includeHeader"
              />
              <label for="includeHeader" class="ml-2">Include Report Header</label>
            </div>

            <!-- Include Footer -->
            <div class="flex items-center">
              <Checkbox
                v-model="form.configuration.include_footer"
                binary
                inputId="includeFooter"
              />
              <label for="includeFooter" class="ml-2">Include Report Footer</label>
            </div>

            <!-- Include Charts -->
            <div class="flex items-center">
              <Checkbox
                v-model="form.configuration.include_charts"
                binary
                inputId="includeCharts"
              />
              <label for="includeCharts" class="ml-2">Include Charts</label>
            </div>

            <!-- Group By Account Type (for specific report types) -->
            <div
              v-if="form.report_type === 'income_statement' || form.report_type === 'balance_sheet'"
              class="flex items-center"
            >
              <Checkbox
                v-model="form.configuration.group_by_account_type"
                binary
                inputId="groupByAccountType"
              />
              <label for="groupByAccountType" class="ml-2">Group by Account Type</label>
            </div>

            <!-- Show Percentages (for specific report types) -->
            <div
              v-if="form.report_type === 'income_statement'"
              class="flex items-center"
            >
              <Checkbox
                v-model="form.configuration.show_percentages"
                binary
                inputId="showPercentages"
              />
              <label for="showPercentages" class="ml-2">Show Percentages</label>
            </div>

            <!-- Include Zero Balances -->
            <div class="flex items-center">
              <Checkbox
                v-model="form.configuration.include_zero_balances"
                binary
                inputId="includeZeroBalances"
              />
              <label for="includeZeroBalances" class="ml-2">Include Zero Balances</label>
            </div>
          </div>
        </div>
      </div>

      <!-- Column Configuration (for trial balance) -->
      <div v-if="form.report_type === 'trial_balance'">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          Column Configuration
        </h3>
        
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
          <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="flex items-center">
                <Checkbox
                  v-model="form.configuration.columns.debit"
                  binary
                  inputId="debitColumn"
                />
                <label for="debitColumn" class="ml-2">Debit Column</label>
              </div>
              
              <div class="flex items-center">
                <Checkbox
                  v-model="form.configuration.columns.credit"
                  binary
                  inputId="creditColumn"
                />
                <label for="creditColumn" class="ml-2">Credit Column</label>
              </div>
              
              <div class="flex items-center">
                <Checkbox
                  v-model="form.configuration.columns.balance"
                  binary
                inputId="balanceColumn" />
                <label for="balanceColumn" class="ml-2">Balance Column</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Configuration (for KPI Dashboard) -->
      <div v-if="form.report_type === 'kpi_dashboard'">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          KPI Configuration
        </h3>
        
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                KPI Metrics
              </label>
              <MultiSelect
                v-model="form.configuration.kpi_metrics"
                :options="kpiMetricOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Select KPI metrics"
                class="w-full"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Layout Type
              </label>
              <Dropdown
                v-model="form.configuration.layout_type"
                :options="layoutTypeOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Select layout type"
                class="w-full"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
        <Button
          label="Cancel"
          severity="secondary"
          @click="$emit('cancel')"
          :disabled="loading"
        />
        
        <Button
          label="Save"
          type="submit"
          :loading="loading"
          :disabled="!isFormValid"
        />
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  template: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['save', 'cancel'])

const toast = useToast()

// Form data
const form = ref({
  name: '',
  description: '',
  report_type: '',
  visibility: 'private',
  position: 0,
  configuration: {
    include_header: true,
    include_footer: true,
    include_charts: false,
    group_by_account_type: true,
    show_percentages: false,
    include_zero_balances: false,
    columns: {
      debit: true,
      credit: true,
      balance: true
    },
    kpi_metrics: [],
    layout_type: 'grid'
  }
})

const errors = ref({})

// Options
const reportTypeOptions = ref([
  { label: 'Income Statement', value: 'income_statement' },
  { label: 'Balance Sheet', value: 'balance_sheet' },
  { label: 'Cash Flow', value: 'cash_flow' },
  { label: 'Trial Balance', value: 'trial_balance' },
  { label: 'KPI Dashboard', value: 'kpi_dashboard' }
])

const visibilityOptions = ref([
  { label: 'Public', value: 'public' },
  { label: 'Private', value: 'private' },
  { label: 'Role-based', value: 'role_based' }
])

const kpiMetricOptions = ref([
  { label: 'Total Revenue', value: 'total_revenue' },
  { label: 'Net Income', value: 'net_income' },
  { label: 'Gross Profit', value: 'gross_profit' },
  { label: 'Operating Income', value: 'operating_income' },
  { label: 'EBITDA', value: 'ebitda' },
  { label: 'Total Assets', value: 'total_assets' },
  { label: 'Total Liabilities', value: 'total_liabilities' },
  { label: 'Cash Balance', value: 'cash_balance' },
  { label: 'Accounts Receivable', value: 'accounts_receivable' },
  { label: 'Accounts Payable', value: 'accounts_payable' }
])

const layoutTypeOptions = ref([
  { label: 'Grid Layout', value: 'grid' },
  { label: 'Card Layout', value: 'cards' },
  { label: 'Table Layout', value: 'table' }
])

// Computed
const isFormValid = computed(() => {
  return form.value.name.trim() !== '' &&
         form.value.report_type !== '' &&
         form.value.visibility !== ''
})

// Methods
const initializeForm = () => {
  if (props.template) {
    form.value = {
      ...props.template,
      configuration: {
        include_header: true,
        include_footer: true,
        include_charts: false,
        group_by_account_type: true,
        show_percentages: false,
        include_zero_balances: false,
        columns: {
          debit: true,
          credit: true,
          balance: true
        },
        kpi_metrics: [],
        layout_type: 'grid',
        ...props.template.configuration
      }
    }
  } else {
    // Reset to defaults for new template
    form.value = {
      name: '',
      description: '',
      report_type: '',
      visibility: 'private',
      position: 0,
      configuration: {
        include_header: true,
        include_footer: true,
        include_charts: false,
        group_by_account_type: true,
        show_percentages: false,
        include_zero_balances: false,
        columns: {
          debit: true,
          credit: true,
          balance: true
        },
        kpi_metrics: [],
        layout_type: 'grid'
      }
    }
  }
  errors.value = {}
}

const onReportTypeChange = () => {
  // Reset type-specific configuration when report type changes
  if (form.value.report_type !== 'trial_balance') {
    form.value.configuration.columns = {
      debit: true,
      credit: true,
      balance: true
    }
  }
  
  if (form.value.report_type !== 'kpi_dashboard') {
    form.value.configuration.kpi_metrics = []
    form.value.configuration.layout_type = 'grid'
  }
  
  if (form.value.report_type !== 'income_statement') {
    form.value.configuration.show_percentages = false
  }
  
  if (form.value.report_type !== 'income_statement' && form.value.report_type !== 'balance_sheet') {
    form.value.configuration.group_by_account_type = true
  }
}

const validateForm = () => {
  errors.value = {}
  
  if (!form.value.name.trim()) {
    errors.value.name = 'Template name is required'
  }
  
  if (!form.value.report_type) {
    errors.value.report_type = 'Report type is required'
  }
  
  if (!form.value.visibility) {
    errors.value.visibility = 'Visibility is required'
  }
  
  // Type-specific validation
  if (form.value.report_type === 'kpi_dashboard' && form.value.configuration.kpi_metrics.length === 0) {
    errors.value.kpi_metrics = 'At least one KPI metric is required'
  }
  
  if (form.value.report_type === 'trial_balance') {
    const hasColumns = form.value.configuration.columns.debit ||
                      form.value.configuration.columns.credit ||
                      form.value.configuration.columns.balance
    
    if (!hasColumns) {
      errors.value.columns = 'At least one column must be selected'
    }
  }
  
  return Object.keys(errors.value).length === 0
}

const handleSubmit = () => {
  if (!validateForm()) {
    return
  }
  
  const submitData = {
    ...form.value,
    position: form.value.position || 0
  }
  
  emit('save', submitData)
}

// Watch for template prop changes
watch(() => props.template, () => {
  initializeForm()
}, { immediate: true })

// Lifecycle
onMounted(() => {
  initializeForm()
})
</script>