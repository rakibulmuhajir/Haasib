<script setup>
import { ref, onMounted } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import Toast from 'primevue/toast'

const page = usePage()
const toast = useToast()

const props = defineProps({
    countries: Array,
    availableCurrencies: Array,
    can: Object
})

const customerTypes = [
    { label: 'Individual', value: 'individual' },
    { label: 'Small Business', value: 'small_business' },
    { label: 'Medium Business', value: 'medium_business' },
    { label: 'Large Business', value: 'large_business' },
    { label: 'Non-profit', value: 'non_profit' },
    { label: 'Government', value: 'government' },
]

// Define page actions for create customer page
const customerCreateActions = [
    {
        key: 'cancel',
        label: 'Cancel',
        icon: 'pi pi-times',
        severity: 'secondary',
        outlined: true,
        action: () => router.visit('/customers')
    },
    {
        key: 'save',
        label: 'Create Customer',
        icon: 'pi pi-check',
        severity: 'primary',
        action: () => submit()
    }
]

const form = useForm({
    name: '',
    customer_type: '',
    address_line_1: '',
    country_id: null,
    currency_id: null,
    contact: '',
    credit_limit: null,
    status: 'active',
})

const submit = () => {
    form.post('/customers', {
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
    const flash = page.props.flash
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
  <LayoutShell>
    <Toast />
    
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Create Customer"
      description="Add a new customer to the system"
      subDescription="Fill in the customer details below"
      :default-actions="customerCreateActions"
      :show-search="false"
    />

    <!-- Main Content -->
    <div class="content-grid-1-1">
      <div class="main-content">
        <div class="form-container">
          <form @submit.prevent="submit">
            <!-- Row 1: Customer Name and Customer Type -->
            <div class="form-grid-2">
              <div class="form-field">
                <label class="form-label">
                  Customer Name <span class="text-red-500">*</span>
                </label>
                <InputText
                  v-model="form.name"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.name }"
                  placeholder="Enter customer name"
                />
                <div v-if="form.errors.name" class="form-error">
                  {{ form.errors.name }}
                </div>
              </div>

              <div class="form-field">
                <label class="form-label">
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
                <div v-if="form.errors.customer_type" class="form-error">
                  {{ form.errors.customer_type }}
                </div>
              </div>
            </div>

            <!-- Row 2: Address and Country -->
            <div class="form-grid-2">
              <div class="form-field">
                <label class="form-label">
                  Address
                </label>
                <InputText
                  v-model="form.address_line_1"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.address_line_1 }"
                  placeholder="Enter customer address"
                />
                <div v-if="form.errors.address_line_1" class="form-error">
                  {{ form.errors.address_line_1 }}
                </div>
              </div>

              <div class="form-field">
                <label class="form-label">
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
                <div v-if="form.errors.country_id" class="form-error">
                  {{ form.errors.country_id }}
                </div>
              </div>
            </div>

            <!-- Row 3: Currency and Contact -->
            <div class="form-grid-2">
              <div class="form-field">
                <label class="form-label">
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
                <div v-if="form.errors.currency_id" class="form-error">
                  {{ form.errors.currency_id }}
                </div>
              </div>

              <div class="form-field">
                <label class="form-label">
                  Contact
                </label>
                <InputText
                  v-model="form.contact"
                  placeholder="Email or phone number"
                  class="w-full"
                />
              </div>
            </div>

            <!-- Row 4: Credit Limit -->
            <div class="form-grid-2">
              <div class="form-field">
                <label class="form-label">
                  Credit Limit
                </label>
                <InputNumber
                  v-model="form.credit_limit"
                  mode="currency"
                  currency="USD"
                  locale="en-US"
                  placeholder="Enter credit limit"
                  class="w-full"
                  :class="{ 'p-invalid': form.errors.credit_limit }"
                  :minFractionDigits="2"
                  :maxFractionDigits="2"
                />
                <div v-if="form.errors.credit_limit" class="form-error">
                  {{ form.errors.credit_limit }}
                </div>
              </div>
              
              <!-- Empty second column to maintain grid layout -->
              <div></div>
            </div>

            <!-- Hidden Status Field -->
            <input type="hidden" v-model="form.status" />

            <!-- Info Note -->
            <div class="info-note">
              <p class="info-note-text">
                Additional information such as tax details, payment terms, and other settings can be added later from the customer details page.
              </p>
            </div>

            <!-- Form Actions (for mobile/desktop) -->
            <div class="form-actions">
              <Button 
                @click="router.visit('/customers')" 
                type="button"
                severity="secondary"
                text
                label="Cancel"
                :disabled="form.processing"
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
    </div>
  </LayoutShell>
</template>

<style scoped>
.form-container {
  @apply bg-white dark:bg-gray-800 shadow-sm rounded-lg;
}

.form-grid-2 {
  @apply grid grid-cols-1 md:grid-cols-2 gap-6 p-6;
}

.form-field {
  @apply space-y-2;
}

.form-label {
  @apply block text-sm font-medium text-gray-700 dark:text-gray-300;
}

.form-error {
  @apply text-red-500 text-sm mt-1;
}

.info-note {
  @apply mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 mx-6 mb-6;
}

.info-note-text {
  @apply text-sm text-blue-700 dark:text-blue-300;
}

.form-actions {
  @apply pt-4 flex items-center gap-2 px-6 pb-6;
}
</style>