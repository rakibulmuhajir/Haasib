<template>
  <div class="space-y-4">
    <div v-if="allocations.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
      <i class="fas fa-receipt text-3xl mb-2"></i>
      <p>No allocations yet</p>
      <p class="text-sm">Select invoices to allocate this payment</p>
    </div>

    <div v-else class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Invoice
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Balance Due
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Applied
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Remaining
            </th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-for="allocation in allocations" :key="allocation.invoice_id">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-white">
                {{ allocation.invoice_number }}
              </div>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="font-medium">
                {{ formatMoney(allocation.balance_due, allocation.currency) }}
              </div>
            </td>
            <td class="px-4 py-3">
              <InputNumber
                :modelValue="allocation.applied_amount"
                :max="allocation.balance_due"
                :min="0"
                :step="0.01"
                mode="currency"
                :currency="allocation.currency?.code || allocation.currency_code"
                class="w-full"
                @update:modelValue="(value) => onAllocationChange(allocation.invoice_id, value)"
              />
            </td>
            <td class="px-4 py-3 text-right">
              <div :class="allocation.balance_due - allocation.applied_amount > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'">
                {{ formatMoney(allocation.balance_due - allocation.applied_amount, allocation.currency) }}
              </div>
            </td>
            <td class="px-4 py-3 text-center">
              <Button
                icon="pi pi-times"
                size="small"
                severity="danger"
                outlined
                @click="onRemoveAllocation(allocation.invoice_id)"
                v-tooltip="'Remove allocation'"
              />
            </td>
          </tr>
        </tbody>
        <tfoot class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
              Total Applied:
            </td>
            <td colspan="3" class="px-4 py-3 text-right text-lg font-bold text-blue-600 dark:text-blue-400">
              {{ formatMoney(totalApplied, previewCurrency) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Remaining Payment Summary -->
    <div class="flex justify-between items-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
        Remaining Payment:
      </span>
      <span class="text-lg font-bold" :class="remainingPayment > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'">
        {{ formatMoney(remainingPayment, previewCurrency) }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { formatMoney } from '@/Utils/formatting'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'

interface Props {
  allocations: Array<{
    invoice_id: number | string
    invoice_number: string
    balance_due: number
    applied_amount: number
    currency_code: string
    currency?: any
  }>
  totalApplied: number
  remainingPayment: number
  previewCurrency: any
}

interface Emits {
  (e: 'update-allocation', invoiceId: number | string, amount: number): void
  (e: 'remove-allocation', invoiceId: number | string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const onAllocationChange = (invoiceId: number | string, amount: number) => {
  emit('update-allocation', invoiceId, amount)
}

const onRemoveAllocation = (invoiceId: number | string) => {
  emit('remove-allocation', invoiceId)
}
</script>