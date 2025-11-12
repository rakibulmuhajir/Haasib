<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { usePageActions } from '@/composables/usePageActions'
import { useBulkSelection } from '@/composables/useBulkSelection'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Toast from 'primevue/toast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

// Initialize dynamic page actions
const { setActions, clearActions } = usePageActions()

// Initialize bulk selection
const {
    selectedItems,
    selectedCount,
    hasSelection,
    clearSelection,
    updateItems: updateBulkSelection,
    toggleItemSelection,
    isItemSelected
} = useBulkSelection([], 'purchaseOrders')

const page = usePage()
const toast = ref()

// Reactive data
const loading = ref(false)
const purchaseOrders = ref([])
const filters = ref({
    search: '',
    status: null,
    vendor_id: null,
    date_from: null,
    date_to: null,
})

// Computed properties
const user = computed(() => page.props.auth?.user)
const currentCompany = computed(() => page.props.currentCompany)

// Filter and search functionality
const filteredPurchaseOrders = computed(() => {
    return purchaseOrders.value
})

// Options for filters
const statusOptions = [
    { label: 'All Status', value: null },
    { label: 'Draft', value: 'draft' },
    { label: 'Pending Approval', value: 'pending_approval' },
    { label: 'Approved', value: 'approved' },
    { label: 'Sent', value: 'sent' },
    { label: 'Partially Received', value: 'partial_received' },
    { label: 'Received', value: 'received' },
    { label: 'Closed', value: 'closed' },
    { label: 'Cancelled', value: 'cancelled' }
]

const vendorOptions = computed(() => {
    const options = [{ label: 'All Vendors', value: null }]
    if (page.props.vendors) {
        page.props.vendors.forEach(vendor => {
            options.push({
                label: vendor.display_name || vendor.legal_name,
                value: vendor.id
            })
        })
    }
    return options
})

// Bulk actions
const handleBulkAction = (action) => {
    switch (action) {
        case 'approve':
            bulkApprovePurchaseOrders()
            break
        case 'cancel':
            bulkCancelPurchaseOrders()
            break
        case 'export':
            exportPurchaseOrders()
            break
        case 'clear-selection':
            clearSelection()
            break
        default:
            // Silently handle unknown actions
    }
}

const bulkApprovePurchaseOrders = async () => {
    const pendingOrders = selectedItems.value.filter(po => po.status === 'pending_approval')
    if (pendingOrders.length === 0) {
        toast.value.add({
            severity: 'warn',
            summary: 'No Approvable Orders',
            detail: 'Only orders in "Pending Approval" status can be approved.',
            life: 3000
        })
        return
    }

    if (!confirm(`Are you sure you want to approve ${pendingOrders.length} purchase orders?`)) {
        return
    }

    try {
        const promises = pendingOrders.map(po => 
            router.post(`/purchase-orders/${po.id}/approve`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.value.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'Purchase orders approved successfully',
                        life: 3000
                    })
                },
                onError: (error) => {
                    toast.value.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: error.message || 'Failed to approve purchase orders',
                        life: 5000
                    })
                }
            })
        )

        await Promise.all(promises)
        clearSelection()
        loadPurchaseOrders()
    } catch (error) {
        console.error('Failed to bulk approve purchase orders:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to approve purchase orders. Please try again.',
            life: 5000
        })
    }
}

