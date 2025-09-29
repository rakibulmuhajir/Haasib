<template>
  <div class="space-y-4">
    <!-- Amount and Currency Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Amount *
        </label>
        <InputNumber
          v-model="localAmount"
          :max="maxAmount"
          :min="0"
          :step="0.01"
          mode="currency"
          :currency="selectedCurrency?.code || 'USD'"
          class="w-full"
          :class="{ 'p-invalid': errors.amount }"
          @update:modelValue="onAmountChange"
        />
        <small v-if="errors.amount" class="text-red-600 dark:text-red-400">
          {{ errors.amount }}
        </small>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Currency *
        </label>
        <CurrencyPicker
          v-model="localCurrencyId"
          :currencies="currencies"
          :error="errors.currency_id"
          @change="onCurrencyChange"
        />
        <small v-if="errors.currency_id" class="text-red-600 dark:text-red-400">
          {{ errors.currency_id }}
        </small>
      </div>
    </div>

    <!-- Payment Date and Method Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Payment Date *
        </label>
        <Calendar
          v-model="localPaymentDate"
          placeholder="Select date"
          class="w-full"
          dateFormat="yy-mm-dd"
          :class="{ 'p-invalid': errors.payment_date }"
          @update:modelValue="onPaymentDateChange"
        />
        <small v-if="errors.payment_date" class="text-red-600 dark:text-red-400">
          {{ errors.payment_date }}
        </small>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Payment Method *
        </label>
        <Dropdown
          v-model="localPaymentMethod"
          :options="paymentMethods"
          optionLabel="label"
          optionValue="value"
          placeholder="Select payment method"
          class="w-full"
          :class="{ 'p-invalid': errors.payment_method }"
          @update:modelValue="onPaymentMethodChange"
        />
        <small v-if="errors.payment_method" class="text-red-600 dark:text-red-400">
          {{ errors.payment_method }}
        </small>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import InputNumber from 'primevue/inputnumber'
import Calendar from 'primevue/calendar'
import Dropdown from 'primevue/dropdown'
import CurrencyPicker from '@/Components/UI/Forms/CurrencyPicker.vue'

interface Props {
  modelValue?: {
    amount?: number
    currency_id?: number | string
    payment_date?: string
    payment_method?: string
  }
  currencies: Array<any>
  paymentMethods: Array<{ label: string; value: string }>
  errors?: Record<string, string>
  maxAmount?: number
}

interface Emits {
  (e: 'update:modelValue', value: any): void
  (e: 'amount-change', value: number): void
  (e: 'currency-change', value: number | string): void
  (e: 'payment-date-change', value: string): void
  (e: 'payment-method-change', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => ({}),
  errors: () => ({}),
  maxAmount: Number.MAX_SAFE_INTEGER
})

const emit = defineEmits<Emits>()

const localAmount = computed({
  get: () => props.modelValue?.amount || 0,
  set: (value) => updateModelValue({ amount: value })
})

const localCurrencyId = computed({
  get: () => props.modelValue?.currency_id || null,
  set: (value) => updateModelValue({ currency_id: value })
})

const localPaymentDate = computed({
  get: () => props.modelValue?.payment_date || new Date().toISOString().split('T')[0],
  set: (value) => updateModelValue({ payment_date: value })
})

const localPaymentMethod = computed({
  get: () => props.modelValue?.payment_method || '',
  set: (value) => updateModelValue({ payment_method: value })
})

const selectedCurrency = computed(() => {
  return props.currencies.find(c => c.currency_id === props.modelValue?.currency_id || c.id === props.modelValue?.currency_id)
})

const updateModelValue = (updates: Partial<Props['modelValue']>) => {
  const newValue = { ...props.modelValue, ...updates }
  emit('update:modelValue', newValue)
}

const onAmountChange = (value: number) => {
  emit('amount-change', value)
}

const onCurrencyChange = (value: number | string) => {
  emit('currency-change', value)
}

const onPaymentDateChange = (value: string) => {
  emit('payment-date-change', value)
}

const onPaymentMethodChange = (value: string) => {
  emit('payment-method-change', value)
}
</script>