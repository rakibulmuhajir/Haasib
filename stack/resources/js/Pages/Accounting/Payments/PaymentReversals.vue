<template>
  <LayoutShell>
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Payments"
      description="Manage payment processing and transactions"
      subDescription="Track receipts, reversals, and payment allocations"
      :show-search="true"
      search-placeholder="Search payments..."
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <!-- Left Column - Main Content -->
      <div class="main-content">
        <div class="payment-reversal-manager">

    <!-- Search and Filters -->
    <Card class="mb-6">
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="flex flex-col gap-2">
            <label for="search-payment" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Search Payment
            </label>
            <IconField iconPosition="left">
              <InputIcon class="pi pi-search" />
              <InputText
                id="search-payment"
                v-model="searchQuery"
                placeholder="Search by payment # or customer"
                class="w-full"
              />
            </IconField>
          </div>

          <div class="flex flex-col gap-2">
            <label for="status-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Payment Status
            </label>
            <Dropdown
              id="status-filter"
              v-model="selectedStatus"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="All Statuses"
              class="w-full"
              showClear
            />
          </div>

          <div class="flex flex-col gap-2">
            <label for="date-range" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Payment Date
            </label>
            <Calendar
              id="date-range"
              v-model="dateRange"
              selectionMode="range"
              :manualInput="false"
              dateFormat="yy-mm-dd"
              placeholder="Select date range"
              class="w-full"
            />
          </div>
        </div>

        <div class="flex justify-end mt-4 gap-2">
          <Button
            label="Clear Filters"
            @click="clearFilters"
            severity="secondary"
            size="small"
          />
          <Button
            label="Search"
            @click="searchPayments"
            :loading="loading"
            size="small"
          />
        </div>
      </template>
    </Card>

    <!-- Payments Table -->
    <Card>
      <template #title>
        <span class="flex items-center gap-2">
          <i class="pi pi-replay"></i>
          Payment Reversals
        </span>
      </template>
      
      <template #content>
        <div v-if="loading" class="flex justify-center py-8">
          <ProgressSpinner />
        </div>

        <DataTable
          v-else
          :value="payments"
          :paginator="true"
          :rows="10"
          :loading="loading"
          :globalFilterFields="['payment_number', 'entity_name']"
          v-model:filters="filters"
          filterDisplay="menu"
          :rowHover="true"
          dataKey="id"
          class="p-datatable-sm"
        >
          <!-- Payment Number Column -->
          <Column field="payment_number" header="Payment #" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <span class="font-medium">{{ data.payment_number }}</span>
                <Tag
                  v-if="data.reconciled"
                  value="Reconciled"
                  severity="success"
                  size="small"
                />
              </div>
            </template>
          </Column>

          <!-- Entity Column -->
          <Column field="entity_name" header="Customer" sortable>
            <template #body="{ data }">
              <div class="flex flex-col">
                <span class="font-medium">{{ data.entity?.name || 'N/A' }}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                  {{ data.entity_type }}
                </span>
              </div>
            </template>
          </Column>

          <!-- Amount Column -->
          <Column field="amount" header="Amount" sortable>
            <template #body="{ data }">
              <div class="text-right">
                <div class="font-medium">
                  {{ data.currency?.symbol }}{{ formatCurrency(data.amount) }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                  {{ data.currency?.code }}
                </div>
              </div>
            </template>
          </Column>

          <!-- Status Column -->
          <Column field="status" header="Status" sortable>
            <template #body="{ data }">
              <Tag
                :value="data.status_label"
                :severity="getStatusSeverity(data.status)"
                size="small"
              />
            </template>
          </Column>

          <!-- Payment Method Column -->
          <Column field="payment_method" header="Method" sortable>
            <template #body="{ data }">
              <span>{{ data.payment_method_label }}</span>
            </template>
          </Column>

          <!-- Date Column -->
          <Column field="payment_date" header="Payment Date" sortable>
            <template #body="{ data }">
              <span>{{ formatDate(data.payment_date) }}</span>
            </template>
          </Column>

          <!-- Allocations Column -->
          <Column field="allocations" header="Allocations">
            <template #body="{ data }">
              <div class="flex flex-col">
                <span class="font-medium">{{ data.allocation_summary?.allocated_count || 0 }}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                  {{ data.currency?.symbol }}{{ formatCurrency(data.allocation_summary?.total_allocated || 0) }}
                </span>
              </div>
            </template>
          </Column>

          <!-- Actions Column -->
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <Button
                  icon="pi pi-eye"
                  size="small"
                  text
                  rounded
                  v-tooltip="'View Details'"
                  @click="viewPaymentDetails(data)"
                />
                
                <Button
                  v-if="canReversePayment(data)"
                  icon="pi pi-replay"
                  size="small"
                  text
                  rounded
                  severity="danger"
                  v-tooltip="'Reverse Payment'"
                  @click="confirmPaymentReversal(data)"
                />

                <Button
                  v-if="hasAllocations(data)"
                  icon="pi pi-undo"
                  size="small"
                  text
                  rounded
                  severity="warning"
                  v-tooltip="'Manage Allocations'"
                  @click="showAllocations(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Payment Details Dialog -->
    <Dialog
      v-model:visible="showDetailsDialog"
      modal
      header="Payment Details"
      :style="{ width: '70vw' }"
      :maximizable="true"
    >
      <div v-if="selectedPayment" class="space-y-6">
        <!-- Payment Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card>
            <template #title>Payment Information</template>
            <template #content>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Payment #:</span>
                  <span class="font-medium">{{ selectedPayment.payment_number }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Amount:</span>
                  <span class="font-medium">
                    {{ selectedPayment.currency?.symbol }}{{ formatCurrency(selectedPayment.amount) }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Method:</span>
                  <span>{{ selectedPayment.payment_method_label }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Date:</span>
                  <span>{{ formatDate(selectedPayment.payment_date) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Status:</span>
                  <Tag
                    :value="selectedPayment.status_label"
                    :severity="getStatusSeverity(selectedPayment.status)"
                  />
                </div>
              </div>
            </template>
          </Card>

          <Card>
            <template #title>Entity Information</template>
            <template #content>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Name:</span>
                  <span class="font-medium">{{ selectedPayment.entity?.name || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Type:</span>
                  <span>{{ selectedPayment.entity_type }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Created By:</span>
                  <span>{{ selectedPayment.created_by || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Created:</span>
                  <span>{{ formatDateTime(selectedPayment.created_at) }}</span>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Allocations Information -->
        <Card>
          <template #title>Allocation Summary</template>
          <template #content>
            <div v-if="selectedPayment.allocation_summary">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="text-center">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Total Allocated</p>
                  <p class="text-xl font-semibold text-green-600 dark:text-green-400">
                    {{ selectedPayment.currency?.symbol }}{{ formatCurrency(selectedPayment.allocation_summary.total_allocated) }}
                  </p>
                </div>
                <div class="text-center">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Remaining</p>
                  <p class="text-xl font-semibold text-blue-600 dark:text-blue-400">
                    {{ selectedPayment.currency?.symbol }}{{ formatCurrency(selectedPayment.allocation_summary.remaining_amount) }}
                  </p>
                </div>
                <div class="text-center">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Allocations</p>
                  <p class="text-xl font-semibold">
                    {{ selectedPayment.allocation_summary.allocated_count }}
                  </p>
                </div>
              </div>

              <Button
                v-if="hasAllocations(selectedPayment)"
                label="View Allocations"
                icon="pi pi-list"
                @click="showAllocations(selectedPayment)"
                severity="secondary"
                size="small"
              />
            </div>
          </template>
        </Card>
      </div>

      <template #footer>
        <div class="flex justify-between">
          <div>
            <Button
              v-if="canReversePayment(selectedPayment)"
              label="Reverse Payment"
              icon="pi pi-replay"
              severity="danger"
              @click="confirmPaymentReversal(selectedPayment)"
            />
          </div>
          <Button
            label="Close"
            @click="showDetailsDialog = false"
            severity="secondary"
          />
        </div>
      </template>
    </Dialog>

    <!-- Payment Reversal Dialog -->
    <Dialog
      v-model:visible="showReversalDialog"
      modal
      header="Reverse Payment"
      :style="{ width: '50vw' }"
    >
      <form @submit.prevent="executePaymentReversal" class="space-y-4">
        <div v-if="reversalPayment" class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
          <h4 class="font-medium text-yellow-800 dark:text-yellow-200 mb-2">Payment to Reverse</h4>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500 dark:text-gray-400">Payment #:</span>
              <span class="ml-2 font-medium">{{ reversalPayment.payment_number }}</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Amount:</span>
              <span class="ml-2 font-medium">
                {{ reversalPayment.currency?.symbol }}{{ formatCurrency(reversalPayment.amount) }}
              </span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Customer:</span>
              <span class="ml-2 font-medium">{{ reversalPayment.entity?.name }}</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Method:</span>
              <span class="ml-2">{{ reversalPayment.payment_method_label }}</span>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label for="reversal-method" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Reversal Method *
            </label>
            <Dropdown
              id="reversal-method"
              v-model="reversalForm.method"
              :options="reversalMethods"
              optionLabel="label"
              optionValue="value"
              placeholder="Select reversal method"
              class="w-full"
              required
            />
            <small class="text-gray-500 dark:text-gray-400">
              Choose the appropriate reversal method for this payment
            </small>
          </div>

          <div class="flex flex-col gap-2">
            <label for="reversal-amount" class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Reversal Amount
            </label>
            <InputNumber
              id="reversal-amount"
              v-model="reversalForm.amount"
              :max="reversalPayment?.amount"
              :min="0.01"
              :step="0.01"
              mode="currency"
              :currency="reversalPayment?.currency?.code || 'USD'"
              placeholder="Leave empty for full reversal"
              class="w-full"
            />
            <small class="text-gray-500 dark:text-gray-400">
              Leave empty to reverse the full amount
            </small>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label for="reversal-reason" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Reason for Reversal *
          </label>
          <Textarea
            id="reversal-reason"
            v-model="reversalForm.reason"
            rows="3"
            placeholder="Explain why this payment is being reversed..."
            class="w-full"
            required
          />
          <small class="text-gray-500 dark:text-gray-400">
            This reason will be recorded in the audit trail
          </small>
        </div>

        <div v-if="reversalPayment?.allocation_summary?.allocated_count > 0" 
             class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
          <h4 class="font-medium text-amber-800 dark:text-amber-200 mb-2">
            <i class="pi pi-exclamation-triangle mr-2"></i>
            Allocation Warning
          </h4>
          <p class="text-sm text-amber-700 dark:text-amber-300">
            This payment has {{ reversalPayment.allocation_summary.allocated_count }} allocation(s) 
            totaling {{ reversalPayment.currency?.symbol }}{{ formatCurrency(reversalPayment.allocation_summary.total_allocated) }}. 
            Reversing this payment will automatically reverse all associated allocations.
          </p>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-between">
          <Button
            label="Cancel"
            @click="showReversalDialog = false"
            severity="secondary"
          />
          <Button
            label="Reverse Payment"
            icon="pi pi-replay"
            severity="danger"
            @click="executePaymentReversal"
            :loading="reversing"
          />
        </div>
      </template>
    </Dialog>

    <!-- Allocations Management Dialog -->
    <Dialog
      v-model:visible="showAllocationsDialog"
      modal
      header="Manage Allocations"
      :style="{ width: '80vw' }"
      :maximizable="true"
    >
      <div v-if="selectedPayment">
        <div class="mb-4 flex justify-between items-center">
          <h3 class="text-lg font-medium">
            Allocations for Payment {{ selectedPayment.payment_number }}
          </h3>
          <Button
            icon="pi pi-refresh"
            label="Refresh"
            @click="loadAllocations"
            :loading="loadingAllocations"
            size="small"
          />
        </div>

        <DataTable
          :value="allocations"
          :loading="loadingAllocations"
          dataKey="id"
          class="p-datatable-sm"
        >
          <Column field="invoice_number" header="Invoice #">
            <template #body="{ data }">
              <span class="font-medium">{{ data.invoice_number || 'N/A' }}</span>
            </template>
          </Column>

          <Column field="invoice_due_date" header="Due Date">
            <template #body="{ data }">
              <span>{{ data.invoice_due_date ? formatDate(data.invoice_due_date) : 'N/A' }}</span>
            </template>
          </Column>

          <Column field="allocated_amount" header="Amount">
            <template #body="{ data }">
              <span class="font-medium">
                {{ formatCurrency(data.allocated_amount) }}
              </span>
            </template>
          </Column>

          <Column field="allocation_method" header="Method">
            <template #body="{ data }">
              <Tag :value="data.allocation_method" size="small" />
            </template>
          </Column>

          <Column field="allocation_date" header="Date">
            <template #body="{ data }">
              <span>{{ data.allocation_date ? formatDate(data.allocation_date) : 'N/A' }}</span>
            </template>
          </Column>

          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="getAllocationStatusSeverity(data.status)"
                size="small"
              />
            </template>
          </Column>

          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <Button
                  v-if="canReverseAllocation(data)"
                  icon="pi pi-undo"
                  size="small"
                  text
                  rounded
                  severity="warning"
                  v-tooltip="'Reverse Allocation'"
                  @click="confirmAllocationReversal(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>

      <template #footer>
        <Button
          label="Close"
          @click="showAllocationsDialog = false"
          severity="secondary"
        />
      </template>
    </Dialog>

    <!-- Allocation Reversal Dialog -->
    <Dialog
      v-model:visible="showAllocationReversalDialog"
      modal
      header="Reverse Allocation"
      :style="{ width: '50vw' }"
    >
      <form @submit.prevent="executeAllocationReversal" class="space-y-4">
        <div v-if="reversalAllocation" class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
          <h4 class="font-medium text-yellow-800 dark:text-yellow-200 mb-2">Allocation to Reverse</h4>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500 dark:text-gray-400">Payment #:</span>
              <span class="ml-2 font-medium">{{ selectedPayment?.payment_number }}</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Invoice #:</span>
              <span class="ml-2 font-medium">{{ reversalAllocation.invoice_number || 'N/A' }}</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Allocated Amount:</span>
              <span class="ml-2 font-medium">
                {{ formatCurrency(reversalAllocation.allocated_amount) }}
              </span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Allocation Date:</span>
              <span class="ml-2">
                {{ reversalAllocation.allocation_date ? formatDate(reversalAllocation.allocation_date) : 'N/A' }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label for="allocation-refund-amount" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Refund Amount
          </label>
          <InputNumber
            id="allocation-refund-amount"
            v-model="allocationReversalForm.refundAmount"
            :max="reversalAllocation?.allocated_amount"
            :min="0.01"
            :step="0.01"
            mode="currency"
            currency="USD"
            placeholder="Leave empty for full refund"
            class="w-full"
          />
          <small class="text-gray-500 dark:text-gray-400">
            Leave empty to refund the full allocated amount. This amount will be restored to the invoice balance.
          </small>
        </div>

        <div class="flex flex-col gap-2">
          <label for="allocation-reversal-reason" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Reason for Reversal *
          </label>
          <Textarea
            id="allocation-reversal-reason"
            v-model="allocationReversalForm.reason"
            rows="3"
            placeholder="Explain why this allocation is being reversed..."
            class="w-full"
            required
          />
          <small class="text-gray-500 dark:text-gray-400">
            This reason will be recorded in the audit trail
          </small>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-between">
          <Button
            label="Cancel"
            @click="showAllocationReversalDialog = false"
            severity="secondary"
          />
          <Button
            label="Reverse Allocation"
            icon="pi pi-undo"
            severity="warning"
            @click="executeAllocationReversal"
            :loading="reversingAllocation"
          />
        </div>
      </template>
    </Dialog>

    <!-- Audit Trail Dialog -->
    <Dialog
      v-model:visible="showAuditTrail"
      modal
      header="Payment Audit Trail"
      :style="{ width: '90vw' }"
      :maximizable="true"
    >
      <AuditTimeline :payment-id="selectedPayment?.id" />
    </Dialog>
      </div>
    </div>

      <!-- Right Column - Quick Links -->
      <div class="sidebar-content">
        <QuickLinks 
          :links="quickLinks" 
          title="Payment Actions"
        />
      </div>
    </div>
  </LayoutShell>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { format } from 'date-fns'
import AuditTimeline from './AuditTimeline.vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import { usePageActions } from '@/composables/usePageActions'

// Composition
const toast = useToast()
const { actions } = usePageActions()

// Define page actions
const pageActions = [
  {
    key: 'new-reversal',
    label: 'New Reversal',
    icon: 'pi pi-plus',
    severity: 'primary',
    action: () => showNewReversalDialog.value = true
  },
  {
    key: 'audit-trail',
    label: 'View Audit Trail',
    icon: 'pi pi-history',
    severity: 'secondary',
    action: () => showAuditTrail.value = true
  }
]

// Define quick links for the payments page
const quickLinks = [
  {
    label: 'New Payment Reversal',
    url: '#',
    icon: 'pi pi-plus',
    action: () => showNewReversalDialog.value = true
  },
  {
    label: 'Payment Batches',
    url: '/payments/batches',
    icon: 'pi pi-database'
  },
  {
    label: 'Audit Timeline',
    url: '/payments/audit',
    icon: 'pi pi-history'
  },
  {
    label: 'Payment Reports',
    url: '/payments/reports',
    icon: 'pi pi-chart-bar'
  }
]

// Set page actions
actions.value = pageActions

// State
const loading = ref(false)
const payments = ref([])
const selectedPayment = ref(null)
const allocations = ref([])
const loadingAllocations = ref(false)

// Dialog states
const showDetailsDialog = ref(false)
const showReversalDialog = ref(false)
const showAllocationsDialog = ref(false)
const showAllocationReversalDialog = ref(false)
const showNewReversalDialog = ref(false)
const showAuditTrail = ref(false)

// Reversal states
const reversalPayment = ref(null)
const reversalAllocation = ref(null)
const reversing = ref(false)
const reversingAllocation = ref(false)

// Forms
const reversalForm = reactive({
  method: null,
  amount: null,
  reason: ''
})

const allocationReversalForm = reactive({
  refundAmount: null,
  reason: ''
})

// Filters
const searchQuery = ref('')
const selectedStatus = ref(null)
const dateRange = ref(null)
const filters = ref({})

// Options
const statusOptions = [
  { label: 'All Statuses', value: null },
  { label: 'Pending', value: 'pending' },
  { label: 'Allocated', value: 'allocated' },
  { label: 'Reconciled', value: 'reconciled' },
  { label: 'Reversed', value: 'reversed' }
]

const reversalMethods = [
  { label: 'Void - Cancel payment completely', value: 'void' },
  { label: 'Refund - Return funds to customer', value: 'refund' },
  { label: 'Chargeback - Payment disputed by bank', value: 'chargeback' }
]

// Methods
const loadPayments = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }
    
    if (selectedStatus.value) {
      params.append('status', selectedStatus.value)
    }
    
    if (dateRange.value) {
      params.append('start_date', dateRange.value[0])
      params.append('end_date', dateRange.value[1])
    }

    const response = await fetch(`/api/accounting/payments?${params}`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load payments')
    }

    const data = await response.json()
    payments.value = data.data || data
  } catch (error) {
    console.error('Error loading payments:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load payments',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const searchPayments = () => {
  loadPayments()
}

const clearFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = null
  dateRange.value = null
  loadPayments()
}

const viewPaymentDetails = (payment) => {
  selectedPayment.value = payment
  showDetailsDialog.value = true
}

const confirmPaymentReversal = (payment) => {
  reversalPayment.value = payment
  reversalForm.method = null
  reversalForm.amount = null
  reversalForm.reason = ''
  showReversalDialog.value = true
}

const executePaymentReversal = async () => {
  if (!reversalForm.reason.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Validation Error',
      detail: 'Please provide a reason for the reversal',
      life: 3000
    })
    return
  }

  reversing.value = true
  try {
    const payload = {
      reason: reversalForm.reason,
      method: reversalForm.method,
    }

    if (reversalForm.amount) {
      payload.amount = reversalForm.amount
    }

    const response = await fetch(`/api/accounting/payments/${reversalPayment.value.id}/reverse`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      },
      body: JSON.stringify(payload)
    })

    if (!response.ok) {
      const error = await response.json()
      throw new Error(error.message || 'Failed to reverse payment')
    }

    const result = await response.json()
    
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Payment reversed successfully',
      life: 3000
    })

    showReversalDialog.value = false
    await loadPayments()
    
    // Show details dialog with updated information
    selectedPayment.value = reversalPayment.value
    showDetailsDialog.value = true

  } catch (error) {
    console.error('Error reversing payment:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message || 'Failed to reverse payment',
      life: 3000
    })
  } finally {
    reversing.value = false
  }
}

const showAllocations = async (payment) => {
  selectedPayment.value = payment
  await loadAllocations()
  showAllocationsDialog.value = true
}

const loadAllocations = async () => {
  if (!selectedPayment.value) return

  loadingAllocations.value = true
  try {
    const response = await fetch(`/api/accounting/payments/${selectedPayment.value.id}/allocations`, {
      headers: {
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load allocations')
    }

    const data = await response.json()
    allocations.value = data
  } catch (error) {
    console.error('Error loading allocations:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load allocations',
      life: 3000
    })
  } finally {
    loadingAllocations.value = false
  }
}

const confirmAllocationReversal = (allocation) => {
  reversalAllocation.value = allocation
  allocationReversalForm.refundAmount = null
  allocationReversalForm.reason = ''
  showAllocationReversalDialog.value = true
}

const executeAllocationReversal = async () => {
  if (!allocationReversalForm.reason.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Validation Error',
      detail: 'Please provide a reason for the reversal',
      life: 3000
    })
    return
  }

  reversingAllocation.value = true
  try {
    const payload = {
      reason: allocationReversalForm.reason
    }

    if (allocationReversalForm.refundAmount) {
      payload.refund_amount = allocationReversalForm.refundAmount
    }

    const response = await fetch(`/api/accounting/payments/${selectedPayment.value.id}/allocations/${reversalAllocation.value.id}/reverse`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Company-Id': localStorage.getItem('companyId') || '',
        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
      },
      body: JSON.stringify(payload)
    })

    if (!response.ok) {
      const error = await response.json()
      throw new Error(error.message || 'Failed to reverse allocation')
    }

    const result = await response.json()
    
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Allocation reversed successfully',
      life: 3000
    })

    showAllocationReversalDialog.value = false
    await loadAllocations()
    await loadPayments()

  } catch (error) {
    console.error('Error reversing allocation:', error)
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.message || 'Failed to reverse allocation',
      life: 3000
    })
  } finally {
    reversingAllocation.value = false
  }
}

// Helper Methods
const canReversePayment = (payment) => {
  return ['pending', 'allocated', 'reconciled'].includes(payment?.status)
}

const hasAllocations = (payment) => {
  return payment?.allocation_summary?.allocated_count > 0
}

const canReverseAllocation = (allocation) => {
  return ['active'].includes(allocation?.status)
}

const getStatusSeverity = (status) => {
  const severities = {
    'pending': 'warning',
    'allocated': 'info',
    'reconciled': 'success',
    'reversed': 'danger'
  }
  return severities[status] || 'secondary'
}

const getAllocationStatusSeverity = (status) => {
  const severities = {
    'active': 'success',
    'reversed': 'danger'
  }
  return severities[status] || 'secondary'
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount || 0)
}

const formatDate = (dateString) => {
  try {
    return format(new Date(dateString), 'MMM dd, yyyy')
  } catch {
    return dateString
  }
}

const formatDateTime = (dateString) => {
  try {
    return format(new Date(dateString), 'MMM dd, yyyy HH:mm')
  } catch {
    return dateString
  }
}

// Lifecycle
onMounted(() => {
  loadPayments()
})
</script>