const bulkCancelPurchaseOrders = async () => {
    const cancellableOrders = selectedItems.value.filter(po => 
        ['draft', 'pending_approval', 'approved', 'sent'].includes(po.status)
    )
    if (cancellableOrders.length === 0) {
        toast.value.add({
            severity: 'warn',
            summary: 'No Cancellable Orders',
            detail: 'Only orders in Draft, Pending Approval, Approved, or Sent status can be cancelled.',
            life: 3000
        })
        return
    }

    if (!confirm(`Are you sure you want to cancel ${cancellableOrders.length} purchase orders? This action cannot be undone.`)) {
        return
    }

    try {
        const promises = cancellableOrders.map(po => 
            router.delete(`/purchase-orders/${po.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.value.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'Purchase orders cancelled successfully',
                        life: 3000
                    })
                },
                onError: (error) => {
                    toast.value.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: error.message || 'Failed to cancel purchase orders',
                        life: 5000
                    })
                }
            })
        )

        await Promise.all(promises)
        clearSelection()
        loadPurchaseOrders()
    } catch (error) {
        console.error('Failed to bulk cancel purchase orders:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to cancel purchase orders. Please try again.',
            life: 5000
        })
    }
}

const exportPurchaseOrders = () => {
    const params = new URLSearchParams({
        ...filters.value,
        export: 'true'
    })

    window.open(`/purchase-orders/export?${params.toString()}`, '_blank')

    toast.value.add({
        severity: 'success',
        summary: 'Export Started',
        detail: 'Purchase orders export is being prepared',
        life: 3000
    })
}

const loadPurchaseOrders = () => {
    purchaseOrders.value = page.props.purchaseOrders.data || []
    updateBulkSelection(purchaseOrders.value)
}

const clearFilters = () => {
    filters.value = {
        search: '',
        status: null,
        vendor_id: null,
        date_from: null,
        date_to: null,
    }
}

const clearSearch = () => {
    filters.value.search = ''
}

const applyFilters = () => {
    router.get('/purchase-orders', filters.value, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Watch for filter changes
watch(filters, () => {
    applyFilters()
}, { deep: true })

// Helper functions
const getStatusSeverity = (status) => {
    const severities = {
        'draft': 'secondary',
        'pending_approval': 'warning',
        'approved': 'info',
        'sent': 'primary',
        'partial_received': 'warning',
        'received': 'success',
        'closed': 'info',
        'cancelled': 'danger'
    }
    return severities[status] || 'info'
}

const getStatusColor = (status) => {
    const colors = {
        'draft': 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-900/30 dark:text-gray-400 dark:border-gray-800',
        'pending_approval': 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-800',
        'approved': 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800',
        'sent': 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800',
        'partial_received': 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-800',
        'received': 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
        'closed': 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-900/30 dark:text-teal-400 dark:border-teal-800',
        'cancelled': 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800'
    }
    return colors[status] || colors['draft']
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount)
}

const approvePurchaseOrder = (po) => {
    if (!confirm(`Are you sure you want to approve ${po.po_number}?`)) {
        return
    }

    router.post(`/purchase-orders/${po.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Purchase Order approved successfully',
                life: 3000
            })
            loadPurchaseOrders()
        },
        onError: (error) => {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: error.message || 'Failed to approve purchase order',
                life: 5000
            })
        }
    })
}

const sendPurchaseOrder = (po) => {
    if (!confirm(`Are you sure you want to send ${po.po_number} to the vendor?`)) {
        return
    }

    router.post(`/purchase-orders/${po.id}/send`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Purchase Order sent to vendor successfully',
                life: 3000
            })
            loadPurchaseOrders()
        },
        onError: (error) => {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: error.message || 'Failed to send purchase order',
                life: 5000
            })
        }
    })
}

