<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payment Details</h1>
        <p class="text-gray-600 dark:text-gray-400">
          Payment #{{ payment.payment_number }}
        </p>
      </div>
      <div class="flex space-x-2">
        <Link :href="route('payments.index')">
          <Button
            icon="fas fa-arrow-left"
            label="Back to Payments"
            class="p-button-outlined p-button-secondary"
          />
        </Link>
        <Link
          v-if="payment.status === 'pending'"
          :href="route('payments.edit', payment.id)"
        >
          <Button
            icon="fas fa-edit"
            label="Edit Payment"
          />
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - Payment Information -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Payment Details -->
        <Card>
          <template #title>Payment Information</template>
          <template #content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Payment Number
                </label>
                <div class="text-lg font-medium">{{ payment.payment_number }}</div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Status
                </label>
                <Tag
                  :value="formatStatus(payment.status)"
                  :severity="getStatusSeverity(payment.status)"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Payment Date
                </label>
                <div>{{ formatDate(payment.payment_date) }}</div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Payment Method
                </label>
                <div>{{ formatPaymentMethod(payment.payment_method) }}</div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Amount
                </label>
                <div class="text-lg font-medium">
                  {{ formatMoney(payment.amount, payment.currency) }}
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Allocated Amount
                </label>
                <div class="text-lg font-medium">
                  {{ formatMoney(payment.allocated_amount, payment.currency) }}
                </div>
              </div>
              
              <div v-if="payment.reference_number">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Reference Number
                </label>
                <div>{{ payment.reference_number }}</div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Unallocated Amount
                </label>
                <div class="text-lg font-medium" :class="{ 'text-red-600': unallocatedAmount > 0 }">
                  {{ formatMoney(unallocatedAmount, payment.currency) }}
                </div>
              </div>
            </div>
            
            <div v-if="payment.notes" class="mt-4">
              <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                Notes
              </label>
              <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                {{ payment.notes }}
              </div>
            </div>
          </template>
        </Card>

        <!-- Invoice Allocations -->
        <Card>
          <template #title>Invoice Allocations</template>
          <template #content>
            <div v-if="payment.allocations && payment.allocations.length > 0">
              <DataTable
                :value="payment.allocations"
                stripedRows
                responsiveLayout="scroll"
                class="w-full"
              >
                <Column field="invoice.invoice_number" header="Invoice #" />
                <Column field="invoice.invoice_date" header="Invoice Date">
                  <template #body="{ data }">
                    {{ formatDate(data.invoice.invoice_date) }}
                  </template>
                </Column>
                <Column field="amount" header="Amount">
                  <template #body="{ data }">
                    {{ formatMoney(data.amount, payment.currency) }}
                  </template>
                </Column>
                <Column field="allocated_at" header="Allocated At">
                  <template #body="{ data }">
                    {{ formatDateTime(data.allocated_at) }}
                  </template>
                </Column>
              </DataTable>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
              <i class="fas fa-invoice text-3xl mb-2"></i>
              <p>No invoices allocated to this payment.</p>
            </div>
          </template>
        </Card>
      </div>

      <!-- Right Column - Actions & Customer -->
      <div class="space-y-6">
        <!-- Available Actions -->
        <Card>
          <template #title>Actions</template>
          <template #content>
            <div class="space-y-2">
              <Button
                v-if="payment.status === 'pending'"
                label="Auto-Allocate"
                icon="fas fa-magic"
                class="w-full justify-start"
                @click="handleAutoAllocate"
              />
              
              <Button
                v-if="payment.status === 'pending' || payment.status === 'partial'"
                label="Allocate Manually"
                icon="fas fa-hand-holding-usd"
                class="w-full justify-start"
                @click="showAllocateDialog = true"
              />
              
              <Button
                v-if="payment.status === 'allocated' || payment.status === 'partial'"
                label="Refund Payment"
                icon="fas fa-undo"
                class="w-full justify-start p-button-warning"
                @click="showRefundDialog = true"
              />
              
              <Button
                v-if="payment.status !== 'void' && payment.status !== 'refunded'"
                label="Void Payment"
                icon="fas fa-ban"
                class="w-full justify-start p-button-danger"
                @click="confirmVoid"
              />
              
              <Button
                v-if="payment.status === 'pending'"
                label="Delete Payment"
                icon="fas fa-trash"
                class="w-full justify-start p-button-danger"
                @click="confirmDelete"
              />
            </div>
          </template>
        </Card>

        <!-- Customer Information -->
        <Card>
          <template #title>Customer Information</template>
          <template #content>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Customer
                </label>
                <Link
                  :href="route('customers.show', payment.customer.id)"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                >
                  {{ payment.customer.name }}
                </Link>
              </div>
              
              <div v-if="payment.customer.email">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Email
                </label>
                <a
                  :href="`mailto:${payment.customer.email}`"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  {{ payment.customer.email }}
                </a>
              </div>
              
              <div v-if="payment.customer.phone">
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Phone
                </label>
                <div>{{ payment.customer.phone }}</div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                  Outstanding Balance
                </label>
                <div class="text-lg font-medium" :class="{ 'text-red-600': customerBalance > 0 }">
                  {{ formatMoney(customerBalance, payment.currency) }}
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Payment Summary -->
        <Card>
          <template #title>Summary</template>
          <template #content>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span>Total Amount:</span>
                <span class="font-medium">{{ formatMoney(payment.amount, payment.currency) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Allocated:</span>
                <span class="font-medium">{{ formatMoney(payment.allocated_amount, payment.currency) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Unallocated:</span>
                <span class="font-medium" :class="{ 'text-red-600': unallocatedAmount > 0 }">
                  {{ formatMoney(unallocatedAmount, payment.currency) }}
                </span>
              </div>
              <div class="border-t pt-3 flex justify-between font-bold">
                <span>Allocation Status:</span>
                <span :class="{ 'text-green-600': unallocatedAmount === 0, 'text-yellow-600': unallocatedAmount > 0 }">
                  {{ unallocatedAmount === 0 ? 'Fully Allocated' : 'Partially Allocated' }}
                </span>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Allocate Dialog -->
    <Dialog
      v-model:visible="showAllocateDialog"
      header="Allocate Payment"
      :style="{ width: '600px' }"
      :modal="true"
    >
      <div v-if="unpaidInvoices.length > 0" class="space-y-4">
        <div
          v-for="invoice in unpaidInvoices"
          :key="invoice.id"
          class="flex items-center justify-between p-3 border rounded-lg"
        >
          <div>
            <div class="font-medium">{{ invoice.invoice_number }}</div>
            <div class="text-sm text-gray-500">
              Due: {{ formatDate(invoice.due_date) }}
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <div class="text-right">
              <div class="font-medium">
                {{ formatMoney(invoice.balance_due, invoice.currency) }}
              </div>
              <div class="text-sm text-gray-500">Balance Due</div>
            </div>
            <div class="w-32">
              <InputText
                v-model="allocationAmounts[invoice.id]"
                type="number"
                step="0.01"
                min="0"
                :max="Math.min(invoice.balance_due, unallocatedAmount)"
                placeholder="0.00"
                class="w-full"
              />
            </div>
          </div>
        </div>
        <div class="border-t pt-3 flex justify-between font-medium">
          <span>Total to Allocate:</span>
          <span>{{ formatMoney(totalAllocationAmount, payment.currency) }}</span>
        </div>
      </div>
      <div v-else class="text-center py-8 text-gray-500">
        <i class="fas fa-invoice text-3xl mb-2"></i>
        <p>No unpaid invoices available for allocation.</p>
      </div>
      <template #footer>
        <Button
          label="Cancel"
          @click="showAllocateDialog = false"
          class="p-button-text"
        />
        <Button
          label="Allocate"
          :disabled="totalAllocationAmount === 0"
          @click="handleAllocate"
        />
      </template>
    </Dialog>

    <!-- Refund Dialog -->
    <Dialog
      v-model:visible="showRefundDialog"
      header="Refund Payment"
      :style="{ width: '500px' }"
      :modal="true"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Refund Amount
          </label>
          <InputText
            v-model="refundAmount"
            type="number"
            step="0.01"
            min="0.01"
            :max="payment.allocated_amount"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Refund Reason
          </label>
          <Textarea
            v-model="refundReason"
            rows="3"
            class="w-full"
          />
        </div>
      </div>
      <template #footer>
        <Button
          label="Cancel"
          @click="showRefundDialog = false"
          class="p-button-text"
        />
        <Button
          label="Refund"
          @click="handleRefund"
        />
      </template>
    </Dialog>

    <!-- Void Confirmation Dialog -->
    <Dialog
      v-model:visible="voidDialog"
      header="Confirm Void"
      :style="{ width: '450px' }"
      :modal="true"
    >
      <div class="confirmation-content">
        <i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i>
        <span>Are you sure you want to void this payment?</span>
      </div>
      <template #footer>
        <Button
          label="No"
          @click="voidDialog = false"
          class="p-button-text"
        />
        <Button
          label="Yes"
          @click="handleVoid"
          class="p-button-danger"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog
      v-model:visible="deleteDialog"
      header="Confirm Delete"
      :style="{ width: '450px' }"
      :modal="true"
    >
      <div class="confirmation-content">
        <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
        <span>Are you sure you want to delete this payment?</span>
      </div>
      <template #footer>
        <Button
          label="No"
          @click="deleteDialog = false"
          class="p-button-text"
        />
        <Button
          label="Yes"
          @click="handleDelete"
          class="p-button-danger"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { formatMoney, formatDate, formatDateTime } from '@/Utils/formatting'

interface Payment {
  id: number
  payment_number: string
  payment_date: string
  amount: number
  allocated_amount: number
  payment_method: string
  status: string
  reference_number?: string
  notes?: string
  customer: {
    id: number
    name: string
    email?: string
    phone?: string
  }
  currency: {
    id: number
    code: string
    symbol: string
  }
  allocations: Array<{
    id: number
    amount: number
    allocated_at: string
    invoice: {
      id: number
      invoice_number: string
      invoice_date: string
    }
  }>
}

interface Invoice {
  id: number
  invoice_number: string
  due_date: string
  balance_due: number
  currency: {
    id: number
    code: string
    symbol: string
  }
}

const props = defineProps<{
  payment: Payment
}>()

const showAllocateDialog = ref(false)
const showRefundDialog = ref(false)
const voidDialog = ref(false)
const deleteDialog = ref(false)

const allocationAmounts = reactive<Record<number, number>>({})
const refundAmount = ref('')
const refundReason = ref('')

const unallocatedAmount = computed(() => {
  return props.payment.amount - props.payment.allocated_amount
})

const customerBalance = computed(() => {
  // This would typically come from the customer's current balance
  // For now, we'll use a placeholder
  return 0
})

const unpaidInvoices = computed(() => {
  // This would typically fetch unpaid invoices for the customer
  // For now, we'll use an empty array
  return [] as Invoice[]
})

const totalAllocationAmount = computed(() => {
  return Object.values(allocationAmounts).reduce((sum, amount) => sum + (amount || 0), 0)
})

const handleAutoAllocate = () => {
  router.post(
    route('payments.auto-allocate', props.payment.id),
    {},
    {
      onSuccess: () => {
        showAllocateDialog.value = false
      }
    }
  )
}

const handleAllocate = () => {
  const allocations = Object.entries(allocationAmounts)
    .filter(([_, amount]) => amount > 0)
    .map(([invoiceId, amount]) => ({
      invoice_id: parseInt(invoiceId),
      amount: amount
    }))

  router.post(
    route('payments.allocate', props.payment.id),
    { invoice_allocations: allocations },
    {
      onSuccess: () => {
        showAllocateDialog.value = false
        Object.keys(allocationAmounts).forEach(key => {
          allocationAmounts[key as keyof typeof allocationAmounts] = 0
        })
      }
    }
  )
}

const handleRefund = () => {
  router.post(
    route('payments.refund', props.payment.id),
    {
      refund_amount: parseFloat(refundAmount.value),
      refund_reason: refundReason.value
    },
    {
      onSuccess: () => {
        showRefundDialog.value = false
        refundAmount.value = ''
        refundReason.value = ''
      }
    }
  )
}

const confirmVoid = () => {
  voidDialog.value = true
}

const handleVoid = () => {
  router.post(
    route('payments.void', props.payment.id),
    {},
    {
      onSuccess: () => {
        voidDialog.value = false
      }
    }
  )
}

const confirmDelete = () => {
  deleteDialog.value = true
}

const handleDelete = () => {
  router.delete(route('payments.destroy', props.payment.id), {
    onSuccess: () => {
      deleteDialog.value = false
      router.visit(route('payments.index'))
    }
  })
}

const formatPaymentMethod = (method: string): string => {
  const methodMap: Record<string, string> = {
    'cash': 'Cash',
    'check': 'Check',
    'bank_transfer': 'Bank Transfer',
    'credit_card': 'Credit Card',
    'debit_card': 'Debit Card',
    'paypal': 'PayPal',
    'stripe': 'Stripe',
    'other': 'Other'
  }
  return methodMap[method] || method
}

const formatStatus = (status: string): string => {
  const statusMap: Record<string, string> = {
    'pending': 'Pending',
    'allocated': 'Allocated',
    'partial': 'Partial',
    'refunded': 'Refunded',
    'void': 'Void'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status: string): string => {
  const severityMap: Record<string, string> = {
    'pending': 'warning',
    'allocated': 'success',
    'partial': 'info',
    'refunded': 'danger',
    'void': 'secondary'
  }
  return severityMap[status] || 'secondary'
}
</script>