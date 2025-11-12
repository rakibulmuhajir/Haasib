<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { usePageActions } from '@/composables/usePageActions'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ColumnGroup from 'primevue/columngroup'
import Row from 'primevue/row'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import MultiSelect from 'primevue/multiselect'
import Dialog from 'primevue/dialog'
import Menu from 'primevue/menu'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import CommandPalette from '@/Components/CommandPalette.vue'

const { t } = useI18n()
const page = usePage()
const { actions } = usePageActions()

const emit = defineEmits(['invoice-selected'])

// Define page actions for invoices
const invoiceActions = [
    {
        key: 'create-invoice',
        label: 'Create Invoice',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'invoices.create'
    },
    {
        key: 'export-invoices',
        label: 'Export Invoices',
        icon: 'pi pi-download',
        severity: 'secondary',
        action: () => exportInvoices()
    },
    {
        key: 'batch-send',
        label: 'Batch Send',
        icon: 'pi pi-envelope',
        severity: 'secondary'
    }
]

// Define quick links for the invoices page
const quickLinks = [
    {
        label: 'Create Invoice',
        url: '/invoices/create',
        icon: 'pi pi-plus'
    },
    {
        label: 'Invoice Templates',
        url: '/invoices/templates',
        icon: 'pi pi-file'
    },
    {
        label: 'Export Selected',
        url: '#',
        icon: 'pi pi-download',
        action: () => exportInvoices()
    },
    {
        label: 'Invoice Reports',
        url: '/invoices/reports',
        icon: 'pi pi-chart-bar'
    },
    {
        label: 'Recurring Invoices',
        url: '/invoices/recurring',
        icon: 'pi pi-repeat'
    }
]

// Set page actions
actions.value = invoiceActions

// Refs
const menu = ref()
const commandPalette = ref()
const toast = ref()
const loading = ref(false)
const invoices = ref([])
const selectedInvoices = ref([])
const totalRecords = ref(0)
const filters = ref({
    global: { value: null },
    status: { value: null },
    customer: { value: null },
    date_range: { value: null },
    amount_range: { value: null }
})
const lazyParams = ref({
    first: 0,
    rows: 25,
    sortField: null,
    sortOrder: null
})

// Delete dialog
const deleteDialog = ref(false)
const invoiceToDelete = ref(null)

// Status options
const statusOptions = [
    { label: 'Draft', value: 'draft' },
    { label: 'Sent', value: 'sent' },
    { label: 'Paid', value: 'paid' },
    { label: 'Partially Paid', value: 'partially_paid' },
    { label: 'Overdue', value: 'overdue' },
    { label: 'Cancelled', value: 'cancelled' }
]

// Computed properties
const currentCompany = computed(() => page.props.current_company)
const user = computed(() => page.props.auth?.user)
const hasInvoices = computed(() => invoices.value.length > 0)

// Methods
const loadInvoices = async () => {
    loading.value = true
    
    try {
        const params = new URLSearchParams({
            page: Math.floor(lazyParams.value.first / lazyParams.value.rows) + 1,
            per_page: lazyParams.value.rows,
            ...Object.fromEntries(
                Object.entries(filters.value).map(([key, filter]) => [key, filter.value])
            )
        })

        if (lazyParams.value.sortField) {
            params.append('sort_by', lazyParams.value.sortField)
            params.append('sort_order', lazyParams.value.sortOrder === 1 ? 'asc' : 'desc')
        }

        const response = await fetch(`/api/v1/invoices?${params}`)
        const data = await response.json()
        
        if (response.ok) {
            invoices.value = data.data || []
            totalRecords.value = data.total || 0
        } else {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: data.message || 'Failed to load invoices',
                life: 3000
            })
        }
    } catch (error) {
        console.error('Failed to load invoices:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Network Error',
            detail: 'Failed to load invoices',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

const onPage = (event) => {
    lazyParams.value = event
    loadInvoices()
}

const onSort = (event) => {
    lazyParams.value = event
    loadInvoices()
}

const onFilter = () => {
    lazyParams.value.first = 0
    loadInvoices()
}

const clearFilters = () => {
    Object.keys(filters.value).forEach(key => {
        filters.value[key].value = null
    })
    onFilter()
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount)
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const getSeverity = (status) => {
    const statusMap = {
        'draft': 'secondary',
        'sent': 'info',
        'paid': 'success',
        'partially_paid': 'warning',
        'overdue': 'danger',
        'cancelled': 'danger'
    }
    return statusMap[status] || 'info'
}

const selectInvoice = (invoice) => {
    emit('invoice-selected', invoice)
    router.visit(`/invoicing/${invoice.id}`)
}

const createInvoice = () => {
    router.visit('/invoicing/create')
}

const editInvoice = (invoice) => {
    router.visit(`/invoicing/${invoice.id}/edit`)
}

const duplicateInvoice = async (invoice) => {
    try {
        const response = await fetch(`/api/v1/invoices/${invoice.id}/duplicate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Invoice duplicated successfully',
                life: 3000
            })
            loadInvoices()
        } else {
            const data = await response.json()
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: data.message || 'Failed to duplicate invoice',
                life: 3000
            })
        }
    } catch (error) {
        console.error('Failed to duplicate invoice:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Network Error',
            detail: 'Failed to duplicate invoice',
            life: 3000
        })
    }
}

const confirmDelete = (invoice) => {
    invoiceToDelete.value = invoice
    deleteDialog.value = true
}

const deleteInvoice = async () => {
    if (!invoiceToDelete.value) return

    try {
        const response = await fetch(`/api/v1/invoices/${invoiceToDelete.value.id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Invoice deleted successfully',
                life: 3000
            })
            deleteDialog.value = false
            invoiceToDelete.value = null
            loadInvoices()
        } else {
            const data = await response.json()
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: data.message || 'Failed to delete invoice',
                life: 3000
            })
        }
    } catch (error) {
        console.error('Failed to delete invoice:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Network Error',
            detail: 'Failed to delete invoice',
            life: 3000
        })
    }
}

