<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Customer</h1>
      <p class="text-gray-600 dark:text-gray-400">Add a new customer to your system</p>
    </div>

    <Card>
      <template #content>
        <form @submit.prevent="submit">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column - Basic Information -->
            <div class="space-y-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b pb-2">
                Basic Information
              </h3>
              
              <!-- Customer Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Customer Name <span class="text-red-500">*</span>
                </label>
                <InputText
                  v-model="form.name"
                  class="w-full"
                  :class="{ 'p-invalid': errors.name }"
                />
                <div v-if="errors.name" class="text-red-500 text-sm mt-1">
                  {{ errors.name }}
                </div>
              </div>

              <!-- Customer Number -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Customer Number <span class="text-red-500">*</span>
                </label>
                <InputText
                  v-model="form.customer_number"
                  class="w-full"
                  :class="{ 'p-invalid': errors.customer_number }"
                />
                <div v-if="errors.customer_number" class="text-red-500 text-sm mt-1">
                  {{ errors.customer_number }}
                </div>
              </div>

              <!-- Customer Type -->
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
                  :class="{ 'p-invalid': errors.customer_type }"
                />
                <div v-if="errors.customer_type" class="text-red-500 text-sm mt-1">
                  {{ errors.customer_type }}
                </div>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Email
                </label>
                <InputText
                  v-model="form.email"
                  type="email"
                  class="w-full"
                  :class="{ 'p-invalid': errors.email }"
                />
                <div v-if="errors.email" class="text-red-500 text-sm mt-1">
                  {{ errors.email }}
                </div>
              </div>

              <!-- Phone -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Phone
                </label>
                <InputText
                  v-model="form.phone"
                  class="w-full"
                  :class="{ 'p-invalid': errors.phone }"
                />
                <div v-if="errors.phone" class="text-red-500 text-sm mt-1">
                  {{ errors.phone }}
                </div>
              </div>

              <!-- Website -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Website
                </label>
                <InputText
                  v-model="form.website"
                  type="url"
                  class="w-full"
                  :class="{ 'p-invalid': errors.website }"
                />
                <div v-if="errors.website" class="text-red-500 text-sm mt-1">
                  {{ errors.website }}
                </div>
              </div>
            </div>

            <!-- Right Column - Address & Settings -->
            <div class="space-y-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b pb-2">
                Address Information
              </h3>
              
              <!-- Address Line 1 -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Address Line 1
                </label>
                <InputText
                  v-model="form.address_line_1"
                  class="w-full"
                  :class="{ 'p-invalid': errors.address_line_1 }"
                />
                <div v-if="errors.address_line_1" class="text-red-500 text-sm mt-1">
                  {{ errors.address_line_1 }}
                </div>
              </div>

              <!-- Address Line 2 -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Address Line 2
                </label>
                <InputText
                  v-model="form.address_line_2"
                  class="w-full"
                  :class="{ 'p-invalid': errors.address_line_2 }"
                />
                <div v-if="errors.address_line_2" class="text-red-500 text-sm mt-1">
                  {{ errors.address_line_2 }}
                </div>
              </div>

              <!-- City -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  City
                </label>
                <InputText
                  v-model="form.city"
                  class="w-full"
                  :class="{ 'p-invalid': errors.city }"
                />
                <div v-if="errors.city" class="text-red-500 text-sm mt-1">
                  {{ errors.city }}
                </div>
              </div>

              <!-- State/Province -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  State/Province
                </label>
                <InputText
                  v-model="form.state_province"
                  class="w-full"
                  :class="{ 'p-invalid': errors.state_province }"
                />
                <div v-if="errors.state_province" class="text-red-500 text-sm mt-1">
                  {{ errors.state_province }}
                </div>
              </div>

              <!-- Postal Code -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Postal Code
                </label>
                <InputText
                  v-model="form.postal_code"
                  class="w-full"
                  :class="{ 'p-invalid': errors.postal_code }"
                />
                <div v-if="errors.postal_code" class="text-red-500 text-sm mt-1">
                  {{ errors.postal_code }}
                </div>
              </div>

              <!-- Country -->
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
                  :class="{ 'p-invalid': errors.country_id }"
                />
                <div v-if="errors.country_id" class="text-red-500 text-sm mt-1">
                  {{ errors.country_id }}
                </div>
              </div>
            </div>
          </div>

          <!-- Financial Information -->
          <div class="mt-8 space-y-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b pb-2">
              Financial Information
            </h3>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Currency -->
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
                  :class="{ 'p-invalid': errors.currency_id }"
                />
                <div v-if="errors.currency_id" class="text-red-500 text-sm mt-1">
                  {{ errors.currency_id }}
                </div>
              </div>

              <!-- Tax ID -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Tax ID
                </label>
                <InputText
                  v-model="form.tax_id"
                  class="w-full"
                  :class="{ 'p-invalid': errors.tax_id }"
                />
                <div v-if="errors.tax_id" class="text-red-500 text-sm mt-1">
                  {{ errors.tax_id }}
                </div>
              </div>

              <!-- Payment Terms -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Payment Terms
                </label>
                <InputText
                  v-model="form.payment_terms"
                  placeholder="e.g., Net 30, Due on Receipt"
                  class="w-full"
                  :class="{ 'p-invalid': errors.payment_terms }"
                />
                <div v-if="errors.payment_terms" class="text-red-500 text-sm mt-1">
                  {{ errors.payment_terms }}
                </div>
              </div>

              <!-- Credit Limit -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Credit Limit
                </label>
                <InputText
                  v-model="form.credit_limit"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full"
                  :class="{ 'p-invalid': errors.credit_limit }"
                />
                <div v-if="errors.credit_limit" class="text-red-500 text-sm mt-1">
                  {{ errors.credit_limit }}
                </div>
              </div>

              <!-- Tax Exempt -->
              <div>
                <div class="flex items-center">
                  <Checkbox
                    v-model="form.tax_exempt"
                    :binary="true"
                    class="mr-2"
                  />
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Tax Exempt
                  </label>
                </div>
              </div>

              <!-- Status -->
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
                  :class="{ 'p-invalid': errors.status }"
                />
                <div v-if="errors.status" class="text-red-500 text-sm mt-1">
                  {{ errors.status }}
                </div>
              </div>
            </div>
          </div>

          <!-- Primary Contact -->
          <div class="mt-8 space-y-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b pb-2">
              Primary Contact
            </h3>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- First Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  First Name
                </label>
                <InputText
                  v-model="form.primary_contact.first_name"
                  class="w-full"
                  :class="{ 'p-invalid': errors['primary_contact.first_name'] }"
                />
                <div v-if="errors['primary_contact.first_name']" class="text-red-500 text-sm mt-1">
                  {{ errors['primary_contact.first_name'] }}
                </div>
              </div>

              <!-- Last Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Last Name
                </label>
                <InputText
                  v-model="form.primary_contact.last_name"
                  class="w-full"
                  :class="{ 'p-invalid': errors['primary_contact.last_name'] }"
                />
                <div v-if="errors['primary_contact.last_name']" class="text-red-500 text-sm mt-1">
                  {{ errors['primary_contact.last_name'] }}
                </div>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Email
                </label>
                <InputText
                  v-model="form.primary_contact.email"
                  type="email"
                  class="w-full"
                  :class="{ 'p-invalid': errors['primary_contact.email'] }"
                />
                <div v-if="errors['primary_contact.email']" class="text-red-500 text-sm mt-1">
                  {{ errors['primary_contact.email'] }}
                </div>
              </div>

              <!-- Phone -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Phone
                </label>
                <InputText
                  v-model="form.primary_contact.phone"
                  class="w-full"
                  :class="{ 'p-invalid': errors['primary_contact.phone'] }"
                />
                <div v-if="errors['primary_contact.phone']" class="text-red-500 text-sm mt-1">
                  {{ errors['primary_contact.phone'] }}
                </div>
              </div>

              <!-- Position -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Position
                </label>
                <InputText
                  v-model="form.primary_contact.position"
                  class="w-full"
                  :class="{ 'p-invalid': errors['primary_contact.position'] }"
                />
                <div v-if="errors['primary_contact.position']" class="text-red-500 text-sm mt-1">
                  {{ errors['primary_contact.position'] }}
                </div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b pb-2 mb-4">
              Additional Information
            </h3>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Notes
              </label>
              <Textarea
                v-model="form.notes"
                rows="4"
                class="w-full"
                :class="{ 'p-invalid': errors.notes }"
              />
              <div v-if="errors.notes" class="text-red-500 text-sm mt-1">
                {{ errors.notes }}
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="mt-8 flex justify-end space-x-4">
            <Link :href="route('customers.index')">
              <Button
                label="Cancel"
                class="p-button-outlined p-button-secondary"
              />
            </Link>
            <Button
              type="submit"
              :label="form.processing ? 'Creating...' : 'Create Customer'"
              :loading="form.processing"
            />
          </div>
        </form>
      </template>
    </Card>
  </div>
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

