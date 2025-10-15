<template>
  <Dialog
    v-model:visible="visible"
    :header="isAutoAllocation ? 'Auto-allocate Payment' : 'Allocate Payment'"
    :modal="true"
    :closable="!loading"
    :style="{ width: '90vw', maxWidth: '800px' }"
    @hide="$emit('hide')"
  >
    <div class="space-y-6">
      <!-- Payment Summary -->
      <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
        <h3 class="text-lg font-medium mb-2">Payment Summary</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-500">Payment Number:</span>
            <span class="ml-2 font-medium">{{ payment?.payment_number }}</span>
          </div>
          <div>
            <span class="text-gray-500">Amount:</span>
            <span class="ml-2 font-medium">{{ formatMoney(payment?.amount, payment?.currency) }}</span>
          </div>
          <div>
            <span class="text-gray-500">Already Allocated:</span>
            <span class="ml-2 font-medium">{{ formatMoney(payment?.total_allocated || 0, payment?.currency) }}</span>
          </div>
          <div>
            <span class="text-gray-500">Available to Allocate:</span>
            <span class="ml-2 font-medium text-green-600">{{ formatMoney(remainingAmount, payment?.currency) }}</span>
          </div>
        </div>
      </div>

      <!-- Auto Allocation Mode -->
      <div v-if="isAutoAllocation">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Allocation Strategy
            </label>
            <Dropdown
              v-model="allocationStrategy"
              :options="strategyOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Select allocation strategy"
              class="w-full"
            />
          </div>

          <div v-if="allocationStrategy === 'custom_priority'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Priority Rules
            </label>
            <Textarea
              v-model="priorityRules"
              rows="3"
              placeholder="Enter priority rules (e.g., invoice_priority DESC, due_date ASC)"
              class="w-full"
            />
          </div>

          <!-- Available Invoices Preview -->
          <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Available Invoices ({{ availableInvoices.length }})
            </h4>
            <div class="max-h-60 overflow-y-auto border rounded-lg">
              <DataTable
                :value="availableInvoices"
                :paginator="false"
                :loading="loadingInvoices"
                :scrollable="true"
                :scrollHeight="'200px'"
                class="p-datatable-sm"
              >
                <Column field="invoice_number" header="Invoice #" style="min-width: 120px" />
                <Column field="due_date" header="Due Date" style="min-width: 100px">
                  <template #body="{ data }">
                    {{ formatDate(data.due_date) }}
                  </template>
                </Column>
                <Column field="balance_due" header="Balance Due" style="min-width: 100px">
                  <template #body="{ data }">
                    {{ formatMoney(data.balance_due, data.currency) }}
                  </template>
                </Column>
                <Column field="days_overdue" header="Days Overdue" style="min-width: 80px">
                  <template #body="{ data }">
                    <Badge
                      :value="getDaysOverdueLabel(data.days_overdue)"
                      :severity="getDaysOverdueSeverity(data.days_overdue)"
                      size="small"
                    />
                  </template>
                </Column>
              </DataTable>
            </div>
          </div>
        </div>
      </div>

      <!-- Manual Allocation Mode -->
      <div v-else>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Manual Allocations
            </h4>
            <Button
              label="Add Allocation"
              icon="pi pi-plus"
              size="small"
              @click="addAllocation"
            />
          </div>

          <!-- Allocations List -->
          <div class="space-y-2">
            <div
              v-for="(allocation, index) in allocations"
              :key="index"
              class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
            >
              <div class="flex-1 grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs text-gray-500">Invoice</label>
                  <Dropdown
                    v-model="allocation.invoice_id"
                    :options="availableInvoices"
                    optionLabel="display_name"
                    optionValue="id"
                    placeholder="Select invoice"
                    :filter="true"
                    class="w-full"
                  />
                </div>
                <div>
                  <label class="block text-xs text-gray-500">Amount</label>
                  <InputNumber
                    v-model="allocation.amount"
                    :min="0"
                    :max="remainingAmount + (allocations[index - 1]?.amount || 0)"
                    mode="currency"
                    :currency="payment?.currency?.code"
                    placeholder="0.00"
                    class="w-full"
                  />
                </div>
              </div>
              <div class="flex-1 grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs text-gray-500">Notes</label>
                  <InputText
                    v-model="allocation.notes"
                    placeholder="Allocation notes..."
                    class="w-full"
                  />
                </div>
                <div>
                  <label class="block text-xs text-gray-500">Early Payment Discount</label>
                  <div class="flex items-center gap-2 mt-1">
                    <Checkbox
                      v-model="allocation.apply_early_payment_discount"
                      :binary="true"
                      inputId="discount-{{ index }}"
                    />
                    <label :for="'discount-' + index" class="text-xs text-gray-600">
                      Apply if eligible
                    </label>
                  </div>
                </div>
              </div>
              <Button
                icon="pi pi-trash"
                size="small"
                text
                severity="danger"
                @click="removeAllocation(index)"
                v-tooltip.top="'Remove allocation'"
              />
            </div>
          </div>

          <!-- Allocation Summary -->
          <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
            <div class="flex justify-between items-center text-sm">
              <span>Total to Allocate:</span>
              <span class="font-medium">{{ formatMoney(totalAllocationAmount, payment?.currency) }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span>Remaining:</span>
              <span class="font-medium" :class="remainingAmount < 0 ? 'text-red-600' : 'text-green-600'">
                {{ formatMoney(remainingAmount, payment?.currency) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3 pt-4 border-t">
        <Button
          label="Cancel"
          text
          @click="$emit('hide')"
        />
        <Button
          :label="isAutoAllocation ? 'Auto-allocate' : 'Allocate'"
          :loading="loading"
          :disabled="!canSubmit"
          @click="handleSubmit"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { formatMoney, formatDate } from '@/Utils/formatting'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Badge from 'primevue/badge'

interface Payment {
  id: string
  payment_number: string
  amount: number
  currency: {
    code: string
  }
  total_allocated?: number
}

interface Invoice {
  id: string
  invoice_number: string
  due_date: string
  balance_due: number
  currency: {
    code: string
  }
  days_overdue: number
  display_name: string
}

interface Allocation {
  invoice_id: string
  amount: number
  notes: string
  apply_early_payment_discount: boolean
}

const props = defineProps<{
  visible: boolean
  payment: Payment | null
  isAutoAllocation: boolean
  loading: boolean
  loadingInvoices: boolean
  availableInvoices: Invoice[]
}>()

const emit = defineEmits<{
  hide: []
  allocate: [data: {
    strategy?: string
    priorityRules?: string
    allocations?: Allocation[]
  }]
}>()

// Form state
const allocationStrategy = ref('fifo')
const priorityRules = ref('')
const allocations = ref<Allocation[]>([{ invoice_id: '', amount: 0, notes: '', apply_early_payment_discount: false }])

// Strategy options
const strategyOptions = [
  { label: 'First In, First Out (FIFO)', value: 'fifo' },
  { label: 'Proportional', value: 'proportional' },
  { label: 'Overdue First', value: 'overdue_first' },
  { label: 'Largest First', value: 'largest_first' },
  { label: 'Percentage Based', value: 'percentage_based' },
  { label: 'Custom Priority', value: 'custom_priority' },
]

// Computed
const remainingAmount = computed(() => {
  if (!props.payment) return 0
  return props.payment.amount - (props.payment.total_allocated || 0) - totalAllocationAmount.value
})

const totalAllocationAmount = computed(() => {
  return allocations.value.reduce((sum, allocation) => sum + allocation.amount, 0)
})

const canSubmit = computed(() => {
  if (props.isAutoAllocation) {
    return allocationStrategy.value && remainingAmount.value >= 0
  }
  return allocations.value.every(a => a.invoice_id && a.amount > 0) && 
         remainingAmount.value >= 0 && 
         remainingAmount.value <= (props.payment?.amount || 0)
})

// Methods
const addAllocation = () => {
  allocations.value.push({ invoice_id: '', amount: 0, notes: '', apply_early_payment_discount: false })
}

const removeAllocation = (index: number) => {
  allocations.value.splice(index, 1)
}

const handleSubmit = () => {
  if (!canSubmit.value) return

  if (props.isAutoAllocation) {
    emit('allocate', {
      strategy: allocationStrategy.value,
      priorityRules: priorityRules.value || undefined
    })
  } else {
    emit('allocate', {
      allocations: allocations.value.filter(a => a.invoice_id && a.amount > 0)
    })
  }
}

const getDaysOverdueLabel = (days: number) => {
  if (days < 0) return 'Current'
  if (days <= 30) return `${days} days`
  return `${Math.floor(days / 30)}+ months`
}

const getDaysOverdueSeverity = (days: number) => {
  if (days < 0) return 'success'
  if (days <= 30) return 'warning'
  return 'danger'
}

// Watch for changes and reset form
watch(() => props.visible, (visible) => {
  if (!visible) {
    allocationStrategy.value = 'fifo'
    priorityRules.value = ''
    allocations.value = [{ invoice_id: '', amount: 0, notes: '', apply_early_payment_discount: false }]
  }
})
</script>