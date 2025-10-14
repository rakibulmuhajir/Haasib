<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'

const page = usePage()
const toast = useToast()

const props = defineProps<{
  countries: any[]
  availableCurrencies: any[]
}>()

const customerTypes = [
  { label: 'Individual', value: 'individual' },
  { label: 'Small Business', value: 'small_business' },
  { label: 'Medium Business', value: 'medium_business' },
  { label: 'Large Business', value: 'large_business' },
  { label: 'Non-profit', value: 'non_profit' },
  { label: 'Government', value: 'government' },
]

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoicing', icon: 'invoice' },
  { label: 'Customers', url: '/invoicing/customers', icon: 'customers' },
  { label: 'Create Customer', url: '#', icon: 'plus' }
])

const form = useForm({
  name: '',
  customer_type: '',
  address_line_1: '',
  country_id: null as string | null,
  currency_id: null as string | null,
  contact: '',
  status: 'active',
})

const submit = () => {
  form.post(route('customers.store'), {
    onSuccess: () => {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Customer created successfully',
        life: 3000,
      })
    },
    onError: () => {
      toast.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to create customer',
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
  <Head title="Create Customer" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing" />
    </template>

    <template #topbar>
      <Breadcrumb :items="breadcrumbItems" />
    </template>

    <div class="max-w-4xl">
      <PageHeader title="Create Customer" subtitle="Add a new customer to the system" />
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
          <form @submit.prevent="submit">
            <!-- Row 1: Customer Name and Customer Type -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
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
            </div>

            <!-- Row 2: Address and Country -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-6 pb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Address
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

            <!-- Row 3: Currency and Contact -->
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
                  Contact
                </label>
                <InputText
                  v-model="form.contact"
                  placeholder="Email or phone number"
                  class="w-full"
                />
              </div>
            </div>

            <!-- Hidden Status Field -->
            <input type="hidden" v-model="form.status" />

            <!-- Info Note -->
            <div class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 mx-6 mb-6">
              <p class="text-sm text-blue-700 dark:text-blue-300">
                Additional information such as tax details, payment terms, and other settings can be added later from the customer details page.
              </p>
            </div>

            <div class="pt-4 flex items-center gap-2 px-6 pb-6">
              <Button 
                @click="router.visit(route('customers.index'))" 
                type="button"
                severity="secondary"
                text
                label="Cancel"
              />
              <Button
                label="Create Customer"
                type="submit"
                :loading="form.processing"
                icon="pi pi-check"
              />
            </div>
          </form>
        </div>
    </div>
  </LayoutShell>
</template>