const cancelPurchaseOrder = (po) => {
    if (!confirm(`Are you sure you want to cancel ${po.po_number}? This action cannot be undone.`)) {
        return
    }

    router.delete(`/purchase-orders/${po.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Purchase Order cancelled successfully',
                life: 3000
            })
            loadPurchaseOrders()
        },
        onError: (error) => {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: error.message || 'Failed to cancel purchase order',
                life: 5000
            })
        }
    })
}

// Contextual quick links
const quickLinks = computed(() => {
    const links = []

    // Create Purchase Order
    links.push({
        label: 'Create Purchase Order',
        url: '/purchase-orders/create',
        icon: 'pi pi-plus'
    })

    // Export Purchase Orders
    links.push({
        label: 'Export Purchase Orders',
        url: '#',
        icon: 'pi pi-download',
        action: exportPurchaseOrders
    })

    return links
})

// Setup dynamic page actions
const setupPageActions = () => {
    const actions = [
        {
            key: 'create-po',
            label: 'New Purchase Order',
            icon: 'fas fa-plus',
            severity: 'primary',
            click: () => router.visit('/purchase-orders/create')
        },
        {
            key: 'export-pos',
            label: 'Export',
            icon: 'fas fa-download',
            severity: 'secondary',
            click: exportPurchaseOrders
        }
    ]

    setActions(actions)
}

// Lifecycle
onMounted(() => {
    loadPurchaseOrders()
    setupPageActions()
})

onUnmounted(() => {
    clearActions()
})

// Update bulk selection when purchase orders change
watch(purchaseOrders, (newPurchaseOrders) => {
    if (newPurchaseOrders.length > 0) {
        updateBulkSelection(newPurchaseOrders)
    }
})
</script>

<template>
    <LayoutShell>
        <Toast ref="toast" />

        <!-- Universal Page Header -->
        <UniversalPageHeader
            title="Purchase Orders"
            description="Manage your purchase requisitions and orders"
            subDescription="Create, approve, and track purchase orders with vendors"
            :show-search="true"
            search-placeholder="Search purchase orders..."
        />

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Search -->
                <div class="relative">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="filters.search"
                            placeholder="Search PO numbers..."
                            class="w-full"
                            @input="applyFilters"
                        />
                    </IconField>
                    <button
                        v-if="filters.search"
                        @click="clearSearch"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Status Filter -->
                <Dropdown
                    v-model="filters.status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="All Status"
                    class="w-full"
                    @change="applyFilters"
                />

                <!-- Vendor Filter -->
                <Dropdown
                    v-model="filters.vendor_id"
                    :options="vendorOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="All Vendors"
                    class="w-full"
                    @change="applyFilters"
                />

                <!-- Date From -->
                <Calendar
                    v-model="filters.date_from"
                    placeholder="From Date"
                    dateFormat="yy-mm-dd"
                    class="w-full"
                    @change="applyFilters"
                />

                <!-- Date To -->
                <Calendar
                    v-model="filters.date_to"
                    placeholder="To Date"
                    dateFormat="yy-mm-dd"
                    class="w-full"
                    @change="applyFilters"
                />
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid-5-6">
            <!-- Left Column - Main Content -->
            <div class="main-content bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <!-- Purchase Orders Table -->
                <DataTable
                    :value="filteredPurchaseOrders"
                    v-model:selection="selectedItems"
                    :paginator="true"
                    :rows="25"
                    dataKey="id"
                    :loading="loading"
                    :globalFilterFields="['po_number', 'notes']"
                    responsiveLayout="scroll"
                    :row-hover="true"
                >
                    <!-- Selection Column -->
                    <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

                    <!-- PO Number Column -->
                    <Column field="po_number" header="PO Number" sortable style="min-width: 140px">
                        <template #body="{ data }">
                            <Link
                                :href="`/purchase-orders/${data.id}`"
                                class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                            >
                                {{ data.po_number }}
                            </Link>
                        </template>
                    </Column>

                    <!-- Vendor Column -->
                    <Column field="vendor.legal_name" header="Vendor" sortable style="min-width: 200px">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ data.vendor?.display_name || data.vendor?.legal_name }}
                                </div>
                                <div v-if="data.vendor && data.vendor.legal_name !== data.vendor.display_name" class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ data.vendor.legal_name }}
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Order Date Column -->
                    <Column field="order_date" header="Order Date" sortable style="min-width: 120px">
                        <template #body="{ data }">
                            <div class="text-sm">
                                {{ formatDate(data.order_date) }}
                            </div>
                        </template>
                    </Column>

                    <!-- Status Column -->
                    <Column field="status" header="Status" sortable style="min-width: 140px">
                        <template #body="{ data }">
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
                                    getStatusColor(data.status)
                                ]"
                            >
                                {{ data.status.replace('_', ' ').charAt(0).toUpperCase() + data.status.replace('_', ' ').slice(1) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Amount Column -->
                    <Column field="total_amount" header="Total Amount" sortable style="min-width: 120px">
                        <template #body="{ data }">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ formatCurrency(data.total_amount, data.currency) }}
                            </div>
                        </template>
                    </Column>

                    <!-- Actions Column -->
                    <Column header="Actions" style="min-width: 200px">
                        <template #body="{ data }">
                            <div class="flex items-center space-x-2">
                                <Link
                                    :href="`/purchase-orders/${data.id}`"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                    title="View"
                                >
                                    <i class="fas fa-eye"></i>
                                </Link>
                                
                                <!-- Edit button for editable POs -->
                                <Link
                                    v-if="data.canBeEdited"
                                    :href="`/purchase-orders/${data.id}/edit`"
                                    class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"
                                    title="Edit"
                                >
                                    <i class="fas fa-edit"></i>
                                </Link>
                                
                                <!-- Approve button -->
                                <button
                                    v-if="data.canBeApproved"
                                    @click="approvePurchaseOrder(data)"
                                    class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300"
                                    title="Approve"
                                >
                                    <i class="fas fa-check"></i>
                                </button>
                                
                                <!-- Send button -->
                                <button
                                    v-if="data.canBeSent"
                                    @click="sendPurchaseOrder(data)"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                    title="Send to Vendor"
                                >
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                
                                <!-- Cancel button -->
                                <button
                                    v-if="data.canBeCancelled"
                                    @click="cancelPurchaseOrder(data)"
                                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                    title="Cancel"
                                >
                                    <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- Empty State -->
                <div v-if="filteredPurchaseOrders.length === 0 && !loading" class="text-center py-16">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full mb-6">
                        <i class="fas fa-file-invoice text-2xl text-gray-400 dark:text-gray-500"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                        {{ filters.search || filters.status || filters.vendor_id ? 'No purchase orders found' : 'No purchase orders yet' }}
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
                        {{ filters.search || filters.status || filters.vendor_id
                            ? 'Try adjusting your search terms or filters to find what you\'re looking for.'
                            : 'Get started by creating your first purchase order using the actions above.'
                        }}
                    </p>
                    <div v-if="filters.search || filters.status || filters.vendor_id" class="flex justify-center">
                        <button
                            @click="clearFilters"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column - Quick Links -->
            <div class="sidebar-content">
                <QuickLinks
                    :links="quickLinks"
                    title="Purchase Order Actions"
                />
            </div>
        </div>

        <!-- Floating Selection Bar -->
        <Transition
            name="bulk-actions-float"
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-300 ease-in"
            enter-from-class="opacity-0 transform translate-y-4"
            enter-to-class="opacity-100 transform translate-y-0"
            leave-from-class="opacity-100 transform translate-y-0"
            leave-to-class="opacity-0 transform translate-y-4"
        >
            <div v-if="hasSelection" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50">
                <div class="bw-floating-toolbar">
                    <span class="bw-floating-toolbar__count">
                        {{ selectedCount }} selected
                    </span>
                    <div class="bw-floating-toolbar__divider" />
                    <button
                        @click="handleBulkAction('approve')"
                        class="bw-floating-toolbar__button"
                        title="Approve selected"
                    >
                        <i class="fas fa-check text-sm" />
                    </button>
                    <button
                        @click="handleBulkAction('cancel')"
                        class="bw-floating-toolbar__button bw-floating-toolbar__button--danger"
                        title="Cancel selected"
                    >
                        <i class="fas fa-ban text-sm" />
                    </button>
                    <button
                        @click="handleBulkAction('export')"
                        class="bw-floating-toolbar__button"
                        title="Export selected"
                    >
                        <i class="fas fa-download text-sm" />
                    </button>
                    <button
                        @click="clearSelection"
                        class="bw-floating-toolbar__button"
                        title="Clear selection"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </Transition>
    </LayoutShell>
</template>

<style scoped>
.bw-floating-toolbar {
    @apply bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg px-4 py-2 flex items-center space-x-2;
}

.bw-floating-toolbar__count {
    @apply text-sm font-medium text-gray-700 dark:text-gray-300 mr-3;
}

.bw-floating-toolbar__divider {
    @apply w-px h-6 bg-gray-300 dark:bg-gray-600;
}

.bw-floating-toolbar__button {
    @apply w-8 h-8 flex items-center justify-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white rounded-md transition-colors duration-200;
}

.bw-floating-toolbar__button--danger {
    @apply text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300;
}
</style>