<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Toast from 'primevue/toast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import ContactsTab from './Tabs/ContactsTab.vue'
import AddressesTab from './Tabs/AddressesTab.vue'
import CommunicationsTab from './Tabs/CommunicationsTab.vue'
import CreditLimitDialog from './CreditLimitDialog.vue'

const props = defineProps({
    customer: Object,
    contacts: Array,
    addresses: Array,
    communications: Array,
    creditData: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

const showCreditLimitDialog = ref(false)

const currentBalance = computed(() => {
    // This would be calculated from invoices and payments
    return props.customer.invoices?.reduce((total, invoice) => {
        return total + (invoice.balance_due || 0)
    }, 0) || 0
})

const availableCredit = computed(() => {
    const creditLimit = props.customer.credit_limit || 0
    return Math.max(0, creditLimit - currentBalance.value)
})

const creditUtilization = computed(() => {
    const creditLimit = props.customer.credit_limit || 0
    if (creditLimit === 0) return 0
    return ((creditLimit - availableCredit.value) / creditLimit * 100).toFixed(1)
})

// Credit limit specific computed properties
const currentCreditLimit = computed(() => {
    return props.creditData?.credit_limit ?? props.customer.credit_limit
})

const currentExposure = computed(() => {
    return props.creditData?.current_exposure ?? currentBalance.value
})

const creditLimitUtilization = computed(() => {
    const limit = currentCreditLimit.value
    const exposure = currentExposure.value
    if (!limit || limit === 0) return 0
    return Math.round((exposure / limit) * 100)
})

const getCreditLimitSeverity = () => {
    const utilization = creditLimitUtilization.value
    if (utilization >= 90) return 'danger'
    if (utilization >= 75) return 'warning'
    if (utilization >= 50) return 'info'
    return 'success'
}

const getCreditLimitLabel = () => {
    const utilization = creditLimitUtilization.value
    if (utilization >= 90) return 'Critical'
    if (utilization >= 75) return 'High'
    if (utilization >= 50) return 'Moderate'
    return 'Healthy'
}

// Additional computed properties for template
const creditUtilizationPercentage = computed(() => {
    return props.creditData?.utilization_percentage ?? creditLimitUtilization.value
})

const getCreditUtilizationSeverity = () => {
    const utilization = creditUtilizationPercentage.value
    if (utilization >= 90) return 'danger'
    if (utilization >= 75) return 'warning'
    if (utilization >= 50) return 'info'
    return 'success'
}

const getCreditUtilizationLabel = () => {
    const utilization = creditUtilizationPercentage.value
    if (utilization >= 90) return 'Critical'
    if (utilization >= 75) return 'High'
    if (utilization >= 50) return 'Moderate'
    return 'Healthy'
}

const creditLimitWarnings = computed(() => {
    const warnings = []
    const utilization = creditUtilizationPercentage.value
    
    if (utilization >= 90) {
        warnings.push({
            id: 'critical_utilization',
            severity: 'danger',
            message: `Credit utilization is critical at ${utilization}%. Immediate action required.`
        })
    } else if (utilization >= 75) {
        warnings.push({
            id: 'high_utilization',
            severity: 'warning',
            message: `Credit utilization is high at ${utilization}%. Consider increasing limit or reducing exposure.`
        })
    }
    
    const available = availableCredit.value
    if (available !== null && available <= 0) {
        warnings.push({
            id: 'no_available_credit',
            severity: 'danger',
            message: 'No available credit remaining. Customer cannot create new invoices.'
        })
    }
    
    return warnings
})

const getSeverity = (status) => {
    switch (status) {
        case 'active': return 'success'
        case 'inactive': return 'warning'
        case 'blocked': return 'danger'
        default: return 'info'
    }
}

const canManageCustomers = computed(() => {
    return props.can.manage_credit || props.can.update
})

const onCreditLimitSaved = () => {
    // Refresh customer data after credit limit is saved
    refreshCustomerData()
    
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Credit limit updated successfully',
        life: 3000
    })
}

