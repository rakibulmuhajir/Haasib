<template>
  <LayoutShell :title="pageMeta.title">
    <template #title>
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Payments</h1>
        <div class="flex items-center gap-3">
          <Button 
            :label="'Export Payments'" 
            icon="pi pi-download" 
            outlined 
            size="small"
            @click="exportPayments"
          />
          <Button 
            :label="'Create Payment'" 
            icon="pi pi-plus" 
            size="small"
            @click="navigateTo(route('payments.create'))"
          />
        </div>
      </div>
    </template>

    <template #filters>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4">
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Status</label>
          <Dropdown 
            v-model="filterForm.status" 
            :options="statusOptions" 
            optionLabel="label" 
            optionValue="value"
            placeholder="All Statuses"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Customer</label>
          <Dropdown 
            v-model="filterForm.customer_id" 
            :options="customers" 
            optionLabel="name" 
            optionValue="id"
            placeholder="All Customers"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Payment Method</label>
          <Dropdown 
            v-model="filterForm.payment_method" 
            :options="paymentMethodOptions" 
            optionLabel="label" 
            optionValue="value"
            placeholder="All Methods"
            class="w-full"
            @change="applyFilters"
          />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Date Range</label>
          <Calendar 
            v-model="dateRange" 
            selectionMode="range" 
            placeholder="Select date range"
            class="w-full"
            @date-select="handleDateChange"
          />
        </div>
      </div>

      <div class="p-4 border-t">
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-600">Search:</span>
          <InputText 
            v-model="filterForm.search" 
            placeholder="Search payments..."
            class="flex-1"
            @keyup.enter="applyFilters"
          />
          <Button 
            icon="pi pi-refresh" 
            size="small" 
            outlined
            @click="clearFilters"
            v-tooltip.bottom="'Reset filters'"
          />
        </div>
      </div>
    </template>

    <template #content>
      <Card>
        <template #content>
          <DataTable
            :value="payments.data"
            :loading="payments.loading"
            :paginator="true"
            :rows="payments.per_page"
            :totalRecords="payments.total"
            :lazy="true"
            @sort="handleSort"
            :sortField="filterForm.sort_by"
            :sortOrder="filterForm.sort_direction === 'asc' ? 1 : -1"
            stripedRows
            responsiveLayout="scroll"
            class="w-full"
          >
            <Column 
              field="payment_number" 
              header="Payment #" 
              sortable
              style="width: 140px"
            >
              <template #body="{ data }">
                <div class="font-medium text-gray-900">
                  {{ data.payment_number }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ formatDate(data.payment_date) }}
                </div>
              </template>
            </Column>

            <Column 
              field="customer" 
              header="Customer" 
              sortable
              style="width: 200px"
            >
              <template #body="{ data }">
                <div class="font-medium text-gray-900">
                  {{ data.customer?.name }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ data.customer?.email }}
                </div>
              </template>
            </Column>

            <Column 
              field="amount" 
              header="Amount" 
              sortable
              style="width: 120px; text-align: right"
            >
              <template #body="{ data }">
                <div class="font-medium text-gray-900">
                  {{ formatMoney(data.amount, data.currency) }}
                </div>
                <div class="text-xs text-gray-500">
                  Allocated: {{ formatMoney(data.allocated_amount, data.currency) }}
                </div>
              </template>
            </Column>

            <Column 
              field="payment_method" 
              header="Method" 
              sortable
              style="width: 100px"
            >
              <template #body="{ data }">
                <Badge 
                  :value="formatPaymentMethod(data.payment_method)"
                  :severity="getPaymentMethodSeverity(data.payment_method)"
                  size="small"
                />
              </template>
            </Column>

            <Column 
              field="status" 
              header="Status" 
              sortable
              style="width: 100px"
            >
              <template #body="{ data }">
                <Badge 
                  :value="formatStatus(data.status)"
                  :severity="getStatusSeverity(data.status)"
                  size="small"
                />
              </template>
            </Column>

            <Column 
              field="created_at" 
              header="Created" 
              sortable
              style="width: 120px"
            >
              <template #body="{ data }">
                <div class="text-sm text-gray-600">
                  {{ formatDate(data.created_at) }}
                </div>
              </template>
            </Column>

            <Column 
              header="Actions" 
              style="width: 100px; text-align: center"
              exportable="false"
            >
              <template #body="{ data }">
                <div class="flex items-center justify-center gap-1">
                  <Button
                    icon="pi pi-eye"
                    size="small"
                    text
                    rounded
                    @click="viewPayment(data)"
                    v-tooltip.bottom="'View payment details'"
                  />
                  
                  <template v-if="canEdit(data)">
                    <Button
                      icon="pi pi-edit"
                      size="small"
                      text
                      rounded
                      @click="editPayment(data)"
                      v-tooltip.bottom="'Edit payment'"
                    />
                  </template>

                  <template v-if="canAllocate(data)">
                    <Button
                      icon="pi pi-link"
                      size="small"
                      text
                      rounded
                      @click="allocatePayment(data)"
                      v-tooltip.bottom="'Allocate payment'"
                    />
                  </template>

                  <template v-if="canVoid(data)">
                    <Button
                      icon="pi pi-times"
                      size="small"
                      text
                      rounded
                      severity="danger"
                      @click="confirmVoid(data)"
                      v-tooltip.bottom="'Void payment'"
                    />
                  </template>

                  <template v-if="canRefund(data)">
                    <Button
                      icon="pi pi-undo"
                      size="small"
                      text
                      rounded
                      severity="warning"
                      @click="confirmRefund(data)"
                      v-tooltip.bottom="'Refund payment'"
                    />
                  </template>
                </div>
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8">
                <i class="pi pi-money-bill text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No payments found</p>
                <Button 
                  :label="'Create Payment'" 
                  icon="pi pi-plus" 
                  size="small"
                  class="mt-3"
                  @click="navigateTo(route('payments.create'))"
                />
              </div>
            </template>

            <template #footer>
              <div class="flex items-center justify-between text-sm text-gray-600">
                <span>
                  Showing {{ payments.from }} to {{ payments.to }} of {{ payments.total }} payments
                </span>
                <span>
                  Total Amount: {{ formatMoney(payments.total_amount, payments.currency) }}
                </span>
              </div>
            </template>
          </DataTable>
        </template>
      </Card>
    </template>

    <!-- Void Confirmation Dialog -->
    <Dialog 
      v-model:visible="voidDialog.visible" 
      :header="'Void Payment'" 
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div class="text-gray-600">
          Are you sure you want to void payment <strong>{{ voidDialog.payment?.payment_number }}</strong>?
        </div>
        
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Void Reason</label>
          <Textarea 
            v-model="voidDialog.reason" 
            :rows="3" 
            placeholder="Please specify the reason for voiding this payment..."
            class="w-full"
          />
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          text 
          @click="voidDialog.visible = false"
        />
        <Button 
          label="Void Payment" 
          severity="danger" 
          :loading="voidDialog.loading"
          @click="voidPayment"
        />
      </template>
    </Dialog>

    <!-- Refund Confirmation Dialog -->
    <Dialog 
      v-model:visible="refundDialog.visible" 
      :header="'Refund Payment'" 
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div class="text-gray-600">
          Are you sure you want to refund payment <strong>{{ refundDialog.payment?.payment_number }}</strong>?
        </div>
        
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Refund Amount</label>
          <InputNumber 
            v-model="refundDialog.amount" 
            :min="0" 
            :max="refundDialog.payment?.allocated_amount || 0"
            :currency="refundDialog.payment?.currency"
            :locale="'en-US'"
            mode="currency"
            class="w-full"
          />
        </div>
        
        <div class="space-y-2">
          <label class="text-sm font-medium text-gray-700">Refund Reason</label>
          <Textarea 
            v-model="refundDialog.reason" 
            :rows="3" 
            placeholder="Please specify the reason for this refund..."
            class="w-full"
          />
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          text 
          @click="refundDialog.visible = false"
        />
        <Button 
          label="Refund Payment" 
          severity="warning" 
          :loading="refundDialog.loading"
          @click="refundPayment"
        />
      </template>
    </Dialog>
  </LayoutShell>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Calendar from 'primevue/calendar'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import { formatMoney, formatDate } from '@/Utils/formatting'

