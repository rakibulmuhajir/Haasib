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
        :maxActions="5"
      >
        <template #actions>
          <span class="p-input-icon-left">
            <i class="fas fa-search"></i>
            <InputText
              v-model="table.filterForm.search"
              placeholder="Search customers..."
              @keyup.enter="table.fetchData()"
              class="w-64"
            />
          </span>
        </template>
      </PageHeader>

      <!-- Column-menu filters only (header filter card removed) -->

      <!-- Customers Table -->
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
            :value="customers.data"
            :loading="customers.loading"
            :paginator="true"
            :rows="customers.per_page"
            :totalRecords="customers.total"
            :lazy="true"
            :sortField="table.filterForm.sort_by"
            :sortOrder="table.filterForm.sort_direction === 'asc' ? 1 : -1"
            :columns="columns"
            v-model:filters="table.tableFilters.value"
            v-model:selection="table.selectedRows.value"
            selectionMode="multiple"
            dataKey="id"
            :showSelectionColumn="true"
            :virtualScroll="customers.total > 200"
            scrollHeight="500px"
            responsiveLayout="stack"
            breakpoint="960px"
            @page="table.onPage"
            @sort="table.onSort"
            @filter="table.onFilter"
          >
            <Column
              field="created_at"
              header="Customer Since"
              sortable
              style="width: 140px"
              class="hidden md:table-cell"
            >
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i class="fas fa-calendar-plus text-gray-400"></i>
                  <div>
                    <div class="font-medium text-gray-900">
                      {{ formatCustomerSince(data.created_at) }}
                    </div>
                    <div class="text-xs text-gray-500" v-if="data.is_active">
                      Active
                    </div>
                  </div>
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
                <CustomerInfoDisplay
                  :name="data.name"
                  :email="data.email"
                  :phone="data.phone"
                />
              </template>
            </Column>

            <Column
              field="tax_number"
              header="Tax ID"
              sortable
              style="width: 120px"
              class="hidden lg:table-cell"
            >
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i class="fas fa-receipt text-gray-400"></i>
                  <span class="text-sm text-gray-600">
                    {{ data.tax_number || '-' }}
                  </span>
                </div>
              </template>
            </Column>

            <Column
              field="country.name"
              header="Country"
              sortable
              style="width: 120px"
              class="hidden sm:table-cell"
            >
              <template #body="{ data }">
                <CountryDisplay :country="parseAndEnhanceCountry(data.country)" />
              </template>
            </Column>

            <Column
              field="currency.code"
              header="Currency"
              sortable
              style="width: 100px"
              class="hidden md:table-cell"
            >
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i class="fas fa-coins text-gray-500"></i>
                  <span class="font-medium">
                    {{ data.currency?.code || '-' }}
                  </span>
                </div>
              </template>
            </Column>

            <Column
              field="status"
              header="Status"
              sortable
              style="width: 100px"
              class="hidden sm:table-cell"
            >
              <template #body="{ data }">
                <StatusBadge :is-active="data.is_active" />
              </template>
            </Column>

            <Column
              field="outstanding_balance"
              header="Balance"
              sortable
              style="width: 140px; text-align: right"
              class="hidden md:table-cell"
            >
              <template #body="{ data }">
                <BalanceDisplay
                  :balance="data.outstanding_balance"
                  :currencyCode="data.currency?.code"
                  :riskLevel="data.risk_level"
                  :showRisk="true"
                />
              </template>
            </Column>

            <Column
              field="created_at"
              header="Created"
              sortable
              style="width: 120px"
              class="hidden lg:table-cell"
            >
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i class="fas fa-clock text-gray-400"></i>
                  <div class="text-sm text-gray-600">
                    {{ formatDate(data.created_at) }}
                  </div>
                </div>
              </template>
            </Column>

            <Column
              header="Actions"
              style="width: 200px; text-align: center"
              :exportable="false"
              class="actions-column"
            >
              <template #body="{ data }">
                <div class="flex items-center justify-center gap-2">
                  <!-- Actions -->
                  <button
                    @click="viewCustomer(data)"
                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-full transition-colors"
                    title="View customer"
                  >
                    <i class="fas fa-eye"></i>
                  </button>
                  
                  <button
                    @click="editCustomer(data)"
                    class="p-2 text-green-600 hover:bg-green-50 rounded-full transition-colors"
                    title="Edit customer"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  
                  <button
                    @click="viewStatistics(data)"
                    class="p-2 text-purple-600 hover:bg-purple-50 rounded-full transition-colors"
                    title="View statistics"
                  >
                    <i class="fas fa-chart-line"></i>
                  </button>
                  
                  <button
                    @click="viewInvoices(data)"
                    class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-full transition-colors"
                    title="View invoices"
                  >
                    <i class="fas fa-file-invoice"></i>
                  </button>
                  
                  <button
                    v-if="canDelete(data)"
                    @click="deleteConfirmation.show(data)"
                    class="p-2 text-red-600 hover:bg-red-50 rounded-full transition-colors"
                    title="Delete customer"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                  
                  <button
                    @click="toggleStatus(data)"
                    class="p-2 transition-colors rounded-full"
                    :class="data.is_active ? 'text-yellow-600 hover:bg-yellow-50' : 'text-green-600 hover:bg-green-50'"
                    :title="data.is_active ? 'Disable customer' : 'Enable customer'"
                  >
                    <i :class="data.is_active ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                  </button>
                </div>
              </template>
            </Column>

            <template #empty>
              <div class="text-center py-8">
                <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No customers found</p>
                <Button
                  :label="'Create Customer'"
                  icon="fas fa-plus"
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
      v-model:visible="deleteConfirmation.isVisible.value"
      :header="'Delete Customer'"
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div class="text-gray-600">
          Are you sure you want to delete customer <strong>{{ deleteConfirmation.itemToDelete.value?.name }}</strong>?
        </div>

        <div v-if="deleteConfirmation.itemToDelete.value?.outstanding_balance > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
          <div class="flex items-center gap-2">
            <i class="pi pi-exclamation-triangle text-yellow-600"></i>
            <span class="text-sm text-yellow-800">
              This customer has an outstanding balance of {{ formatMoney(deleteConfirmation.itemToDelete.value.outstanding_balance, deleteConfirmation.itemToDelete.value.currency) }}
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
          @click="deleteConfirmation.hide()"
        />
        <Button
          label="Delete Customer"
          severity="danger"
          :loading="deleteConfirmation.isLoading.value"
          @click="deleteConfirmation.confirm()"
        />
      </template>
    </Dialog>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from 'primevue/button'
