<template>
  <div class="amount-input">
    <InputGroup>
      <!-- Currency Symbol/Picker -->
      <InputGroupAddon v-if="showCurrency">
        <CurrencyPicker
          v-if="allowCurrencyChange"
          v-model="selectedCurrencyId"
          :currencies="currencies"
          :showClear="false"
          :disabled="disabled"
          size="small"
          @change="onCurrencyChange"
        />
        <span v-else class="font-medium">
          {{ selectedCurrency?.symbol || defaultCurrencySymbol }}
        </span>
      </InputGroupAddon>

      <!-- Amount Input -->
      <InputNumber
        ref="input"
        v-model="internalValue"
        :mode="mode"
        :currency="selectedCurrency?.code || defaultCurrency"
        :locale="locale"
        :min="min"
        :max="max"
        :step="step"
        :minFractionDigits="minFractionDigits"
        :maxFractionDigits="maxFractionDigits"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :invalid="invalid"
        :class="inputClass"
        :inputId="inputId"
        :inputStyle="inputStyle"
        @input="onInput"
        @change="onChange"
        @blur="onBlur"
        @focus="onFocus"
      />

      <!-- Clear button -->
      <Button
        v-if="showClear && value !== null && value !== undefined && value !== ''"
        icon="pi pi-times"
        class="p-button-text p-button-sm"
        @click="clearValue"
        :disabled="disabled"
      />
    </InputGroup>

    <!-- Error message -->
    <div v-if="error" class="mt-1 text-sm text-red-600 flex items-center">
      <i class="pi pi-exclamation-triangle mr-1"></i>
      {{ error }}
    </div>

    <!-- Helper text -->
    <div v-if="helperText" class="mt-1 text-sm text-gray-500">
      {{ helperText }}
    </div>

    <!-- Balance info for customer/invoice contexts -->
    <div
      v-if="showBalanceInfo && balance !== undefined"
      class="mt-2 p-2 bg-gray-50 rounded border border-gray-200 text-xs"
    >
      <div class="flex items-center justify-between">
        <span class="text-gray-600">{{ balanceLabel }}:</span>
        <BalanceDisplay
          :amount="balance"
          :currency="selectedCurrency?.code || defaultCurrency"
          size="xs"
          variant="inline"
        />
      </div>
    </div>

    <!-- Conversion info when currency is different from base -->
    <div
      v-if="showConversion && selectedCurrency && baseCurrency && selectedCurrency.code !== baseCurrency"
      class="mt-2 p-2 bg-blue-50 rounded border border-blue-200 text-xs"
    >
      <div class="flex items-center justify-between">
        <span class="text-blue-700">In {{ baseCurrency }}:</span>
        <span class="font-medium text-blue-900">
          {{ formatConvertedValue }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import InputNumber from 'primevue/inputnumber'
import InputGroup from 'primevue/inputgroup'
import InputGroupAddon from 'primevue/inputgroupaddon'
import Button from 'primevue/button'
import CurrencyPicker from './CurrencyPicker.vue'
import BalanceDisplay from '@/Components/BalanceDisplay.vue'

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
  currencies?: Currency[]
  currency?: string | number | null
  defaultCurrency?: string
  defaultCurrencySymbol?: string
  baseCurrency?: string
  showCurrency?: boolean
  allowCurrencyChange?: boolean
  mode?: 'decimal' | 'currency'
  locale?: string
  min?: number
  max?: number
  step?: number
  minFractionDigits?: number
  maxFractionDigits?: number
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  invalid?: boolean
  showClear?: boolean
  error?: string
  helperText?: string
  inputClass?: string | object | any[]
  inputId?: string
  inputStyle?: object
  balance?: number
  balanceLabel?: string
  showBalanceInfo?: boolean
  showConversion?: boolean
  autoFocus?: boolean
}

interface Emits {
  (e: 'update:modelValue', value: number | null): void
  (e: 'input', value: number | null): void
  (e: 'change', value: number | null): void
  (e: 'blur', event: Event): void
  (e: 'focus', event: Event): void
  (e: 'currency-change', currency: Currency | null): void
  (e: 'clear'): void
}

const props = withDefaults(defineProps<Props>(), {
  currencies: () => [],
  defaultCurrency: 'USD',
  defaultCurrencySymbol: '$',
  showCurrency: true,
  allowCurrencyChange: false,
  mode: 'currency',
  locale: 'en-US',
  step: 0.01,
  minFractionDigits: 2,
  maxFractionDigits: 2,
  placeholder: '0.00',
  disabled: false,
  readonly: false,
  invalid: false,
  showClear: false,
  balanceLabel: 'Available',
  showBalanceInfo: false,
  showConversion: false,
  autoFocus: false
})

const emit = defineEmits<Emits>()

const input = ref()

// Internal value for InputNumber
const internalValue = computed({
  get: () => props.modelValue,
  set: (value) => {
    const numValue = value === null || value === '' ? null : Number(value)
    emit('update:modelValue', numValue)
  }
})

// Selected currency handling
const selectedCurrencyId = computed({
  get: () => props.currency,
  set: (value) => emit('update:currency', value)
})

const selectedCurrency = computed(() => {
  if (!selectedCurrencyId.value || !props.currencies.length) return null
  
  return props.currencies.find(currency => {
    const currencyId = currency.id
    return currencyId === selectedCurrencyId.value
  }) || null
})

// Formatted converted value
const formatConvertedValue = computed(() => {
  if (!props.modelValue || !selectedCurrency.value?.exchange_rate) return '0.00'
  
  const convertedValue = props.modelValue * selectedCurrency.value.exchange_rate
  return new Intl.NumberFormat(props.locale, {
    style: 'currency',
    currency: props.baseCurrency || props.defaultCurrency,
    minimumFractionDigits: props.minFractionDigits,
    maximumFractionDigits: props.maxFractionDigits
  }).format(convertedValue)
})

// Event handlers
const onInput = (event: any) => {
  const value = event.value === null || event.value === '' ? null : Number(event.value)
  emit('input', value)
}

const onChange = (event: any) => {
  const value = event.value === null || event.value === '' ? null : Number(event.value)
  emit('change', value)
}

const onBlur = (event: Event) => {
  emit('blur', event)
}

const onFocus = (event: Event) => {
  emit('focus', event)
}

const onCurrencyChange = (currency: Currency | null) => {
  emit('currency-change', currency)
}

const clearValue = () => {
  emit('update:modelValue', null)
  emit('clear')
}

// Auto-focus on mount
watch(() => props.autoFocus, (shouldFocus) => {
  if (shouldFocus) {
    nextTick(() => {
      input.value?.$el?.querySelector('input')?.focus()
    })
  }
}, { immediate: true })

// Expose methods
const focus = () => {
  nextTick(() => {
    input.value?.$el?.querySelector('input')?.focus()
  })
}

const blur = () => {
  input.value?.$el?.querySelector('input')?.blur()
}

defineExpose({
  focus,
  blur
})
</script>

<style scoped>
.amount-input :deep(.p-inputnumber) {
  flex: 1;
}

.amount-input :deep(.p-inputtext) {
  width: 100%;
}

/* Currency picker in input group */
.amount-input :deep(.p-dropdown) {
  border: none;
  background: transparent;
  padding: 0;
}

.amount-input :deep(.p-dropdown .p-dropdown-label) {
  padding: 0;
  font-weight: 500;
}

.amount-input :deep(.p-dropdown-trigger) {
  width: 1.5rem;
}
</style>