const page = usePage()

// Page meta
const pageMeta = computed(() => ({
  title: 'Payments'
}))

interface Payment {
  id: number
  payment_number: string
  payment_date: string
  amount: number
  allocated_amount: number
  payment_method: string
  status: string
  customer?: {
    id: number
    name: string
    email?: string
  }
  currency?: {
    id: number
    code: string
    symbol: string
  }
  created_at: string
}

interface FilterForm {
  status: string | null
  customer_id: number | null
  payment_method: string | null
  date_from: string | null
  date_to: string | null
  search: string | null
  sort_by: string
  sort_direction: string
}

const props = defineProps<{
  payments: any
  filters: any
  customers: any[]
  statusOptions: any[]
  paymentMethodOptions: any[]
}>()

const filterForm = reactive<FilterForm>({
  status: props.filters.status || null,
  customer_id: props.filters.customer_id || null,
  payment_method: props.filters.payment_method || null,
  date_from: props.filters.date_from || null,
  date_to: props.filters.date_to || null,
  search: props.filters.search || null,
  sort_by: props.filters.sort_by || 'created_at',
  sort_direction: props.filters.sort_direction || 'desc'
})

const dateRange = ref(null)

// Dialog states
const voidDialog = reactive({
  visible: false,
  payment: null as Payment | null,
  reason: '',
  loading: false
})