interface Country {
  id: number
  name: string
  code: string
}

interface Currency {
  id: number
  code: string
  symbol: string
}

const props = defineProps<{
  countries: Country[]
  nextCustomerNumber: string
}>()


const customerTypes = [
  { label: 'Individual', value: 'individual' },
  { label: 'Business', value: 'business' },
  { label: 'Non-Profit', value: 'non_profit' },
  { label: 'Government', value: 'government' }
]

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Suspended', value: 'suspended' }
]

// Mock available currencies - in a real app, this would come from the backend
const availableCurrencies = ref<Currency[]>([
  { id: 1, code: 'USD', symbol: '$' },
  { id: 2, code: 'EUR', symbol: '€' },
  { id: 3, code: 'GBP', symbol: '£' }
])

const form = useForm({
  name: '',
  customer_number: props.nextCustomerNumber,
  customer_type: '',
  email: '',
  phone: '',
  website: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  state_province: '',
  postal_code: '',
  country_id: null as number | null,
  currency_id: null as number | null,
  tax_id: '',
  tax_exempt: false,
  payment_terms: '',
  credit_limit: '',
  status: 'active',
  notes: '',
  primary_contact: {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position: ''
  }
})

const submit = () => {
  form.post(route('customers.store'), {
    onSuccess: () => {
      // Handle success
    }
  })
}

onMounted(() => {
  // Set default values
  form.status = 'active'
  form.customer_type = 'business'
})
</script>