const sendInvoice = async (invoice) => {
    try {
        const response = await fetch(`/api/v1/invoices/${invoice.id}/send`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Invoice sent successfully',
                life: 3000
            })
            loadInvoices()
        } else {
            const data = await response.json()
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: data.message || 'Failed to send invoice',
                life: 3000
            })
        }
    } catch (error) {
        console.error('Failed to send invoice:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Network Error',
            detail: 'Failed to send invoice',
            life: 3000
        })
    }
}

const exportInvoices = async () => {
    try {
        const params = new URLSearchParams({
            format: 'csv',
            ...Object.fromEntries(
                Object.entries(filters.value).map(([key, filter]) => [key, filter.value])
            )
        })

        window.open(`/api/v1/invoices/export?${params}`, '_blank')
        
        toast.value.add({
            severity: 'success',
            summary: 'Export Started',
            detail: 'Invoice export is being prepared',
            life: 3000
        })
    } catch (error) {
        console.error('Failed to export invoices:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Export Error',
            detail: 'Failed to export invoices',
            life: 3000
        })
    }
}

const getRowActions = (invoice) => {
    const items = [
        {
            label: 'View',
            icon: 'fas fa-eye',
            command: () => selectInvoice(invoice)
        },
        {
            label: 'Edit',
            icon: 'fas fa-edit',
            command: () => editInvoice(invoice),
            disabled: invoice.status === 'paid' || invoice.status === 'cancelled'
        },
        {
            label: 'Duplicate',
            icon: 'fas fa-copy',
            command: () => duplicateInvoice(invoice)
        },
        {
            label: 'Send',
            icon: 'fas fa-paper-plane',
            command: () => sendInvoice(invoice),
            disabled: invoice.status === 'draft'
        },
        { separator: true },
        {
            label: 'Delete',
            icon: 'fas fa-trash',
            command: () => confirmDelete(invoice),
            disabled: invoice.status === 'paid',
            class: 'text-red-600 dark:text-red-400'
        }
    ]

    return items
}

// Lifecycle
onMounted(() => {
    loadInvoices()
})
</script>

