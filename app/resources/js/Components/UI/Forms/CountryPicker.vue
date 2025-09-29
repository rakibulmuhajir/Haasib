<template>
  <div class="country-picker">
    <Select
      ref="dropdown"
      v-model="selectedCountryId"
      :options="countries"
      :optionLabel="optionLabel"
      :optionValue="optionValue"
      :optionDisabled="optionDisabled"
      :placeholder="placeholder"
      :filter="true"
      :filterFields="filterFields"
      :filterPlaceholder="filterPlaceholder"
      :showClear="showClear"
      :disabled="disabled"
      :class="['w-full', { 'p-invalid': error }]"
      :loading="loading"
      @filter="onFilter"
      @change="onChange"
      @show="onShow"
      @hide="onHide"
    >
      <template #option="{ option }">
        <div class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded">
          <!-- Flag -->
          <div class="flex-shrink-0 text-2xl">
            {{ option.flag || getCountryFlag(option.code) }}
          </div>

          <!-- Country Info -->
          <div class="flex-1 min-w-0">
            <div class="font-medium text-gray-900">
              {{ option[optionLabel] }}
            </div>
            <div class="text-xs text-gray-500">
              <span v-if="option.code">{{ option.code }}</span>
              <span v-if="option.code && option.phone_code"> • </span>
              <span v-if="option.phone_code">+{{ option.phone_code }}</span>
            </div>
            <div v-if="showExtraInfo && option.region" class="text-xs text-gray-400 mt-1">
              {{ option.region }}
            </div>
          </div>

          <!-- Additional Info -->
          <div v-if="showExtraInfo" class="text-right text-xs text-gray-500">
            <div v-if="option.currency_code">
              {{ option.currency_code }}
            </div>
            <div v-if="option.timezone">
              {{ option.timezone }}
            </div>
          </div>
        </div>
      </template>

      <template #value="{ value, placeholder }">
        <div v-if="value" class="flex items-center space-x-3">
          <!-- Selected Country Flag -->
          <div class="flex-shrink-0 text-xl">
            {{ selectedCountry?.flag || getCountryFlag(selectedCountry?.code) }}
          </div>

          <!-- Selected Country Info -->
          <div class="flex-1 min-w-0">
            <span class="text-gray-900">{{ selectedCountry?.[optionLabel] }}</span>
          </div>

          <!-- Phone code if available -->
          <div v-if="showPhoneCode && selectedCountry?.phone_code" class="flex-shrink-0 text-sm text-gray-500">
            +{{ selectedCountry.phone_code }}
          </div>
        </div>
        <span v-else class="text-gray-400">{{ placeholder }}</span>
      </template>

      <!-- Header with region filter -->
      <template #header>
        <div class="p-3 border-b border-gray-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700">Select Country</span>
            <Button
              v-if="allowCreate"
              label="Add Country"
              icon="pi pi-plus"
              class="p-button-text p-button-sm"
              @click.stop="createCountry"
            />
          </div>
          
          <!-- Region filter -->
          <div v-if="showRegionFilter" class="flex items-center space-x-2">
            <label class="text-xs text-gray-600">Region:</label>
            <Dropdown
              v-model="selectedRegion"
              :options="regions"
              optionLabel="name"
              optionValue="code"
              placeholder="All regions"
              class="w-full text-xs"
              @change="onRegionChange"
            />
          </div>
        </div>
      </template>

      <!-- Empty template -->
      <template #emptyfilter>
        <div class="p-4 text-center text-gray-500">
          <i class="pi pi-search text-2xl mb-2 block text-gray-300"></i>
          <p class="text-sm">No countries found matching "{{ currentFilter }}"</p>
          <Button
            v-if="allowCreate"
            label="Add New Country"
            class="p-button-outlined p-button-sm mt-2"
            @click="createCountry"
          />
        </div>
      </template>

      <template #empty>
        <div class="p-4 text-center text-gray-500">
          <i class="pi pi-globe text-2xl mb-2 block text-gray-300"></i>
          <p class="text-sm mb-2">No countries available</p>
          <Button
            v-if="allowCreate"
            label="Add Your First Country"
            class="p-button-outlined p-button-sm"
            @click="createCountry"
          />
        </div>
      </template>
    </Dropdown>

    <!-- Error message -->
    <div v-if="error" class="mt-1 text-sm text-red-600 flex items-center">
      <i class="pi pi-exclamation-triangle mr-1"></i>
      {{ error }}
    </div>

    <!-- Timezone info -->
    <div
      v-if="showTimezone && selectedCountry?.timezone"
      class="mt-2 p-2 bg-gray-50 rounded border border-gray-200 text-xs"
    >
      <div class="flex items-center justify-between">
        <span class="text-gray-600">Timezone:</span>
        <span class="font-medium">
          {{ selectedCountry.timezone }}
        </span>
      </div>
    </div>

    <!-- Currency info -->
    <div
      v-if="showCurrency && selectedCountry?.currency_code"
      class="mt-1 p-2 bg-blue-50 rounded border border-blue-200 text-xs"
    >
      <div class="flex items-center justify-between">
        <span class="text-blue-700">Currency:</span>
        <span class="font-medium text-blue-900">
          {{ selectedCountry.currency_code }}
          <span v-if="selectedCountry.currency_symbol">({{ selectedCountry.currency_symbol }})</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import Select from 'primevue/select'
