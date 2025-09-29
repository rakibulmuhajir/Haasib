<template>
  <EntityPicker
    ref="dropdown"
    v-model="selectedCustomerId"
    :entities="customers"
    entity-type="customer"
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
    @create-entity="createCustomer"
    @view-entity="viewCustomer"
  />
</template>

<script setup lang="ts">
import { computed, nextTick } from 'vue'
import EntityPicker from './EntityPicker.vue'

interface Customer {
  customer_id?: number
  id?: number
  name: string
  email?: string
  phone?: string
  status?: string
  type?: string
  avatar?: string
  balance?: number
  currency?: string
  outstanding_balance?: number
  invoice_count?: number
  [key: string]: any
}

interface Props {
  modelValue?: number | string | null
  customers: Customer[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (customer: Customer) => boolean
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
}

interface Emits {
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'change', customer: Customer | null): void
  (e: 'filter', event: Event): void
  (e: 'show'): void
  (e: 'hide'): void
  (e: 'create-customer'): void
  (e: 'view-customer', customer: Customer): void
}

const props = withDefaults(defineProps<Props>(), {
  optionLabel: 'name',
  optionValue: 'customer_id',
  placeholder: 'Select a customer...',
  filterPlaceholder: 'Search customers...',
  filterFields: () => ['name', 'email', 'phone'],
  showClear: true,
  disabled: false,
  loading: false,
  showBalance: true,
  showStats: false,
  allowCreate: true
})

const emit = defineEmits<Emits>()

const dropdown = ref()

const selectedCustomerId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedCustomer = computed(() => {
  if (!selectedCustomerId.value) return null
  
  return props.customers.find(customer => {
    const customerId = customer[props.optionValue]
    return customerId === selectedCustomerId.value
  }) || null
})

const onChange = (customer: Customer | null) => {
  emit('change', customer)
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

const createCustomer = () => {
  emit('create-customer')
}

const viewCustomer = (customer: Customer) => {
  emit('view-customer', customer)
}

// Expose dropdown methods for backward compatibility
const show = () => {
  dropdown.value?.show()
}

const hide = () => {
  dropdown.value?.hide()
}

const focus = () => {
  nextTick(() => {
    dropdown.value?.focus()
  })
}

defineExpose({
  show,
  hide,
  focus
})
</script>