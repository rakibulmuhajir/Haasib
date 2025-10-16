<template>
  <div class="trial-balance-page">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
          Trial Balance
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Mathematical verification of debits and credits from posted journal entries
        </p>
      </div>
      
      <div class="flex gap-3">
        <Button
          icon="pi pi-file-pdf"
          label="Export CSV"
          @click="exportTrialBalance"
          :loading="exporting"
          severity="primary"
        />
        <Button
          icon="pi pi-refresh"
          label="Refresh"
          @click="refreshData"
          :loading="loading"
          severity="secondary"
        />
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Debits</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ formatCurrency(trialBalance.summary?.total_debits || 0) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ getPeriodLabel() }}
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full">
              <i class="pi pi-arrow-up text-blue-600 dark:text-blue-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Credits</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ formatCurrency(trialBalance.summary?.total_credits || 0) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ getPeriodLabel() }}
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full">
              <i class="pi pi-arrow-down text-green-600 dark:text-green-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Difference</p>
              <p class="text-2xl font-bold" :class="differenceClass">
                {{ formatCurrency(trialBalance.summary?.total_difference || 0) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Balance Check
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 rounded-full" :class="differenceIconClass">
              <i class="pi text-xl" :class="differenceIcon"></i>
            </div>
          </div>
        </template>
      </Card>

      <Card class="metric-card">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Account Count</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ trialBalance.summary?.account_count || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Active Accounts
              </p>
            </div>
            <div class="flex items-center justify-center w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full">
              <i class="pi pi-book text-purple-600 dark:text-purple-400 text-xl"></i>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filters -->
    <Card class="mb-6">
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Date From
            </label>
            <Calendar
              v-model="filters.date_from"
              placeholder="Start date"
              dateFormat="yy-mm-dd"
              @change="applyFilters"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Date To
            </label>
            <Calendar
              v-model="filters.date_to"
              placeholder="End date"
              dateFormat="yy-mm-dd"
              @change="applyFilters"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Currency
            </label>
            <Dropdown
              v-model="filters.currency"
              :options="currencyOptions"
              placeholder="All currencies"
              optionLabel="label"
              optionValue="value"
              @change="applyFilters"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Include Zero Balances
            </label>
            <div class="flex items-center">
              <Checkbox
                v-model="filters.include_zero_balances"
                binary
                @change="applyFilters"
              />
              <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                Show zero balance accounts
              </span>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Balance Status Alert -->
    <Message
      v-if="!trialBalance.summary?.is_balanced"
      severity="error"
      :closable="false"
      class="mb-6"
    >
      <div class="flex items-center">
        <i class="pi pi-exclamation-triangle mr-2"></i>
        <span>
          Trial balance is out of balance by {{ formatCurrency(trialBalance.summary?.total_difference || 0) }}.
          Please review journal entries for errors.
        </span>
      </div>
    </Message>

    <Message
      v-else
      severity="success"
      :closable="false"
      class="mb-6"
    >
      <div class="flex items-center">
        <i class="pi pi-check-circle mr-2"></i>
        <span>
          Trial balance is in balance - all debits equal all credits.
        </span>
      </div>
    </Message>

    <!-- Trial Balance Table -->
    <Card>
      <template #header>
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Account Balances
          </h3>
          <div class="text-sm text-gray-500 dark:text-gray-400">
            Generated: {{ formatDate(trialBalance.generated_at) }}
          </div>
        </div>
      </template>
      
      <template #content>
        <DataTable
          :value="trialBalance.accounts"
          :loading="loading"
          stripedRows
          scrollable
          scrollHeight="600px"
          :globalFilterFields="['account_code', 'account_name']"
          v-model:filters="tableFilters"
          filterDisplay="menu"
          :paginator="true"
          :rows="25"
          :rowsPerPageOptions="[10, 25, 50, 100]"
        >
          <template #header>
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold">Trial Balance Details</span>
              <IconField>
                <InputIcon>
                  <i class="pi pi-search" />
                </InputIcon>
                <InputText
                  v-model="tableFilters['global'].value"
                  placeholder="Search accounts..."
                />
              </IconField>
            </div>
          </template>

          <Column field="account_code" header="Account Code" sortable style="min-width: 120px">
            <template #body="{ data }">
              <span class="font-mono text-sm">{{ data.account_code }}</span>
            </template>
          </Column>

          <Column field="account_name" header="Account Name" sortable style="min-width: 250px">
            <template #body="{ data }">
              <span class="font-medium">{{ data.account_name }}</span>
            </template>
          </Column>

          <Column field="normal_balance" header="Normal Balance" style="min-width: 120px">
            <template #body="{ data }">
              <Tag
                :value="data.normal_balance"
                :severity="data.normal_balance === 'debit' ? 'info' : 'success'"
                class="text-xs"
              />
            </template>
          </Column>

          <Column field="debit_total" header="Debit Total" sortable style="min-width: 140px">
            <template #body="{ data }">
              <span class="text-right font-mono">
                {{ formatCurrency(data.debit_total) }}
              </span>
            </template>
          </Column>

          <Column field="credit_total" header="Credit Total" sortable style="min-width: 140px">
            <template #body="{ data }">
              <span class="text-right font-mono">
                {{ formatCurrency(data.credit_total) }}
              </span>
            </template>
          </Column>

          <Column field="balance" header="Balance" sortable style="min-width: 140px">
            <template #body="{ data }">
              <span 
                class="text-right font-mono font-medium"
                :class="data.balance >= 0 ? 'text-blue-600' : 'text-red-600'"
              >
                {{ formatCurrency(Math.abs(data.balance)) }}
                <span class="text-xs text-gray-500">
                  {{ data.balance >= 0 ? 'Dr' : 'Cr' }}
                </span>
              </span>
            </template>
          </Column>

          <Column field="balance_display" header="Display" style="min-width: 120px">
            <template #body="{ data }">
              <span class="font-mono text-sm">{{ data.balance_display }}</span>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'

// PrimeVue Components
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Calendar from 'primevue/calendar'
import Dropdown from 'primevue/dropdown'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'

const props = defineProps({
  trialBalance: Object,
  filters: Object,
})

const toast = useToast()
const { t } = useI18n()

const loading = ref(false)
const exporting = ref(false)
const tableFilters = ref({})

const filters = ref({
  date_from: props.filters?.date_from || null,
  date_to: props.filters?.date_to || null,
  currency: props.filters?.currency || null,
  include_zero_balances: props.filters?.include_zero_balances || false,
})

const currencyOptions = [
  { label: 'All Currencies', value: null },
  { label: 'USD', value: 'USD' },
  { label: 'EUR', value: 'EUR' },
  { label: 'GBP', value: 'GBP' },
]

// Computed Properties
const differenceClass = computed(() => {
  const diff = props.trialBalance.summary?.total_difference || 0
  return diff === 0 ? 'text-green-600' : 'text-red-600'
})

const differenceIconClass = computed(() => {
  const diff = props.trialBalance.summary?.total_difference || 0
  return diff === 0 
    ? 'bg-green-100 dark:bg-green-900' 
    : 'bg-red-100 dark:bg-red-900'
})

const differenceIcon = computed(() => {
  const diff = props.trialBalance.summary?.total_difference || 0
  return diff === 0 
    ? 'pi-check-circle text-green-600 dark:text-green-400' 
    : 'pi-exclamation-triangle text-red-600 dark:text-red-400'
})

// Methods
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString()
}

const getPeriodLabel = () => {
  const from = filters.value.date_from
  const to = filters.value.date_to
  
  if (from && to) {
    return `${formatDate(from)} - ${formatDate(to)}`
  } else if (from) {
    return `From ${formatDate(from)}`
  } else if (to) {
    return `Until ${formatDate(to)}`
  }
  return 'All time'
}

const applyFilters = () => {
  loading.value = true
  
  const params = new URLSearchParams()
  
  if (filters.value.date_from) {
    params.append('date_from', filters.value.date_from)
  }
  if (filters.value.date_to) {
    params.append('date_to', filters.value.date_to)
  }
  if (filters.value.currency) {
    params.append('currency', filters.value.currency)
  }
  if (filters.value.include_zero_balances) {
    params.append('include_zero_balances', '1')
  }
  
  router.get(
    route('ledger.reports.trial-balance'),
    Object.fromEntries(params),
    {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => {
        loading.value = false
      },
    }
  )
}

const refreshData = () => {
  applyFilters()
}

const exportTrialBalance = () => {
  exporting.value = true
  
  const params = new URLSearchParams()
  
  if (filters.value.date_from) {
    params.append('date_from', filters.value.date_from)
  }
  if (filters.value.date_to) {
    params.append('date_to', filters.value.date_to)
  }
  if (filters.value.currency) {
    params.append('currency', filters.value.currency)
  }
  params.append('format', 'csv')
  
  // Create download URL
  const url = `/api/ledger/trial-balance/export?${params.toString()}`
  
  // Create temporary link and trigger download
  const link = document.createElement('a')
  link.href = url
  link.download = `trial-balance-${new Date().toISOString().split('T')[0]}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  setTimeout(() => {
    exporting.value = false
    toast.add({
      severity: 'success',
      summary: 'Export Complete',
      detail: 'Trial balance exported successfully',
      life: 3000,
    })
  }, 1000)
}

// Lifecycle
onMounted(() => {
  // Initialize any required data
})
</script>

<style scoped>
.trial-balance-page {
  @apply space-y-6;
}

.metric-card {
  @apply transition-all duration-200 hover:shadow-lg;
}

.metric-card:hover {
  @apply transform scale-105;
}
</style>