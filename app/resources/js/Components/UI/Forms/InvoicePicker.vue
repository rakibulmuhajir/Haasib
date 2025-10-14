<template>
  <EntityPicker
    ref="dropdown"
    v-model="selectedInvoiceId"
    :entities="invoices"
    entity-type="invoice"
    :optionLabel="optionLabel"
    :optionValue="optionValue"
    :optionDisabled="optionDisabled"
    :placeholder="placeholder"
    :filterPlaceholder="filterPlaceholder"
    :filterFields="filterFields"
    :showClear="showClear"
    :disabled="disabled"
    :loading="loading"
    :error="error"
    :showBalance="showBalance"
    :showStats="showStats"
    :allowCreate="allowCreate"
    @change="onChange"
    @filter="onFilter"
    @show="onShow"
    @hide="onHide"
    @create-entity="createInvoice"
    @view-entity="viewInvoice"
  />
</template>

<script setup lang="ts">
import { computed, nextTick } from 'vue'
import EntityPicker from './EntityPicker.vue'

interface Invoice {
  invoice_id?: number
  id?: number
  customer_id?: number
  invoice_number: string
  invoice_date: string
  due_date?: string
  total_amount: number
  balance_due: number
  status?: string
  currency?: { id: number; code: string; symbol: string }
  currency_id?: number
  notes?: string
  customer_name?: string
  overdue?: boolean
  [key: string]: any
}

interface Props {
  modelValue?: number | string | null
  invoices: Invoice[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (invoice: Invoice) => boolean
  placeholder?: string
  filterPlaceholder?: string
  filterFields?: string[]
  showClear?: boolean
  disabled?: boolean
  loading?: boolean
  error?: string
  showBalance?: boolean
  showStats?: boolean
  allowCreate?: boolean
  customerFilter?: number | null
  statusFilter?: string | null
  currencyFilter?: number | null
  dateRangeFilter?: { start: string; end: string } | null
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  invoices: () => [],
  optionLabel: 'invoice_number',
  optionValue: 'invoice_id',
  placeholder: 'Select an invoice...',
  filterPlaceholder: 'Search invoices...',
  filterFields: () => ['invoice_number', 'customer_name', 'notes'],
  showClear: true,
  disabled: false,
  loading: false,
  error: '',
  showBalance: true,
  showStats: true,
  allowCreate: true,
  customerFilter: null,
  statusFilter: null,
  currencyFilter: null,
  dateRangeFilter: null
})

const emit = defineEmits<{
  'update:modelValue': [value: number | string | null]
  'change': [invoice: Invoice | null]
  'filter': [event: Event]
  'show': []
  'hide': []
  'create-invoice': []
  'view-invoice': [invoice: Invoice]
}>()

const dropdown = ref()

const selectedInvoiceId = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
  }
})

// Computed filtered invoices based on filters
const filteredInvoices = computed(() => {
  let result = [...props.invoices]
  
  if (props.customerFilter) {
    result = result.filter(inv => inv.customer_id === props.customerFilter)
  }
  
  if (props.statusFilter) {
    result = result.filter(inv => inv.status === props.statusFilter)
  }
  
  if (props.currencyFilter) {
    result = result.filter(inv => inv.currency_id === props.currencyFilter)
  }
  
  if (props.dateRangeFilter) {
    const start = new Date(props.dateRangeFilter.start)
    const end = new Date(props.dateRangeFilter.end)
    result = result.filter(inv => {
      const invDate = new Date(inv.invoice_date)
      return invDate >= start && invDate <= end
    })
  }
  
  return result
})

// Event handlers
const onChange = (invoice: Invoice | null) => {
  emit('change', invoice)
}

const onFilter = (event: Event) => {
  emit('filter', event)
}

const onShow = () => {
  emit('show')
}

const onHide = () => {
  emit('hide')
}

const createInvoice = () => {
  emit('create-invoice')
}

const viewInvoice = (invoice: Invoice) => {
  emit('view-invoice', invoice)
}

// Expose methods
defineExpose({
  show: () => dropdown.value?.show(),
  hide: () => dropdown.value?.hide(),
  focus: async () => {
    await nextTick()
    dropdown.value?.focus()
  }
})

// Helper functions (used by EntityPicker templates)
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  })
}

const formatMoney = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD'
  }).format(amount)
}

const getBalanceColorClass = (invoice: Invoice) => {
  if (invoice.balance_due <= 0) return 'text-green-600'
  if (invoice.overdue) return 'text-red-600'
  return 'text-gray-600'
}
</script>

<style scoped>
/* Any component-specific styles here */
</style>