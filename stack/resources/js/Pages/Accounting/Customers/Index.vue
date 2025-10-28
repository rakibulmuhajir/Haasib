<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import { usePageActions } from '@/composables/usePageActions'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Toolbar from 'primevue/toolbar'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Toast from 'primevue/toast'

const props = defineProps({
    customers: Object,
    filters: Object,
    statistics: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()
const { actions } = usePageActions()

// Define page actions for customers
const customerActions = [
    {
        key: 'add-customer',
        label: 'Add Customer',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'customers.create'
    },
    {
        key: 'import-customers',
        label: 'Import Customers',
        icon: 'pi pi-upload',
        severity: 'secondary'
    },
    {
        key: 'export-customers',
        label: 'Export Customers',
        icon: 'pi pi-download',
        severity: 'secondary',
        routeName: 'customers.export'
    }
]

// Define quick links for the customers page
const quickLinks = [
    {
        label: 'Add Customer',
        url: '/customers/create',
        icon: 'pi pi-plus'
    },
    {
        label: 'Customer Statements',
        url: '/customers/statements',
        icon: 'pi pi-file-text'
    },
    {
        label: 'Export Data',
        url: '/customers/export',
        icon: 'pi pi-download'
    },
    {
        label: 'Customer Reports',
        url: '/customers/reports',
        icon: 'pi pi-chart-bar'
    },
    {
        label: 'Bulk Import',
        url: '#',
        icon: 'pi pi-upload',
        action: () => toast.add({
            severity: 'info',
            summary: 'Coming Soon',
            detail: 'Bulk import will be available soon',
            life: 3000
        })
    }
]

// Bulk actions configuration
const bulkActionsConfig = [
    {
        key: 'bulk-enable',
        label: 'Enable ({count})',
        icon: 'pi pi-check',
        severity: 'success'
    },
    {
        key: 'bulk-disable', 
        label: 'Disable ({count})',
        icon: 'pi pi-times',
        severity: 'warning'
    },
    {
        key: 'bulk-delete',
        label: 'Delete ({count})',
        icon: 'pi pi-trash',
        severity: 'danger'
    }
]

// Set page actions
actions.value = customerActions

const dt = ref()
const deleteCustomerDialog = ref(false)
const customerToDelete = ref(null)
const menuRef = ref()

// Selection state
const selectedCustomers = ref([])
const bulkActionDialog = ref(false)
const bulkActionType = ref('')

const actionsMenu = ref([
    {
        label: 'Edit',
        icon: 'pi pi-pencil',
        command: (customer) => router.get(route('customers.edit', customer.id))
    },
    {
        label: 'View Details',
        icon: 'pi pi-eye',
        command: (customer) => router.get(route('customers.show', customer.id))
    },
    {
        label: 'Change Status',
        icon: 'pi pi-refresh',
        command: (customer) => changeStatus(customer)
    },
    {
        separator: true
    },
    {
        label: 'Delete',
        icon: 'pi pi-trash',
        command: (customer) => confirmDelete(customer)
    }
])

// Search and filters
const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const loading = ref(false)

const statusOptions = [
    { label: 'All', value: '' },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Blocked', value: 'blocked' }
]

// Computed properties
const filteredCustomers = computed(() => props.customers)

// Methods
const applyFilters = () => {
    loading.value = true
    
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('search', searchQuery.value)
    if (statusFilter.value) params.append('status', statusFilter.value)
    
    router.get(
        route('customers.index') + (params.toString() ? '?' + params.toString() : ''),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => { loading.value = false }
        }
    )
}

const clearFilters = () => {
    searchQuery.value = ''
    statusFilter.value = ''
    applyFilters()
}

const confirmDelete = (customer) => {
    customerToDelete.value = customer
    deleteCustomerDialog.value = true
}

const deleteCustomer = () => {
    if (!customerToDelete.value) return
    
    router.delete(route('customers.destroy', customerToDelete.value.id), {
        onSuccess: () => {
            deleteCustomerDialog.value = false
            customerToDelete.value = null
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Customer deleted successfully',
                life: 3000
            })
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.error || 'Failed to delete customer',
                life: 3000
            })
        }
    })
}

const changeStatus = (customer) => {
    // This would open a status change dialog
    // For now, just show a toast
    toast.add({
        severity: 'info',
        summary: 'Info',
        detail: 'Status change feature coming soon',
        life: 3000
    })
}

const exportCustomers = () => {
    window.location.href = route('customers.export')
}

