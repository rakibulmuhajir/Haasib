<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, watch, computed, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import DataTablePro from '@/Components/DataTablePro.vue'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { FilterMatchMode } from '@primevue/core/api'
import { buildDefaultTableFiltersFromColumns, buildDslFromTableFilters } from '@/Utils/filters'
import { usePageActions } from '@/composables/usePageActions'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  invoices: Object,
  filters: Object,
  customers: Array,
  currencies: Array,
  statusOptions: Array,
})

const page = usePage()
const toast = page.props.toast || {}
const { setActions, clearActions } = usePageActions()
const toasty = useToast()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Invoices', url: '/invoices', icon: 'list' },
])

// Filter form
const filterForm = useForm({
  status: props.filters?.status || '',
  customer_id: props.filters?.customer_id || '',
  currency_id: props.filters?.currency_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  search: props.filters?.search || '',
  sort_by: props.filters?.sort_by || 'created_at',
  sort_direction: props.filters?.sort_direction || 'desc',
})

// DataTablePro columns and filters
const columns = [
  { field: 'invoice_number', header: 'Invoice #', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 160px' },
  { field: 'customer', header: 'Customer', filterField: 'customer_name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 220px' },
  { field: 'invoice_date', header: 'Invoice Date', filter: { type: 'date', matchMode: FilterMatchMode.DATE_AFTER }, style: 'width: 140px' },
  { field: 'due_date', header: 'Due Date', filter: { type: 'date', matchMode: FilterMatchMode.DATE_AFTER }, style: 'width: 140px' },
  { field: 'total_amount', header: 'Total', filter: { type: 'number', matchMode: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO }, style: 'width: 120px; text-align: right' },
  { field: 'balance_due', header: 'Balance', filter: { type: 'number', matchMode: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO }, style: 'width: 120px; text-align: right' },
  { field: 'status', header: 'Status', filter: { type: 'select', matchMode: FilterMatchMode.EQUALS, options: props.statusOptions }, style: 'width: 120px' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 200px; text-align: center' },
]

const tableFilters = ref<Record<string, any>>(buildDefaultTableFiltersFromColumns(columns as any))

const buildQuery = () => {
  const data = filterForm.data() as Record<string, any>
  const base = Object.fromEntries(Object.entries(data).filter(([_, v]) => v !== '' && v !== null && v !== undefined))
  const dsl = buildDslFromTableFilters(tableFilters.value)
  if (dsl.rules.length) base.filters = JSON.stringify(dsl)
  return base
}

// Status badge styling
const getStatusSeverity = (status) => {
  const severityMap = {
    draft: 'secondary',
    sent: 'info',
    posted: 'warning',
    paid: 'success',
    cancelled: 'danger',
    void: 'contrast'
  }
  return severityMap[status] || 'secondary'
}

// Format currency
const formatCurrency = (amount, currency) => {
  if (!amount || !currency) return '-'
  
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency.code || 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

// Format date
const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Apply filters
const applyFilters = () => {
  router.get(route('invoices.index'), buildQuery(), {
    preserveState: true,
    preserveScroll: true
  })
}

// Clear filters
const clearFilters = () => {
  filterForm.reset()
  tableFilters.value = buildDefaultTableFiltersFromColumns(columns as any)
  router.get(route('invoices.index'), {}, {
    preserveState: true,
    preserveScroll: true
  })
}

// Watch for filter changes and auto-apply
watch(
  () => [
    filterForm.status,
    filterForm.customer_id,
    filterForm.currency_id,
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

// Table events
const onPage = (e) => {
  const params = { ...buildQuery(), page: (e.page || 0) + 1 }
  router.get(route('invoices.index'), params, { preserveState: true, preserveScroll: true })
}

const onSort = (e) => {
  if (!e.sortField) return
  const allowed = ['created_at', 'invoice_date', 'due_date', 'total_amount', 'status']
  if (!allowed.includes(e.sortField)) return
  filterForm.sort_by = e.sortField
  filterForm.sort_direction = e.sortOrder === 1 ? 'asc' : 'desc'
  applyFilters()
}

const onFilter = (e) => {
  if (e && e.filters) tableFilters.value = e.filters
  applyFilters()
}

// Active filter chips derived from tableFilters
const activeFilters = computed(() => {
  const chips: Array<{ key: string; display: string; field: string }> = []
  const f: any = tableFilters.value || {}
  const first = (k: string) => f[k]?.constraints?.[0]

  const addChip = (key: string, field: string, display: string) => chips.push({ key, display, field })

  const pn = first('invoice_number')
  if (pn?.value) addChip('invoice_number', 'invoice_number', `# ${pn.value}`)
  const cn = first('customer_name')
  if (cn?.value) addChip('customer_name', 'customer_name', `Customer: ${cn.value}`)
  const st = first('status')
  if (st?.value) addChip('status', 'status', `Status: ${st.value}`)

  // Dates
  const dateChip = (k: string, label: string) => {
    const c = f[k]?.constraints || []
    const between = c.find((x: any) => String(x.matchMode||'').toLowerCase()==='between')
    const fmt = (d:any)=>{ if(!d)return ''; const dt=new Date(d); return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}` }
    if (between && Array.isArray(between.value)) {
      const a = fmt(between.value[0]); const b = fmt(between.value[1])
      if (a||b) addChip(k, k, `${label}: ${a||'…'} — ${b||'…'}`)
    } else {
      for (const x of c) {
        const mm = String(x.matchMode||'').toLowerCase()
        const d = fmt(x.value)
        if (!d) continue
        if (mm.includes('after')) addChip(k, k, `${label} ≥ ${d}`)
        else if (mm.includes('before')) addChip(k, k, `${label} ≤ ${d}`)
        else if (mm.includes('dateis')||mm.includes('equals')) addChip(k, k, `${label} = ${d}`)
      }
    }
  }
  dateChip('invoice_date', 'Invoice Date')
  dateChip('due_date', 'Due Date')

  // Numbers
  const numberChip = (k: string, label: string) => {
    const c = f[k]?.constraints || []
    const between = c.find((x: any) => String(x.matchMode||'').toLowerCase()==='between')
    if (between && Array.isArray(between.value)) {
      const [a,b] = between.value
      if (a!=null || b!=null) addChip(k, k, `${label}: ${a ?? '…'} — ${b ?? '…'}`)
    } else {
      for (const x of c) {
        const mm = String(x.matchMode||'').toLowerCase()
        const n = x.value
        if (n==='' || n==null) continue
        if (mm.includes('greater')) addChip(k, k, `${label} ≥ ${n}`)
        else if (mm.includes('less')) addChip(k, k, `${label} ≤ ${n}`)
        else if (mm.includes('equals')) addChip(k, k, `${label} = ${n}`)
      }
    }
  }
  numberChip('total_amount', 'Total')
  numberChip('balance_due', 'Balance')

  return chips
})

const clearFilterChip = (chip: { key: string; field: string }) => {
  clearTableFilterField(tableFilters.value, chip.field)
  applyFilters()
}

// Export functionality
const exportInvoices = () => {
  window.location.href = route('invoices.export', filterForm.data())
}

// Page Actions for Invoices
const selectedRows = ref<any[]>([])
async function bulkRemind() {
  if (!selectedRows.value.length) return
  await router.post(route('invoices.bulk'), { action: 'remind', invoice_ids: selectedRows.value.map((r:any) => r.invoice_id ?? r.id) }, { preserveState: true, preserveScroll: true })
}
async function bulkCancel() {
  if (!selectedRows.value.length) return
  await router.post(route('invoices.bulk'), { action: 'cancel', invoice_ids: selectedRows.value.map((r:any) => r.invoice_id ?? r.id) }, { preserveState: true, preserveScroll: true })
}

setActions([
  { key: 'create', label: 'Create Invoice', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('invoices.create')) },
  { key: 'remind', label: 'Send Reminders', icon: 'pi pi-bell', severity: 'secondary', disabled: () => selectedRows.value.length === 0, click: bulkRemind },
  { key: 'cancel', label: 'Cancel', icon: 'pi pi-ban', severity: 'danger', disabled: () => selectedRows.value.length === 0, click: bulkCancel },
  { key: 'export', label: 'Export', icon: 'pi pi-download', severity: 'secondary', outlined: true, click: () => exportInvoices() },
  { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => applyFilters() },
])

onUnmounted(() => clearActions())
</script>

<template>
  <Head title="Invoices" />

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
      <PageHeader
        title="Invoices"
        subtitle="Manage and track your invoices"
        :maxActions="4"
      />

      <!-- Column-menu filters only (header filter card removed) -->

      <!-- Invoices Table -->
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
                @click="clearFilterChip(f)"
                aria-label="Clear filter"
              >
                ×
              </button>
            </span>
            <Button label="Clear all" size="small" text @click="clearFilters" />
          </div>
          <DataTablePro
            :value="invoices.data"
            :loading="invoices.loading"
            :paginator="true"
            :rows="invoices.per_page"
            :totalRecords="invoices.total"
            :lazy="true"
            :sortField="filterForm.sort_by"
            :sortOrder="filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            v-model:filters="tableFilters"
            v-model:selection="selectedRows"
            selectionMode="multiple"
            dataKey="invoice_id"
            :showSelectionColumn="true"
            @page="onPage"
            @sort="onSort"
            @filter="onFilter"
          >
            <template #cell-invoice_number="{ data }">
              <Link :href="route('invoices.show', data.invoice_id)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                {{ data.invoice_number }}
              </Link>
            </template>

            <template #cell-customer="{ data }">
              <div>
                <div class="font-medium">{{ data.customer?.name }}</div>
                <div class="text-sm text-gray-500" v-if="data.customer?.email">{{ data.customer.email }}</div>
              </div>
            </template>

            <template #cell-invoice_date="{ data }">{{ formatDate(data.invoice_date) }}</template>

            <template #cell-due_date="{ data }">
              <div>
                <div>{{ formatDate(data.due_date) }}</div>
                <div v-if="data.status !== 'paid' && new Date(data.due_date) < new Date()" class="text-xs text-red-600 dark:text-red-400">Overdue</div>
              </div>
            </template>

            <template #cell-total_amount="{ data }">{{ formatCurrency(data.total_amount, data.currency) }}</template>

            <template #cell-paid_amount="{ data }">{{ formatCurrency(data.paid_amount, data.currency) }}</template>

            <template #cell-balance_due="{ data }">
              <div :class="data.balance_due > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-green-600 dark:text-green-400'">
                {{ formatCurrency(data.balance_due, data.currency) }}
              </div>
            </template>

            <template #cell-status="{ data }">
              <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
            </template>

            <template #cell-actions="{ data }">
                <div class="flex items-center gap-1">
                  <Link :href="route('invoices.show', data.invoice_id)">
                    <Button 
                      icon="pi pi-eye" 
                      size="small" 
                      severity="secondary" 
                      outlined 
                      v-tooltip="'View Invoice'"
                    />
                  </Link>

                  <Link 
                    :href="route('invoices.edit', data.invoice_id)"
                    v-if="data.status === 'draft'"
                  >
                    <Button 
                      icon="pi pi-pencil" 
                      size="small" 
                      severity="primary" 
                      outlined 
                      v-tooltip="'Edit Invoice'"
                    />
                  </Link>

                  <Link :href="route('invoices.generate-pdf', data.invoice_id)" target="_blank">
                    <Button 
                      icon="pi pi-file-pdf" 
                      size="small" 
                      severity="info" 
                      outlined 
                      v-tooltip="'Download PDF'"
                    />
                  </Link>

                  <!-- Status-based actions -->
                  <template v-if="data.status === 'draft'">
                    <Button 
                      icon="pi pi-send" 
                      size="small" 
                      severity="warning" 
                      outlined 
                      v-tooltip="'Mark as Sent'"
                      @click="router.post(route('invoices.send', data.invoice_id))"
                    />
                  </template>

                  <template v-else-if="data.status === 'sent'">
                    <Button 
                      icon="pi pi-check" 
                      size="small" 
                      severity="success" 
                      outlined 
                      v-tooltip="'Post to Ledger'"
                      @click="router.post(route('invoices.post', data.invoice_id))"
                    />
                  </template>
                </div>
            </template>

            <template #empty>
              <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <SvgIcon name="file-text" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                <p>No invoices found.</p>
                <p class="text-sm">Try adjusting your filters or create your first invoice.</p>
              </div>
            </template>

            <template #loading>
              <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                Loading invoices...
              </div>
            </template>
          </DataTablePro>
        </template>
      </Card>
    </div>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>
