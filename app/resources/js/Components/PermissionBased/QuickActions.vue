<template>
  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
    <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">Quick Actions</h3>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <!-- Invoices -->
      <Button
        v-if="can.createInvoices()"
        label="New Invoice"
        icon="pi pi-file-invoice"
        class="p-button-outlined w-full"
        @click="navigateTo('/invoices/create')"
        data-test="quick-new-invoice"
      />
      
      <!-- Payments -->
      <Button
        v-if="can.createPayments()"
        label="Record Payment"
        icon="pi pi-money-bill"
        class="p-button-outlined w-full"
        @click="navigateTo('/payments/create')"
        data-test="quick-record-payment"
      />
      
      <!-- Customers -->
      <Button
        v-if="can.createCustomers()"
        label="Add Customer"
        icon="pi pi-user-plus"
        class="p-button-outlined w-full"
        @click="navigateTo('/customers/create')"
        data-test="quick-add-customer"
      />
      
      <!-- Ledger -->
      <Button
        v-if="can.createLedgerEntries()"
        label="Journal Entry"
        icon="pi pi-book"
        class="p-button-outlined w-full"
        @click="navigateTo('/ledger/create')"
        data-test="quick-journal-entry"
      />
    </div>
    
    <!-- View-only actions -->
    <div v-if="!can.createInvoices() && !can.createPayments()" class="mt-4 text-center">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        Contact your administrator to get permission to create records
      </p>
    </div>
  </div>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions'
import { useRouter } from '@inertiajs/vue3'
import Button from 'primevue/button'

const { can } = usePermissions()
const router = useRouter()

function navigateTo(url) {
  router.visit(url)
}
</script>