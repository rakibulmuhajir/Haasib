<template>
  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing" />
    </template>

    <template #topbar>
      <Breadcrumb :items="breadcrumbItems" />
    </template>

    <div class="max-w-6xl">
      <PageHeader title="Customer Details" :subtitle="customerData.name" :maxActions="5">
        <template #actions-left>
          <Button 
            label="Create Invoice"
            icon="pi pi-file"
            size="small"
            @click="createInvoice"
          />
          <Button 
            label="Record Payment"
            icon="pi pi-dollar"
            size="small"
            severity="success"
            @click="recordPayment"
          />
          <Button 
            label="View Statement"
            icon="pi pi-file-pdf"
            size="small"
            severity="secondary"
            outlined
            @click="viewStatement"
          />
          <Button 
            label="Edit Customer"
            icon="pi pi-pencil"
            size="small"
            severity="info"
            outlined
            @click="editCustomer"
          />
        </template>
      </PageHeader>
      
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Customer Information -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Information -->
          <Card>
            <template #title>Basic Information</template>
            <template #content>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Customer ID -->
                <div>
                  <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                    Customer ID
                  </label>
                  <div class="flex items-center gap-2">
                    <span class="text-lg font-medium">#{{ customerData.id }}</span>
                    <span class="text-xs text-gray-500">(Read-only)</span>
                  </div>
                </div>
                
                <!-- Customer Number -->
                <div>
                  <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                    Customer Number
                  </label>
                  <div class="flex items-center gap-2">
                    <span class="text-lg font-medium">{{ customerData.customer_number }}</span>
                    <span class="text-xs text-gray-500">(Auto-generated)</span>
                  </div>
                </div>
                
                <!-- Customer Type -->
                <div>
                  <InlineEditable
                    v-model="customerData.customer_type"
                    v-model:editing="isEditingCustomerType"
                    label="Customer Type"
                    type="select"
                    :options="customerTypes"
                    :saving="isSaving('customer_type')"
                    @save="onSaveField('customer_type', $event)"
                    @cancel="cancelEditing"
                  >
                    <template #display>
                      <Tag
                        :value="formatCustomerType(customerData.customer_type)"
                        :severity="getTypeSeverity(customerData.customer_type)"
                      />
                    </template>
                  </InlineEditable>
                </div>
                
                <!-- Status -->
                <div>
                  <InlineEditable
                    v-model="customerData.status"
                    v-model:editing="isEditingStatus"
                    label="Status"
                    type="select"
                    :options="statusOptions"
                    :saving="isSaving('status')"
                    @save="onSaveField('status', $event)"
                    @cancel="cancelEditing"
                  >
                    <template #display>
                      <Tag
                        :value="formatStatus(customerData.status)"
                        :severity="getStatusSeverity(customerData.status)"
                      />
                    </template>
                  </InlineEditable>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                    Created
                  </label>
                  <div>{{ formatDate(customerData.created_at) }}</div>
                </div>
                
                <!-- Email -->
                <div>
                  <InlineEditable
                    v-model="customerData.email"
                    v-model:editing="isEditingEmail"
                    label="Email"
                    placeholder="Enter email"
                    :saving="isSaving('email')"
                    :validate="validateEmail"
                    @save="onSaveField('email', $event)"
                    @cancel="cancelEditing"
                  >
                    <template #display>
                      <a
                        v-if="customerData.email"
                        :href="`mailto:${customerData.email}`"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        @click.stop
                      >
                        {{ customerData.email }}
                      </a>
                    </template>
                  </InlineEditable>
                </div>
                
                <!-- Phone -->
                <div>
                  <InlineEditable
                    v-model="customerData.phone"
                    v-model:editing="isEditingPhone"
                    label="Phone"
                    placeholder="Enter phone number"
                    :saving="isSaving('phone')"
                    @save="onSaveField('phone', $event)"
                    @cancel="cancelEditing"
                  >
                    <template #display>
                      <a
                        v-if="customerData.phone"
                        :href="`tel:${customerData.phone}`"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        @click.stop
                      >
                        {{ customerData.phone }}
                      </a>
                    </template>
                  </InlineEditable>
                </div>
                
                <!-- Website -->
                <div class="md:col-span-2">
                  <InlineEditable
                    v-model="customerData.website"
                    v-model:editing="isEditingWebsite"
                    label="Website"
                    placeholder="https://example.com"
                    :saving="isSaving('website')"
                    :validate="validateUrl"
                    @save="onSaveField('website', $event)"
                    @cancel="cancelEditing"
                  >
                    <template #display>
                      <a
                        v-if="customerData.website"
                        :href="customerData.website"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        @click.stop
                      >
                        {{ customerData.website }}
                      </a>
                    </template>
                  </InlineEditable>
                </div>
              </div>
              
              <div v-if="customerData.notes" class="mt-4">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Notes
                </label>
                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                  {{ customerData.notes }}
                </div>
              </div>
            </template>
          </Card>

          <!-- Address Information -->
          <Card>
            <template #title>Address Information</template>
            <template #content>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Address Line 1 -->
                <div class="md:col-span-2">
                  <InlineEditable
                    v-model="customerData.address_line_1"
                    v-model:editing="isEditingAddressLine1"
                    label="Address Line 1"
                    placeholder="Enter address"
                    :saving="isSaving('address_line_1')"
                    @save="onSaveField('address_line_1', $event)"
                    @cancel="cancelEditing"
                  />
                </div>
                
                <!-- City -->
                <div>
                  <InlineEditable
                    v-model="customerData.city"
                    v-model:editing="isEditingCity"
                    label="City"
                    placeholder="Enter city"
                    :saving="isSaving('city')"
                    @save="onSaveField('city', $event)"
                    @cancel="cancelEditing"
                  />
                </div>
                
                <!-- State/Province -->
                <div>
                  <InlineEditable
                    v-model="customerData.state_province"
                    v-model:editing="isEditingStateProvince"
                    label="State/Province"
                    placeholder="Enter state/province"
                    :saving="isSaving('state_province')"
                    @save="onSaveField('state_province', $event)"
                    @cancel="cancelEditing"
                  />
                </div>
                
                <!-- Postal Code -->
                <div>
                  <InlineEditable
                    v-model="customerData.postal_code"
                    v-model:editing="isEditingPostalCode"
                    label="Postal Code"
                    placeholder="Enter postal code"
                    :saving="isSaving('postal_code')"
                    @save="onSaveField('postal_code', $event)"
                    @cancel="cancelEditing"
                  />
                </div>
                
                <!-- Country -->
                <div>
                  <InlineEditable
                    v-model="countryId"
                    v-model:editing="isEditingCountry"
                    label="Country"
                    type="select"
                    :options="countryOptions"
                    placeholder="Select country"
                    :saving="isSaving('country')"
                    @save="onSaveField('country', $event)"
                    @cancel="cancelEditing"
                  >
                    <template #display>
                      <div v-if="customerData.country">
                        {{ customerData.country.name }}
                      </div>
                    </template>
                  </InlineEditable>
                </div>
              </div>
            </template>
          </Card>

          <!-- Contacts Section -->
          <Card>
            <template #title>Contacts</template>
            <template #content>
              <!-- Add Contact Button -->
              <div v-if="!addingContact" class="mb-4">
                <Button
                  label="+ Add Contact"
                  icon="pi pi-plus"
                  severity="secondary"
                  text
                  @click="startAddingContact"
                />
              </div>
              
              <!-- Add Contact Form -->
              <div v-else class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      First Name *
                    </label>
                    <InputText
                      v-model="newContact.first_name"
                      class="w-full"
                      placeholder="First name"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Last Name *
                    </label>
                    <InputText
                      v-model="newContact.last_name"
                      class="w-full"
                      placeholder="Last name"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Position
                    </label>
                    <InputText
                      v-model="newContact.position"
                      class="w-full"
                      placeholder="Job title"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Email
                    </label>
                    <InputText
                      v-model="newContact.email"
                      class="w-full"
                      placeholder="Email address"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Phone
                    </label>
                    <InputText
                      v-model="newContact.phone"
                      class="w-full"
                      placeholder="Phone number"
                    />
                  </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                  <button
                    class="text-xs text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
                    @click="saveContact"
                  >
                    <i class="fas fa-check text-xs mr-1"></i>
                    save contact
                  </button>
                  <button
                    class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                    @click="cancelAddingContact"
                  >
                    <i class="fas fa-times text-xs mr-1"></i>
                    cancel
                  </button>
                </div>
              </div>
              
              <!-- Existing Contacts -->
              <div v-if="customerData.contacts && customerData.contacts.length > 0" class="space-y-4">
                <div
                  v-for="contact in customerData.contacts"
                  :key="contact.id"
                  class="flex items-center justify-between p-4 border rounded-lg"
                >
                  <div class="flex-1">
                    <div class="font-medium">{{ contact.first_name }} {{ contact.last_name }}</div>
                    <div v-if="contact.position" class="text-sm text-gray-500">
                      {{ contact.position }}
                    </div>
                    <div v-if="contact.email" class="text-sm text-blue-600">
                      {{ contact.email }}
                    </div>
                    <div v-if="contact.phone" class="text-sm text-gray-600">
                      {{ contact.phone }}
                    </div>
                  </div>
                  <div v-if="contact.is_primary" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                    Primary
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-4 text-gray-500">
                <p>No contacts added yet</p>
              </div>
            </template>
          </Card>
        </div>

        <!-- Right Column - Financial Summary -->
        <div class="space-y-6">

          <!-- Financial Summary -->
          <Card>
            <template #title>Financial Summary</template>
            <template #content>
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-500">Account Balance</span>
                  <span class="font-medium" :class="accountBalance > 0 ? 'text-red-600' : 'text-green-600'">
                    {{ formatMoney(accountBalance, customerData.currency?.code) }}
                  </span>
                </div>
                
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-500">Available Credit</span>
                  <span class="font-medium">
                    {{ formatMoney(customerData.credit_limit || 0, customerData.currency?.code) }}
                  </span>
                </div>
                
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-500">Outstanding Invoices</span>
                  <span class="font-medium">
                    {{ customerData.invoices?.filter(i => i.status !== 'paid' && i.status !== 'cancelled').length || 0 }}
                  </span>
                </div>
                
                <div class="pt-2 border-t">
                  <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Tax Status</span>
                    <Tag 
                      :value="customerData.tax_exempt ? 'Tax Exempt' : 'Taxable'" 
                      :severity="customerData.tax_exempt ? 'success' : 'info'"
                    />
                  </div>
                </div>
              </div>
            </template>
          </Card>

          <!-- Recent Activity -->
          <Card>
            <template #title>Recent Activity</template>
            <template #content>
              <div v-if="recentActivity.length > 0" class="space-y-3">
                <div
                  v-for="activity in recentActivity"
                  :key="activity.id"
                  class="flex items-start gap-3"
                >
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i :class="getActivityIcon(activity.type)" class="text-xs text-blue-600 dark:text-blue-400"></i>
                  </div>
                  <div class="flex-1">
                    <p class="text-sm">{{ activity.description }}</p>
                    <p class="text-xs text-gray-500">{{ formatDate(activity.date) }}</p>
                    <p v-if="activity.amount" class="text-sm font-medium">
                      {{ formatMoney(activity.amount, customerData.currency?.code) }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-4 text-gray-500">
                <i class="fas fa-history text-2xl mb-2"></i>
                <p>No recent activity</p>
              </div>
            </template>
          </Card>
        </div>
      </div>
    </div>
  </LayoutShell>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import InlineEditable from '@/Components/InlineEditable.vue'
import { useToast } from 'primevue/usetoast'
import { formatMoney, formatDate } from '@/Utils/formatting'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { http } from '@/lib/http'

interface Customer {
  id: string
  customer_number: string
  name: string
  email?: string
  phone?: string
  website?: string
  customer_type: string
  status: string
  created_at: string
  address_line_1?: string
  address_line_2?: string
  city?: string
  state_province?: string
  postal_code?: string
  country?: {
    id: number
    name: string
    code: string
  }
  currency?: {
    id: number
    code: string
    symbol: string
  }
  tax_number?: string
  tax_exempt: boolean
  payment_terms?: string
  credit_limit?: number
  notes?: string
  contacts: Array<{
    id: number
    first_name: string
    last_name: string
    position?: string
    email?: string
    phone?: string
    is_primary: boolean
  }>
}

interface Activity {
  id: number
  description: string
  date: string
  amount?: number
  type: string
}

const props = defineProps<{
  customer: Customer
  countries: any[]
}>()

const emit = defineEmits<{
  customerUpdated: [customer: Customer]
}>()

const toast = useToast()

// Use the inline edit composable
const {
  localData: customerData,
  editingField,
  createEditingComputed,
  isSaving,
  saveField: onSaveField,
  cancelEditing
} = useInlineEdit({
  model: 'customer',
  id: props.customer.id,
  data: props.customer,
  toast,
  onSuccess: (updatedCustomer) => {
    emit('customerUpdated', updatedCustomer)
  },
  onError: (error) => {
    console.error('Update failed:', error)
  }
})

// Ensure string fields are not null
const stringFields = [
  'customer_type', 'status', 'email', 'phone', 'website',
  'address_line_1', 'city', 'state_province', 'postal_code', 'tax_number'
]
stringFields.forEach(field => {
  if (customerData.value[field] === null) {
    customerData.value[field] = ''
  }
})

// Computed properties for each field's editing state
const isEditingCustomerType = createEditingComputed('customer_type')
const isEditingStatus = createEditingComputed('status')
const isEditingPhone = createEditingComputed('phone')
const isEditingEmail = createEditingComputed('email')
const isEditingWebsite = createEditingComputed('website')
const isEditingAddressLine1 = createEditingComputed('address_line_1')
const isEditingCity = createEditingComputed('city')
const isEditingStateProvince = createEditingComputed('state_province')
const isEditingPostalCode = createEditingComputed('postal_code')
const isEditingCountry = createEditingComputed('country')
const addingContact = ref(false)
const newContact = ref({
  first_name: '',
  last_name: '',
  position: '',
  email: '',
  phone: ''
})

// Breadcrumb items
const breadcrumbItems = [
  { label: 'Invoicing', url: '/invoicing', icon: 'invoice' },
  { label: 'Customers', url: '/invoicing/customers', icon: 'customers' },
  { label: 'Customer Details', url: '#', icon: 'user' }
]

// Options for dropdowns
const customerTypes = [
  { label: 'Individual', value: 'individual' },
  { label: 'Small Business', value: 'small_business' },
  { label: 'Medium Business', value: 'medium_business' },
  { label: 'Large Business', value: 'large_business' },
  { label: 'Non-profit', value: 'non_profit' },
  { label: 'Government', value: 'government' }
]

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Suspended', value: 'suspended' }
]