// Define page actions for customer show page
const customerShowActions = [
    {
        key: 'back',
        label: 'Back to Customers',
        icon: 'pi pi-arrow-left',
        severity: 'secondary',
        outlined: true,
        action: () => router.visit(route('customers.index'))
    },
    {
        key: 'edit',
        label: 'Edit Customer',
        icon: 'pi pi-pencil',
        severity: 'primary',
        action: () => router.visit(route('customers.edit', props.customer.id)),
        visible: props.can.edit
    },
    {
        key: 'credit_limit',
        label: 'Adjust Credit Limit',
        icon: 'pi pi-dollar',
        severity: 'info',
        outlined: true,
        action: () => showCreditLimitDialog.value = true,
        visible: props.can.manage_credit
    }
]

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount || 0)
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const formatPercentage = (value) => {
    return `${value}%`
}

const changeStatus = () => {
    // This would open a status change dialog
    toast.add({
        severity: 'info',
        summary: 'Info',
        detail: 'Status change feature coming soon',
        life: 3000
    })
}

const generateStatement = () => {
    toast.add({
        severity: 'info',
        summary: 'Info',
        detail: 'Statement generation feature coming soon',
        life: 3000
    })
}

const deleteCustomer = () => {
    if (confirm('Are you sure you want to delete this customer?')) {
        router.delete(route('customers.destroy', props.customer.id), {
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Customer deleted successfully',
                    life: 3000
                })
            }
        })
    }
}

const refreshCustomerData = () => {
    // Reload the page to refresh all data
    router.reload({
        only: ['customer', 'contacts', 'addresses', 'communications', 'creditData'],
        preserveScroll: true
    })
}

const loadCreditData = async () => {
    if (!props.can.manage_credit) return
    
    try {
        const response = await fetch(route('customers.credit-limit.show', props.customer.id), {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        })
        
        if (response.ok) {
            const data = await response.json()
            // Update the creditData prop by triggering a reload with credit data
            router.reload({
                only: ['creditData'],
                data: { creditData: data.data },
                preserveScroll: true
            })
        }
    } catch (error) {
        console.error('Failed to load credit data:', error)
    }
}

const getCreditUtilizationColor = () => {
    const utilization = parseFloat(creditUtilization.value)
    if (utilization >= 90) return 'text-red-600'
    if (utilization >= 70) return 'text-yellow-600'
    return 'text-green-600'
}

const getStatusSeverity = (status) => {
    if (!status) return 'success'
    
    switch (status.toLowerCase()) {
        case 'active':
            return 'success'
        case 'inactive':
            return 'warning'
        case 'blocked':
            return 'danger'
        case 'pending':
            return 'info'
        default:
            return 'info'
    }
}

// Load credit data when component mounts
onMounted(() => {
    loadCreditData()
})
</script>

