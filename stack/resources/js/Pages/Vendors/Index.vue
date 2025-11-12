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
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Toast from 'primevue/toast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { FilterMatchMode } from '@primevue/core/api'

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
} = useBulkSelection([], 'vendors')

const page = usePage()
const toast = ref()

// Reactive data
const loading = ref(false)
const vendors = ref([])
const filters = ref({
    search: '',
    status: null,
    vendor_type: null,
})

// Computed properties
const user = computed(() => page.props.auth?.user)
const currentCompany = computed(() => page.props.currentCompany)

// Filter and search functionality
const filteredVendors = computed(() => {
    let result = vendors.value

    if (filters.value.search) {
        const search = filters.value.search.toLowerCase()
        result = result.filter(vendor =>
            vendor.legal_name.toLowerCase().includes(search) ||
            vendor.display_name?.toLowerCase().includes(search) ||
            vendor.vendor_code.toLowerCase().includes(search) ||
            vendor.primary_contact?.email?.toLowerCase().includes(search)
        )
    }

    if (filters.value.status) {
        result = result.filter(vendor => vendor.status === filters.value.status)
    }

    if (filters.value.vendor_type) {
        result = result.filter(vendor => vendor.vendor_type === filters.value.vendor_type)
    }

    return result
})

// Options for filters
const statusOptions = [
    { label: 'All Status', value: null },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Suspended', value: 'suspended' }
]

const vendorTypeOptions = [
    { label: 'All Types', value: null },
    { label: 'Company', value: 'company' },
    { label: 'Individual', value: 'individual' },
    { label: 'Other', value: 'other' }
]

// Bulk actions
const handleBulkAction = (action) => {
    switch (action) {
        case 'delete':
            bulkDeleteVendors()
            break
        case 'export':
            exportVendors()
            break
        case 'clear-selection':
            clearSelection()
            break
        default:
            // Silently handle unknown actions
    }
}

const bulkDeleteVendors = async () => {
    if (!confirm(`Are you sure you want to delete ${selectedCount.value} vendors? This action cannot be undone.`)) {
        return
    }

    try {
        const promises = selectedItems.value.map(vendor => 
            router.delete(`/vendors/${vendor.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.value.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: `${selectedCount.value} vendors deleted successfully`,
                        life: 3000
                    })
                },
                onError: (error) => {
                    toast.value.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: error.message || 'Failed to delete vendors',
                        life: 5000
                    })
                }
            })
        )

        await Promise.all(promises)
        clearSelection()
        loadVendors()
    } catch (error) {
        console.error('Failed to bulk delete vendors:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete vendors. Please try again.',
            life: 5000
        })
    }
}

const exportVendors = () => {
    const params = new URLSearchParams({
        ...filters.value,
        export: 'true'
    })

    window.open(`/vendors/export?${params.toString()}`, '_blank')

    toast.value.add({
        severity: 'success',
        summary: 'Export Started',
        detail: 'Vendors export is being prepared',
        life: 3000
    })
}

const loadVendors = () => {
    vendors.value = page.props.vendors.data || []
    updateBulkSelection(vendors.value)
}

const clearFilters = () => {
    filters.value = {
        search: '',
        status: null,
        vendor_type: null,
    }
}

const clearSearch = () => {
    filters.value.search = ''
}

const applyFilters = () => {
    router.get('/vendors', filters.value, {
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
        'active': 'success',
        'inactive': 'warning',
        'suspended': 'danger'
    }
    return severities[status] || 'info'
}

const getStatusColor = (status) => {
    const colors = {
        'active': 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
        'inactive': 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-800',
        'suspended': 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800'
    }
    return colors[status] || colors['inactive']
}

const getVendorTypeColor = (type) => {
    const colors = {
        'company': 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800',
        'individual': 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800',
        'other': 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
    }
    return colors[type] || colors['other']
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const deleteVendor = (vendor) => {
    if (!confirm(`Are you sure you want to delete ${vendor.display_name || vendor.legal_name}? This action cannot be undone.`)) {
        return
    }

    router.delete(`/vendors/${vendor.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Vendor deleted successfully',
                life: 3000
            })
            loadVendors()
        },
        onError: (error) => {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: error.message || 'Failed to delete vendor',
                life: 5000
            })
        }
    })
}

// Contextual quick links
const quickLinks = computed(() => {
    const links = []

    // Create Vendor
    links.push({
        label: 'Create Vendor',
        url: '/vendors/create',
        icon: 'pi pi-plus'
    })

    // Export Vendors
    links.push({
        label: 'Export Vendors',
        url: '#',
        icon: 'pi pi-download',
        action: exportVendors
    })

    return links
})

// Setup dynamic page actions
const setupPageActions = () => {
    const actions = [
        {
            key: 'create-vendor',
            label: 'New Vendor',
            icon: 'fas fa-plus',
            severity: 'primary',
            click: () => router.visit('/vendors/create')
        },
        {
            key: 'export-vendors',
            label: 'Export',
            icon: 'fas fa-download',
            severity: 'secondary',
            click: exportVendors
        }
    ]

    setActions(actions)
}

