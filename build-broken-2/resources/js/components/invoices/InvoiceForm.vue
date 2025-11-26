<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import CurrencySelector from '@/components/currency/CurrencySelector.vue'
import ExchangeRateInput from './ExchangeRateInput.vue'
import LineItemsTable from './LineItemsTable.vue'
import InvoiceTotals from './InvoiceTotals.vue'
import CustomerSelector from './CustomerSelector.vue'

interface Currency {
  code: string
  name: string
  symbol: string
  display_name: string
  is_base?: boolean
  default_rate?: number
}

interface Customer {
  id: string
  name: string
  email?: string
}

interface LineItem {
  id: string
  description: string
  quantity: number
  unit_price: number
  discount_type: 'none' | 'percentage' | 'fixed'
  discount_value: number
  tax_rate: number
  total: number
}

interface InvoiceFormProps {
  currencies: Currency[]
  customers: Customer[]
  baseCurrency: Currency
  defaultCurrency?: string
  initialData?: any
}

const props = withDefaults(defineProps<InvoiceFormProps>(), {
  defaultCurrency: 'USD'
})

const emit = defineEmits<{
  'save': [data: any]
  'cancel': []
}>()

const schema = toTypedSchema(z.object({
  customer_id: z.string().min(1, 'Customer is required'),
  invoice_number: z.string().optional(),
  currency_code: z.string().min(3, 'Currency is required'),
  exchange_rate: z.number().min(0.000001, 'Exchange rate must be positive'),
  issue_date: z.string().min(1, 'Issue date is required'),
  due_date: z.string().min(1, 'Due date is required'),
  po_number: z.string().optional(),
  notes: z.string().optional(),
  terms_and_conditions: z.string().optional(),
}))

const { defineField, handleSubmit, errors, setFieldValue, watch: watchField } = useForm({
  validationSchema: schema,
  initialValues: {
    customer_id: '',
    invoice_number: '',
    currency_code: props.defaultCurrency,
    exchange_rate: 1.0,
    issue_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days from now
    po_number: '',
    notes: '',
    terms_and_conditions: '',
  }
})

// Form fields
const [customerId] = defineField('customer_id')
const [invoiceNumber] = defineField('invoice_number')
const [currencyCode] = defineField('currency_code')
const [exchangeRate] = defineField('exchange_rate')
const [issueDate] = defineField('issue_date')
const [dueDate] = defineField('due_date')
const [poNumber] = defineField('po_number')
const [notes] = defineField('notes')
const [termsAndConditions] = defineField('terms_and_conditions')

// Line items and totals
const lineItems = ref<LineItem[]>([])
const totals = ref({
  subtotal: 0,
  discount_amount: 0,
  tax_amount: 0,
  shipping_amount: 0,
  total_amount: 0,
})

const selectedCurrency = computed(() => {
  return props.currencies.find(c => c.code === currencyCode.value) || props.currencies[0]
})

const isMultiCurrency = computed(() => {
  return currencyCode.value !== props.baseCurrency.code
})

const baseCurrencyTotal = computed(() => {
  if (!isMultiCurrency.value) {
    return totals.value.total_amount
  }
  return totals.value.total_amount * exchangeRate.value
})

// Watch currency changes to update exchange rate
watchField('currency_code', (newCurrency) => {
  if (newCurrency) {
    const currency = props.currencies.find(c => c.code === newCurrency)
    if (currency && currency.default_rate) {
      setFieldValue('exchange_rate', currency.default_rate)
    } else if (newCurrency === props.baseCurrency.code) {
      setFieldValue('exchange_rate', 1.0)
    }
  }
})

const onCurrencyChange = (currency: Currency | null) => {
  if (currency && currency.default_rate) {
    setFieldValue('exchange_rate', currency.default_rate)
  } else if (currency?.code === props.baseCurrency.code) {
    setFieldValue('exchange_rate', 1.0)
  }
}

const updateLineItems = (newLineItems: LineItem[]) => {
  lineItems.value = newLineItems
  calculateTotals()
}

const calculateTotals = () => {
  const subtotal = lineItems.value.reduce((sum, item) => {
    return sum + (item.quantity * item.unit_price)
  }, 0)

  const discountAmount = lineItems.value.reduce((sum, item) => {
    const lineSubtotal = item.quantity * item.unit_price
    if (item.discount_type === 'percentage') {
      return sum + (lineSubtotal * item.discount_value / 100)
    } else if (item.discount_type === 'fixed') {
      return sum + item.discount_value
    }
    return sum
  }, 0)

  const taxAmount = lineItems.value.reduce((sum, item) => {
    const lineSubtotal = item.quantity * item.unit_price
    const lineDiscount = item.discount_type === 'percentage' 
      ? lineSubtotal * item.discount_value / 100 
      : item.discount_value
    const taxableAmount = lineSubtotal - lineDiscount
    return sum + (taxableAmount * item.tax_rate / 100)
  }, 0)

  const totalAmount = subtotal - discountAmount + taxAmount + totals.value.shipping_amount

  totals.value = {
    subtotal,
    discount_amount: discountAmount,
    tax_amount: taxAmount,
    shipping_amount: totals.value.shipping_amount,
    total_amount: totalAmount,
  }
}

