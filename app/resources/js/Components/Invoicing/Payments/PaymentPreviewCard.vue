<template>
  <Card>
    <template #title>
      <span class="flex items-center gap-2">
        <SvgIcon name="credit-card" class="w-5 h-5" />
        Payment Preview
      </span>
    </template>
    <template #content>
      <div class="space-y-4">
        <!-- Company Header -->
        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-2xl">💰</div>
              <div class="text-gray-600 text-xs mt-1">{{ companyName }}</div>
            </div>
            <div class="text-right text-gray-600 text-xs">
              {{ formatDate(form.payment_date) }}
            </div>
          </div>
        </div>

        <!-- Payment Info -->
        <div class="space-y-3">
          <div>
            <div class="text-gray-500 uppercase tracking-wide text-xs mb-1">Payment #</div>
            <div class="font-semibold text-gray-900">{{ form.payment_number }}</div>
          </div>

          <div>
            <div class="text-gray-500 uppercase tracking-wide text-xs mb-1">Customer</div>
            <div class="font-semibold text-gray-900">{{ previewCustomerName || '—' }}</div>
            <div class="text-gray-500 text-xs mt-1">{{ previewCustomerEmail }}</div>
          </div>

          <div>
            <div class="text-gray-500 uppercase tracking-wide text-xs mb-1">Applied To</div>
            <div class="font-semibold text-gray-900">{{ getAppliedToText() }}</div>
          </div>
        </div>

        <!-- Amount Section -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
          <div class="text-gray-500 uppercase tracking-wide text-xs mb-2">Amount</div>
          <div class="text-lg font-bold text-purple-900">
            {{ formatMoney(form.amount || 0, previewCurrency) }}
          </div>
          <div class="text-xs text-gray-500 mt-1">
            {{ paymentMethodLabel }}
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
          <Button
            label="Print"
            icon="pi pi-print"
            size="small"
            severity="secondary"
            outlined
            class="flex-1"
            @click="$emit('print')"
          />
          <Button
            label="Download PDF"
            icon="pi pi-file-pdf"
            size="small"
            severity="info"
            outlined
            class="flex-1"
            @click="$emit('download-pdf')"
          />
        </div>
      </div>
    </template>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatMoney } from '@/Utils/formatting'
import { formatDate } from '@/Utils/formatting'
import Card from 'primevue/card'
import Button from 'primevue/button'
import SvgIcon from '@/Components/SvgIcon.vue'

interface Props {
  form: {
    payment_number?: string
    payment_date?: string
    amount?: number
    payment_method?: string
  }
  previewCustomerName?: string
  previewCustomerEmail?: string
  previewCurrency: any
  paymentMethodLabel: string
  allocations: Array<any>
  autoAllocate: boolean
}

interface Emits {
  (e: 'print'): void
  (e: 'download-pdf'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Mock company name - this should come from props or config
const companyName = computed(() => 'Your Company')

const getAppliedToText = () => {
  if (!props.previewCustomerName) return '—'
  
  if (props.autoAllocate) {
    const count = props.allocations.length
    return count === 0 
      ? 'All outstanding invoices' 
      : `${count} invoice${count > 1 ? 's' : ''}`
  }
  
  if (props.allocations.length === 1) {
    return props.allocations[0]?.invoice_number || 'Specific invoice'
  }
  
  return 'Specific invoices'
}
</script>