<template>
    <LayoutShell>
        <Toast />
        
        <!-- Universal Page Header -->
        <UniversalPageHeader
            :title="customer.name"
            description="Customer Details"
            subDescription="View and manage customer information, credit limits, and transaction history"
            :default-actions="customerShowActions"
            :show-search="false"
        />

        <!-- Main Content Area -->
        <div class="content-grid-1-1">
            <div class="main-content">
                <!-- Customer Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(currentBalance, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">Current Balance</div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(customer.credit_limit, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">Credit Limit</div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold" :class="getCreditUtilizationColor()">
                            {{ creditUtilization }}%
                        </div>
                        <div class="text-sm text-gray-600">Credit Utilization</div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <Tag :value="customer.status" :severity="getSeverity(customer.status)" />
                        <div class="text-sm text-gray-600 mt-1">Status</div>
                    </div>
                </template>
            </Card>
        </div>

        <TabView>
            <!-- Overview Tab -->
            <TabPanel header="Overview">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic Information -->
                        <Card>
                            <template #title>
                                Basic Information
                            </template>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-sm text-gray-600">Customer Number</div>
                                        <div class="font-medium font-mono">{{ customer.customer_number }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Legal Name</div>
                                        <div class="font-medium">{{ customer.legal_name || 'N/A' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Email</div>
                                        <div class="font-medium">{{ customer.email || 'N/A' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Phone</div>
                                        <div class="font-medium">{{ customer.phone || 'N/A' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Website</div>
                                        <div class="font-medium">
                                            <a v-if="customer.website" 
                                               :href="customer.website" 
                                               target="_blank" 
                                               class="text-blue-600 hover:text-blue-800">
                                                {{ customer.website }}
                                            </a>
                                            <span v-else>N/A</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Tax ID</div>
                                        <div class="font-medium">{{ customer.tax_id || 'N/A' }}</div>
                                    </div>
                                </div>
                                
                                <div v-if="customer.notes" class="mt-4">
                                    <div class="text-sm text-gray-600">Notes</div>
                                    <div class="font-medium mt-1">{{ customer.notes }}</div>
                                </div>
                            </template>
                        </Card>

                        <!-- Financial Information -->
                        <Card>
                            <template #title>
                                <div class="flex justify-between items-center">
                                    <span>Financial Information</span>
                                    <Button
                                        v-if="canManageCustomers"
                                        label="Adjust Credit Limit"
                                        icon="pi pi-pencil"
                                        size="small"
                                        severity="secondary"
                                        @click="showCreditLimitDialog = true"
                                    />
                                </div>
                            </template>
                            <template #content>
                                <!-- Credit Limit Summary Card -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-medium text-gray-700">Credit Limit Overview</h4>
                                        <Tag 
                                            :value="getCreditUtilizationLabel()" 
                                            :severity="getCreditUtilizationSeverity()"
                                            size="small"
                                        />
                                    </div>
                                    
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div>
                                            <div class="text-xs text-gray-500">Credit Limit</div>
                                            <div class="text-base font-semibold text-gray-900">
                                                {{ formatCurrency(currentCreditLimit, customer.default_currency) }}
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-xs text-gray-500">Current Exposure</div>
                                            <div class="text-base font-semibold" :class="currentExposure > 0 ? 'text-red-600' : 'text-gray-900'">
                                                {{ formatCurrency(currentExposure, customer.default_currency) }}
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-xs text-gray-500">Available Credit</div>
                                            <div class="text-base font-semibold" :class="availableCredit > 0 ? 'text-green-600' : 'text-red-600'">
                                                {{ availableCredit !== null ? formatCurrency(availableCredit, customer.default_currency) : 'Unlimited' }}
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-xs text-gray-500">Utilization</div>
                                            <div class="flex items-center gap-2">
                                                <ProgressBar
                                                    :value="creditUtilizationPercentage"
                                                    :class="['w-12', getCreditUtilizationSeverity()]"
                                                />
                                                <span class="text-sm font-medium">
                                                    {{ formatPercentage(creditUtilizationPercentage) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Credit Limit Warnings -->
                                    <div v-if="creditLimitWarnings.length > 0" class="mt-3">
                                        <Message
                                            v-for="warning in creditLimitWarnings"
                                            :key="warning.id"
                                            :severity="warning.severity"
                                            :closable="false"
                                            class="mb-2 py-2"
                                        >
                                            <span class="text-sm">{{ warning.message }}</span>
                                        </Message>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-sm text-gray-600">Default Currency</div>
                                        <div class="font-medium">{{ customer.default_currency }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Payment Terms</div>
                                        <div class="font-medium">{{ customer.payment_terms || 'Not specified' }}</div>
                                    </div>
                                    <div v-if="customer.credit_limit_effective_at">
                                        <div class="text-sm text-gray-600">Credit Limit Effective</div>
                                        <div class="font-medium">{{ formatDate(customer.credit_limit_effective_at) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Account Status</div>
                                        <div class="font-medium">
                                            <Tag :value="customer.account_status || 'active'" :severity="getStatusSeverity(customer.account_status)" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Actions -->
                        <Card>
                            <template #title>
                                Quick Actions
                            </template>
                            <template #content>
                                <div class="space-y-3">
                                    <Button
                                        v-if="can.update"
                                        label="Edit Customer"
                                        icon="pi pi-pencil"
                                        @click="router.get(route('customers.edit', customer.id))"
                                        class="w-full"
                                    />
                                    
                                    <Button
                                        v-if="can.generate_statements"
                                        label="Generate Statement"
                                        icon="pi pi-file-pdf"
                                        @click="generateStatement"
                                        class="w-full"
                                    />
                                    
                                    <Button
                                        v-if="can.manage_credit"
                                        label="Adjust Credit Limit"
                                        icon="pi pi-dollar"
                                        severity="secondary"
                                        class="w-full"
                                    />
                                </div>
                            </template>
                        </Card>

                        <!-- System Information -->
                        <Card>
                            <template #title>
                                System Information
                            </template>
                            <template #content>
                                <div class="space-y-2 text-sm">
                                    <div>
                                        <span class="text-gray-600">Customer ID:</span>
                                        <div class="font-mono text-xs mt-1">{{ customer.id }}</div>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Created:</span>
                                        <div>{{ formatDate(customer.created_at) }}</div>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Last Updated:</span>
                                        <div>{{ formatDate(customer.updated_at) }}</div>
                                    </div>
                                    <div v-if="customer.created_by">
                                        <span class="text-gray-600">Created By:</span>
                                        <div>{{ customer.created_by.name }}</div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </TabPanel>

            <!-- Recent Invoices Tab -->
            <TabPanel header="Recent Invoices">
                <Card>
                    <template #title>
                        Recent Invoices
                    </template>
                    <template #content>
                        <div v-if="customer.invoices && customer.invoices.length > 0">
                            <DataTable :value="customer.invoices" responsiveLayout="scroll">
                                <Column field="invoice_number" header="Invoice #" />
                                <Column field="issue_date" header="Date">
                                    <template #body="{ data }">
                                        {{ formatDate(data.issue_date) }}
                                    </template>
                                </Column>
                                <Column field="total" header="Total">
                                    <template #body="{ data }">
                                        {{ formatCurrency(data.total, customer.default_currency) }}
                                    </template>
                                </Column>
                                <Column field="balance_due" header="Balance">
                                    <template #body="{ data }">
                                        {{ formatCurrency(data.balance_due, customer.default_currency) }}
                                    </template>
                                </Column>
                                <Column field="status" header="Status">
                                    <template #body="{ data }">
                                        <Tag :value="data.status" :severity="getSeverity(data.status)" />
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            No invoices found for this customer.
                        </div>
                    </template>
                </Card>
            </TabPanel>

            <!-- Contacts Tab -->
            <TabPanel header="Contacts">
                <ContactsTab
                    :customer="customer"
                    :contacts="contacts || []"
                    :can="can"
                    @refresh="refreshCustomerData"
                />
            </TabPanel>

            <!-- Addresses Tab -->
            <TabPanel header="Addresses">
                <AddressesTab
                    :customer="customer"
                    :addresses="addresses || []"
                    :can="can"
                    @refresh="refreshCustomerData"
                />
            </TabPanel>

            <!-- Communications Tab -->
            <TabPanel header="Communications">
                <CommunicationsTab
                    :customer="customer"
                    :communications="communications || []"
                    :can="can"
                    @refresh="refreshCustomerData"
                />
            </TabPanel>

            <!-- Placeholder tabs for future features -->
            <TabPanel header="Credit History" disabled>
                <div class="text-center py-8 text-gray-500">
                    Credit history tracking coming soon...
                </div>
            </TabPanel>

            <TabPanel header="Statements" disabled>
                <div class="text-center py-8 text-gray-500">
                    Statement generation coming soon...
                </div>
            </TabPanel>
        </TabView>
            </div>
        </div>

        <!-- Credit Limit Dialog -->
        <CreditLimitDialog
            v-model:visible="showCreditLimitDialog"
            :customer="customer"
            :creditData="creditData"
            @saved="onCreditLimitSaved"
            @refresh="refreshCustomerData"
        />
    </LayoutShell>
</template>