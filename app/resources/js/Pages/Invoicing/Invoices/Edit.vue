<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Calendar from 'primevue/calendar'
import Textarea from 'primevue/textarea'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputNumber from 'primevue/inputnumber'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import CustomerPicker from '@/Components/UI/Forms/CustomerPicker.vue'
import CurrencyPicker from '@/Components/UI/Forms/CurrencyPicker.vue'

const props = defineProps({
  invoice: Object,
  customers: Array,
  currencies: Array,
})

const page = usePage()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Invoices', url: '/invoices', icon: 'list' },
  { label: `Edit Invoice ${props.invoice.invoice_number}`, url: `/invoices/${props.invoice.invoice_id}/edit`, icon: 'edit' },
])

// Form setup with existing invoice data
const form = useForm({
  customer_id: props.invoice.customer_id,
  currency_id: props.invoice.currency_id,
  invoice_number: props.invoice.invoice_number,
  invoice_date: props.invoice.invoice_date,
  due_date: props.invoice.due_date,
  notes: props.invoice.notes || '',
  terms: props.invoice.terms || '',
  items: props.invoice.items.map(item => ({
    id: item.item_id,
    description: item.description,
    quantity: item.quantity,
    unit_price: item.unit_price,
    tax_rate: item.tax_rate || 0,
  })) || [
    {
      id: Date.now(),
      description: '',
      quantity: 1,
      unit_price: 0,
      tax_rate: 0,
    }
  ],
})

// Selected customer for currency default
const selectedCustomer = computed(() => {
  return props.customers.find(c => c.customer_id === form.customer_id)
})

// Get the selected currency code
const selectedCurrencyCode = computed(() => {
  if (selectedCustomer.value?.currency_id) {
    const currency = props.currencies.find(c => c.id === selectedCustomer.value.currency_id)
    return currency?.code || 'USD'
  }
  // Fallback to the form's currency_id
  const currency = props.currencies.find(c => c.id === form.currency_id)
  return currency?.code || 'USD'
})

// Auto-set currency based on customer selection
watch(() => form.customer_id, (newCustomerId) => {
  if (newCustomerId && !form.currency_id) {
    const customer = props.customers.find(c => c.customer_id === newCustomerId)
    if (customer?.currency_id) {
      form.currency_id = customer.currency_id
    }
  }
})

// Auto-calculate due date based on customer payment terms
watch(() => form.customer_id, (newCustomerId) => {
  if (newCustomerId && !form.due_date) {
    const customer = props.customers.find(c => c.customer_id === newCustomerId)
    if (customer?.payment_terms) {
      const dueDate = new Date(form.invoice_date)
      dueDate.setDate(dueDate.getDate() + customer.payment_terms)
      form.due_date = dueDate.toISOString().split('T')[0]
    }
  }
})

// Update due date when invoice date changes
watch(() => form.invoice_date, (newInvoiceDate) => {
  if (newInvoiceDate && form.customer_id && form.due_date) {
    const customer = props.customers.find(c => c.customer_id === form.customer_id)
    if (customer?.payment_terms) {
      const dueDate = new Date(newInvoiceDate)
      dueDate.setDate(dueDate.getDate() + customer.payment_terms)
      form.due_date = dueDate.toISOString().split('T')[0]
    }
  }
})

// Event handlers for picker components
const onCustomerChange = (_customer) => {
  // Customer change logic is handled by watchers
}

const onCurrencyChange = () => {
  // Currency change logic is handled by watchers
}

// Item management
const addInvoiceItem = () => {
  form.items.push({
    id: Date.now() + Math.random(),
    description: '',
    quantity: 1,
    unit_price: 0,
    tax_rate: 0,
  })
}

const removeInvoiceItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

// Calculate totals
const subtotal = computed(() => {
  return form.items.reduce((sum, item) => {
    return sum + (item.quantity * item.unit_price)
  }, 0)
})

