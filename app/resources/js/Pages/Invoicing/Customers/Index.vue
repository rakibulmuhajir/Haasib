<template>
  <Head title="Customers" />

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
        title="Customers"
        subtitle="Manage your customer relationships"
        :maxActions="4"
      />

      <!-- Column-menu filters only (header filter card removed) -->

      <!-- Customers Table -->
      <Card>
        <template #content>
          <DataTablePro
            :value="customers.data"
            :loading="customers.loading"
            :paginator="true"
            :rows="customers.per_page"
            :totalRecords="customers.total"
            :lazy="true"
            :sortField="filterForm.sort_by"
            :sortOrder="filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            v-model:filters="tableFilters"
            v-model:selection="selectedRows"
            selectionMode="multiple"
            dataKey="id"
            :showSelectionColumn="true"
            @page="onPage"
            @sort="onSort"
            @filter="onFilter"
          >
            <Column 
              field="created_at" 
              header="Customer Since" 
              sortable
              style="width: 140px"
            >
              <template #body="{ data }">
                <div class="font-medium text-gray-900">
                  {{ formatCustomerSince(data.created_at) }}
                </div>
                <div class="text-xs text-gray-500" v-if="data.is_active">
                  Active
                </div>
              </template>
            </Column>

            <Column 
              field="name" 
              header="Customer Name" 
              sortable
              style="width: 250px"
            >
              <template #body="{ data }">
                <div class="font-medium text-gray-900">
                  {{ data.name }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ data.email }}
                </div>
                <div class="text-xs text-gray-500" v-if="data.phone">
                  {{ data.phone }}
                </div>
              </template>
            </Column>

            <Column 
              field="tax_number" 
              header="Tax ID" 
              sortable
              style="width: 120px"
            >
              <template #body="{ data }">
                <span class="text-sm text-gray-600">
                  {{ data.tax_number || '-' }}
                </span>
              </template>
            </Column>

            <Column 
              field="country" 
              header="Country" 
              sortable
              style="width: 120px"
            >
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <span v-if="data.country?.code" class="text-lg">{{ getCountryFlag(data.country.code) }}</span>
                  <span>{{ data.country?.name || '-' }}</span>
                </div>
              </template>
            </Column>

            <Column 
              field="currency" 
              header="Currency" 
              sortable
              style="width: 100px"
            >
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <span class="font-medium">{{ data.currency?.code || '-' }}</span>
                  <span class="text-xs text-gray-500">{{ data.currency?.symbol || '' }}</span>
                </div>
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
                  :value="formatStatus(data.is_active ? 'active' : 'inactive')"
                  :severity="getStatusSeverity(data.is_active ? 'active' : 'inactive')"
                  size="small"
                />
              </template>
            </Column>

            <Column 
              field="outstanding_balance" 
              header="Balance" 
              sortable
              style="width: 140px; text-align: right"
            >
              <template #body="{ data }">
                <div class="text-right">
                  <div class="font-medium" :class="getBalanceClass(data.outstanding_balance)">
                    {{ formatMoney(data.outstanding_balance, data.currency) }}
                  </div>
                  <div class="text-xs text-gray-500">
                    <span :class="getRiskBadgeClass(data.risk_level)">
                      {{ data.risk_level || 'low' }} risk
                    </span>
                  </div>
                </div>
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
              style="width: 160px; text-align: center"
              exportable="false"
            >
              <template #body="{ data }">
                <div class="flex items-center justify-center gap-1">
                  <Button
                    icon="pi pi-eye"
                    size="small"
                    text
                    rounded
                    @click="viewCustomer(data)"
                    v-tooltip.bottom="'View customer details'"
                    class="text-blue-600 hover:text-blue-800"
                  />
                  
                  <Button
                    icon="pi pi-edit"
                    size="small"
                    text
                    rounded
                    @click="editCustomer(data)"
                    v-tooltip.bottom="'Edit customer'"
                    class="text-green-600 hover:text-green-800"
                  />
                  
                  <Button
                    icon="pi pi-chart-line"
                    size="small"
                    text
                    rounded
                    @click="viewStatistics(data)"
                    v-tooltip.bottom="'View statistics'"
                    class="text-purple-600 hover:text-purple-800"
                  />
                  
                  <Button
                    v-if="canDelete(data)"
                    icon="pi pi-trash"
                    size="small"
                    text
                    rounded
                    severity="danger"
                    @click="confirmDelete(data)"
                    v-tooltip.bottom="'Delete customer'"
                    class="text-red-600 hover:text-red-800"
                  />
                </div>
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8">
                <i class="pi pi-users text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No customers found</p>
                <Button 
                  :label="'Create Customer'" 
                  icon="pi pi-plus" 
                  size="small"
                  class="mt-3"
                  @click="router.visit(route('customers.create'))"
                />
              </div>
            </template>

            <template #footer>
              <div class="flex items-center justify-between text-sm text-gray-600">
                <span>
                  Showing {{ customers.from }} to {{ customers.to }} of {{ customers.total }} customers
                </span>
                <span>
                  Active: {{ customers.total_active }} | Inactive: {{ customers.total_inactive }}
                </span>
              </div>
            </template>
          </DataTablePro>
        </template>
      </Card>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialog.visible" 
      :header="'Delete Customer'" 
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div class="text-gray-600">
          Are you sure you want to delete customer <strong>{{ deleteDialog.customer?.name }}</strong>?
        </div>
        
        <div v-if="deleteDialog.customer?.outstanding_balance > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
          <div class="flex items-center gap-2">
            <i class="pi pi-exclamation-triangle text-yellow-600"></i>
            <span class="text-sm text-yellow-800">
              This customer has an outstanding balance of {{ formatMoney(deleteDialog.customer.outstanding_balance, deleteDialog.customer.currency) }}
            </span>
          </div>
        </div>
        
        <div class="text-sm text-gray-500">
          This action cannot be undone. All related data including invoices, payments, and contacts will be affected.
        </div>
      </div>

      <template #footer>
        <Button 
          label="Cancel" 
          text 
          @click="deleteDialog.visible = false"
        />
        <Button 
          label="Delete Customer" 
          severity="danger" 
          :loading="deleteDialog.loading"
          @click="deleteCustomer"
        />
      </template>
    </Dialog>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>

