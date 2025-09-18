<template>
  <Head title="Payments" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing System" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
        <Link :href="route('payments.create')">
          <Button label="Create Payment" icon="pi pi-plus" severity="primary" />
        </Link>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payments</h1>
          <p class="text-gray-600 dark:text-gray-400">Manage and track your payments</p>
        </div>
        <div class="flex items-center gap-2">
          <Button
            label="Export"
            icon="pi pi-download"
            severity="secondary"
            outlined
            @click="exportPayments"
          />
          <Button
            label="Refresh"
            icon="pi pi-refresh"
            severity="secondary"
            @click="applyFilters"
          />
        </div>
      </div>

      <!-- Filters moved into column menus via DataTablePro -->

      <!-- Payments Table -->
      <Card>
        <template #content>
          <!-- Active Filters Chips -->
          <div v-if="activeFilters.length" class="flex flex-wrap items-center gap-2 mb-3">
            <span class="text-xs text-gray-500">Filters:</span>
            <span
              v-for="f in activeFilters"
              :key="f.key"
              class="inline-flex items-center text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded"
            >
              <span class="mr-1">{{ f.display }}</span>
              <button
                type="button"
                class="ml-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-200"
                @click="clearFilter(f.key)"
                aria-label="Clear filter"
              >
                ×
              </button>
            </span>
            <Button label="Clear all" size="small" text @click="clearFilters" />
          </div>
          <DataTablePro
            :value="payments.data"
            :loading="payments.loading"
            :paginator="true"
            :rows="payments.per_page"
            :totalRecords="payments.total"
            :lazy="true"
            :sortField="filterForm.sort_by"
            :sortOrder="filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            :virtualScroll="payments.total > 200"
            scrollHeight="500px"
            v-model:filters="tableFilters"
            @page="onPage"
            @sort="onSort"
            @filter="onFilter"
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
                  Showing {{ payments.from }} to {{ payments.to }} of {{ payments.total }} payments
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

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>

<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, watch, reactive, computed } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import DataTablePro from '@/Components/DataTablePro.vue'
import Badge from 'primevue/badge'
import Card from 'primevue/card'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import { formatMoney, formatDate } from '@/Utils/formatting'
import { FilterMatchMode, FilterOperator } from '@primevue/core/api'
import { buildDefaultTableFiltersFromColumns, buildDslFromTableFilters, clearTableFilterField } from '@/Utils/filters'

const page = usePage()
const toast = page.props.toast || {}

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Payments', url: '/payments', icon: 'credit-card' },
])

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
  amount_min?: string | number | null
  amount_max?: string | number | null
  created_from?: string | null
  created_to?: string | null
}

const props = defineProps({
  payments: Object,
  filters: Object,
  customers: Array,
  statusOptions: Array,
  paymentMethodOptions: Array,
})

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

// Two-way binding for table filter models
const tableFilters = ref<Record<string, any>>()

const buildDefaultTableFilters = () => buildDefaultTableFiltersFromColumns(columns as any)

