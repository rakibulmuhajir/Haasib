<template>
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
      Customer Management
    </h2>
    
    <div class="flex items-center space-x-2">
      <!-- Only show if user can manage customers -->
      <Button
        v-if="can.createCustomers()"
        label="Add Customer"
        icon="pi pi-plus"
        @click="$emit('createCustomer')"
        data-test="add-customer-button"
      />
      
      <Button
        v-if="can.createCustomers()"
        label="Import"
        icon="pi pi-upload"
        @click="$emit('importCustomers')"
        severity="secondary"
        outlined
        data-test="import-customers-button"
      />
      
      <!-- Export available to all who can view -->
      <Button
        v-if="can.viewCustomers()"
        label="Export"
        icon="pi pi-download"
        @click="$emit('exportCustomers')"
        severity="secondary"
        outlined
        data-test="export-customers-button"
      />
    </div>
  </div>
  
  <!-- Permission warning -->
  <Message
    v-if="!can.createCustomers() && !can.editCustomers()"
    severity="info"
    :closable="false"
    class="mb-4"
  >
    You have read-only access to customer data. Contact your administrator to get edit permissions.
  </Message>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions'
import Message from 'primevue/message'
import Button from 'primevue/button'

const { can } = usePermissions()

defineEmits(['createCustomer', 'importCustomers', 'exportCustomers'])
</script>