const addLineItem = () => {
  const newItem: LineItem = {
    id: `item-${Date.now()}`,
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_type: 'none',
    discount_value: 0,
    tax_rate: 0,
    total: 0,
  }
  lineItems.value.push(newItem)
}

const removeLineItem = (index: number) => {
  lineItems.value.splice(index, 1)
  calculateTotals()
}

const onSubmit = handleSubmit((values) => {
  const invoiceData = {
    ...values,
    line_items: lineItems.value,
    totals: totals.value,
    base_currency_total: baseCurrencyTotal.value,
  }
  emit('save', invoiceData)
})

const onCancel = () => {
  emit('cancel')
}

// Initialize with one line item
addLineItem()
</script>

<template>
  <form @submit="onSubmit" class="space-y-6">
    <!-- Header Section -->
    <Card>
      <CardHeader>
        <CardTitle>Invoice Details</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Customer Selection -->
          <div class="space-y-2">
            <Label for="customer">Customer *</Label>
            <CustomerSelector
              v-model="customerId"
              :customers="customers"
              placeholder="Select customer"
              class="w-full"
            />
            <div v-if="errors.customer_id" class="text-sm text-red-500">
              {{ errors.customer_id }}
            </div>
          </div>

          <!-- Invoice Number -->
          <div class="space-y-2">
            <Label for="invoice-number">Invoice Number</Label>
            <Input
              id="invoice-number"
              v-model="invoiceNumber"
              placeholder="Auto-generated if empty"
            />
          </div>

          <!-- Issue Date -->
          <div class="space-y-2">
            <Label for="issue-date">Issue Date *</Label>
            <Input
              id="issue-date"
              v-model="issueDate"
              type="date"
              required
            />
            <div v-if="errors.issue_date" class="text-sm text-red-500">
              {{ errors.issue_date }}
            </div>
          </div>

          <!-- Due Date -->
          <div class="space-y-2">
            <Label for="due-date">Due Date *</Label>
            <Input
              id="due-date"
              v-model="dueDate"
              type="date"
              required
            />
            <div v-if="errors.due_date" class="text-sm text-red-500">
              {{ errors.due_date }}
            </div>
          </div>

          <!-- PO Number -->
          <div class="space-y-2 md:col-span-2">
            <Label for="po-number">Purchase Order Number</Label>
            <Input
              id="po-number"
              v-model="poNumber"
              placeholder="Customer's PO number"
            />
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Currency Section -->
    <Card>
      <CardHeader>
        <CardTitle>Currency & Exchange Rate</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Currency Selection -->
          <div class="space-y-2">
            <Label>Invoice Currency *</Label>
            <CurrencySelector
              v-model="currencyCode"
              :currencies="currencies"
              @change="onCurrencyChange"
              placeholder="Select currency"
              class="w-full"
            />
            <div v-if="errors.currency_code" class="text-sm text-red-500">
              {{ errors.currency_code }}
            </div>
          </div>

          <!-- Exchange Rate -->
          <div class="space-y-2">
            <Label>Exchange Rate *</Label>
            <ExchangeRateInput
              v-model="exchangeRate"
              :from-currency="currencyCode"
              :to-currency="baseCurrency.code"
              :disabled="!isMultiCurrency"
              class="w-full"
            />
            <div v-if="errors.exchange_rate" class="text-sm text-red-500">
              {{ errors.exchange_rate }}
            </div>
            <div class="text-xs text-muted-foreground">
              <span v-if="isMultiCurrency">
                1 {{ selectedCurrency?.code }} = {{ exchangeRate }} {{ baseCurrency.code }}
              </span>
              <span v-else>
                Base currency - no conversion needed
              </span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Line Items Section -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>Line Items</CardTitle>
          <Button type="button" variant="outline" @click="addLineItem">
            Add Item
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <LineItemsTable
          v-model="lineItems"
          :currency="selectedCurrency"
          @update="updateLineItems"
          @remove="removeLineItem"
        />
      </CardContent>
    </Card>

    <!-- Totals Section -->
    <InvoiceTotals
      :totals="totals"
      :currency="selectedCurrency"
      :base-currency="baseCurrency"
      :exchange-rate="exchangeRate"
      :show-base-currency="isMultiCurrency"
    />

    <!-- Notes Section -->
    <Card>
      <CardHeader>
        <CardTitle>Additional Information</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="space-y-2">
          <Label for="notes">Internal Notes</Label>
          <Textarea
            id="notes"
            v-model="notes"
            placeholder="Internal notes (not visible to customer)"
            rows="3"
          />
        </div>

        <div class="space-y-2">
          <Label for="terms">Terms & Conditions</Label>
          <Textarea
            id="terms"
            v-model="termsAndConditions"
            placeholder="Terms and conditions for this invoice"
            rows="3"
          />
        </div>
      </CardContent>
    </Card>

    <!-- Action Buttons -->
    <div class="flex justify-end gap-4 pt-6">
      <Button type="button" variant="outline" @click="onCancel">
        Cancel
      </Button>
      <Button type="button" variant="secondary">
        Save as Draft
      </Button>
      <Button type="submit">
        Create Invoice
      </Button>
    </div>
  </form>
</template>