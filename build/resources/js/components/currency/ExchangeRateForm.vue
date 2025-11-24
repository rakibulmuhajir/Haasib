<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Calendar } from 'lucide-vue-next'

interface ExchangeRate {
  from_currency_code: string
  to_currency_code: string
  rate: number
  effective_date: string
  notes?: string
}

interface Currency {
  code: string
  name: string
  symbol: string
}

interface ExchangeRateFormProps {
  currencies: Currency[]
  baseCurrency?: Currency
  onSave: (data: ExchangeRate) => void
  onCancel: () => void
}

const props = defineProps<ExchangeRateFormProps>()

const formSchema = toTypedSchema(z.object({
  from_currency_code: z.string().min(1, 'From currency is required'),
  to_currency_code: z.string().min(1, 'To currency is required'),
  rate: z.number().min(0.000001, 'Rate must be greater than 0'),
  effective_date: z.string().min(1, 'Effective date is required'),
  notes: z.string().optional(),
}))

const { defineField, handleSubmit, errors, resetForm } = useForm({
  validationSchema: formSchema,
  initialValues: {
    from_currency_code: '',
    to_currency_code: '',
    rate: 1.0,
    effective_date: new Date().toISOString().split('T')[0],
    notes: ''
  }
})

const [from_currency_code, fromCurrencyAttrs] = defineField('from_currency_code')
const [to_currency_code, toCurrencyAttrs] = defineField('to_currency_code')
const [rate, rateAttrs] = defineField('rate')
const [effective_date, effectiveDateAttrs] = defineField('effective_date')
const [notes, notesAttrs] = defineField('notes')

const availableFromCurrencies = computed(() => {
  if (!props.baseCurrency) return []
  return props.currencies.filter(c => c.code !== props.baseCurrency.code)
})

const availableToCurrencies = computed(() => {
  if (!props.baseCurrency) return []
  return props.currencies.filter(c => c.code !== props.baseCurrency.code)
})

const onSubmit = handleSubmit((values) => {
  const exchangeRateData: ExchangeRate = {
    from_currency_code: values.from_currency_code,
    to_currency_code: values.to_currency_code,
    rate: values.rate,
    effective_date: values.effective_date,
    notes: values.notes
  }
  
  props.onSave(exchangeRateData)
  resetForm()
})
</script>

<template>
  <form @submit="onSubmit" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="space-y-2">
        <Label for="from_currency_code">From Currency *</Label>
        <Select v-bind="fromCurrencyAttrs">
          <SelectTrigger>
            <SelectValue placeholder="Select from currency" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="currency in availableFromCurrencies"
              :key="currency.code"
              :value="currency.code"
            >
              {{ currency.code }} - {{ currency.name }}
            </SelectItem>
          </SelectContent>
        </Select>
        <div v-if="errors.from_currency_code" class="text-sm text-red-500">
          {{ errors.from_currency_code }}
        </div>
      </div>

      <div class="space-y-2">
        <Label for="to_currency_code">To Currency *</Label>
        <Select v-bind="toCurrencyAttrs">
          <SelectTrigger>
            <SelectValue placeholder="Select to currency" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="currency in availableToCurrencies"
              :key="currency.code"
              :value="currency.code"
            >
              {{ currency.code }} - {{ currency.name }}
            </SelectItem>
          </SelectContent>
        </Select>
        <div v-if="errors.to_currency_code" class="text-sm text-red-500">
          {{ errors.to_currency_code }}
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="space-y-2">
        <Label for="rate">Exchange Rate *</Label>
        <Input
          id="rate"
          type="number"
          step="0.000001"
          min="0.000001"
          placeholder="1.000000"
          v-bind="rateAttrs"
        />
        <div v-if="errors.rate" class="text-sm text-red-500">
          {{ errors.rate }}
        </div>
      </div>

      <div class="space-y-2">
        <Label for="effective_date">Effective Date *</Label>
        <Input
          id="effective_date"
          type="date"
          v-bind="effectiveDateAttrs"
        />
        <div v-if="errors.effective_date" class="text-sm text-red-500">
          {{ errors.effective_date }}
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <Label for="notes">Notes (Optional)</Label>
      <Input
        id="notes"
        placeholder="Enter any notes about this exchange rate..."
        v-bind="notesAttrs"
      />
      <div v-if="errors.notes" class="text-sm text-red-500">
        {{ errors.notes }}
      </div>
    </div>

    <div class="flex justify-end gap-2 pt-4">
      <Button type="button" variant="outline" @click="$emit('cancel')">
        Cancel
      </Button>
      <Button type="submit">
        Add Exchange Rate
      </Button>
    </div>
  </form>
</template>