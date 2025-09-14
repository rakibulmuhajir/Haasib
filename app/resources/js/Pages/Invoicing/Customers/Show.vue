<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Customer Details</h1>
        <p class="text-gray-600 dark:text-gray-400">
          {{ customer.name }} ({{ customer.customer_number }})
        </p>
      </div>
      <div class="flex space-x-2">
        <Link :href="route('customers.index')">
          <Button
            icon="fas fa-arrow-left"
            label="Back to Customers"
            class="p-button-outlined p-button-secondary"
          />
        </Link>
        <Link :href="route('customers.edit', customer.id)">
          <Button
            icon="fas fa-edit"
            label="Edit Customer"
          />
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - Customer Information -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Basic Information -->
        <Card>
          <template #title>Basic Information</template>
          <template #content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Customer Number
                </label>
                <div class="text-lg font-medium">{{ customer.customer_number }}</div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Customer Type
                </label>
                <Tag
                  :value="formatCustomerType(customer.customer_type)"
                  :severity="getTypeSeverity(customer.customer_type)"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Status
                </label>
                <Tag
                  :value="formatStatus(customer.status)"
                  :severity="getStatusSeverity(customer.status)"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Created
                </label>
                <div>{{ formatDate(customer.created_at) }}</div>
              </div>
              
              <div v-if="customer.email">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Email
                </label>
                <a
                  :href="`mailto:${customer.email}`"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  {{ customer.email }}
                </a>
              </div>
              
              <div v-if="customer.phone">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Phone
                </label>
                <a
                  :href="`tel:${customer.phone}`"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  {{ customer.phone }}
                </a>
              </div>
              
              <div v-if="customer.website" class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Website
                </label>
                <a
                  :href="customer.website"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  {{ customer.website }}
                </a>
              </div>
            </div>
            
            <div v-if="customer.notes" class="mt-4">
              <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                Notes
              </label>
              <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                {{ customer.notes }}
              </div>
            </div>
          </template>
        </Card>

        <!-- Address Information -->
        <Card v-if="hasAddress">
          <template #title>Address Information</template>
          <template #content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-if="customer.address_line_1">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Address Line 1
                </label>
                <div>{{ customer.address_line_1 }}</div>
              </div>
              
              <div v-if="customer.address_line_2">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Address Line 2
                </label>
                <div>{{ customer.address_line_2 }}</div>
              </div>
              
              <div v-if="customer.city">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  City
                </label>
                <div>{{ customer.city }}</div>
              </div>
              
              <div v-if="customer.state_province">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  State/Province
                </label>
                <div>{{ customer.state_province }}</div>
              </div>
              
              <div v-if="customer.postal_code">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Postal Code
                </label>
                <div>{{ customer.postal_code }}</div>
              </div>
              
              <div v-if="customer.country">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Country
                </label>
                <div>{{ customer.country.name }}</div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Contacts -->
        <Card v-if="customer.contacts && customer.contacts.length > 0">
          <template #title>Contacts</template>
          <template #content>
            <div class="space-y-4">
              <div
                v-for="contact in customer.contacts"
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
          </template>
        </Card>
      </div>

      <!-- Right Column - Actions & Financial -->
      <div class="space-y-6">
        <!-- Quick Actions -->
        <Card>
          <template #title>Quick Actions</template>
          <template #content>
            <div class="space-y-2">
              <Link :href="route('invoices.create') + '?customer_id=' + customer.id">
                <Button
                  label="Create Invoice"
                  icon="fas fa-file-invoice"
                  class="w-full justify-start"
                />
              </Link>
              
              <Link :href="route('payments.create') + '?customer_id=' + customer.id">
                <Button
                  label="Record Payment"
                  icon="fas fa-money-bill-wave"
                  class="w-full justify-start"
                />
              </Link>
              
              <Link :href="route('customers.invoices', customer.id)">
                <Button
                  label="View Invoices"
                  icon="fas fa-file-invoice-dollar"
                  class="w-full justify-start p-button-outlined"
                />
              </Link>
              
              <Link :href="route('customers.payments', customer.id)">
                <Button
                  label="View Payments"
                  icon="fas fa-credit-card"
                  class="w-full justify-start p-button-outlined"
                />
              </Link>
              
              <Link :href="route('customers.statement', customer.id)">
                <Button
                  label="Customer Statement"
                  icon="fas fa-file-alt"
                  class="w-full justify-start p-button-outlined"
                />
              </Link>
              
              <Link :href="route('customers.statistics', customer.id)">
                <Button
                  label="Customer Statistics"
                  icon="fas fa-chart-bar"
                  class="w-full justify-start p-button-outlined"
                />
              </Link>
            </div>
          </template>
        </Card>

        <!-- Financial Information -->
        <Card>
          <template #title>Financial Information</template>
          <template #content>
            <div class="space-y-3">
              <div v-if="customer.currency">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Currency
                </label>
                <div>{{ customer.currency.code }} ({{ customer.currency.symbol }})</div>
              </div>
              
              <div v-if="customer.tax_id">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Tax ID
                </label>
                <div>{{ customer.tax_id }}</div>
              </div>
              
              <div v-if="customer.tax_exempt">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Tax Status
                </label>
                <Tag value="Tax Exempt" severity="success" />
              </div>
              
              <div v-if="customer.payment_terms">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Payment Terms
                </label>
                <div>{{ customer.payment_terms }}</div>
              </div>
              
              <div v-if="customer.credit_limit">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Credit Limit
                </label>
                <div class="font-medium">
                  {{ formatMoney(customer.credit_limit, customer.currency) }}
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Account Balance
                </label>
                <div class="text-lg font-medium" :class="{ 'text-red-600': accountBalance < 0 }">
                  {{ formatMoney(accountBalance, customer.currency) }}
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
                class="flex items-center justify-between text-sm"
              >
                <div class="flex-1">
                  <div class="font-medium">{{ activity.description }}</div>
                  <div class="text-gray-500">{{ formatDate(activity.date) }}</div>
                </div>
                <div class="text-right">
                  <div
                    v-if="activity.amount"
                    class="font-medium"
                    :class="{
                      'text-green-600': activity.amount > 0,
                      'text-red-600': activity.amount < 0
                    }"
                  >
                    {{ formatMoney(activity.amount, customer.currency) }}
                  </div>
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
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { formatMoney, formatDate } from '@/Utils/formatting'

interface Customer {
  id: number
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
  tax_id?: string
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
}

const props = defineProps<{
  customer: Customer
}>()

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
    'business': 'Business',
    'non_profit': 'Non-Profit',
    'government': 'Government'
  }
  return typeMap[type] || type
}

const getTypeSeverity = (type: string): string => {
  const severityMap: Record<string, string> = {
    'individual': 'info',
    'business': 'success',
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
</script>