// Filter form
const filterForm = useForm({
  status: props.filters?.status || '',
  customer_id: props.filters?.customer_id || '',
  payment_method: props.filters?.payment_method || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  search: props.filters?.search || '',
  sort_by: props.filters?.sort_by || 'created_at',
  sort_direction: props.filters?.sort_direction || 'desc',
  amount_min: props.filters?.amount_min || '',
  amount_max: props.filters?.amount_max || '',
  created_from: props.filters?.created_from || '',
  created_to: props.filters?.created_to || '',
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

// Build query without empty values
const buildQuery = () => {
  const data = filterForm.data() as Record<string, any>
  const base = Object.fromEntries(
    Object.entries(data).filter(([_, v]) => v !== '' && v !== null && v !== undefined)
  )
  // Build normalized DSL filters from current table filters
  const dsl = buildDslFromTableFilters(tableFilters.value)
  if (dsl.rules.length) {
    base.filters = JSON.stringify(dsl)
  } else {
    delete base.filters
  }
  return base
}

// Apply filters
const applyFilters = () => {
  router.get(route('payments.index'), buildQuery(), {
    preserveState: true,
    preserveScroll: true
  })
}

// Clear filters
const clearFilters = () => {
  filterForm.reset()
  tableFilters.value = buildDefaultTableFilters()
  router.get(route('payments.index'), {}, { preserveState: true, preserveScroll: true })
}

// Watch for filter changes and auto-apply
watch(
  () => [
    filterForm.status,
    filterForm.customer_id,
    filterForm.payment_method,
    filterForm.date_from,
    filterForm.date_to,
    filterForm.search,
    filterForm.sort_by,
    filterForm.sort_direction
  ],
  () => {
    if (filterForm.recentlySuccessful) return // Skip after form submission
    applyFilters()
  },
  { deep: true }
)

// Map DataTablePro events to server-side filters
const onPage = (e: any) => {
  const params = { ...buildQuery(), page: (e.page || 0) + 1 }
  router.get(route('payments.index'), params, { preserveState: true, preserveScroll: true })
}

const onSort = (e: any) => {
  if (!e.sortField) return
  const allowed = ['created_at', 'payment_date', 'amount', 'status']
  if (!allowed.includes(e.sortField)) return
  filterForm.sort_by = e.sortField
  filterForm.sort_direction = e.sortOrder === 1 ? 'asc' : 'desc'
  applyFilters()
}

const onFilter = (e: any) => {
  // Sync local model first to avoid stale fallbacks
  if (e && e.filters) {
    tableFilters.value = e.filters
  }
  const f: any = (e && e.filters) || tableFilters.value || {}
  const constraints = (key: string) => (f[key]?.constraints || []) as Array<{ value: any; matchMode: string }>
  const firstVal = (key: string) => f[key]?.constraints?.[0]?.value ?? null
  const global = f.global?.value ?? null

  // Map search-like fields
  filterForm.search = global || firstVal('payment_number') || firstVal('customer_name') || ''
  filterForm.status = firstVal('status') || ''
  filterForm.payment_method = firstVal('payment_method') || ''

  // Amount min/max
  let minAmt: number | null = null
  let maxAmt: number | null = null
  for (const c of constraints('amount')) {
    if (c.value === null || c.value === '') continue
    const n = typeof c.value === 'number' ? c.value : Number(c.value)
    if (!Number.isFinite(n)) continue
    const mode = (c.matchMode || '').toString().toLowerCase()
    if (mode.includes('greater') || mode === 'gt' || mode === 'gte') {
      minAmt = minAmt == null ? n : Math.min(minAmt, n)
    } else if (mode.includes('less') || mode === 'lt' || mode === 'lte') {
      maxAmt = maxAmt == null ? n : Math.max(maxAmt, n)
    } else if (mode === 'between' && Array.isArray(c.value)) {
      const [a, b] = c.value.map((v: any) => Number(v))
      if (Number.isFinite(a)) minAmt = minAmt == null ? a : Math.min(minAmt, a)
      if (Number.isFinite(b)) maxAmt = maxAmt == null ? b : Math.max(maxAmt, b)
    } else if (mode === 'equals' || mode === 'eq') {
      minAmt = maxAmt = n
    }
  }
  filterForm.amount_min = minAmt ?? ''
  filterForm.amount_max = maxAmt ?? ''

  // Created_at date range
  const toDateStr = (d: any) => {
    if (!d) return ''
    const dt = typeof d === 'string' ? new Date(d) : d
    if (!dt || isNaN(dt.getTime())) return ''
    // Format as local date (avoid UTC shift from toISOString)
    const y = dt.getFullYear()
    const m = String(dt.getMonth() + 1).padStart(2, '0')
    const day = String(dt.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }

  let createdFrom: string | '' = ''
  let createdTo: string | '' = ''
  for (const c of constraints('created_at')) {
    const mode = (c.matchMode || '').toString().toLowerCase()
    if (mode === 'dateafter' || mode === 'after' || mode === 'gt' || mode === 'gte') {
      createdFrom = toDateStr(c.value) || createdFrom
    } else if (mode === 'datebefore' || mode === 'before' || mode === 'lt' || mode === 'lte') {
      createdTo = toDateStr(c.value) || createdTo
    } else if (mode === 'dateis' || mode === 'equals' || mode === 'eq') {
      const d = toDateStr(c.value)
      createdFrom = d
      createdTo = d
    } else if (mode === 'between' && Array.isArray(c.value)) {
      const [a, b] = c.value
      const aS = toDateStr(a)
      const bS = toDateStr(b)
      if (aS) createdFrom = aS
      if (bS) createdTo = bS
    }
  }
  filterForm.created_from = createdFrom
  filterForm.created_to = createdTo

  applyFilters()
}

// Build a normalized DSL (rules + logic) from the DataTable filters
const buildDslFromTableFilters = () => {
  return buildDslFromTableFilters(tableFilters.value)
}


// Active filter chips derived from filterForm
const optionLabel = (opts: Array<any>, value: string) => {
  const found = (opts || []).find((o: any) => (o.value ?? o.id) === value)
  return found?.label || value
}

const activeFilters = computed(() => {
  const chips: Array<{ key: string; display: string }> = []
  if (filterForm.search) chips.push({ key: 'search', display: `Search: ${filterForm.search}` })
  if (filterForm.status) chips.push({ key: 'status', display: `Status: ${optionLabel(props.statusOptions as any, filterForm.status as any)}` })
  if (filterForm.payment_method) chips.push({ key: 'payment_method', display: `Method: ${optionLabel(props.paymentMethodOptions as any, filterForm.payment_method as any)}` })
  if (filterForm.amount_min) chips.push({ key: 'amount_min', display: `Amount ≥ ${filterForm.amount_min}` })
  if (filterForm.amount_max) chips.push({ key: 'amount_max', display: `Amount ≤ ${filterForm.amount_max}` })
  if (filterForm.created_from) chips.push({ key: 'created_from', display: `Created ≥ ${filterForm.created_from}` })
  if (filterForm.created_to) chips.push({ key: 'created_to', display: `Created ≤ ${filterForm.created_to}` })
  return chips
})

const clearFilter = (key: string) => {
  ;(filterForm as any)[key] = ''
  // also clear the corresponding table filter UI state
  if (!tableFilters.value) tableFilters.value = buildDefaultTableFilters()
  const tf = tableFilters.value
  const resetField = (field: string) => {
    if (tf[field]) {
      const mm = tf[field].constraints?.[0]?.matchMode
      tf[field] = { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: mm }] }
    }
  }
  switch (key) {
    case 'status':
      resetField('status')
      break
    case 'payment_method':
      resetField('payment_method')
      break
    case 'amount_min':
    case 'amount_max':
      resetField('amount')
      break
    case 'created_from':
    case 'created_to':
      resetField('created_at')
      break
    case 'search':
      if (tf.global) tf.global.value = null
      resetField('payment_number')
      resetField('customer_name')
      break
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

// Export functionality
const exportPayments = () => {
  window.location.href = route('payments.export', filterForm.data())
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
</script>