const countryOptions = computed(() => {
  return (props.countries || []).map(country => ({
    label: country.name,
    value: country.id
  }))
})

const countryId = computed({
  get: () => props.customer.country?.id,
  set: (value) => {
    // This is just for v-model binding, actual save happens via @save event
  }
})

// Field validation
const validateEmail = (email: string): string | null => {
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return 'Please enter a valid email address'
  }
  return null
}

const validateUrl = (url: string): string | null => {
  if (url && !/^https?:\/\/.+/.test(url)) {
    return 'Please enter a valid URL (e.g., https://example.com)'
  }
  return null
}

const startAddingContact = () => {
  addingContact.value = true
  newContact.value = {
    first_name: '',
    last_name: '',
    position: '',
    email: '',
    phone: ''
  }
}

const cancelAddingContact = () => {
  addingContact.value = false
  newContact.value = {
    first_name: '',
    last_name: '',
    position: '',
    email: '',
    phone: ''
  }
}

const saveContact = async () => {
  try {
    await http.post(route('customers.contacts.store', props.customer.id), newContact.value)
    
    // Refresh the page to show updated data
    router.reload()
    
    addingContact.value = false
    
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Contact added successfully',
      life: 3000
    })
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to add contact',
      life: 3000
    })
  }
}

const hasAddress = computed(() => {
  return props.customer.address_line_1 || 
         props.customer.city || 
         props.customer.state_province || 
         props.customer.postal_code ||
         props.customer.country
})