import DataTablePro from '@/Components/DataTablePro.vue'
import { FilterMatchMode } from '@primevue/core/api'
import Card from 'primevue/card'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CountryDisplay from '@/Components/CountryDisplay.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import BalanceDisplay from '@/Components/BalanceDisplay.vue'
import CustomerInfoDisplay from '@/Components/CustomerInfoDisplay.vue'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { formatDate, formatMoney } from '@/Utils/formatting'
import { usePageActions } from '@/composables/usePageActions'
import { useDataTable } from '@/composables/useDataTable'
import { useDeleteConfirmation } from '@/composables/useDeleteConfirmation'
import { useToast } from 'primevue/usetoast'

interface Customer {
  id: string
  customer_number: string
  name: string
  email?: string
  phone?: string
  customer_type: string
  status: string
  tax_number?: string
  outstanding_balance: number
  risk_level?: string
  is_active: boolean
  country?: {
    code: string
    name: string
    flag: string
  }
  currency?: {
    code: string
    name: string
    symbol: string
  }
  created_at: string
}

const props = defineProps({
  customers: Object,
  filters: Object,
  countries: Array,
  statusOptions: Array,
  customerTypeOptions: Array,
})

// Columns for DataTablePro
const columns = [
  { field: 'created_at', header: 'Customer Since', filter: { type: 'date', matchMode: FilterMatchMode.DATE_AFTER }, style: 'width: 140px' },
  { field: 'name', header: 'Customer Name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 250px' },
  { field: 'tax_number', header: 'Tax ID', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 140px' },
  { field: 'country.name', header: 'Country', filterField: 'country_name', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 140px' },
  { field: 'currency.code', header: 'Currency', filterField: 'currency_code', filter: { type: 'text', matchMode: FilterMatchMode.CONTAINS }, style: 'width: 100px' },
  { field: 'is_active', header: 'Status', filter: { type: 'select', matchMode: FilterMatchMode.EQUALS, options: [{label:'Active', value:'1'},{label:'Inactive', value:'0'}] }, style: 'width: 120px' },
  { field: 'outstanding_balance', header: 'Balance', filter: { type: 'number', matchMode: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO }, style: 'width: 140px; text-align: right' },
  { field: 'actions', header: 'Actions', filterable: false, sortable: false, style: 'width: 140px; text-align: center' },
]

const table = useDataTable({
  columns: columns,
  initialFilters: props.filters,
  routeName: 'customers.index',
  filterLookups: {
    is_active: {
      options: props.statusOptions || [],
    }
  }
})

const deleteConfirmation = useDeleteConfirmation<Customer>({
  deleteRouteName: 'customers.destroy',
})
const toast = useToast()
const formatCustomerSince = (dateString: string): string => {
  const date = new Date(dateString)
  const month = date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase()
  const year = date.getFullYear().toString().slice(-2)
  return `${month.slice(0, 3)}'${year}`
}
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

const viewInvoices = (customer: Customer) => {
  router.visit(route('customers.invoices', customer.id))
}


const toggleStatus = (customer: Customer) => {
  router.put(route('customers.update', customer.id), {
    ...customer,
    is_active: !customer.is_active
  }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: `Customer ${customer.is_active ? 'disabled' : 'enabled'} successfully`,
        life: 3000
      })
    },
    onError: () => {
      toast.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to update customer status',
        life: 3000
      })
    }
  })
}