const totalTax = computed(() => {
  return form.items.reduce((sum, item) => {
    return sum + (item.quantity * item.unit_price * (item.tax_rate / 100))
  }, 0)
})

const totalAmount = computed(() => {
  return subtotal.value + totalTax.value
})

// Format currency
const formatCurrency = (amount, currency) => {
  if (!amount || !currency) return '-'

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 2,
  }).format(amount)
}

// Submit form
const submitForm = () => {
  form.transform(data => ({
    ...data,
    subtotal: subtotal.value,
    tax_amount: totalTax.value,
    total_amount: totalAmount.value,
  }))

  form.put(route('invoices.update', props.invoice.invoice_id), {
    preserveScroll: true,
    onSuccess: () => {
      // Success toast is handled by Inertia flash messages
    },
    onError: (errors) => {
      console.error('Form validation errors:', errors)
    }
  })
}
</script>

<template>
  <Head :title="`Edit Invoice ${invoice.invoice_number}`" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar />
    </template>

    <div class="p-6">
      <!-- Page Header -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
              Edit Invoice {{ invoice.invoice_number }}
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
              Update invoice details and line items
            </p>
          </div>
          <div class="flex items-center gap-3">
            <Link
              :href="route('invoices.show', invoice.invoice_id)"
              class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              <SvgIcon name="arrow-left" class="w-4 h-4 mr-2" />
              Back to Invoice
            </Link>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="form.processing" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl">
          <div class="flex items-center">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <span class="ml-3 text-gray-900 dark:text-gray-100">Updating invoice...</span>
          </div>
        </div>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Invoice Details -->
        <Card>
          <template #title>
            <span class="flex items-center gap-2">
              <SvgIcon name="file-text" class="w-5 h-5" />
              Invoice Details
            </span>
          </template>
          <template #content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Customer -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Customer *
                </label>
                <CustomerPicker
                  v-model="form.customer_id"
                  :customers="customers"
                  :error="form.errors.customer_id"
                  @change="onCustomerChange"
                />
                <div v-if="form.errors.customer_id" class="text-red-600 text-sm mt-1">
                  {{ form.errors.customer_id }}
                </div>
              </div>

              <!-- Currency -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Currency *
                </label>
                <CurrencyPicker
                  v-model="form.currency_id"
                  :currencies="currencies"
                  :error="form.errors.currency_id"
                  @change="onCurrencyChange"
                />
                <div v-if="form.errors.currency_id" class="text-red-600 text-sm mt-1">
                  {{ form.errors.currency_id }}
                </div>
              </div>

              <!-- Invoice Number -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Invoice Number *
                </label>
                <InputText
                  v-model="form.invoice_number"
                  placeholder="INV-0001"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.invoice_number }"
                />
                <div v-if="form.errors.invoice_number" class="text-red-600 text-sm mt-1">
                  {{ form.errors.invoice_number }}
                </div>
              </div>

              <!-- Invoice Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Invoice Date *
                </label>
                <Calendar
                  v-model="form.invoice_date"
                  dateFormat="yy-mm-dd"
                  placeholder="Select date"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.invoice_date }"
                />
                <div v-if="form.errors.invoice_date" class="text-red-600 text-sm mt-1">
                  {{ form.errors.invoice_date }}
                </div>
              </div>

              <!-- Due Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Due Date *
                </label>
                <Calendar
                  v-model="form.due_date"
                  dateFormat="yy-mm-dd"
                  placeholder="Select date"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.due_date }"
                />
                <div v-if="form.errors.due_date" class="text-red-600 text-sm mt-1">
                  {{ form.errors.due_date }}
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div class="mt-6">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Notes
              </label>
              <Textarea
                v-model="form.notes"
                placeholder="Additional notes..."
                rows="3"
                class="w-full"
              />
            </div>

            <!-- Terms -->
            <div class="mt-6">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Terms & Conditions
              </label>
              <Textarea
                v-model="form.terms"
                placeholder="Payment terms and conditions..."
                rows="3"
                class="w-full"
              />
            </div>
          </template>
        </Card>

        <!-- Line Items -->
        <Card>
          <template #title>
            <span class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <SvgIcon name="list" class="w-5 h-5" />
                Line Items
              </span>
              <Button
                type="button"
                label="Add Item"
                icon="pi pi-plus"
                size="small"
                @click="addInvoiceItem"
              />
            </span>
          </template>
          <template #content>
            <DataTable :value="form.items" stripedRows class="mb-4">
              <Column field="description" header="Description">
                <template #body="slotProps">
                  <Textarea
                    v-model="slotProps.data.description"
                    placeholder="Item description"
                    rows="2"
                    class="w-full"
                    :class="{ 'p-invalid': form.errors[`items.${slotProps.index}.description`] }"
                  />
                  <div v-if="form.errors[`items.${slotProps.index}.description`]" class="text-red-600 text-sm mt-1">
                    {{ form.errors[`items.${slotProps.index}.description`] }}
                  </div>
                </template>
              </Column>

              <Column field="quantity" header="Qty" style="width: 100px">
                <template #body="slotProps">
                  <InputNumber
                    v-model="slotProps.data.quantity"
                    :min="0.01"
                    :step="0.01"
                    class="w-full"
                    :class="{ 'p-invalid': form.errors[`items.${slotProps.index}.quantity`] }"
                  />
                  <div v-if="form.errors[`items.${slotProps.index}.quantity`]" class="text-red-600 text-sm mt-1">
                    {{ form.errors[`items.${slotProps.index}.quantity`] }}
                  </div>
                </template>
              </Column>

              <Column field="unit_price" header="Unit Price" style="width: 150px">
                <template #body="slotProps">
                  <InputNumber
                    v-model="slotProps.data.unit_price"
                    :min="0"
                    :step="0.01"
                    mode="currency"
                    :currency="selectedCurrencyCode"
                    class="w-full"
                    :class="{ 'p-invalid': form.errors[`items.${slotProps.index}.unit_price`] }"
                  />
                  <div v-if="form.errors[`items.${slotProps.index}.unit_price`]" class="text-red-600 text-sm mt-1">
                    {{ form.errors[`items.${slotProps.index}.unit_price`] }}
                  </div>
                </template>
              </Column>

              <Column field="tax_rate" header="Tax %" style="width: 100px">
                <template #body="slotProps">
                  <InputNumber
                    v-model="slotProps.data.tax_rate"
                    :min="0"
                    :max="100"
                    :step="0.1"
                    suffix="%"
                    class="w-full"
                  />
                </template>
              </Column>

              <Column field="total" header="Total" style="width: 150px">
                <template #body="slotProps">
                  <div class="text-right font-medium">
                    {{ formatCurrency(slotProps.data.quantity * slotProps.data.unit_price, selectedCurrencyCode) }}
                  </div>
                </template>
              </Column>

              <Column style="width: 50px">
                <template #body="slotProps">
                  <Button
                    type="button"
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    outlined
                    @click="removeInvoiceItem(slotProps.index)"
                    :disabled="form.items.length <= 1"
                    v-tooltip="'Remove item'"
                  />
                </template>
              </Column>
            </DataTable>

            <!-- Totals -->
            <div class="flex justify-end">
              <div class="w-80 space-y-2">
                <div class="flex justify-between">
                  <span>Subtotal:</span>
                  <span class="font-medium">{{ formatCurrency(subtotal, selectedCurrencyCode) }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Tax:</span>
                  <span class="font-medium">{{ formatCurrency(totalTax, selectedCurrencyCode) }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                  <span>Total:</span>
                  <span>{{ formatCurrency(totalAmount, selectedCurrencyCode) }}</span>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3">
          <Link
            :href="route('invoices.show', invoice.invoice_id)"
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            Cancel
          </Link>
          <Button
            type="submit"
            label="Update Invoice"
            icon="pi pi-save"
            :loading="form.processing"
            :disabled="form.processing"
          />
        </div>
      </form>
    </div>
  </LayoutShell>
</template>
