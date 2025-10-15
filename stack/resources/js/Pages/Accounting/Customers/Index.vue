<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
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

const dt = ref()
const deleteCustomerDialog = ref(false)
const customerToDelete = ref(null)
const menuRef = ref()
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
    <div>
        <Toast />
        
        <div class="card">
            <Toolbar class="mb-4">
                <template #start>
                    <div class="flex gap-2 items-center">
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="searchQuery"
                                placeholder="Search customers..."
                                @keyup.enter="applyFilters"
                                class="w-64"
                            />
                        </IconField>
                        
                        <Dropdown
                            v-model="statusFilter"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Filter by status"
                            class="w-40"
                            @change="applyFilters"
                        />
                        
                        <Button
                            label="Apply"
                            icon="pi pi-filter"
                            @click="applyFilters"
                            :loading="loading"
                        />
                        
                        <Button
                            label="Clear"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            @click="clearFilters"
                            :disabled="!searchQuery && !statusFilter"
                        />
                    </div>
                </template>

                <template #end>
                    <div class="flex gap-2">
                        <Button
                            v-if="can.create"
                            label="Add Customer"
                            icon="pi pi-plus"
                            @click="router.get(route('customers.create'))"
                        />
                        
                        <Button
                            v-if="can.export"
                            label="Export"
                            icon="pi pi-download"
                            severity="secondary"
                            @click="exportCustomers"
                        />
                    </div>
                </template>
            </Toolbar>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="text-blue-600 text-sm font-medium">Total Customers</div>
                    <div class="text-2xl font-bold text-blue-800">{{ statistics.total_customers }}</div>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <div class="text-green-600 text-sm font-medium">Active</div>
                    <div class="text-2xl font-bold text-green-800">{{ statistics.active_customers }}</div>
                </div>
                
                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                    <div class="text-yellow-600 text-sm font-medium">Inactive</div>
                    <div class="text-2xl font-bold text-yellow-800">{{ statistics.inactive_customers }}</div>
                </div>
                
                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                    <div class="text-red-600 text-sm font-medium">Blocked</div>
                    <div class="text-2xl font-bold text-red-800">{{ statistics.blocked_customers }}</div>
                </div>
            </div>

            <DataTable
                ref="dt"
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
                class="p-datatable-sm"
            >
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
    </div>
</template>

<style scoped>
.confirmation-content {
    display: flex;
    align-items: center;
}
</style>