// Export functionality
const exportCustomers = () => {
  window.location.href = route('customers.export', table.filterForm.data())
}

const canDelete = (customer: Customer): boolean => {
  return customer.outstanding_balance === 0
}

// Helper function to parse and enhance country data
const parseAndEnhanceCountry = (country: any) => {
  if (!country) return { code: '', name: 'Unknown', flag: '🏳️' }
  return {
    ...country,
    flag: country.flag || `🏳️`
  }
}

// Page Actions rendered in page header
const { setActions, clearActions } = usePageActions()

async function bulkDelete() {
  if (!table.selectedRows.value.length) return
  router.post(route('customers.bulk'), {
    action: 'delete',
    customer_ids: table.selectedRows.value.map((r: Customer) => r.id)
  }, { 
    preserveState: true, 
    preserveScroll: true,
    onSuccess: () => {
      toast.add({ severity: 'success', summary: 'Success', detail: `${table.selectedRows.value.length} customer(s) deleted successfully`, life: 3000 })
      table.selectedRows.value = []
    }
  })
}
async function bulkDisable() {
  if (!table.selectedRows.value.length) return
  router.post(route('customers.bulk'), {
    action: 'disable',
    customer_ids: table.selectedRows.value.map((r: Customer) => r.id)
  }, { 
    preserveState: true, 
    preserveScroll: true,
    onSuccess: () => {
      toast.add({ severity: 'success', summary: 'Success', detail: `${table.selectedRows.value.length} customer(s) disabled successfully`, life: 3000 })
      table.selectedRows.value = []
    }
  })
}
async function bulkEnable() {
  if (!table.selectedRows.value.length) return
  router.post(route('customers.bulk'), {
    action: 'enable',
    customer_ids: table.selectedRows.value.map((r: Customer) => r.id)
  }, { 
    preserveState: true, 
    preserveScroll: true,
    onSuccess: () => {
      toast.add({ severity: 'success', summary: 'Success', detail: `${table.selectedRows.value.length} customer(s) enabled successfully`, life: 3000 })
      table.selectedRows.value = []
    }
  })
}

setActions([
  { key: 'add', label: 'Add New', icon: 'fas fa-plus', severity: 'primary', click: () => router.visit(route('customers.create')) },
  { key: 'delete', label: 'Delete Selected', icon: 'fas fa-trash', severity: 'danger', disabled: () => table.selectedRows.value.length === 0, click: bulkDelete },
  { key: 'disable', label: 'Disable', icon: 'fas fa-ban', severity: 'secondary', disabled: () => table.selectedRows.value.length === 0, click: bulkDisable },
  { key: 'enable', label: 'Enable', icon: 'fas fa-check', severity: 'success', disabled: () => table.selectedRows.value.length === 0, click: bulkEnable },
  { key: 'export', label: 'Export', icon: 'fas fa-download', severity: 'secondary', outlined: true, click: () => exportCustomers() },
  { key: 'refresh', label: 'Refresh', icon: 'fas fa-sync', severity: 'secondary', click: () => table.fetchData() },
])

onUnmounted(() => clearActions())
</script>