const accountBalance = computed(() => {
  // This would typically come from the customer's current balance
  // For now, we'll use a placeholder
  return 0
})

const recentActivity = computed(() => {
  // This would typically fetch recent activity for the customer
  // For now, we'll use an empty array
  return [] as Activity[]
})

const formatCustomerType = (type: string): string => {
  const typeMap: Record<string, string> = {
    'individual': 'Individual',
    'small_business': 'Small Business',
    'medium_business': 'Medium Business',
    'large_business': 'Large Business',
    'non_profit': 'Non-Profit',
    'government': 'Government'
  }
  return typeMap[type] || type
}

const getTypeSeverity = (type: string): string => {
  const severityMap: Record<string, string> = {
    'individual': 'info',
    'small_business': 'success',
    'medium_business': 'success',
    'large_business': 'success',
    'non_profit': 'warning',
    'government': 'secondary'
  }
  return severityMap[type] || 'secondary'
}

const formatStatus = (status: string): string => {
  const statusMap: Record<string, string> = {
    'active': 'Active',
    'inactive': 'Inactive',
    'suspended': 'Suspended'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status: string): string => {
  const severityMap: Record<string, string> = {
    'active': 'success',
    'inactive': 'secondary',
    'suspended': 'danger'
  }
  return severityMap[status] || 'secondary'
}

const getActivityIcon = (type: string): string => {
  const iconMap: Record<string, string> = {
    'invoice': 'fas fa-file-invoice',
    'payment': 'fas fa-money-bill-wave',
    'credit': 'fas fa-credit-card',
    'debit': 'fas fa-arrow-down',
    'adjustment': 'fas fa-edit'
  }
  return iconMap[type] || 'fas fa-circle'
}

const createInvoice = () => {
  // Navigate to create invoice page with customer pre-selected
  router.visit(route('invoices.create') + '?customer_id=' + props.customer.id)
}

const recordPayment = () => {
  // Navigate to record payment page with customer pre-selected
  router.visit(route('payments.create') + '?customer_id=' + props.customer.id)
}

const viewStatement = () => {
  // Navigate to customer statement page
  router.visit(route('customers.statement', props.customer.id))
}

const editCustomer = () => {
  // Navigate to edit page
  router.visit(route('customers.edit', props.customer.id))
}
</script>