const refundDialog = reactive({
  visible: false,
  payment: null as Payment | null,
  amount: 0,
  reason: '',
  loading: false
})

const applyFilters = () => {
  router.get(route('payments.index'), filterForm, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}

const clearFilters = () => {
  Object.keys(filterForm).forEach(key => {
    if (key === 'sort_by' || key === 'sort_direction') return
    filterForm[key as keyof FilterForm] = null
  })
  dateRange.value = null
  applyFilters()
}

const handleSort = (event: any) => {
  filterForm.sort_by = event.sortField
  filterForm.sort_direction = event.sortOrder === 1 ? 'asc' : 'desc'
  applyFilters()
}

const handleDateChange = () => {
  if (dateRange.value && dateRange.value.length === 2) {
    filterForm.date_from = dateRange.value[0].toISOString().split('T')[0]
    filterForm.date_to = dateRange.value[1].toISOString().split('T')[0]
  } else {
    filterForm.date_from = null
    filterForm.date_to = null
  }
  applyFilters()
}

const viewPayment = (payment: Payment) => {
  router.visit(route('payments.show', payment.id))
}

const editPayment = (payment: Payment) => {
  router.visit(route('payments.edit', payment.id))
}

const allocatePayment = (payment: Payment) => {
  router.visit(route('payments.show', payment.id), {
    data: { action: 'allocate' }
  })
}

const confirmVoid = (payment: Payment) => {
  voidDialog.payment = payment
  voidDialog.reason = ''
  voidDialog.visible = true
}

const confirmRefund = (payment: Payment) => {
  refundDialog.payment = payment
  refundDialog.amount = payment.allocated_amount || 0
  refundDialog.reason = ''
  refundDialog.visible = true
}

const voidPayment = async () => {
  voidDialog.loading = true
  try {
    await router.post(route('payments.void', voidDialog.payment?.id), {
      reason: voidDialog.reason
    })
    voidDialog.visible = false
    applyFilters()
  } catch (error) {
    console.error('Error voiding payment:', error)
  } finally {
    voidDialog.loading = false
  }
}

const refundPayment = async () => {
  refundDialog.loading = true
  try {
    await router.post(route('payments.refund', refundDialog.payment?.id), {
      amount: refundDialog.amount,
      reason: refundDialog.reason
    })
    refundDialog.visible = false
    applyFilters()
  } catch (error) {
    console.error('Error refunding payment:', error)
  } finally {
    refundDialog.loading = false
  }
}

const exportPayments = () => {
  const params = new URLSearchParams(filterForm as any)
  window.open(`/payments/export?${params}`, '_blank')
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
    'completed': 'Completed',
    'failed': 'Failed',
    'cancelled': 'Cancelled'
  }
  return statusMap[status] || status
}

const getPaymentMethodSeverity = (method: string): string => {
  const severityMap: Record<string, string> = {
    cash: 'success',
    bank_transfer: 'success',
    credit_card: 'info',
    debit_card: 'info',
    paypal: 'warning',
    stripe: 'warning',
    check: 'secondary',
    other: 'secondary'
  }
  return severityMap[method] || 'secondary'
}

const getStatusSeverity = (status: string): string => {
  const severityMap: Record<string, string> = {
    pending: 'warning',
    completed: 'success',
    failed: 'danger',
    cancelled: 'secondary'
  }
  return severityMap[status] || 'secondary'
}

const navigateTo = (url: string) => {
  router.visit(url)
}

// Permission helpers
const canEdit = (payment: Payment) => {
  return payment.status === 'pending'
}

const canAllocate = (payment: Payment) => {
  return payment.status === 'completed' && 
         (payment.allocated_amount || 0) < payment.amount
}

const canVoid = (payment: Payment) => {
  return ['pending', 'completed'].includes(payment.status)
}

const canRefund = (payment: Payment) => {
  return payment.status === 'completed' && 
         (payment.allocated_amount || 0) > 0
}

onMounted(() => {
  // Initialize any tooltips or other UI elements if needed
})
</script>