<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, watch, reactive, computed, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import DataTablePro from '@/Components/DataTablePro.vue'
import { FilterMatchMode } from '@primevue/core/api'
import { buildDefaultTableFiltersFromColumns, buildDslFromTableFilters, clearTableFilterField } from '@/Utils/filters'
import Badge from 'primevue/badge'
import Card from 'primevue/card'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import Dialog from 'primevue/dialog'
import { formatDate, formatMoney } from '@/Utils/formatting'
import { usePageActions } from '@/composables/usePageActions'
import { useToast } from 'primevue/usetoast'

interface Customer {
  id: number
  customer_number: string
  name: string
  email?: string
  phone?: string
  customer_type: string
  status: string
  created_at: string
  tax_number?: string
  outstanding_balance?: number
  risk_level?: string
  is_active?: boolean
  country?: {
    id: number
    name: string
    code: string
  }
  currency?: {
    id: number
    code: string
    symbol: string
  }
}

interface FilterForm {
  status: string
  customer_type: string
  country_id: string
  date_from: string
  date_to: string
  search: string
  sort_by: string
  sort_direction: string
}

const props = defineProps({
  customers: Object,
  filters: Object,
  countries: Array,
  statusOptions: Array,
  customerTypeOptions: Array,
})

// Filter form
const filterForm = useForm({
  status: props.filters?.status || '',
  customer_type: props.filters?.customer_type || '',
  country_id: props.filters?.country_id || '',
  date_from: props.filters?.created_from || '',
  date_to: props.filters?.created_to || '',
  search: props.filters?.search || '',
  sort_by: props.filters?.sort_by || 'created_at',
  sort_direction: props.filters?.sort_direction || 'desc',
})
// Columns for DataTablePro
const columns = [
  { field: 'created_at', header: 'Customer Since', filter: { type: 'date', matchMode: FilterMatchMode.DATE_AFTER }, style: 'width: 140px' },
  { field: 'name', header: 'Customer Name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 250px' },
  { field: 'tax_number', header: 'Tax ID', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 140px' },
  { field: 'country', header: 'Country', filterField: 'country_name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 140px' },
  { field: 'currency', header: 'Currency', filterField: 'currency_code', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 100px' },
  { field: 'is_active', header: 'Status', filter: { type: 'select', matchMode: FilterMatchMode.EQUALS, options: [{label:'Active', value:'1'},{label:'Inactive', value:'0'}] }, style: 'width: 120px' },
  { field: 'outstanding_balance', header: 'Balance', filter: { type: 'number', matchMode: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO }, style: 'width: 140px; text-align: right' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 140px; text-align: center' },
]

const tableFilters = ref<Record<string, any>>(buildDefaultTableFiltersFromColumns(columns as any))

const buildQuery = () => {
  const data = filterForm.data() as Record<string, any>
  const base = Object.fromEntries(Object.entries(data).filter(([_, v]) => v !== '' && v !== null && v !== undefined))
  const dsl = buildDslFromTableFilters(tableFilters.value)
  if (dsl.rules.length) base.filters = JSON.stringify(dsl)
  return base
}

const deleteDialog = ref({
  visible: false,
  customer: null as Customer | null,
  loading: false
})
const page = usePage()
const toast = page.props.toast || {}

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Customers', url: '/customers', icon: 'users' },
])

const viewCustomer = (customer: Customer) => {
  router.visit(route('customers.show', customer.id))
}

const editCustomer = (customer: Customer) => {
  router.visit(route('customers.edit', customer.id))
}

const viewStatistics = (customer: Customer) => {
  router.visit(route('customers.statistics', customer.id))
}

// Export functionality
const exportCustomers = () => {
  window.location.href = route('customers.export', filterForm.data())
}

const canDelete = (customer: Customer): boolean => {
  return customer.outstanding_balance === 0
}

const getBalanceClass = (balance: number): string => {
  if (balance > 0) return 'text-red-600'
  if (balance < 0) return 'text-green-600'
  return 'text-gray-600'
}

// Apply filters
const applyFilters = () => {
  router.get(route('customers.index'), buildQuery(), { preserveState: true, preserveScroll: true })
}

// Clear filters
const clearFilters = () => {
  filterForm.reset()
  tableFilters.value = buildDefaultTableFiltersFromColumns(columns as any)
  router.get(route('customers.index'), {}, { preserveState: true, preserveScroll: true })
}

// Watch for filter changes and auto-apply
watch(
  () => [
    filterForm.status,
    filterForm.customer_type,
    filterForm.country_id,
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

// Handle sorting
const onPage = (e: any) => {
  const params = { ...buildQuery(), page: (e.page || 0) + 1 }
  router.get(route('customers.index'), params, { preserveState: true, preserveScroll: true })
}

const onSort = (e: any) => {
  if (!e.sortField) return
  const allowed = ['created_at', 'name', 'email', 'tax_number', 'is_active']
  if (!allowed.includes(e.sortField)) return
  filterForm.sort_by = e.sortField
  filterForm.sort_direction = e.sortOrder === 1 ? 'asc' : 'desc'
  applyFilters()
}

const onFilter = (e: any) => {
  if (e && e.filters) tableFilters.value = e.filters
  applyFilters()
}

// Active filter chips derived from tableFilters
const activeFilters = computed(() => {
  const chips: Array<{ key: string; display: string; field: string }> = []
  const f: any = tableFilters.value || {}
  const first = (k: string) => f[k]?.constraints?.[0]
  const add = (key: string, field: string, display: string) => chips.push({ key, field, display })

  const name = first('name')
  if (name?.value) add('name', 'name', `Name: ${name.value}`)
  const country = first('country_name')
  if (country?.value) add('country_name', 'country_name', `Country: ${country.value}`)
  const currency = first('currency_code')
  if (currency?.value) add('currency_code', 'currency_code', `Currency: ${currency.value}`)
  const status = first('is_active')
  const statusLabel = (val: string|number) => {
    const opts = (props.statusOptions as any[] | undefined) || []
    const found = opts.find((o:any) => String(o.value) === String(val))
    return found?.label ?? (String(val) === '1' ? 'Active' : 'Inactive')
  }
  if (status?.value !== null && status?.value !== '' && status?.value !== undefined) add('is_active', 'is_active', `Status: ${statusLabel(status.value)}`)

  // Date created_at
  const dateCs = f.created_at?.constraints || []
  const toLocal = (d:any)=>{ if(!d) return ''; const dt=new Date(d); return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}` }
  const dBetween = dateCs.find((c:any)=>String(c.matchMode||'').toLowerCase()==='between')
  if (dBetween && Array.isArray(dBetween.value)) {
    const a = toLocal(dBetween.value[0]); const b = toLocal(dBetween.value[1])
    if (a||b) add('created_at', 'created_at', `Created: ${a||'…'} — ${b||'…'}`)
  } else {
    for (const c of dateCs) {
      const mm = String(c.matchMode||'').toLowerCase(); const d = toLocal(c.value); if (!d) continue
      if (mm.includes('after')) add('created_at', 'created_at', `Created ≥ ${d}`)
      else if (mm.includes('before')) add('created_at', 'created_at', `Created ≤ ${d}`)
      else if (mm.includes('dateis')||mm.includes('equals')) add('created_at', 'created_at', `Created = ${d}`)
    }
  }

  // Number outstanding_balance
  const numCs = f.outstanding_balance?.constraints || []
  const nBetween = numCs.find((c:any)=>String(c.matchMode||'').toLowerCase()==='between')
  if (nBetween && Array.isArray(nBetween.value)) {
    const [a,b] = nBetween.value
    if (a!=null || b!=null) add('outstanding_balance', 'outstanding_balance', `Balance: ${a ?? '…'} — ${b ?? '…'}`)
  } else {
    for (const c of numCs) {
      const mm = String(c.matchMode||'').toLowerCase(); const v=c.value; if (v===''||v==null) continue
      if (mm.includes('greater')) add('outstanding_balance','outstanding_balance',`Balance ≥ ${v}`)
      else if (mm.includes('less')) add('outstanding_balance','outstanding_balance',`Balance ≤ ${v}`)
      else if (mm.includes('equals')) add('outstanding_balance','outstanding_balance',`Balance = ${v}`)
    }
  }

  return chips
})

const clearFilterChip = (chip: { key: string; field: string }) => {
  clearTableFilterField(tableFilters.value, chip.field)
  applyFilters()
}

const confirmDelete = (customer: Customer) => {
  deleteDialog.value.customer = customer
  deleteDialog.value.visible = true
}

const deleteCustomer = () => {
  if (!deleteDialog.value.customer) return

  deleteDialog.value.loading = true
  router.delete(route('customers.destroy', deleteDialog.value.customer.id), {
    onSuccess: () => {
      deleteDialog.value.visible = false
      deleteDialog.value.customer = null
    },
    onFinish: () => {
      deleteDialog.value.loading = false
    }
  })
}

const formatCustomerType = (type: string): string => {
  const typeMap: Record<string, string> = {
    'individual': 'Individual',
    'business': 'Business',
    'non_profit': 'Non-Profit',
    'government': 'Government'
  }
  return typeMap[type] || type
}

const getTypeSeverity = (type: string): string => {
  const severityMap: Record<string, string> = {
    'individual': 'info',
    'business': 'success',
    'non_profit': 'warning',
    'government': 'secondary'
  }
  return severityMap[type] || 'secondary'
}

const formatStatus = (status: string): string => {
  const statusMap: Record<string, string> = {
    'active': 'Active',
    'inactive': 'Inactive',
    'suspended': 'Suspended'
  }
  return statusMap[status] || status
}

const getStatusSeverity = (status: string): string => {
  const severityMap: Record<string, string> = {
    'active': 'success',
    'inactive': 'secondary',
    'suspended': 'danger'
  }
  return severityMap[status] || 'secondary'
}

const formatCustomerSince = (dateString: string): string => {
  const date = new Date(dateString)
  const month = date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase()
  const year = date.getFullYear().toString().slice(-2)
  return `${month.slice(0, 3)}'${year}`
}

const getCountryFlag = (countryCode: string): string => {
  const flagMap: Record<string, string> = {
    'US': '🇺🇸', 'GB': '🇬🇧', 'CA': '🇨🇦', 'AU': '🇦🇺', 'DE': '🇩🇪',
    'FR': '🇫🇷', 'IT': '🇮🇹', 'ES': '🇪🇸', 'JP': '🇯🇵', 'CN': '🇨🇳', 'IN': '🇮🇳'
  }
  return flagMap[countryCode] || '🌐'
}

const getRiskBadgeClass = (riskLevel: string): string => {
  const risk = riskLevel?.toLowerCase() || 'low'
  switch (risk) {
    case 'high': return 'text-red-600 font-medium'
    case 'medium': return 'text-orange-600 font-medium'
    case 'low': return 'text-green-600 font-medium'
    default: return 'text-gray-600'
  }
}
// Selection for bulk actions
const selectedRows = ref<any[]>([])

// Page Actions rendered in page header
const { setActions, clearActions } = usePageActions()
const toasty = useToast()

async function bulkDelete() {
  if (!selectedRows.value.length) return
  await router.post(route('customers.bulk'), {
    action: 'delete',
    customer_ids: selectedRows.value.map((r:any) => r.id)
  }, { preserveState: true, preserveScroll: true })
}
async function bulkDisable() {
  if (!selectedRows.value.length) return
  await router.post(route('customers.bulk'), {
    action: 'disable',
    customer_ids: selectedRows.value.map((r:any) => r.id)
  }, { preserveState: true, preserveScroll: true })
}
async function bulkEnable() {
  if (!selectedRows.value.length) return
  await router.post(route('customers.bulk'), {
    action: 'enable',
    customer_ids: selectedRows.value.map((r:any) => r.id)
  }, { preserveState: true, preserveScroll: true })
}

setActions([
  { key: 'add', label: 'Add New', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('customers.create')) },
  { key: 'delete', label: 'Delete Selected', icon: 'pi pi-trash', severity: 'danger', disabled: () => selectedRows.value.length === 0, click: bulkDelete },
  { key: 'disable', label: 'Disable', icon: 'pi pi-ban', severity: 'secondary', disabled: () => selectedRows.value.length === 0, click: bulkDisable },
  { key: 'enable', label: 'Enable', icon: 'pi pi-check', severity: 'success', disabled: () => selectedRows.value.length === 0, click: bulkEnable },
  { key: 'export', label: 'Export', icon: 'pi pi-download', severity: 'secondary', outlined: true, click: () => exportCustomers() },
  { key: 'refresh', label: 'Refresh', icon: 'pi pi-refresh', severity: 'secondary', click: () => applyFilters() },
])

onUnmounted(() => clearActions())
</script>
