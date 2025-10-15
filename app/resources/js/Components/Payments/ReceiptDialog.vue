<template>
  <Dialog
    v-model:visible="visible"
    header="Payment Receipt"
    :modal="true"
    :closable="!loading"
    :style="{ width: '90vw', maxWidth: '800px' }"
    @hide="$emit('hide')"
  >
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center py-8">
        <ProgressSpinner />
      </div>

      <!-- Receipt Content -->
      <div v-else-if="receiptData" class="bg-white dark:bg-gray-800 p-8 rounded-lg border">
        <!-- Header -->
        <div class="border-b pb-4 mb-6">
          <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              PAYMENT RECEIPT
            </h1>
            <p class="text-sm text-gray-500 mt-2">
              Receipt #{{ receiptData.receipt_number }}
            </p>
          </div>
        </div>

        <!-- Company and Customer Info -->
        <div class="grid grid-cols-2 gap-8 mb-6">
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">From:</h3>
            <div class="space-y-1 text-sm">
              <p class="font-medium">{{ receiptData.company_name }}</p>
              <p class="text-gray-600 dark:text-gray-400">{{ receiptData.company_address }}</p>
              <p class="text-gray-600 dark:text-gray-400">
                {{ receiptData.company_city }}, {{ receiptData.company_country }} {{ receiptData.company_postal }}
              </p>
            </div>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">To:</h3>
            <div class="space-y-1 text-sm">
              <p class="font-medium">{{ receiptData.entity_name }}</p>
              <p class="text-gray-600 dark:text-gray-400">{{ receiptData.entity_email }}</p>
              <p class="text-gray-600 dark:text-gray-400">{{ receiptData.entity_address }}</p>
            </div>
          </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-6">
          <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Payment Details</h3>
          <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-500">Payment Number:</span>
                <span class="ml-2 font-medium">{{ receiptData.payment_number }}</span>
              </div>
              <div>
                <span class="text-gray-500">Payment Date:</span>
                <span class="ml-2 font-medium">{{ formatDate(receiptData.payment_date) }}</span>
              </div>
              <div>
                <span class="text-gray-500">Payment Method:</span>
                <span class="ml-2 font-medium">{{ receiptData.payment_method_label }}</span>
              </div>
              <div>
                <span class="text-gray-500">Reference:</span>
                <span class="ml-2 font-medium">{{ receiptData.reference_number || '-' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Amount Summary -->
        <div class="mb-6">
          <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Amount Summary</h3>
          <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span>Payment Amount:</span>
                <span class="font-medium">
                  {{ formatMoney(receiptData.amount, receiptData.currency_code) }}
                </span>
              </div>
              <div v-if="receiptData.total_discount_applied > 0" class="flex justify-between text-sm">
                <span>Total Discount Applied:</span>
                <span class="font-medium text-green-600">
                  -{{ formatMoney(receiptData.total_discount_applied, receiptData.currency_code) }}
                </span>
              </div>
              <div class="flex justify-between text-sm border-t pt-2">
                <span>Total Allocated:</span>
                <span class="font-medium">
                  {{ formatMoney(receiptData.total_allocated, receiptData.currency_code) }}
                </span>
              </div>
              <div v-if="receiptData.remaining_amount > 0" class="flex justify-between text-sm">
                <span>Unallocated Cash:</span>
                <span class="font-medium text-blue-600">
                  {{ formatMoney(receiptData.remaining_amount, receiptData.currency_code) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Allocations -->
        <div v-if="receiptData.allocations?.length">
          <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Allocations</h3>
          <div class="border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="text-left p-3 border-b">Invoice #</th>
                  <th class="text-left p-3 border-b">Allocation Date</th>
                  <th class="text-right p-3 border-b">Original Amount</th>
                  <th class="text-right p-3 border-b">Discount</th>
                  <th class="text-right p-3 border-b">Allocated Amount</th>
                  <th class="text-left p-3 border-b">Notes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="allocation in receiptData.allocations" :key="allocation.allocation_id" class="border-b">
                  <td class="p-3">{{ allocation.invoice_number }}</td>
                  <td class="p-3">{{ formatDate(allocation.allocation_date) }}</td>
                  <td class="p-3 text-right">
                    {{ formatMoney(allocation.original_amount, receiptData.currency_code) }}
                  </td>
                  <td class="p-3 text-right">
                    <span v-if="allocation.discount_amount > 0" class="text-green-600 font-medium">
                      -{{ formatMoney(allocation.discount_amount, receiptData.currency_code) }}
                      <span class="text-xs text-gray-500 block">{{ allocation.discount_percent }}%</span>
                    </span>
                    <span v-else class="text-gray-400">-</span>
                  </td>
                  <td class="p-3 text-right font-medium">
                    {{ formatMoney(allocation.allocated_amount, receiptData.currency_code) }}
                  </td>
                  <td class="p-3">{{ allocation.notes || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="receiptData.notes">
          <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Notes</h3>
          <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-sm">
            {{ receiptData.notes }}
          </div>
        </div>

        <!-- Footer -->
        <div class="border-t pt-4 mt-6 text-center">
          <p class="text-xs text-gray-500">
            Generated on {{ formatDate(receiptData.generated_at) }}
          </p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t">
        <Button
          label="Download PDF"
          icon="pi pi-file-pdf"
          :loading="downloadingPdf"
          @click="downloadPdf"
        />
        <Button
          label="Download JSON"
          icon="pi pi-code"
          :loading="downloadingJson"
          @click="downloadJson"
        />
        <Button
          label="Close"
          text
          @click="$emit('hide')"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { formatDate, formatMoney } from '@/Utils/formatting'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'

interface ReceiptData {
  receipt_number: string
  company_name: string
  company_address: string
  company_city: string
  company_country: string
  company_postal: string
  entity_name: string
  entity_email: string
  entity_address: string
  payment_number: string
  payment_date: string
  payment_method_label: string
  reference_number?: string
  amount: number
  currency_code: string
  total_allocated: number
  remaining_amount: number
  allocations?: Array<{
    allocation_id: string
    invoice_number: string
    allocation_date: string
    allocated_amount: number
    notes?: string
  }>
  notes?: string
  generated_at: string
}

const props = defineProps<{
  visible: boolean
  paymentId: string
}>()

const emit = defineEmits<{
  hide: []
}>()

// State
const loading = ref(false)
const downloadingPdf = ref(false)
const downloadingJson = ref(false)
const receiptData = ref<ReceiptData | null>(null)

// Methods
const downloadPdf = async () => {
  if (!props.paymentId) return
  
  downloadingPdf.value = true
  try {
    // Create download link
    const url = `/api/accounting/payments/${props.paymentId}/receipt?format=pdf`
    const link = document.createElement('a')
    link.href = url
    link.download = `receipt-${receiptData.value?.receipt_number}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    console.error('Error downloading PDF:', error)
  } finally {
    downloadingPdf.value = false
  }
}

const downloadJson = async () => {
  if (!props.paymentId) return
  
  downloadingJson.value = true
  try {
    const response = await fetch(`/api/accounting/payments/${props.paymentId}/receipt?format=json`)
    const data = await response.json()
    
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `receipt-${receiptData.value?.receipt_number}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error downloading JSON:', error)
  } finally {
    downloadingJson.value = false
  }
}

const fetchReceiptData = async () => {
  if (!props.paymentId) return
  
  loading.value = true
  try {
    const response = await fetch(`/api/accounting/payments/${props.paymentId}/receipt?format=json`)
    receiptData.value = await response.json()
  } catch (error) {
    console.error('Error fetching receipt data:', error)
  } finally {
    loading.value = false
  }
}

// Watch for payment ID changes
watch(() => props.paymentId, (newPaymentId) => {
  if (newPaymentId && props.visible) {
    fetchReceiptData()
  }
})

// Watch for visibility changes
watch(() => props.visible, (visible) => {
  if (visible && props.paymentId) {
    fetchReceiptData()
  }
})
</script>