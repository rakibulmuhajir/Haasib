<template>
  <Head title="Payments" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing System" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
      </div>
    </template>

    <div class="space-y-6">
      <!-- Page Header -->
      <PageHeader
        title="Payments"
        subtitle="Manage and track your payments"
        :maxActions="5"
      >
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="table.filterForm.search"
              placeholder="Search payments..."
              @keyup.enter="table.fetchData()"
              class="w-64"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Payments Table -->
      <Card>
        <template #content>
          <!-- Active Filters Chips -->
          <div v-if="table.activeFilters.value.length" class="flex flex-wrap items-center gap-2 mb-3">
            <span class="text-xs text-gray-500">Filters:</span>
            <span
              v-for="f in table.activeFilters.value"
              :key="f.key"
              class="inline-flex items-center text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded"
            >
              <span class="mr-1">{{ f.display }}</span>
              <button
                type="button"
                class="ml-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200"
                @click="table.clearTableFilterField(table.tableFilters.value, f.field)"
                aria-label="Clear filter"
              >
                ×
              </button>
            </span>
            <Button label="Clear all" size="small" text @click="table.clearFilters()" />
          </div>
          <DataTablePro
            :value="payments?.data || []"
            :loading="payments?.loading || false"
            :paginator="true"
            :rows="payments?.per_page || 10"
            :totalRecords="payments?.total || 0"
            :lazy="true"
            :sortField="table.filterForm.sort_by"
            :sortOrder="table.filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            v-model:filters="table.tableFilters.value"
            v-model:selection="table.selectedRows.value"
            @page="table.onPage"
            @sort="table.onSort"
            @filter="table.onFilter"
          >
            <template #empty>
              <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <SvgIcon name="credit-card" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                <p>No payments found.</p>
                <p class="text-sm">Try adjusting your filters or create your first payment.</p>
              </div>
            </template>

            <template #footer>
              <div class="flex items-center justify-between text-sm text-gray-600">
                <span>
                  Showing {{ payments?.from || 0 }} to {{ payments?.to || 0 }} of {{ payments?.total || 0 }} payments
                </span>
              </div>
            </template>

            <template #cell-payment_number="{ data }">
              <div class="font-medium text-gray-900">
                {{ data.payment_number }}
              </div>
              <div class="text-xs text-gray-500">
                {{ formatDate(data.payment_date) }}
              </div>
            </template>

            <template #cell-customer="{ data }">
              <div class="font-medium text-gray-900">
                {{ data.customer?.name || '(unallocated)' }}
              </div>
              <div class="text-xs text-gray-500">
                {{ data.customer?.email }}
              </div>
            </template>

            <template #cell-amount="{ data }">
              <div class="font-medium text-right text-gray-900">
                {{ formatMoney(data.amount, data.currency) }}
              </div>
              <div class="text-xs text-gray-500 text-right">
                Allocated: {{ formatMoney(data.allocated_amount, data.currency) }}
              </div>
            </template>

            <template #cell-payment_method="{ data }">
              <Badge
                :value="formatPaymentMethod(data.payment_method)"
                :severity="getPaymentMethodSeverity(data.payment_method)"
                size="small"
              />
            </template>

            <template #cell-status="{ data }">
              <Badge
                :value="formatStatus(data.status)"
                :severity="getStatusSeverity(data.status)"
                size="small"
              />
            </template>

            <template #cell-created_at="{ data }">
              <div class="text-sm text-gray-600">
                {{ formatDate(data.created_at) }}
              </div>
            </template>

            <template #cell-actions="{ data }">
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
          </DataTablePro>
        </template>
      </Card>
    </div>

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
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, reactive, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import DataTablePro from '@/Components/DataTablePro.vue'
import Badge from 'primevue/badge'
import Card from 'primevue/card'
import { FilterMatchMode } from '@primevue/core/api'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useLookups } from '@/composables/useLookups'
import { formatMoney, formatDate } from '@/Utils/formatting'

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

const props = defineProps({
  payments: {
    type: Object,
    default: () => ({
      data: [],
      current_page: 1,
      per_page: 10,
      total: 0,
      from: 0,
      to: 0,
      loading: false
    })
  },
  filters: {
    type: Object,
    default: () => ({})
  },
  customers: {
    type: Array,
    default: () => []
  },
  statusOptions: {
    type: Array,
    default: () => []
  },
  paymentMethodOptions: {
    type: Array,
    default: () => []
  },
})

const { setActions, clearActions } = usePageActions()
const { formatPaymentMethod, getPaymentMethodSeverity, formatStatus, getStatusSeverity } = useLookups()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Payments', url: '/payments', icon: 'credit-card' },
])

// DataTablePro columns definition
const columns = [
  { field: 'payment_number', header: 'Payment #', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 160px' },
  { field: 'customer', header: 'Customer', filterField: 'customer_name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 220px' },
  { field: 'amount', header: 'Amount', filter: { type: 'number', matchMode: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO }, style: 'width: 140px; text-align: right' },
  { field: 'payment_method', header: 'Method', filter: { type: 'select', options: props.paymentMethodOptions }, style: 'width: 120px' },
  { field: 'status', header: 'Status', filter: { type: 'select', options: props.statusOptions }, style: 'width: 120px' },
  { field: 'created_at', header: 'Created', filter: { type: 'date', matchMode: FilterMatchMode.DATE_AFTER }, style: 'width: 140px' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 120px; text-align: center' },
]

const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'payments.index',
  filterLookups: {
    status: { options: props.statusOptions },
    payment_method: { options: props.paymentMethodOptions },
  },
})

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
    }, { preserveState: true, preserveScroll: true })
    voidDialog.visible = false
    table.fetchData()
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
    }, { preserveState: true, preserveScroll: true })
    refundDialog.visible = false
    table.fetchData()
  } catch (error) {
    console.error('Error refunding payment:', error)
  } finally {
    refundDialog.loading = false
  }
}

// Export functionality
const exportPayments = () => {
  window.location.href = route('payments.export', table.filterForm.data())
}

setActions([
  { key: 'create', label: 'Create Payment', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('payments.create')) },
  { key: 'export', label: 'Export', icon: 'pi pi-download', severity: 'secondary', outlined: true, click: () => exportPayments() },
  { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => table.fetchData() },
])

onUnmounted(() => clearActions())

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
</script>