// Bulk action methods
const performBulkAction = (action) => {
    if (selectedCustomers.value.length === 0) return
    
    bulkActionType.value = action
    
    if (action === 'delete') {
        // Show confirmation dialog for delete
        bulkActionDialog.value = true
    } else {
        // Directly execute enable/disable actions
        executeBulkAction(action)
    }
}

const executeBulkAction = async (action) => {
    if (selectedCustomers.value.length === 0) return
    
    loading.value = true
    
    const customerIds = selectedCustomers.value.map(customer => customer.id)
    
    try {
        const response = await fetch(route('customers.bulk-update'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                customer_ids: customerIds,
                action: action
            })
        })
        
        if (response.ok) {
            const actionText = action.charAt(0).toUpperCase() + action.slice(1)
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `${customerIds.length} customers ${actionText}d successfully`,
                life: 3000
            })
            
            // Clear selection and refresh data
            clearSelection()
            applyFilters()
        } else {
            throw new Error('Failed to perform bulk action')
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to ${action} selected customers`,
            life: 3000
        })
    } finally {
        loading.value = false
        bulkActionDialog.value = false
        bulkActionType.value = ''
    }
}

const confirmBulkDelete = () => {
    executeBulkAction('delete')
}

const clearSelection = () => {
    selectedCustomers.value = []
    if (dt.value) {
        dt.value.clearSelection()
    }
}

// Universal page header handlers
const handleHeaderSearch = (searchData) => {
    searchQuery.value = searchData.query || ''
    statusFilter.value = searchData.status || ''
    applyFilters()
}

const handleFilterChange = (filterData) => {
    // Status filter is already handled in handleHeaderSearch
    // This can be used for additional filter logic if needed
}

const handleFiltersCleared = () => {
    searchQuery.value = ''
    statusFilter.value = ''
    applyFilters()
}

const handleBulkAction = (action) => {
    performBulkAction(action.key)
}

const handleSelectionCleared = () => {
    clearSelection()
}

const getBulkActionTitle = () => {
    const actionText = bulkActionType.value.charAt(0).toUpperCase() + bulkActionType.value.slice(1)
    return `Confirm ${actionText}`
}

const getBulkActionIcon = () => {
    switch (bulkActionType.value) {
        case 'delete': return 'pi pi-exclamation-triangle text-red-500'
        case 'enable': return 'pi pi-check text-green-500'
        case 'disable': return 'pi pi-times text-yellow-500'
        default: return 'pi pi-question-circle'
    }
}

const getSeverity = (status) => {
    switch (status) {
        case 'active': return 'success'
        case 'inactive': return 'warning'
        case 'blocked': return 'danger'
        default: return 'info'
    }
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount || 0)
}

const toggleActionsMenu = (event, customer) => {
    menuRef.value.toggle(event)
    menuRef.value.customer = customer
}
</script>

<template>
  <LayoutShell>
    <Toast />
    
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Customers"
      description="Manage your customer relationships and information"
      subDescription="Create, edit, and manage customer accounts"
      :default-actions="customerActions"
      :bulk-actions="bulkActionsConfig"
      :selected-items="selectedCustomers"
      :loading="loading"
      :status-options="statusOptions"
      search-placeholder="Search customers..."
      @search="handleHeaderSearch"
      @filter-changed="handleFilterChange"
      @filters-cleared="handleFiltersCleared"
      @bulk-action="handleBulkAction"
      @selection-cleared="handleSelectionCleared"
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <!-- Left Column - Main Content -->
      <div class="main-content">
        <!-- Statistics Cards -->
        <div class="stats-grid-4">
          <div class="stat-card bg-blue-50 border-blue-200">
            <div class="stat-label text-blue-600">Total Customers</div>
            <div class="stat-value text-blue-800">{{ statistics.total_customers }}</div>
          </div>
          
          <div class="stat-card bg-green-50 border-green-200">
            <div class="stat-label text-green-600">Active</div>
            <div class="stat-value text-green-800">{{ statistics.active_customers }}</div>
          </div>
          
          <div class="stat-card bg-yellow-50 border-yellow-200">
            <div class="stat-label text-yellow-600">Inactive</div>
            <div class="stat-value text-yellow-800">{{ statistics.inactive_customers }}</div>
          </div>
          
          <div class="stat-card bg-red-50 border-red-200">
            <div class="stat-label text-red-600">Blocked</div>
            <div class="stat-value text-red-800">{{ statistics.blocked_customers }}</div>
          </div>
        </div>

    
        <!-- Data Table -->
        <DataTable
          ref="dt"
          v-model:selection="selectedCustomers"
          :value="filteredCustomers.data"
          :paginator="true"
          :rows="filteredCustomers.per_page"
          :totalRecords="filteredCustomers.total"
          :lazy="true"
          @page="applyFilters"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          :rowsPerPageOptions="[10, 25, 50]"
          currentPageReportTemplate="Showing {first} to {last} of {totalRecords} customers"
          responsiveLayout="scroll"
          dataKey="id"
          class="customers-table"
        >
          <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
          <Column field="customer_number" header="Customer #" sortable style="min-width: 8rem">
            <template #body="{ data }">
              <span class="font-mono text-sm">{{ data.customer_number }}</span>
            </template>
          </Column>
          
          <Column field="name" header="Name" sortable style="min-width: 12rem">
            <template #body="{ data }">
              <div>
                <div class="font-medium">{{ data.name }}</div>
                <div v-if="data.legal_name && data.legal_name !== data.name" 
                     class="text-sm text-gray-500">{{ data.legal_name }}</div>
              </div>
            </template>
          </Column>
          
          <Column field="email" header="Email" style="min-width: 12rem">
            <template #body="{ data }">
              <span v-if="data.email">{{ data.email }}</span>
              <span v-else class="text-gray-400 italic">No email</span>
            </template>
          </Column>
          
          <Column field="status" header="Status" sortable style="min-width: 6rem">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="getSeverity(data.status)" />
            </template>
          </Column>
          
          <Column field="default_currency" header="Currency" sortable style="min-width: 4rem">
            <template #body="{ data }">
              <span class="font-mono">{{ data.default_currency }}</span>
            </template>
          </Column>
          
          <Column field="credit_limit" header="Credit Limit" sortable style="min-width: 8rem">
            <template #body="{ data }">
              <span v-if="data.credit_limit">
                {{ formatCurrency(data.credit_limit, data.default_currency) }}
              </span>
              <span v-else class="text-gray-400 italic">No limit</span>
            </template>
          </Column>
          
          <Column field="created_at" header="Created" sortable style="min-width: 6rem">
            <template #body="{ data }">
              {{ new Date(data.created_at).toLocaleDateString() }}
            </template>
          </Column>
          
          <Column header="Actions" style="min-width: 4rem">
            <template #body="{ data }">
              <Button
                type="button"
                icon="pi pi-ellipsis-v"
                size="small"
                text
                @click="toggleActionsMenu($event, data)"
              />
              <Menu ref="menuRef" :model="actionsMenu" popup />
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Right Column - Quick Links -->
      <div class="sidebar-content">
        <QuickLinks 
          :links="quickLinks" 
          title="Customer Actions"
        />
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog
      v-model:visible="deleteCustomerDialog"
      :style="{ width: '450px' }"
      header="Confirm Delete"
      :modal="true"
    >
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
        <span v-if="customerToDelete">
          Are you sure you want to delete <strong>{{ customerToDelete.name }}</strong>?
          This action cannot be undone.
        </span>
      </div>
      <template #footer>
        <Button label="Cancel" icon="pi pi-times" text @click="deleteCustomerDialog = false" />
        <Button
          label="Delete"
          icon="pi pi-check"
          severity="danger"
          @click="deleteCustomer"
        />
      </template>
    </Dialog>

    <!-- Bulk Action Confirmation Dialog -->
    <Dialog
      v-model:visible="bulkActionDialog"
      :style="{ width: '450px' }"
      :header="getBulkActionTitle()"
      :modal="true"
    >
      <div class="confirmation-content">
        <i :class="getBulkActionIcon()" class="mr-3" style="font-size: 2rem" />
        <span>
          Are you sure you want to {{ bulkActionType }} <strong>{{ selectedCustomers.length }}</strong> 
          customer{{ selectedCustomers.length > 1 ? 's' : '' }}? 
          <span v-if="bulkActionType === 'delete'">This action cannot be undone.</span>
        </span>
      </div>
      <template #footer>
        <Button 
          label="Cancel" 
          icon="pi pi-times" 
          text 
          @click="bulkActionDialog = false" 
        />
        <Button
          :label="`Confirm ${bulkActionType}`"
          icon="pi pi-check"
          :severity="bulkActionType === 'delete' ? 'danger' : 'primary'"
          @click="confirmBulkDelete"
          :loading="loading"
        />
      </template>
    </Dialog>
  </LayoutShell>
</template>

