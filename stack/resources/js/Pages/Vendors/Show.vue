<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import Card from 'primevue/card'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import TabView from 'primevue/tabview'

const page = usePage()
const toast = ref()

// Props from controller
const props = defineProps({
    vendor: {
        type: Object,
        required: true
    }
})

// Computed properties
const vendor = computed(() => props.vendor)

// Helper functions
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
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

const deleteVendor = () => {
    if (!confirm(`Are you sure you want to delete ${vendor.value.display_name || vendor.value.legal_name}? This action cannot be undone.`)) {
        return
    }

    router.delete(`/vendors/${vendor.value.id}`, {
        onSuccess: () => {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Vendor deleted successfully',
                life: 3000
            })
            router.visit('/vendors')
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
</script>

<template>
    <LayoutShell>
        <Toast ref="toast" />

        <!-- Universal Page Header -->
        <UniversalPageHeader
            :title="vendor.display_name || vendor.legal_name"
            :description="`Vendor information and management for ${vendor.legal_name}`"
            :sub-description="`Vendor Code: ${vendor.vendor_code}`"
        >
            <!-- Header Actions -->
            <div class="flex items-center space-x-3">
                <Link
                    :href="`/vendors/${vendor.id}/edit`"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                >
                    <i class="fas fa-edit mr-2"></i>
                    Edit Vendor
                </Link>
                <button
                    @click="deleteVendor"
                    class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                >
                    <i class="fas fa-trash mr-2"></i>
                    Delete
                </button>
            </div>
        </UniversalPageHeader>

        <!-- Main Content -->
        <div class="max-w-6xl mx-auto">
            <TabView class="vendor-tabs">
                <!-- Overview Tab -->
                <TabPanel header="Overview">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Main Information (2/3 width) -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Basic Information Card -->
                            <Card class="vendor-card">
                                <template #title>
                                    <div class="flex items-center">
                                        <i class="fas fa-building mr-2 text-blue-600"></i>
                                        Basic Information
                                    </div>
                                </template>
                                <template #content>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Legal Name
                                            </label>
                                            <p class="text-gray-900 dark:text-white font-medium">
                                                {{ vendor.legal_name }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Display Name
                                            </label>
                                            <p class="text-gray-900 dark:text-white font-medium">
                                                {{ vendor.display_name || 'Same as legal name' }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Vendor Code
                                            </label>
                                            <p class="text-gray-900 dark:text-white font-medium">
                                                {{ vendor.vendor_code }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Tax ID / EIN
                                            </label>
                                            <p class="text-gray-900 dark:text-white font-medium">
                                                {{ vendor.tax_id || 'Not provided' }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Vendor Type
                                            </label>
                                            <span
                                                :class="[
                                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
                                                    getVendorTypeColor(vendor.vendor_type)
                                                ]"
                                            >
                                                {{ vendor.vendor_type.charAt(0).toUpperCase() + vendor.vendor_type.slice(1) }}
                                            </span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Status
                                            </label>
                                            <span
                                                :class="[
                                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
                                                    getStatusColor(vendor.status)
                                                ]"
                                            >
                                                {{ vendor.status.charAt(0).toUpperCase() + vendor.status.slice(1) }}
                                            </span>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Website
                                            </label>
                                            <a
                                                v-if="vendor.website"
                                                :href="vendor.website"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                            >
                                                {{ vendor.website }}
                                                <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                            </a>
                                            <p v-else class="text-gray-500 dark:text-gray-400">
                                                Not provided
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- Notes Card -->
                            <Card v-if="vendor.notes" class="vendor-card">
                                <template #title>
                                    <div class="flex items-center">
                                        <i class="fas fa-sticky-note mr-2 text-yellow-600"></i>
                                        Notes
                                    </div>
                                </template>
                                <template #content>
                                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                        {{ vendor.notes }}
                                    </p>
                                </template>
                            </Card>
                        </div>

                        <!-- Sidebar Information (1/3 width) -->
                        <div class="space-y-6">
                            <!-- Dates Card -->
                            <Card class="vendor-card">
                                <template #title>
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar mr-2 text-green-600"></i>
                                        Dates
                                    </div>
                                </template>
                                <template #content>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Created
                                            </label>
                                            <p class="text-gray-900 dark:text-white">
                                                {{ formatDate(vendor.created_at) }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Last Updated
                                            </label>
                                            <p class="text-gray-900 dark:text-white">
                                                {{ formatDate(vendor.updated_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- Quick Actions Card -->
                            <Card class="vendor-card">
                                <template #title>
                                    <div class="flex items-center">
                                        <i class="fas fa-bolt mr-2 text-purple-600"></i>
                                        Quick Actions
                                    </div>
                                </template>
                                <template #content>
                                    <div class="space-y-3">
                                        <Link
                                            :href="`/purchase-orders/create?vendor_id=${vendor.id}`"
                                            class="block w-full text-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                                        >
                                            <i class="fas fa-file-invoice mr-2"></i>
                                            Create Purchase Order
                                        </Link>
                                        <Link
                                            :href="`/bills/create?vendor_id=${vendor.id}`"
                                            class="block w-full text-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                                        >
                                            <i class="fas fa-receipt mr-2"></i>
                                            Create Bill
                                        </Link>
                                        <button
                                            disabled
                                            class="block w-full px-4 py-2 bg-gray-400 text-gray-200 text-sm font-medium rounded-lg cursor-not-allowed"
                                            title="Coming soon"
                                        >
                                            <i class="fas fa-file-export mr-2"></i>
                                            Generate Statement
                                        </button>
                                    </div>
                                </template>
                            </Card>
                        </div>
                    </div>
                </TabPanel>

                <!-- Contacts Tab -->
                <TabPanel header="Contacts">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Contact Persons
                            </h3>
                            <Link
                                :href="`/vendors/${vendor.id}/edit`"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                            >
                                <i class="fas fa-edit mr-2"></i>
                                Edit Contacts
                            </Link>
                        </div>

                        <div v-if="vendor.contacts && vendor.contacts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <Card
                                v-for="contact in vendor.contacts"
                                :key="contact.id"
                                class="contact-card"
                            >
                                <template #content>
                                    <div class="text-center">
                                        <!-- Avatar -->
                                        <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <i class="fas fa-user text-2xl text-gray-400 dark:text-gray-500"></i>
                                        </div>

                                        <!-- Name -->
                                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                                            {{ contact.first_name }} {{ contact.last_name }}
                                        </h4>

                                        <!-- Contact Type Badge -->
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border mb-4',
                                                contact.contact_type === 'primary' 
                                                    ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800'
                                                    : 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
                                            ]"
                                        >
                                            {{ contact.contact_type.charAt(0).toUpperCase() + contact.contact_type.slice(1) }}
                                        </span>

                                        <!-- Contact Information -->
                                        <div class="space-y-2 text-left">
                                            <div v-if="contact.email" class="flex items-center text-sm">
                                                <i class="fas fa-envelope text-gray-400 mr-2 w-4"></i>
                                                <a
                                                    :href="`mailto:${contact.email}`"
                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                                >
                                                    {{ contact.email }}
                                                </a>
                                            </div>
                                            <div v-if="contact.phone" class="flex items-center text-sm">
                                                <i class="fas fa-phone text-gray-400 mr-2 w-4"></i>
                                                <a
                                                    :href="`tel:${contact.phone}`"
                                                    class="text-gray-900 dark:text-white"
                                                >
                                                    {{ contact.phone }}
                                                </a>
                                            </div>
                                            <div v-if="contact.mobile" class="flex items-center text-sm">
                                                <i class="fas fa-mobile-alt text-gray-400 mr-2 w-4"></i>
                                                <a
                                                    :href="`tel:${contact.mobile}`"
                                                    class="text-gray-900 dark:text-white"
                                                >
                                                    {{ contact.mobile }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <div v-else class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full mb-6">
                                <i class="fas fa-users text-2xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                                No contacts added
                            </h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
                                Add contact persons to this vendor to manage communication and relationships.
                            </p>
                            <Link
                                :href="`/vendors/${vendor.id}/edit`"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                            >
                                <i class="fas fa-plus mr-2"></i>
                                Add Contacts
                            </Link>
                        </div>
                    </div>
                </TabPanel>

                <!-- Transactions Tab -->
                <TabPanel header="Transactions">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Purchase Orders Summary -->
                            <Card class="transaction-summary-card">
                                <template #title>
                                    <div class="flex items-center">
                                        <i class="fas fa-file-invoice mr-2 text-blue-600"></i>
                                        Purchase Orders
                                    </div>
                                </template>
                                <template #content>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                            {{ vendor.purchase_orders?.length || 0 }}
                                        </div>
                                        <p class="text-gray-500 dark:text-gray-400">
                                            Total purchase orders
                                        </p>
                                        <Link
                                            v-if="vendor.purchase_orders?.length > 0"
                                            :href="`/purchase-orders?vendor_id=${vendor.id}`"
                                            class="inline-flex items-center mt-4 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                        >
                                            View All <i class="fas fa-arrow-right ml-1"></i>
                                        </Link>
                                    </div>
                                </template>
                            </Card>

                            <!-- Bills Summary -->
                            <Card class="transaction-summary-card">
                                <template #title>
                                    <div class="flex items-center">
                                        <i class="fas fa-receipt mr-2 text-green-600"></i>
                                        Bills
                                    </div>
                                </template>
                                <template #content>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2">
                                            {{ vendor.bills?.length || 0 }}
                                        </div>
                                        <p class="text-gray-500 dark:text-gray-400">
                                            Total bills
                                        </p>
                                        <Link
                                            v-if="vendor.bills?.length > 0"
                                            :href="`/bills?vendor_id=${vendor.id}`"
                                            class="inline-flex items-center mt-4 text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300"
                                        >
                                            View All <i class="fas fa-arrow-right ml-1"></i>
                                        </Link>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- Recent Transactions -->
                        <Card>
                            <template #title>
                                <div class="flex items-center">
                                    <i class="fas fa-history mr-2 text-purple-600"></i>
                                    Recent Activity
                                </div>
                            </template>
                            <template #content>
                                <div class="text-center py-8">
                                    <i class="fas fa-chart-line text-4xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        Transaction history will be available here once purchase orders and bills are created.
                                    </p>
                                </div>
                            </template>
                        </Card>
                    </div>
                </TabPanel>
            </TabView>
        </div>
    </LayoutShell>
</template>

<style scoped>
.vendor-tabs :deep(.p-tabview-nav) {
    @apply border-b border-gray-200 dark:border-gray-700;
}

.vendor-tabs :deep(.p-tabview-header) {
    @apply mr-6;
}

.vendor-tabs :deep(.p-tabview-header-link) {
    @apply px-4 py-3 text-sm font-medium text-gray-500 dark:text-gray-400 border-b-2 border-transparent hover:text-gray-700 dark:hover:text-gray-300;
}

.vendor-tabs :deep(.p-tabview-header.p-highlight .p-tabview-header-link) {
    @apply text-blue-600 dark:text-blue-400 border-blue-600 dark:border-blue-400;
}

.vendor-card :deep(.p-card-content) {
    @apply p-6;
}

.contact-card :deep(.p-card-content) {
    @apply p-4;
}

.transaction-summary-card :deep(.p-card-content) {
    @apply p-8;
}
</style>