// Lifecycle
onMounted(() => {
    loadVendors()
    setupPageActions()
})

onUnmounted(() => {
    clearActions()
})

// Update bulk selection when vendors change
watch(vendors, (newVendors) => {
    if (newVendors.length > 0) {
        updateBulkSelection(newVendors)
    }
})
</script>

<template>
    <LayoutShell>
        <Toast ref="toast" />

        <!-- Universal Page Header -->
        <UniversalPageHeader
            title="Vendors"
            description="Manage your supplier and vendor relationships"
            subDescription="Create and maintain vendor information for purchasing and accounts payable"
            :show-search="true"
            search-placeholder="Search vendors..."
        />

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div class="relative">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="filters.search"
                            placeholder="Search vendors..."
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

                <!-- Vendor Type Filter -->
                <Dropdown
                    v-model="filters.vendor_type"
                    :options="vendorTypeOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="All Types"
                    class="w-full"
                    @change="applyFilters"
                />
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid-5-6">
            <!-- Left Column - Main Content -->
            <div class="main-content bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <!-- Vendor Table -->
                <DataTable
                    :value="filteredVendors"
                    v-model:selection="selectedItems"
                    :paginator="true"
                    :rows="25"
                    dataKey="id"
                    :loading="loading"
                    :globalFilterFields="['legal_name', 'display_name', 'vendor_code', 'primary_contact.email']"
                    responsiveLayout="scroll"
                    :row-hover="true"
                >
                    <!-- Selection Column -->
                    <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

                    <!-- Vendor Information Column -->
                    <Column field="legal_name" header="Vendor" sortable style="min-width: 250px">
                        <template #body="{ data }">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center">
                                        <i class="fas fa-building text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <Link
                                        :href="`/vendors/${data.id}`"
                                        class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 truncate block"
                                    >
                                        {{ data.display_name || data.legal_name }}
                                    </Link>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ data.vendor_code }}
                                    </div>
                                    <div v-if="data.legal_name !== data.display_name" class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                        {{ data.legal_name }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Type Column -->
                    <Column field="vendor_type" header="Type" sortable style="min-width: 120px">
                        <template #body="{ data }">
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
                                    getVendorTypeColor(data.vendor_type)
                                ]"
                            >
                                {{ data.vendor_type.charAt(0).toUpperCase() + data.vendor_type.slice(1) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Contact Column -->
                    <Column field="primary_contact.email" header="Primary Contact" style="min-width: 200px">
                        <template #body="{ data }">
                            <div v-if="data.primary_contact" class="text-sm">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ data.primary_contact.first_name }} {{ data.primary_contact.last_name }}
                                </div>
                                <div class="text-gray-500 dark:text-gray-400">
                                    {{ data.primary_contact.email }}
                                </div>
                                <div v-if="data.primary_contact.phone" class="text-gray-400 dark:text-gray-500 text-xs">
                                    {{ data.primary_contact.phone }}
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 dark:text-gray-500">
                                No primary contact
                            </div>
                        </template>
                    </Column>

                    <!-- Status Column -->
                    <Column field="status" header="Status" sortable style="min-width: 100px">
                        <template #body="{ data }">
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
                                    getStatusColor(data.status)
                                ]"
                            >
                                {{ data.status.charAt(0).toUpperCase() + data.status.slice(1) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Actions Column -->
                    <Column header="Actions" style="min-width: 120px">
                        <template #body="{ data }">
                            <div class="flex items-center space-x-2">
                                <Link
                                    :href="`/vendors/${data.id}`"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                    title="View"
                                >
                                    <i class="fas fa-eye"></i>
                                </Link>
                                <Link
                                    :href="`/vendors/${data.id}/edit`"
                                    class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"
                                    title="Edit"
                                >
                                    <i class="fas fa-edit"></i>
                                </Link>
                                <button
                                    @click="deleteVendor(data)"
                                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                    title="Delete"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- Empty State -->
                <div v-if="filteredVendors.length === 0 && !loading" class="text-center py-16">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full mb-6">
                        <i class="fas fa-building text-2xl text-gray-400 dark:text-gray-500"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                        {{ filters.search || filters.status || filters.vendor_type ? 'No vendors found' : 'No vendors yet' }}
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
                        {{ filters.search || filters.status || filters.vendor_type
                            ? 'Try adjusting your search terms or filters to find what you\'re looking for.'
                            : 'Get started by creating your first vendor using the actions above.'
                        }}
                    </p>
                    <div v-if="filters.search || filters.status || filters.vendor_type" class="flex justify-center">
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
                    title="Vendor Actions"
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
                        @click="handleBulkAction('export')"
                        class="bw-floating-toolbar__button"
                        title="Export selected"
                    >
                        <i class="fas fa-download text-sm" />
                    </button>
                    <button
                        @click="handleBulkAction('delete')"
                        class="bw-floating-toolbar__button bw-floating-toolbar__button--danger"
                        title="Delete selected"
                    >
                        <i class="fas fa-trash text-sm" />
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