<template>
  <Card>
    <template #title>
      <span class="flex items-center gap-2">
        <SvgIcon name="users" class="w-5 h-5" />
        Customer Details
      </span>
    </template>
    <template #content>
      <div v-if="selectedCustomer" class="space-y-4">
        <!-- Customer Info -->
        <div class="text-sm space-y-2">
          <div class="flex items-start justify-between">
            <div>
              <span class="font-medium text-gray-900 dark:text-white">{{ selectedCustomer.name }}</span>
              <div v-if="selectedCustomer.email" class="text-gray-600 dark:text-gray-400 text-xs mt-1">
                {{ selectedCustomer.email }}
              </div>
              <div v-if="selectedCustomer.phone" class="text-gray-600 dark:text-gray-400 text-xs">
                {{ selectedCustomer.phone }}
              </div>
            </div>
            <BalanceDisplay
              :balance="selectedCustomer.outstanding_balance || 0"
              :currency-code="selectedCustomer.currency?.code || 'USD'"
              :risk-level="selectedCustomer.risk_level"
              show-risk
            />
          </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
          <div class="text-center">
            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
              {{ outstandingInvoices.length }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Outstanding</div>
          </div>
          <div class="text-center">
            <div class="text-lg font-bold text-green-600 dark:text-green-400">
              {{ formatMoney(totalOutstanding, selectedCustomer.currency) }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Total Due</div>
          </div>
        </div>

        <!-- Outstanding Invoices -->
        <div v-if="outstandingInvoices.length > 0" class="space-y-2">
          <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Outstanding Invoices
          </h4>
          <div class="space-y-2 max-h-60 overflow-y-auto">
            <div
              v-for="invoice in outstandingInvoices"
              :key="invoice.id"
              class="p-2 bg-gray-50 dark:bg-gray-800 rounded text-xs hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors"
              :class="{ 'ring-2 ring-blue-500': selectedInvoiceId === invoice.id }"
              @click="$emit('select-invoice', invoice)"
            >
              <div class="flex justify-between items-start">
                <div>
                  <div class="font-medium">{{ invoice.invoice_number }}</div>
                  <div class="text-gray-500 dark:text-gray-400">
                    Due: {{ formatDate(invoice.due_date) }}
                  </div>
                  <div v-if="invoice.overdue_days > 0" class="text-red-600 dark:text-red-400 text-xs mt-1">
                    {{ invoice.overdue_days }} days overdue
                  </div>
                </div>
                <div class="text-right">
                  <div class="font-medium">{{ formatMoney(invoice.balance_due, invoice.currency) }}</div>
                  <Tag
                    :value="invoice.status"
                    :severity="getInvoiceStatusSeverity(invoice.status)"
                    size="small"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Coverage -->
        <div v-if="formAmount > 0" class="pt-3 border-t border-gray-200 dark:border-gray-700">
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600 dark:text-gray-400">Payment covers:</span>
              <span class="font-medium">
                {{ Math.min(100, Math.round((formAmount / totalOutstanding) * 100)) }}%
              </span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
              <div
                class="bg-blue-600 h-2 rounded-full transition-all"
                :style="{ width: Math.min(100, (formAmount / totalOutstanding) * 100) + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
        <i class="fas fa-user-slash text-3xl mb-2"></i>
        <p>No customer selected</p>
      </div>
    </template>
  </Card>
</template>

<script setup lang="ts">
import { formatMoney } from '@/Utils/formatting'
import { formatDate } from '@/Utils/formatting'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import SvgIcon from '@/Components/SvgIcon.vue'
import BalanceDisplay from '@/Components/BalanceDisplay.vue'
import { useLookups } from '@/composables/useLookups'

interface Props {
  selectedCustomer?: any
  outstandingInvoices: Array<any>
  totalOutstanding: number
  formAmount: number
  selectedInvoiceId?: number | string | null
}

interface Emits {
  (e: 'select-invoice', invoice: any): void
}

const props = withDefaults(defineProps<Props>(), {
  selectedCustomer: null,
  selectedInvoiceId: null
})

const emit = defineEmits<Emits>()

const { getInvoiceStatusSeverity } = useLookups()
</script>