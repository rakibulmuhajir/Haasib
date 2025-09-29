<template>
  <EntityPicker
    ref="dropdown"
    v-model="selectedVendorId"
    :entities="vendors"
    entity-type="vendor"
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
    @create-entity="createVendor"
    @view-entity="viewVendor"
  />
</template>

<script setup lang="ts">
import { computed, nextTick } from 'vue'
import EntityPicker from './EntityPicker.vue'

interface Vendor {
  id?: number
  vendor_id?: number
  name: string
  email?: string
  phone?: string
  status?: string
  category?: string
  website?: string
  avatar?: string
  bill_count?: number
  total_paid?: number
  currency?: string
  [key: string]: any
}

interface Props {
  modelValue?: number | string | null
  vendors: Vendor[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (vendor: Vendor) => boolean
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
  (e: 'change', vendor: Vendor | null): void
  (e: 'filter', event: Event): void
  (e: 'show'): void
  (e: 'hide'): void
  (e: 'create-vendor'): void
  (e: 'view-vendor', vendor: Vendor): void
}

const props = withDefaults(defineProps<Props>(), {
  optionLabel: 'name',
  optionValue: 'id',
  placeholder: 'Select a vendor...',
  filterPlaceholder: 'Search vendors...',
  filterFields: () => ['name', 'email', 'phone', 'category'],
  showClear: true,
  disabled: false,
  loading: false,
  showBalance: true,
  showStats: false,
  allowCreate: true
})

const emit = defineEmits<Emits>()

const dropdown = ref()

const selectedVendorId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedVendor = computed(() => {
  if (!selectedVendorId.value) return null
  
  return props.vendors.find(vendor => {
    const vendorId = vendor[props.optionValue]
    return vendorId === selectedVendorId.value
  }) || null
})

const onChange = (vendor: Vendor | null) => {
  emit('change', vendor)
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

const createVendor = () => {
  emit('create-vendor')
}

const viewVendor = (vendor: Vendor) => {
  emit('view-vendor', vendor)
}

// Expose dropdown methods
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