import Button from 'primevue/button'

interface Country {
  id?: number | string
  code?: string
  name: string
  flag?: string
  phone_code?: string
  region?: string
  currency_code?: string
  currency_symbol?: string
  timezone?: string
  [key: string]: any
}

interface Region {
  code: string
  name: string
}

interface Props {
  modelValue?: number | string | null
  countries: Country[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (country: Country) => boolean
  placeholder?: string
  filterPlaceholder?: string
  filterFields?: string[]
  showClear?: boolean
  disabled?: boolean
  loading?: boolean
  error?: string
  showExtraInfo?: boolean
  showPhoneCode?: boolean
  showRegionFilter?: boolean
  showTimezone?: boolean
  showCurrency?: boolean
  allowCreate?: boolean
  regions?: Region[]
}

interface Emits {
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'change', country: Country | null): void
  (e: 'filter', event: Event): void
  (e: 'show'): void
  (e: 'hide'): void
  (e: 'region-change', region: string | null): void
  (e: 'create-country'): void
}

const props = withDefaults(defineProps<Props>(), {
  optionLabel: 'name',
  optionValue: 'id',
  placeholder: 'Select a country...',
  filterPlaceholder: 'Search countries...',
  filterFields: () => ['name', 'code', 'phone_code'],
  showClear: true,
  disabled: false,
  loading: false,
  showExtraInfo: false,
  showPhoneCode: false,
  showRegionFilter: false,
  showTimezone: false,
  showCurrency: false,
  allowCreate: false,
  regions: () => []
})

const emit = defineEmits<Emits>()

const dropdown = ref()
const currentFilter = ref('')
const selectedRegion = ref<string | null>(null)

const selectedCountryId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedCountry = computed(() => {
  if (!selectedCountryId.value) return null
  
  return props.countries.find(country => {
    const countryId = country[props.optionValue]
    return countryId === selectedCountryId.value
  }) || null
})

// Get country flag emoji from country code
const getCountryFlag = (code?: string): string => {
  if (!code || code.length !== 2) return '🏳️'
  
  // Convert country code to regional indicator symbols
  const codePoints = code
    .toUpperCase()
    .split('')
    .map(char => 127397 + char.charCodeAt(0))
  
  try {
    return String.fromCodePoint(...codePoints)
  } catch {
    return '🏳️'
  }
}

const onChange = (event: any) => {
  emit('change', selectedCountry.value)
}

const onFilter = (event: any) => {
  currentFilter.value = event.target?.value || ''
  emit('filter', event)
}

const onShow = () => {
  emit('show')
}

const onHide = () => {
  currentFilter.value = ''
  emit('hide')
}

const onRegionChange = (region: string | null) => {
  selectedRegion.value = region
  emit('region-change', region)
}

const createCountry = () => {
  emit('create-country')
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
    dropdown.value?.$el?.querySelector('input')?.focus()
  })
}

defineExpose({
  show,
  hide,
  focus
})
</script>

<style scoped>
.country-picker :deep(.p-select) {
  border-radius: 0.375rem;
}

.country-picker :deep(.p-select-overlay) {
  max-height: 300px;
}

.country-picker :deep(.p-select-option) {
  padding: 0;
}

.country-picker :deep(.p-select-label) {
  padding: 0.5rem 0.75rem;
}

/* Region filter dropdown */
.country-picker :deep(.p-select-header .p-select) {
  border: 1px solid #e5e7eb;
  border-radius: 0.25rem;
  font-size: 0.75rem;
}
</style>