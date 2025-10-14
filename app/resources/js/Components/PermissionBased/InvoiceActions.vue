<template>
  <div class="flex items-center space-x-2">
    <!-- Invoice Actions -->
    <Button
      v-if="can.createInvoices()"
      label="Create Invoice"
      icon="pi pi-plus"
      @click="$emit('createInvoice')"
      size="small"
      data-test="create-invoice-button"
    />
    
    <Button
      v-if="selectedInvoices.length > 0 && can.deleteInvoices()"
      label="Delete Selected"
      icon="pi pi-trash"
      @click="$emit('deleteSelected')"
      size="small"
      severity="danger"
      class="p-button-outlined"
      data-test="delete-invoices-button"
    />
    
    <!-- Export button for viewers and above -->
    <Button
      v-if="can.viewInvoices()"
      label="Export"
      icon="pi pi-download"
      @click="$emit('export')"
      size="small"
      class="p-button-outlined"
      data-test="export-invoices-button"
    />
  </div>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions'

const { can } = usePermissions()

defineProps({
  selectedInvoices: {
    type: Array,
    default: () => []
  }
})

defineEmits(['createInvoice', 'deleteSelected', 'export'])
</script>