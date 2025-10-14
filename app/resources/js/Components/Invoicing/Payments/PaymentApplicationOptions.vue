<template>
  <div class="space-y-4">
    <!-- Radio Button Options -->
    <div class="space-y-3">
      <div class="flex items-center">
        <RadioButton
          v-model="localAutoAllocate"
          :value="true"
          inputId="autoAllocate"
          name="allocationType"
          @change="onAutoAllocateChange"
        />
        <label for="autoAllocate" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
          Auto-allocate to all outstanding invoices
        </label>
      </div>

      <div class="flex items-center">
        <RadioButton
          v-model="localAutoAllocate"
          :value="false"
          inputId="specificInvoice"
          name="allocationType"
          @change="onAutoAllocateChange"
        />
        <label for="specificInvoice" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
          Apply to specific invoice
        </label>
      </div>
    </div>

    <!-- Invoice Selection (shown when auto-allocate is false) -->
    <div v-if="!localAutoAllocate" class="mt-4">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Select Invoice *
      </label>
      <InvoicePicker
        v-model="localSelectedInvoice"
        :invoices="customerInvoices"
        :customer-id="customerId"
        :error="errors.invoice_id"
        placeholder="Select invoice to apply payment..."
        @change="onInvoiceChange"
      />
      <small v-if="errors.invoice_id" class="text-red-600 dark:text-red-400">
        {{ errors.invoice_id }}
      </small>
    </div>

    <!-- Auto-allocate Info -->
    <div v-if="localAutoAllocate && customerInvoices.length > 0" class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
      <div class="text-sm text-blue-700 dark:text-blue-300">
        <i class="fas fa-info-circle mr-1"></i>
        Payment will be automatically distributed to all outstanding invoices, starting with the oldest.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import RadioButton from 'primevue/radiobutton'
import InvoicePicker from '@/Components/UI/Forms/InvoicePicker.vue'

interface Props {
  modelValue?: {
    autoAllocate?: boolean
    invoiceId?: number | string | null
  }
  customerInvoices: Array<any>
  customerId?: number | string | null
  errors?: Record<string, string>
}

interface Emits {
  (e: 'update:modelValue', value: any): void
  (e: 'auto-allocate-change', value: boolean): void
  (e: 'invoice-change', invoice: any): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => ({}),
  customerId: null,
  errors: () => ({})
})

const emit = defineEmits<Emits>()

const localAutoAllocate = computed({
  get: () => props.modelValue?.autoAllocate ?? true,
  set: (value) => updateModelValue({ autoAllocate: value })
})

const localSelectedInvoice = computed({
  get: () => props.modelValue?.invoiceId || null,
  set: (value) => updateModelValue({ invoiceId: value })
})

const updateModelValue = (updates: Partial<Props['modelValue']>) => {
  const newValue = { ...props.modelValue, ...updates }
  emit('update:modelValue', newValue)
}

const onAutoAllocateChange = (value: boolean) => {
  emit('auto-allocate-change', value)
  if (value) {
    // Clear selected invoice when switching to auto-allocate
    updateModelValue({ invoiceId: null })
  }
}

const onInvoiceChange = (invoice: any) => {
  emit('invoice-change', invoice)
}
</script>