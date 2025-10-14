<template>
  <EntityPicker
    ref="dropdown"
    v-model="selectedCompanyId"
    :entities="companies"
    entity-type="company"
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
    @create-entity="createCompany"
    @view-entity="viewCompany"
  />
</template>

<script setup lang="ts">
import { computed, nextTick } from 'vue'
import EntityPicker from './EntityPicker.vue'

interface Company {
  id?: number
  company_id?: number
  name: string
  email?: string
  phone?: string
  status?: string
  industry?: string
  website?: string
  avatar?: string
  employee_count?: number
  annual_revenue?: number
  currency?: string
  [key: string]: any
}

interface Props {
  modelValue?: number | string | null
  companies: Company[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (company: Company) => boolean
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
  (e: 'change', company: Company | null): void
  (e: 'filter', event: Event): void
  (e: 'show'): void
  (e: 'hide'): void
  (e: 'create-company'): void
  (e: 'view-company', company: Company): void
}

const props = withDefaults(defineProps<Props>(), {
  optionLabel: 'name',
  optionValue: 'id',
  placeholder: 'Select a company...',
  filterPlaceholder: 'Search companies...',
  filterFields: () => ['name', 'email', 'phone', 'industry'],
  showClear: true,
  disabled: false,
  loading: false,
  showBalance: true,
  showStats: false,
  allowCreate: true
})

const emit = defineEmits<Emits>()

const dropdown = ref()

const selectedCompanyId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedCompany = computed(() => {
  if (!selectedCompanyId.value) return null
  
  return props.companies.find(company => {
    const companyId = company[props.optionValue]
    return companyId === selectedCompanyId.value
  }) || null
})

const onChange = (company: Company | null) => {
  emit('change', company)
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

const createCompany = () => {
  emit('create-company')
}

const viewCompany = (company: Company) => {
  emit('view-company', company)
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