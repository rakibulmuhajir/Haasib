<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Textarea from 'primevue/textarea'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputNumber from 'primevue/inputnumber'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'

const props = defineProps({
  customers: Array,
  currencies: Array,
  nextInvoiceNumber: String,
})

const page = usePage()
const toast = page.props.toast || {}

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Invoices', url: '/invoices', icon: 'list' },
  { label: 'Create Invoice', url: '/invoices/create', icon: 'plus' },
])

// Form setup
const form = useForm({
  customer_id: '',
  currency_id: '',
  invoice_number: props.nextInvoiceNumber || '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  notes: '',
  terms: '',
  items: [
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
  return props.customers.find(c => c.id === form.customer_id)
})

// Auto-set currency based on customer selection
watch(() => form.customer_id, (newCustomerId) => {
  if (newCustomerId && !form.currency_id) {
    const customer = props.customers.find(c => c.id === newCustomerId)
    if (customer?.currency_id) {
      form.currency_id = customer.currency_id
    }
  }
})

// Auto-calculate due date based on customer payment terms
watch(() => form.customer_id, (newCustomerId) => {
  if (newCustomerId && !form.due_date) {
    const customer = props.customers.find(c => c.id === newCustomerId)
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
    const customer = props.customers.find(c => c.id === form.customer_id)
    if (customer?.payment_terms) {
      const dueDate = new Date(newInvoiceDate)
      dueDate.setDate(dueDate.getDate() + customer.payment_terms)
      form.due_date = dueDate.toISOString().split('T')[0]
    }
  }
})

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

// Calculations
const calculateSubtotal = (item) => {
  return (item.quantity || 0) * (item.unit_price || 0)
}

const calculateTaxAmount = (item) => {
  const subtotal = calculateSubtotal(item)
  return subtotal * ((item.tax_rate || 0) / 100)
}

const calculateItemTotal = (item) => {
  return calculateSubtotal(item) + calculateTaxAmount(item)
}

const invoiceSubtotal = computed(() => {
  return form.items.reduce((sum, item) => sum + calculateSubtotal(item), 0)
})

const invoiceTaxTotal = computed(() => {
  return form.items.reduce((sum, item) => sum + calculateTaxAmount(item), 0)
})

const invoiceTotal = computed(() => {
  return invoiceSubtotal.value + invoiceTaxTotal.value
})

// Format currency
const formatCurrency = (amount, currencyCode = 'USD') => {
  if (!amount) return '$0.00'
  
  const currency = props.currencies.find(c => c.id === form.currency_id)
  const code = currency?.code || currencyCode
  
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: code,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

// Validation
const validateForm = () => {
  const errors = []

  if (!form.customer_id) {
    errors.push('Customer is required')
  }

  if (!form.invoice_number?.trim()) {
    errors.push('Invoice number is required')
  }

  if (!form.invoice_date) {
    errors.push('Invoice date is required')
  }

  if (!form.due_date) {
    errors.push('Due date is required')
  }

  for (let i = 0; i < form.items.length; i++) {
    const item = form.items[i]
    if (!item.description?.trim()) {
      errors.push(`Item ${i + 1}: Description is required`)
    }
    if (!item.quantity || item.quantity <= 0) {
      errors.push(`Item ${i + 1}: Quantity must be greater than 0`)
    }
    if (!item.unit_price || item.unit_price < 0) {
      errors.push(`Item ${i + 1}: Unit price must be 0 or greater`)
    }
    if (item.tax_rate < 0 || item.tax_rate > 100) {
      errors.push(`Item ${i + 1}: Tax rate must be between 0 and 100`)
    }
  }

  return errors
}

// Submit form
const submitForm = () => {
  const errors = validateForm()
  if (errors.length > 0) {
    alert('Please fix the following errors:\n\n' + errors.join('\n'))
    return
  }

  form.transform((data) => ({
    ...data,
    items: data.items.map(item => ({
      description: item.description,
      quantity: parseFloat(item.quantity),
      unit_price: parseFloat(item.unit_price),
      tax_rate: parseFloat(item.tax_rate),
    }))
  }))

  form.post(route('invoices.store'), {
    onSuccess: () => {
      form.reset()
      toast.success = 'Invoice created successfully!'
    },
    onError: (errors) => {
      console.error('Form errors:', errors)
      toast.error = 'Failed to create invoice. Please check the form and try again.'
    }
  })
}

// Cancel and go back
const cancel = () => {
  form.reset()
  window.location.href = route('invoices.index')
}
</script>

<template>
  <Head title="Create Invoice" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing System" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
        <div class="flex items-center gap-2">
          <Button label="Cancel" severity="secondary" outlined @click="cancel" />
          <Button label="Save as Draft" severity="info" @click="submitForm" />
          <Button label="Create Invoice" severity="primary" @click="submitForm" />
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Page Header -->
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Invoice</h1>
        <p class="text-gray-600 dark:text-gray-400">Create and send professional invoices to your customers</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Customer Information -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="users" class="w-5 h-5" />
                Customer Information
              </span>
            </template>
            <template #content>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Customer *
                  </label>
                  <Dropdown
                    v-model="form.customer_id"
                    :options="customers"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select a customer"
                    class="w-full"
                    fluid
                    filter
                    :class="{ 'p-invalid': form.errors.customer_id }"
                  />
                  <small v-if="form.errors.customer_id" class="text-red-600 dark:text-red-400">
                    {{ form.errors.customer_id }}
                  </small>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Currency
                  </label>
                  <Dropdown
                    v-model="form.currency_id"
                    :options="currencies"
                    optionLabel="code"
                    optionValue="id"
                    placeholder="Select currency"
                    class="w-full"
                    fluid
                    :class="{ 'p-invalid': form.errors.currency_id }"
                  />
                  <small v-if="form.errors.currency_id" class="text-red-600 dark:text-red-400">
                    {{ form.errors.currency_id }}
                  </small>
                </div>
              </div>
            </template>
          </Card>

          <!-- Invoice Details -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="file-text" class="w-5 h-5" />
                Invoice Details
              </span>
            </template>
            <template #content>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Invoice Number *
                  </label>
                  <InputText
                    v-model="form.invoice_number"
                    placeholder="INV-2025-001"
                    class="w-full"
                    :class="{ 'p-invalid': form.errors.invoice_number }"
                  />
                  <small v-if="form.errors.invoice_number" class="text-red-600 dark:text-red-400">
                    {{ form.errors.invoice_number }}
                  </small>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Invoice Date *
                  </label>
                  <Calendar
                    v-model="form.invoice_date"
                    placeholder="Select date"
                    class="w-full"
                    dateFormat="yy-mm-dd"
                    :class="{ 'p-invalid': form.errors.invoice_date }"
                  />
                  <small v-if="form.errors.invoice_date" class="text-red-600 dark:text-red-400">
                    {{ form.errors.invoice_date }}
                  </small>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Due Date *
                  </label>
                  <Calendar
                    v-model="form.due_date"
                    placeholder="Select date"
                    class="w-full"
                    dateFormat="yy-mm-dd"
                    :class="{ 'p-invalid': form.errors.due_date }"
                  />
                  <small v-if="form.errors.due_date" class="text-red-600 dark:text-red-400">
                    {{ form.errors.due_date }}
                  </small>
                </div>
              </div>

              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Notes
                </label>
                <Textarea
                  v-model="form.notes"
                  placeholder="Additional notes for the customer..."
                  rows="3"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.notes }"
                />
              </div>

              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Terms & Conditions
                </label>
                <Textarea
                  v-model="form.terms"
                  placeholder="Payment terms and conditions..."
                  rows="3"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.terms }"
                />
              </div>
            </template>
          </Card>

          <!-- Invoice Items -->
          <Card>
            <template #title>
              <div class="flex items-center justify-between">
                <span class="flex items-center gap-2">
                  <SvgIcon name="list" class="w-5 h-5" />
                  Invoice Items
                </span>
                <Button 
                  label="Add Item" 
                  icon="pi pi-plus" 
                  size="small" 
                  severity="secondary" 
                  @click="addInvoiceItem"
                />
              </div>
            </template>
            <template #content>
              <div class="space-y-4">
                <div v-for="(item, index) in form.items" :key="item.id" class="border rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                      Item {{ index + 1 }}
                    </span>
                    <Button
                      icon="pi pi-trash"
                      size="small"
                      severity="danger"
                      outlined
                      @click="removeInvoiceItem(index)"
                      v-if="form.items.length > 1"
                      v-tooltip="'Remove item'"
                    />
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description *
                      </label>
                      <InputText
                        v-model="item.description"
                        placeholder="Product or service description"
                        class="w-full"
                        :class="{ 'p-invalid': form.errors[`items.${index}.description`] }"
                      />
                    </div>

                    <div class="md:col-span-2">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Quantity *
                      </label>
                      <InputNumber
                        v-model="item.quantity"
                        :min="0"
                        :step="1"
                        class="w-full"
                        :class="{ 'p-invalid': form.errors[`items.${index}.quantity`] }"
                      />
                    </div>

                    <div class="md:col-span-2">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Unit Price *
                      </label>
                      <InputNumber
                        v-model="item.unit_price"
                        :min="0"
                        :step="0.01"
                        mode="currency"
                        :currency="currencies.find(c => c.id === form.currency_id)?.code || 'USD'"
                        class="w-full"
                        :class="{ 'p-invalid': form.errors[`items.${index}.unit_price`] }"
                      />
                    </div>

                    <div class="md:col-span-2">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Tax Rate (%)
                      </label>
                      <InputNumber
                        v-model="item.tax_rate"
                        :min="0"
                        :max="100"
                        :step="0.1"
                        suffix="%"
                        class="w-full"
                        :class="{ 'p-invalid': form.errors[`items.${index}.tax_rate`] }"
                      />
                    </div>
                  </div>

                  <!-- Item totals -->
                  <div class="flex justify-end mt-3 text-sm text-gray-600 dark:text-gray-400">
                    <div class="text-right">
                      <div>Subtotal: {{ formatCurrency(calculateSubtotal(item)) }}</div>
                      <div>Tax: {{ formatCurrency(calculateTaxAmount(item)) }}</div>
                      <div class="font-medium text-gray-900 dark:text-white">
                        Total: {{ formatCurrency(calculateItemTotal(item)) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Invoice Summary -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="calculator" class="w-5 h-5" />
                Invoice Summary
              </span>
            </template>
            <template #content>
              <div class="space-y-3">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                  <span class="font-medium">{{ formatCurrency(invoiceSubtotal) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Tax:</span>
                  <span class="font-medium">{{ formatCurrency(invoiceTaxTotal) }}</span>
                </div>
                <div class="border-t pt-3">
                  <div class="flex justify-between">
                    <span class="font-medium text-gray-900 dark:text-white">Total:</span>
                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                      {{ formatCurrency(invoiceTotal) }}
                    </span>
                  </div>
                </div>
              </div>
            </template>
          </Card>

          <!-- Quick Actions -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="lightning" class="w-5 h-5" />
                Quick Actions
              </span>
            </template>
            <template #content>
              <div class="space-y-2">
                <Button 
                  label="Save Draft" 
                  icon="pi pi-save" 
                  severity="secondary" 
                  class="w-full" 
                  @click="submitForm"
                />
                <Button 
                  label="Create & Send" 
                  icon="pi pi-send" 
                  severity="primary" 
                  class="w-full" 
                  @click="submitForm"
                />
                <Button 
                  label="Preview" 
                  icon="pi pi-eye" 
                  severity="info" 
                  outlined 
                  class="w-full" 
                  disabled
                />
              </div>
            </template>
          </Card>

          <!-- Customer Preview -->
          <Card v-if="selectedCustomer">
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="users" class="w-5 h-5" />
                Customer Preview
              </span>
            </template>
            <template #content>
              <div class="text-sm space-y-2">
                <div>
                  <span class="font-medium">{{ selectedCustomer.name }}</span>
                </div>
                <div v-if="selectedCustomer.email" class="text-gray-600 dark:text-gray-400">
                  {{ selectedCustomer.email }}
                </div>
                <div v-if="selectedCustomer.phone" class="text-gray-600 dark:text-gray-400">
                  {{ selectedCustomer.phone }}
                </div>
                <div v-if="selectedCustomer.payment_terms" class="text-xs text-gray-500 dark:text-gray-500">
                  Payment Terms: {{ selectedCustomer.payment_terms }} days
                </div>
              </div>
            </template>
          </Card>
        </div>
      </div>
    </div>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>