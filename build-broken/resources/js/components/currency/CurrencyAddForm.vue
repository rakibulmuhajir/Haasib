<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'

interface CurrencyOption {
  code: string
  name: string
  symbol: string
  decimal_places: number
  is_popular: boolean
}

// Mock data - in real implementation, this would come from API
const availableCurrencies: CurrencyOption[] = [
  { code: 'USD', name: 'US Dollar', symbol: '$', decimal_places: 2, is_popular: true },
  { code: 'EUR', name: 'Euro', symbol: '€', decimal_places: 2, is_popular: true },
  { code: 'GBP', name: 'British Pound Sterling', symbol: '£', decimal_places: 2, is_popular: true },
  { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$', decimal_places: 2, is_popular: true },
  { code: 'AUD', name: 'Australian Dollar', symbol: 'A$', decimal_places: 2, is_popular: true },
  { code: 'JPY', name: 'Japanese Yen', symbol: '¥', decimal_places: 0, is_popular: true },
  { code: 'CHF', name: 'Swiss Franc', symbol: 'CHF', decimal_places: 2, is_popular: true },
  { code: 'CNY', name: 'Chinese Yuan', symbol: '¥', decimal_places: 2, is_popular: false },
  { code: 'INR', name: 'Indian Rupee', symbol: '₹', decimal_places: 2, is_popular: false },
  { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$', decimal_places: 2, is_popular: false },
]

const emit = defineEmits<{
  'save': [data: any]
  'cancel': []
}>()

const schema = toTypedSchema(z.object({
  currency_code: z.string().min(3, 'Currency code is required').max(3, 'Invalid currency code'),
  default_exchange_rate: z.number().min(0.000001, 'Exchange rate must be positive'),
  is_base_currency: z.boolean(),
  is_active: z.boolean(),
}))

const { defineField, handleSubmit, errors, setFieldValue } = useForm({
  validationSchema: schema,
  initialValues: {
    currency_code: '',
    default_exchange_rate: 1.0,
    is_base_currency: false,
    is_active: true,
  }
})

const [currencyCode, currencyCodeAttrs] = defineField('currency_code')
const [exchangeRate, exchangeRateAttrs] = defineField('default_exchange_rate')
const [isBaseCurrency, isBaseCurrencyAttrs] = defineField('is_base_currency')
const [isActive, isActiveAttrs] = defineField('is_active')

const selectedCurrency = ref<CurrencyOption | null>(null)

const popularCurrencies = computed(() => 
  availableCurrencies.filter(c => c.is_popular)
)

const allCurrencies = computed(() => 
  availableCurrencies.sort((a, b) => {
    if (a.is_popular && !b.is_popular) return -1
    if (!a.is_popular && b.is_popular) return 1
    return a.name.localeCompare(b.name)
  })
)

const onCurrencySelect = (code: string) => {
  const currency = availableCurrencies.find(c => c.code === code)
  if (currency) {
    selectedCurrency.value = currency
    setFieldValue('currency_code', currency.code)
  }
}

const onSubmit = handleSubmit((values) => {
  const currency = selectedCurrency.value
  if (!currency) return

  emit('save', {
    currency_code: values.currency_code,
    currency_name: currency.name,
    currency_symbol: currency.symbol,
    default_exchange_rate: values.default_exchange_rate,
    is_base_currency: values.is_base_currency,
    is_active: values.is_active,
  })
})

const onCancel = () => {
  emit('cancel')
}
</script>

<template>
  <form @submit="onSubmit" class="space-y-4">
    <div class="space-y-2">
      <Label for="currency-select">Select Currency</Label>
      <Select @update:model-value="onCurrencySelect">
        <SelectTrigger>
          <SelectValue>
            <span v-if="selectedCurrency" class="flex items-center gap-2">
              <span class="font-mono">{{ selectedCurrency.symbol }}</span>
              <span>{{ selectedCurrency.name }} ({{ selectedCurrency.code }})</span>
            </span>
            <span v-else class="text-muted-foreground">Choose a currency</span>
          </SelectValue>
        </SelectTrigger>
        <SelectContent>
          <div class="px-2 py-1 text-xs font-medium text-muted-foreground">Popular Currencies</div>
          <SelectItem
            v-for="currency in popularCurrencies"
            :key="currency.code"
            :value="currency.code"
          >
            <div class="flex items-center gap-2">
              <span class="font-mono w-8">{{ currency.symbol }}</span>
              <span class="font-medium">{{ currency.code }}</span>
              <span class="text-sm text-muted-foreground">{{ currency.name }}</span>
            </div>
          </SelectItem>
          
          <div class="px-2 py-1 text-xs font-medium text-muted-foreground border-t mt-2">All Currencies</div>
          <SelectItem
            v-for="currency in allCurrencies.filter(c => !c.is_popular)"
            :key="currency.code"
            :value="currency.code"
          >
            <div class="flex items-center gap-2">
              <span class="font-mono w-8">{{ currency.symbol }}</span>
              <span class="font-medium">{{ currency.code }}</span>
              <span class="text-sm text-muted-foreground">{{ currency.name }}</span>
            </div>
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-if="errors.currency_code" class="text-sm text-red-500">
        {{ errors.currency_code }}
      </div>
    </div>

    <div class="space-y-2">
      <Label for="exchange-rate">Default Exchange Rate</Label>
      <Input
        id="exchange-rate"
        v-model.number="exchangeRate"
        v-bind="exchangeRateAttrs"
        type="number"
        step="0.000001"
        placeholder="1.000000"
        class="w-full"
      />
      <div v-if="errors.default_exchange_rate" class="text-sm text-red-500">
        {{ errors.default_exchange_rate }}
      </div>
      <div class="text-xs text-muted-foreground">
        Exchange rate relative to your base currency (1 base = X of this currency)
      </div>
    </div>

    <div class="space-y-4">
      <div class="flex items-center space-x-2">
        <Switch
          id="is-base"
          v-model:checked="isBaseCurrency"
          v-bind="isBaseCurrencyAttrs"
        />
        <Label for="is-base" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
          Set as base currency
        </Label>
      </div>
      <div class="text-xs text-muted-foreground">
        Base currency cannot be deleted and has a fixed rate of 1.0
      </div>

      <div class="flex items-center space-x-2">
        <Switch
          id="is-active"
          v-model:checked="isActive"
          v-bind="isActiveAttrs"
        />
        <Label for="is-active" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
          Active for transactions
        </Label>
      </div>
    </div>

    <div class="flex justify-end gap-2 pt-4">
      <Button type="button" variant="outline" @click="onCancel">
        Cancel
      </Button>
      <Button type="submit" :disabled="!selectedCurrency">
        Add Currency
      </Button>
    </div>
  </form>
</template>