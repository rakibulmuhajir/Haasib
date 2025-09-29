<template>
  <div class="currency-picker">
    <Select
      v-model="selectedCurrencyId"
      :options="currencies"
      :optionLabel="optionLabel"
      :optionValue="optionValue"
      :optionDisabled="optionDisabled"
      :placeholder="placeholder"
      :showClear="showClear"
      :disabled="disabled"
      :class="['w-full', { 'p-invalid': error }]"
      :loading="loading"
      @change="onChange"
      @show="onShow"
      @hide="onHide"
    >
      <template #option="{ option }">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center space-x-3">
            <!-- Currency Code -->
            <span class="font-mono font-bold text-lg min-w-[3rem]">
              {{ option.code }}
            </span>
            
            <!-- Symbol -->
            <span class="text-gray-500 text-sm">
              {{ option.symbol }}
            </span>
            
            <!-- Name -->
            <span class="text-gray-700">
              {{ option.name }}
            </span>
          </div>
          
          <!-- Additional Info -->
          <div v-if="showExtraInfo" class="text-right text-xs text-gray-500">
            <div v-if="option.exchange_rate">
              1 {{ option.code }} = {{ formatExchangeRate(option.exchange_rate) }}
            </div>
            <div v-if="option.is_base" class="text-green-600 font-medium">
              Base Currency
            </div>
          </div>
        </div>
      </template>

      <template #value="{ value, placeholder }">
        <div v-if="value" class="flex items-center space-x-3">
          <span class="font-mono font-bold">{{ selectedCurrency?.code }}</span>
          <span class="text-gray-500">{{ selectedCurrency?.symbol }}</span>
          <span v-if="showName" class="text-gray-700">{{ selectedCurrency?.name }}</span>
        </div>
        <span v-else class="text-gray-400">{{ placeholder }}</span>
      </template>

      <!-- Header -->
      <template #header>
        <div class="p-3 border-b border-gray-200">
          <span class="text-sm font-medium text-gray-700">Select Currency</span>
        </div>
      </template>

      <!-- Empty state -->
      <template #empty>
        <div class="p-4 text-center text-gray-500">
          <i class="pi pi-money-bill text-2xl mb-2 block text-gray-300"></i>
          <p class="text-sm">No currencies available</p>
        </div>
      </template>
    </Select>

    <!-- Error message -->
    <div v-if="error" class="mt-1 text-sm text-red-600 flex items-center">
      <i class="pi pi-exclamation-triangle mr-1"></i>
      {{ error }}
    </div>

    <!-- Exchange rate info -->
    <div
      v-if="showExchangeRate && selectedCurrency && selectedCurrency.exchange_rate"
      class="mt-2 p-2 bg-blue-50 rounded border border-blue-200 text-xs"
    >
      <div class="flex items-center justify-between">
        <span class="text-blue-700">Exchange Rate:</span>
        <span class="font-medium text-blue-900">
          1 {{ baseCurrency }} = {{ formatExchangeRate(1 / selectedCurrency.exchange_rate) }} {{ selectedCurrency.code }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Select from 'primevue/select'

interface Currency {
  id: number | string
  code: string
  symbol: string
  name: string
  exchange_rate?: number
  is_base?: boolean
  [key: string]: any
}

interface Props {
  modelValue?: number | string | null
  currencies: Currency[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (currency: Currency) => boolean
  placeholder?: string
  showClear?: boolean
  disabled?: boolean
  loading?: boolean
  error?: string
  showName?: boolean
  showExtraInfo?: boolean
  showExchangeRate?: boolean
  baseCurrency?: string
}

interface Emits {
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'change', currency: Currency | null): void
  (e: 'show'): void
  (e: 'hide'): void
}

const props = withDefaults(defineProps<Props>(), {
  optionLabel: 'code',
  optionValue: 'id',
  placeholder: 'Select currency...',
  showClear: false,
  disabled: false,
  loading: false,
  showName: false,
  showExtraInfo: false,
  showExchangeRate: false,
  baseCurrency: 'USD'
})

const emit = defineEmits<Emits>()

const selectedCurrencyId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedCurrency = computed(() => {
  if (!selectedCurrencyId.value) return null
  
  return props.currencies.find(currency => {
    const currencyId = currency[props.optionValue]
    return currencyId === selectedCurrencyId.value
  }) || null
})

const formatExchangeRate = (rate: number) => {
  return new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 4,
    maximumFractionDigits: 4
  }).format(rate)
}

const onChange = (event: any) => {
  emit('change', selectedCurrency.value)
}

const onShow = () => {
  emit('show')
}

const onHide = () => {
  emit('hide')
}
</script>

<style scoped>
.currency-picker :deep(.p-select) {
  border-radius: 0.375rem;
}

.currency-picker :deep(.p-select-overlay) {
  max-height: 300px;
}
</style>