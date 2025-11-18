<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Textarea from 'primevue/textarea'
import Message from 'primevue/message'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'

const page = usePage()

const props = defineProps({
  customers: Array,
  unpaidInvoices: Array
})

// Form data
const form = ref({
  customer_id: null,
  amount: 0,
  payment_date: new Date(),
  payment_method: null,
  reference: '',
  notes: ''
})

const paymentMethods = [
  { label: 'Cash', value: 'cash' },
  { label: 'Bank Transfer', value: 'bank_transfer' },
  { label: 'Credit Card', value: 'credit_card' },
  { label: 'Check', value: 'check' },
  { label: 'Other', value: 'other' }
]

const selectedCustomer = computed(() => {
  return props.customers?.find(c => c.id === form.value.customer_id) || null
})

const customerInvoices = computed(() => {
  if (!form.value.customer_id) return []
  return props.unpaidInvoices?.filter(invoice => invoice.customer_id === form.value.customer_id) || []
})

const totalOutstanding = computed(() => {
  return customerInvoices.value.reduce((sum, invoice) => {
    return sum + (invoice.total_amount - (invoice.paid_amount || 0))
  }, 0)
})

const submit = () => {
  router.post(route('payments.store'), {
    ...form.value,
    payment_date: form.value.payment_date.toISOString().split('T')[0]
  })
}

const cancel = () => {
  router.visit(route('payments.index'))
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="flex h-screen">
      <!-- Sidebar -->
      <CompanySwitcher />
      
      <!-- Main Content -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Navigation -->
        <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                  Create Payment
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                  Record a new customer payment
                </p>
              </div>
              <div class="flex space-x-3">
                <Button 
                  label="Back to Payments"
                  icon="pi pi-arrow-left"
                  class="p-button-outlined"
                  @click="cancel"
                />
              </div>
            </div>
          </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-800">
          <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
            <Card>
              <template #title>Payment Details</template>
              <template #content>
                <form @submit.prevent="submit" class="space-y-6">
                  <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Customer -->
                    <div>
                      <label for="customer_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Customer
                      </label>
                      <div class="mt-1">
                        <Dropdown 
                          id="customer_id"
                          v-model="form.customer_id"
                          :options="customers"
                          optionLabel="name"
                          optionValue="id"
                          placeholder="Select a customer"
                          class="w-full"
                          filter
                        />
                      </div>
                    </div>
                    
                    <!-- Amount -->
                    <div>
                      <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Amount
                      </label>
                      <div class="mt-1">
                        <InputNumber 
                          id="amount"
                          v-model="form.amount"
                          mode="currency"
                          currency="USD"
                          locale="en-US"
                          :min="0.01"
                          class="w-full"
                        />
                      </div>
                    </div>
                    
                    <!-- Payment Date -->
                    <div>
                      <label for="payment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Payment Date
                      </label>
                      <div class="mt-1">
                        <Calendar 
                          id="payment_date"
                          v-model="form.payment_date"
                          dateFormat="yy-mm-dd"
                          class="w-full"
                        />
                      </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div>
                      <label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Payment Method
                      </label>
                      <div class="mt-1">
                        <Dropdown 
                          id="payment_method"
                          v-model="form.payment_method"
                          :options="paymentMethods"
                          optionLabel="label"
                          optionValue="value"
                          placeholder="Select payment method"
                          class="w-full"
                        />
                      </div>
                    </div>
                  </div>
                  
                  <!-- Reference Number -->
                  <div>
                    <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      Reference Number (Optional)
                    </label>
                    <div class="mt-1">
                      <InputText 
                        id="reference"
                        v-model="form.reference"
                        placeholder="Optional reference number"
                        class="w-full"
                      />
                    </div>
                  </div>
                  
                  <!-- Notes -->
                  <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      Notes (Optional)
                    </label>
                    <div class="mt-1">
                      <Textarea 
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        placeholder="Optional payment notes"
                        class="w-full"
                      />
                    </div>
                  </div>
                  
                  <!-- Customer Invoices Summary -->
                  <Message v-if="selectedCustomer && customerInvoices.length > 0" severity="info" :closable="false">
                    <div class="flex justify-between items-center">
                      <span>
                        <strong>{{ selectedCustomer.name }}</strong> has 
                        {{ customerInvoices.length }} outstanding invoice(s) totaling 
                        ${{ totalOutstanding.toFixed(2) }}
                      </span>
                    </div>
                  </Message>
                  
                  <Message v-if="selectedCustomer && customerInvoices.length === 0" severity="warn" :closable="false">
                    <strong>{{ selectedCustomer.name }}</strong> has no outstanding invoices.
                  </Message>
                  
                  <!-- Actions -->
                  <div class="flex justify-end space-x-3 pt-4">
                    <Button 
                      label="Cancel"
                      icon="pi pi-times"
                      class="p-button-outlined"
                      @click="cancel"
                    />
                    <Button 
                      label="Create Payment"
                      icon="pi pi-check"
                      type="submit"
                    />
                  </div>
                </form>
              </template>
            </Card>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>