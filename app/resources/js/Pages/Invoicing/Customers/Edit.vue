<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'

const page = usePage()
const toast = useToast()

const props = defineProps<{
  customer: any
  countries: any[]
  availableCurrencies: any[]
}>()

const customerTypes = [
  { label: 'Individual', value: 'individual' },
  { label: 'Business', value: 'business' },
  { label: 'Non-profit', value: 'non_profit' },
  { label: 'Government', value: 'government' },
]

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Suspended', value: 'suspended' },
]

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoicing', icon: 'invoice' },
  { label: 'Customers', url: '/invoicing/customers', icon: 'customers' },
  { label: props.customer.name, url: `/invoicing/customers/${props.customer.id}`, icon: 'customer' },
  { label: 'Edit Customer', url: '#', icon: 'edit' }
])


// Initialize form with customer data
const form = useForm({
  name: props.customer.name || '',
  customer_type: props.customer.customer_type || '',
  customer_number: props.customer.customer_number || '',
  email: props.customer.email || '',
  phone: props.customer.phone || '',
  website: props.customer.website || '',
  address_line_1: props.customer.address_line_1 || '',
  address_line_2: props.customer.address_line_2 || '',
  city: props.customer.city || '',
  state_province: props.customer.state_province || '',
  postal_code: props.customer.postal_code || '',
  country_id: props.customer.country_id || null,
  currency_id: props.customer.currency_id || null,
  tax_id: props.customer.tax_number || '',
  tax_exempt: props.customer.tax_exempt || false,
  payment_terms: props.customer.payment_terms?.toString() || '',
  credit_limit: props.customer.credit_limit || null,
  status: props.customer.status || 'active',
  notes: props.customer.notes || '',
})

const submit = () => {
  form.put(route('customers.update', props.customer.id), {
    onSuccess: () => {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Customer updated successfully',
        life: 3000,
      })
    },
    onError: () => {
      toast.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to update customer',
        life: 3000,
      })
    },
  })
}

// Handle flash messages
onMounted(() => {
  const flash = page.props.flash as any
  if (flash?.success) {
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: flash.success,
      life: 3000,
    })
  }

  if (flash?.error) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: flash.error,
      life: 3000,
    })
  }
})
</script>