<template>
  <LayoutShell>
    <Toast ref="toast" />
    <CommandPalette ref="commandPalette" />
    
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Invoices"
      description="Create, manage, and track your invoices"
      subDescription="Send professional invoices and track payments"
      :show-search="true"
      search-placeholder="Search invoices..."
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <!-- Left Column - Main Content -->
      <div class="main-content">
        <!-- Filters Card -->
        <Card class="mb-6 filters-card">
          <template #title>
            <div class="flex justify-between items-center">
              <span>Filters</span>
              <Button 
                @click="clearFilters"
                icon="pi pi-filter-slash"
                label="Clear"
                text
                size="small"
              />
            </div>
          </template>
          <template #content>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Search
                </label>
                <InputText 
                  v-model="filters.global.value"
                  placeholder="Search invoices..."
                  class="w-full"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Status
                </label>
                <Dropdown 
                  v-model="filters.status.value"
                  :options="statusOptions"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="All Statuses"
                  class="w-full"
                  showClear
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Customer
                </label>
                <InputText 
                  v-model="filters.customer.value"
                  placeholder="Customer name..."
                  class="w-full"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Date Range
                </label>
                <Calendar 
                  v-model="filters.date_range.value"
                  selectionMode="range"
                  placeholder="Select dates..."
                  class="w-full"
                  showButtonBar
                />
              </div>
            </div>
            <div class="mt-4 flex justify-end">
              <Button 
                @click="onFilter"
                icon="pi pi-filter"
                label="Apply Filters"
              />
            </div>
          </template>
        </Card>

        <!-- Invoices Table -->
        <Card>
          <template #title>
            <div class="flex justify-between items-center">
              <span>{{ t('invoicing.invoice_list') }}</span>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ totalRecords }} total invoices
              </div>
            </div>
          </template>
          <template #content>
            <!-- Loading State -->
            <div v-if="loading && !hasInvoices" class="flex justify-center py-12">
              <ProgressSpinner />
            </div>

            <!-- Empty State -->
            <div v-else-if="!hasInvoices && !loading" class="text-center py-12">
              <i class="fas fa-file-invoice text-4xl text-gray-400 mb-4"></i>
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                No invoices found
              </h3>
              <p class="text-gray-600 dark:text-gray-400 mb-4">
                Get started by creating your first invoice
              </p>
              <Button 
                @click="createInvoice"
                icon="fas fa-plus"
                :label="t('invoicing.create_invoice')"
              />
            </div>

            <!-- Data Table -->
            <DataTable 
              v-else
              :value="invoices"
              :paginator="true"
              :rows="25"
              :totalRecords="totalRecords"
              :lazy="true"
              :loading="loading"
              @page="onPage"
              @sort="onSort"
              @filter="onFilter"
              v-model:selection="selectedInvoices"
              :filters="filters"
              filterDisplay="menu"
              :globalFilterFields="['invoice_number', 'customer.name', 'amount']"
              sortMode="single"
              dataKey="id"
              :rowsPerPageOptions="[10, 25, 50]"
              currentPageReportTemplate="Showing {first} to {last} of {totalRecords} invoices"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
              responsiveLayout="scroll"
            >
              <template #header>
                <div class="flex justify-between items-center">
                  <span class="text-xl text-gray-900 dark:text-white">
                    Invoice Management
                  </span>
                  <div class="flex space-x-2">
                    <Button 
                      v-if="selectedInvoices.length > 0"
                      @click="exportInvoices"
                      icon="fas fa-download"
                      label="Export Selected"
                      size="small"
                    />
                  </div>
                </div>
              </template>

              <!-- Selection Column -->
              <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

              <!-- Invoice Number -->
              <Column field="invoice_number" header="Invoice #" sortable>
                <template #body="{ data }">
                  <div class="font-medium text-blue-600 dark:text-blue-400 cursor-pointer hover:underline">
                    {{ data.invoice_number }}
                  </div>
                </template>
              </Column>

              <!-- Customer -->
              <Column field="customer.name" header="Customer" sortable>
                <template #body="{ data }">
                  <div class="text-gray-900 dark:text-white">
                    {{ data.customer?.name || 'N/A' }}
                  </div>
                </template>
              </Column>

              <!-- Date -->
              <Column field="invoice_date" header="Date" sortable>
                <template #body="{ data }">
                  <div class="text-gray-600 dark:text-gray-400">
                    {{ formatDate(data.invoice_date) }}
                  </div>
                </template>
              </Column>

              <!-- Due Date -->
              <Column field="due_date" header="Due Date" sortable>
                <template #body="{ data }">
                  <div class="text-gray-600 dark:text-gray-400">
                    {{ formatDate(data.due_date) }}
                  </div>
                </template>
              </Column>

              <!-- Amount -->
              <Column field="amount" header="Amount" sortable>
                <template #body="{ data }">
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ formatCurrency(data.amount) }}
                  </div>
                </template>
              </Column>

              <!-- Status -->
              <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                  <Tag :value="data.status" :severity="getSeverity(data.status)" />
                </template>
              </Column>

              <!-- Actions -->
              <Column header="Actions" style="width: 120px">
                <template #body="{ data }">
                  <div class="flex space-x-2">
                    <Button 
                      @click="selectInvoice(data)"
                      icon="fas fa-eye"
                      text
                      size="small"
                      v-tooltip="'View Invoice'"
                    />
                    <Button 
                      @click="$event => menu.toggle($event)"
                      icon="fas fa-ellipsis-v"
                      text
                      size="small"
                      v-tooltip="'More Actions'"
                    />
                    <Menu ref="menu" :model="getRowActions(data)" popup />
                  </div>
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>
      </div>

      <!-- Right Column - Sidebar -->
      <div class="sidebar-content">
        <QuickLinks :links="quickLinks" />
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog 
      v-model:visible="deleteDialog" 
      modal 
      header="Confirm Delete"
      :style="{ width: '450px' }"
    >
      <div class="text-center">
        <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-4"></i>
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
          Delete Invoice
        </h4>
        <p class="text-gray-600 dark:text-gray-400">
          Are you sure you want to delete invoice {{ invoiceToDelete?.invoice_number }}? 
          This action cannot be undone.
        </p>
      </div>

      <template #footer>
        <Button 
          @click="deleteDialog = false"
          :label="$t('common.cancel')"
          text
        />
        <Button 
          @click="deleteInvoice"
          label="Delete"
          severity="danger"
          :loading="loading"
        />
      </template>
    </Dialog>
  </LayoutShell>
</template>

