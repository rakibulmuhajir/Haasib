<template>
  <LayoutShell :title="pageMeta.title">
    <template #title>
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Create Payment</h1>
        <Button 
          :label="'Back to Payments'" 
          icon="pi pi-arrow-left" 
          outlined 
          size="small"
          @click="navigateTo(route('payments.index'))"
        />
      </div>
    </template>

    <template #content>
      <Card>
        <template #title>Payment Information</template>
        <template #content>
          <Form
            :action="route('payments.store')"
            method="post"
            #default="{ errors, hasErrors, processing, wasSuccessful, reset }"
          >
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Left Column -->
              <div class="space-y-6">
                <!-- Customer Selection -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Customer *</label>
                  <Dropdown
                    name="customer_id"
                    v-model="form.customer_id"
                    :options="customers"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select a customer"
                    class="w-full"
                    :class="{ 'p-invalid': errors.customer_id }"
                    :loading="loadingCustomers"
                    @change="onCustomerChange"
                  />
                  <div v-if="errors.customer_id" class="text-sm text-red-600">
                    {{ errors.customer_id }}
                  </div>
                </div>

                <!-- Payment Amount -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Payment Amount *</label>
                  <InputNumber
                    name="amount"
                    v-model="form.amount"
                    :min="0.01"
                    :currency="selectedCurrency?.code || 'USD'"
                    :locale="'en-US'"
                    mode="currency"
                    class="w-full"
                    :class="{ 'p-invalid': errors.amount }"
                  />
                  <div v-if="errors.amount" class="text-sm text-red-600">
                    {{ errors.amount }}
                  </div>
                </div>

                <!-- Payment Method -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Payment Method *</label>
                  <Dropdown
                    name="payment_method"
                    v-model="form.payment_method"
                    :options="paymentMethodOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Select payment method"
                    class="w-full"
                    :class="{ 'p-invalid': errors.payment_method }"
                  />
                  <div v-if="errors.payment_method" class="text-sm text-red-600">
                    {{ errors.payment_method }}
                  </div>
                </div>

                <!-- Payment Date -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Payment Date *</label>
                  <Calendar
                    name="payment_date"
                    v-model="form.payment_date"
                    dateFormat="yy-mm-dd"
                    placeholder="Select payment date"
                    class="w-full"
                    :class="{ 'p-invalid': errors.payment_date }"
                  />
                  <div v-if="errors.payment_date" class="text-sm text-red-600">
                    {{ errors.payment_date }}
                  </div>
                </div>

                <!-- Currency -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Currency *</label>
                  <Dropdown
                    name="currency_id"
                    v-model="form.currency_id"
                    :options="currencies"
                    optionLabel="code"
                    optionValue="id"
                    placeholder="Select currency"
                    class="w-full"
                    :class="{ 'p-invalid': errors.currency_id }"
                  />
                  <div v-if="errors.currency_id" class="text-sm text-red-600">
                    {{ errors.currency_id }}
                  </div>
                </div>
              </div>

              <!-- Right Column -->
              <div class="space-y-6">
                <!-- Payment Reference -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Payment Reference</label>
                  <InputText
                    name="payment_reference"
                    v-model="form.payment_reference"
                    placeholder="Enter payment reference (optional)"
                    class="w-full"
                    :class="{ 'p-invalid': errors.payment_reference }"
                  />
                  <div v-if="errors.payment_reference" class="text-sm text-red-600">
                    {{ errors.payment_reference }}
                  </div>
                  <p class="text-xs text-gray-500">Leave blank to auto-generate</p>
                </div>

                <!-- Exchange Rate (if different currency) -->
                <div v-if="showExchangeRate" class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Exchange Rate *</label>
                  <InputNumber
                    name="exchange_rate"
                    v-model="form.exchange_rate"
                    :min="0.0001"
                    :max-fraction-digits="6"
                    mode="decimal"
                    class="w-full"
                    :class="{ 'p-invalid': errors.exchange_rate }"
                  />
                  <div v-if="errors.exchange_rate" class="text-sm text-red-600">
                    {{ errors.exchange_rate }}
                  </div>
                  <p class="text-xs text-gray-500">Rate from {{ selectedCurrency?.code }} to company currency</p>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Notes</label>
                  <Textarea
                    name="notes"
                    v-model="form.notes"
                    :rows="4"
                    placeholder="Enter any additional notes about this payment"
                    class="w-full"
                    :class="{ 'p-invalid': errors.notes }"
                  />
                  <div v-if="errors.notes" class="text-sm text-red-600">
                    {{ errors.notes }}
                  </div>
                </div>

                <!-- Auto-Allocate Option -->
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Auto-Allocate</label>
                  <div class="flex items-center gap-2">
                    <Checkbox
                      name="auto_allocate"
                      v-model="form.auto_allocate"
                      :binary="true"
                      inputId="autoAllocate"
                    />
                    <label for="autoAllocate" class="text-sm text-gray-600">
                      Automatically allocate to customer's outstanding invoices
                    </label>
                  </div>
                </div>

                <!-- Outstanding Invoices Preview -->
                <div v-if="selectedCustomer && outstandingInvoices.length > 0" class="space-y-3">
                  <label class="text-sm font-medium text-gray-700">Outstanding Invoices</label>
                  <div class="border rounded-lg p-3 bg-gray-50">
                    <div class="space-y-2">
                      <div
                        v-for="invoice in outstandingInvoices"
                        :key="invoice.id"
                        class="flex justify-between items-center text-sm"
                      >
                        <span class="font-medium">{{ invoice.invoice_number }}</span>
                        <span class="text-red-600 font-medium">
                          {{ formatMoney(invoice.balance_due, invoice.currency) }}
                        </span>
                      </div>
                    </div>
                    <div class="mt-2 pt-2 border-t flex justify-between items-center">
                      <span class="font-medium text-gray-700">Total Outstanding:</span>
                      <span class="text-lg font-bold text-red-600">
                        {{ formatMoney(totalOutstanding, outstandingInvoices[0]?.currency) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex items-center justify-end gap-3 border-t pt-6">
              <Button
                type="button"
                label="Cancel"
                outlined
                @click="navigateTo(route('payments.index'))"
              />
              <Button
                type="submit"
                label="Create Payment"
                :loading="processing"
                icon="pi pi-save"
              />
            </div>
          </Form>
        </template>
      </Card>
    </template>
  </LayoutShell>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Card from 'primevue/card'
import { useForm } from '@inertiajs/vue3'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import { formatMoney, formatDate } from '@/Utils/formatting'

interface Customer {
  id: number
  name: string
  email?: string
  currency_id?: number
}

interface Invoice {
  id: number
  invoice_number: string
  due_date: string
  balance_amount: number
  currency: {
    id: number
    code: string
    symbol: string
  }
}

interface Currency {
  id: number
  code: string
  symbol: string
}

const props = defineProps<{
  customers: Customer[]
  invoices: Invoice[]
  nextPaymentNumber: string
}>()


const customerInvoices = ref<Invoice[]>([])
const availableCurrencies = ref<Currency[]>([])
const selectedCurrency = ref<Currency | null>(null)

const paymentMethods = [
  { label: 'Cash', value: 'cash' },
  { label: 'Check', value: 'check' },
  { label: 'Bank Transfer', value: 'bank_transfer' },
  { label: 'Credit Card', value: 'credit_card' },
  { label: 'Debit Card', value: 'debit_card' },
  { label: 'PayPal', value: 'paypal' },
  { label: 'Stripe', value: 'stripe' },
  { label: 'Other', value: 'other' }
]

const totalAllocated = computed(() => {
  return Object.values(form.invoice_allocations).reduce((sum, amount) => sum + (amount || 0), 0)
})

const handleCustomerChange = () => {
  const customer = props.customers.find(c => c.id === form.customer_id)
  if (customer) {
    // Filter invoices for this customer
    customerInvoices.value = props.invoices.filter(invoice => invoice.customer_id === customer.id)
    
    // Set default currency if customer has one
    if (customer.currency_id) {
      form.currency_id = customer.currency_id
      updateAvailableCurrencies()
    }
  } else {
    customerInvoices.value = []
  }
  
  // Clear allocations
  form.invoice_allocations = {}
}

const handleCurrencyChange = () => {
  updateAvailableCurrencies()
}

const updateAvailableCurrencies = () => {
  // For now, just use all currencies from invoices
  const currencySet = new Set()
  props.invoices.forEach(invoice => {
    currencySet.add(JSON.stringify(invoice.currency))
  })
  
  availableCurrencies.value = Array.from(currencySet).map(currencyStr => JSON.parse(currencyStr))
  
  selectedCurrency.value = availableCurrencies.value.find(c => c.id === form.currency_id) || null
}

const form = useForm({
  customer_id: null as number | null,
  payment_number: props.nextPaymentNumber,
  payment_date: new Date().toISOString().split('T')[0],
  amount: '',
  currency_id: null as number | null,
  payment_method: '',
  reference_number: '',
  notes: '',
  auto_allocate: true,
  invoice_allocations: {} as Record<number, number>
})

const submit = () => {
  form.post(route('payments.store'), {
    onSuccess: () => {
      // Handle success
    }
  })
}

onMounted(() => {
  // Set default payment date to today
  form.payment_date = new Date().toISOString().split('T')[0]
  
  // Initialize available currencies
  updateAvailableCurrencies()
})
</script>