<template>
  <Head title="Edit Customer" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing" />
    </template>

    <template #topbar>
      <Breadcrumb :items="breadcrumbItems" />
    </template>

    <div class="max-w-4xl">
      <PageHeader 
        title="Edit Customer" 
        :subtitle="`Update information for ${customer.name}`" 
      />
      
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
        <form @submit.prevent="submit">
          <!-- Basic Information Section -->
          <div class="border-b border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">Basic Information</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the customer's primary details</p>
            </div>
            
            <!-- Row 1: Customer Name and Customer Number -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Customer Name <span class="text-red-500">*</span>
                </label>
                <InputText
                  v-model="form.name"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.name }"
                />
                <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
                  {{ form.errors.name }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Customer Number
                </label>
                <InputText
                  v-model="form.customer_number"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.customer_number }"
                  placeholder="Auto-generated if empty"
                />
                <div v-if="form.errors.customer_number" class="text-red-500 text-sm mt-1">
                  {{ form.errors.customer_number }}
                </div>
              </div>
            </div>

            <!-- Row 2: Customer Type and Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Customer Type <span class="text-red-500">*</span>
                </label>
                <Dropdown
                  v-model="form.customer_type"
                  :options="customerTypes"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Select customer type"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.customer_type }"
                />
                <div v-if="form.errors.customer_type" class="text-red-500 text-sm mt-1">
                  {{ form.errors.customer_type }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Status <span class="text-red-500">*</span>
                </label>
                <Dropdown
                  v-model="form.status"
                  :options="statusOptions"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Select status"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.status }"
                />
                <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                  {{ form.errors.status }}
                </div>
              </div>
            </div>

            <!-- Row 3: Email and Phone -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Email
                </label>
                <InputText
                  v-model="form.email"
                  type="email"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.email }"
                />
                <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">
                  {{ form.errors.email }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Phone
                </label>
                <InputText
                  v-model="form.phone"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.phone }"
                />
                <div v-if="form.errors.phone" class="text-red-500 text-sm mt-1">
                  {{ form.errors.phone }}
                </div>
              </div>
            </div>

            <!-- Row 4: Website -->
            <div class="px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Website
                </label>
                <InputText
                  v-model="form.website"
                  type="url"
                  class="w-full"
                  placeholder="https://example.com"
                  :class="{ 'p-invalid': form.errors.website }"
                />
                <div v-if="form.errors.website" class="text-red-500 text-sm mt-1">
                  {{ form.errors.website }}
                </div>
              </div>
            </div>
          </div>

          <!-- Address Information Section -->
          <div class="border-b border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">Billing Address</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Customer's primary billing address</p>
            </div>
            
            <!-- Row 1: Address Line 1 and 2 -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Address Line 1
                </label>
                <InputText
                  v-model="form.address_line_1"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.address_line_1 }"
                />
                <div v-if="form.errors.address_line_1" class="text-red-500 text-sm mt-1">
                  {{ form.errors.address_line_1 }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Address Line 2
                </label>
                <InputText
                  v-model="form.address_line_2"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.address_line_2 }"
                />
                <div v-if="form.errors.address_line_2" class="text-red-500 text-sm mt-1">
                  {{ form.errors.address_line_2 }}
                </div>
              </div>
            </div>

            <!-- Row 2: City and State/Province -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  City
                </label>
                <InputText
                  v-model="form.city"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.city }"
                />
                <div v-if="form.errors.city" class="text-red-500 text-sm mt-1">
                  {{ form.errors.city }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  State/Province
                </label>
                <InputText
                  v-model="form.state_province"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.state_province }"
                />
                <div v-if="form.errors.state_province" class="text-red-500 text-sm mt-1">
                  {{ form.errors.state_province }}
                </div>
              </div>
            </div>

            <!-- Row 3: Postal Code and Country -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Postal Code
                </label>
                <InputText
                  v-model="form.postal_code"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.postal_code }"
                />
                <div v-if="form.errors.postal_code" class="text-red-500 text-sm mt-1">
                  {{ form.errors.postal_code }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Country
                </label>
                <Dropdown
                  v-model="form.country_id"
                  :options="countries"
                  optionLabel="name"
                  optionValue="id"
                  placeholder="Select country"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.country_id }"
                />
                <div v-if="form.errors.country_id" class="text-red-500 text-sm mt-1">
                  {{ form.errors.country_id }}
                </div>
              </div>
            </div>
          </div>

          <!-- Financial Information Section -->
          <div class="border-b border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">Financial Information</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tax, payment terms, and credit settings</p>
            </div>
            
            <!-- Row 1: Tax ID and Tax Exempt -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Tax ID / VAT Number
                </label>
                <InputText
                  v-model="form.tax_id"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.tax_id }"
                />
                <div v-if="form.errors.tax_id" class="text-red-500 text-sm mt-1">
                  {{ form.errors.tax_id }}
                </div>
              </div>

              <div class="flex items-center space-x-3 pt-6">
                <Checkbox
                  v-model="form.tax_exempt"
                  :binary="true"
                  inputId="tax_exempt"
                />
                <label for="tax_exempt" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                  Tax Exempt
                </label>
              </div>
            </div>

            <!-- Row 2: Currency and Payment Terms -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Currency
                </label>
                <Dropdown
                  v-model="form.currency_id"
                  :options="availableCurrencies"
                  optionLabel="code"
                  optionValue="id"
                  placeholder="Select currency"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.currency_id }"
                />
                <div v-if="form.errors.currency_id" class="text-red-500 text-sm mt-1">
                  {{ form.errors.currency_id }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Payment Terms (days)
                </label>
                <InputText
                  v-model="form.payment_terms"
                  class="w-full"
                  placeholder="e.g., 30"
                  :class="{ 'p-invalid': form.errors.payment_terms }"
                />
                <div v-if="form.errors.payment_terms" class="text-red-500 text-sm mt-1">
                  {{ form.errors.payment_terms }}
                </div>
              </div>
            </div>

            <!-- Row 3: Credit Limit -->
            <div class="grid grid-cols-1 gap-6 px-6 pb-6">
              <div class="md:w-1/2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Credit Limit
                </label>
                <InputNumber
                  v-model="form.credit_limit"
                  mode="currency"
                  currency="USD"
                  :class="{ 'p-invalid': form.errors.credit_limit }"
                  class="w-full"
                />
                <div v-if="form.errors.credit_limit" class="text-red-500 text-sm mt-1">
                  {{ form.errors.credit_limit }}
                </div>
              </div>
            </div>
          </div>

          <!-- Additional Information Section -->
          <div>
            <div class="px-6 py-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">Additional Information</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Notes and other details</p>
            </div>
            
            <!-- Notes -->
            <div class="px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Notes
                </label>
                <Textarea
                  v-model="form.notes"
                  rows="4"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.notes }"
                />
                <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">
                  {{ form.errors.notes }}
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-4">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-500 dark:text-gray-400">
                Last updated: {{ new Date(customer.updated_at).toLocaleString() }}
              </div>
              <div class="flex items-center gap-2">
                <Button 
                  @click="router.visit(route('customers.show', customer.id))" 
                  type="button"
                  severity="secondary"
                  text
                  label="Cancel"
                />
                <Button
                  label="Save Changes"
                  type="submit"
                  :loading="form.processing"
                  icon="pi pi-check"
                />
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </LayoutShell>
</template>