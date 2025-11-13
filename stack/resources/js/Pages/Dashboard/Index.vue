<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataView from 'primevue/dataview'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Chart from 'primevue/chart'
import Divider from 'primevue/divider'
import Menubar from 'primevue/menubar'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import ModuleToggle from '@/Components/ModuleToggle.vue'

const { t } = useI18n()
const page = usePage()

const loading = ref(false)
const dashboardData = ref({
    financialSummary: {
        totalRevenue: 0,
        totalExpenses: 0,
        netIncome: 0,
        cashFlow: 0
    },
    recentInvoices: [],
    recentPayments: [],
    activityLog: [],
    companyStats: {
        totalCompanies: 0,
        activeModules: 0,
        totalUsers: 0
    }
})

const chartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top'
        }
    },
    scales: {
        y: {
            beginAtZero: true
        }
    }
})

const revenueChart = ref({
    labels: [],
    datasets: [{
        label: 'Revenue',
        data: [],
        backgroundColor: '#10B981',
        borderColor: '#10B981',
        borderWidth: 1
    }, {
        label: 'Expenses',
        data: [],
        backgroundColor: '#EF4444',
        borderColor: '#EF4444',
        borderWidth: 1
    }]
})

// Computed properties
const user = computed(() => page.props.auth?.user)
const currentCompany = computed(() => page.props.current_company)
const hasCompanies = computed(() => currentCompany.value !== null)

// Methods
const loadDashboardData = async () => {
    loading.value = true
    try {
        const response = await fetch('/api/v1/dashboard/data')
        const data = await response.json()
        
        if (response.ok) {
            dashboardData.value = data
            // Prepare chart data
            revenueChart.value.labels = data.monthlyData?.labels || []
            revenueChart.value.datasets[0].data = data.monthlyData?.revenue || []
            revenueChart.value.datasets[1].data = data.monthlyData?.expenses || []
        }
    } catch (error) {
        console.error('Failed to load dashboard data:', error)
    } finally {
        loading.value = false
    }
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount)
}

const getSeverity = (status) => {
    const statusMap = {
        'paid': 'success',
        'pending': 'warning',
        'overdue': 'danger',
        'draft': 'info'
    }
    return statusMap[status] || 'info'
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const createInvoice = () => {
    router.visit('/invoicing/create')
}

const viewAllInvoices = () => {
    router.visit('/invoicing')
}

const viewModule = (moduleName) => {
    router.visit(`/modules/${moduleName.toLowerCase()}`)
}

// Lifecycle
onMounted(() => {
    loadDashboardData()
})
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Navigation Bar -->
        <Menubar class="border-b border-gray-200 dark:border-gray-700">
            <template #start>
                <div class="flex items-center space-x-4">
                    <i class="fas fa-chart-line text-blue-600 dark:text-blue-400"></i>
                    <span class="font-semibold text-gray-900 dark:text-white">
                        {{ t('dashboard.title') }}
                    </span>
                </div>
            </template>
            <template #end>
                <div class="flex items-center space-x-4">
                    <CompanySwitcher v-if="hasCompanies" />
                    <ModuleToggle />
                    <Button icon="fas fa-user" text rounded />
                </div>
            </template>
        </Menubar>

        <!-- Page Header -->
        <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ t('setup.welcome_back', { name: user?.name }) }}
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            {{ currentCompany?.name || 'No company selected' }}
                        </p>
                    </div>
                    <div class="flex space-x-3">
                        <Button 
                            @click="createInvoice"
                            icon="fas fa-plus"
                            :label="t('invoicing.create_invoice')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center py-12">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400">{{ t('common.loading') }}</p>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div v-else class="space-y-8">
                <!-- Financial Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <Card class="shadow-md">
                        <template #content>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        {{ t('dashboard.financial_summary') }} - Revenue
                                    </p>
                                    <p class="text-2xl font-bold text-green-600">
                                        {{ formatCurrency(dashboardData.financialSummary.totalRevenue) }}
                                    </p>
                                </div>
                                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                                    <i class="fas fa-arrow-trend-up text-green-600 dark:text-green-400"></i>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card class="shadow-md">
                        <template #content>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        {{ t('dashboard.financial_summary') }} - Expenses
                                    </p>
                                    <p class="text-2xl font-bold text-red-600">
                                        {{ formatCurrency(dashboardData.financialSummary.totalExpenses) }}
                                    </p>
                                </div>
                                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-full">
                                    <i class="fas fa-arrow-trend-down text-red-600 dark:text-red-400"></i>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card class="shadow-md">
                        <template #content>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Net Income
                                    </p>
                                    <p class="text-2xl font-bold" 
                                       :class="dashboardData.financialSummary.netIncome >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(dashboardData.financialSummary.netIncome) }}
                                    </p>
                                </div>
                                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                                    <i class="fas fa-chart-pie text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card class="shadow-md">
                        <template #content>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Cash Flow
                                    </p>
                                    <p class="text-2xl font-bold" 
                                       :class="dashboardData.financialSummary.cashFlow >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(dashboardData.financialSummary.cashFlow) }}
                                    </p>
                                </div>
                                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                                    <i class="fas fa-money-bill-wave text-purple-600 dark:text-purple-400"></i>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Revenue Chart -->
                <Card class="shadow-md">
                    <template #title>
                        <div class="flex justify-between items-center">
                            <span>{{ t('dashboard.financial_summary') }} - Trend</span>
                            <div class="flex space-x-2">
                                <span class="flex items-center text-sm">
                                    <i class="fas fa-circle text-green-500 mr-1" style="font-size: 8px;"></i>
                                    Revenue
                                </span>
                                <span class="flex items-center text-sm">
                                    <i class="fas fa-circle text-red-500 mr-1" style="font-size: 8px;"></i>
                                    Expenses
                                </span>
                            </div>
                        </div>
                    </template>
                    <template #content>
                        <div class="h-64">
                            <Chart 
                                type="line" 
                                :data="revenueChart" 
                                :options="chartOptions"
                            />
                        </div>
                    </template>
                </Card>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Recent Invoices -->
                    <Card class="shadow-md">
                        <template #title>
                            <div class="flex justify-between items-center">
                                <span>{{ t('invoicing.invoices') }}</span>
                                <Button 
                                    @click="viewAllInvoices"
                                    icon="fas fa-arrow-right"
                                    text
                                    size="small"
                                />
                            </div>
                        </template>
                        <template #content>
                            <div v-if="dashboardData.recentInvoices.length === 0" class="text-center py-8">
                                <i class="fas fa-file-invoice text-3xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 dark:text-gray-400">No recent invoices</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="invoice in dashboardData.recentInvoices" 
                                    :key="invoice.id"
                                    class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ invoice.invoice_number }}
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ invoice.customer?.name }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ formatCurrency(invoice.amount) }}
                                        </p>
                                        <Tag :value="invoice.status" :severity="getSeverity(invoice.status)" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- Recent Activity -->
                    <Card class="shadow-md">
                        <template #title>
                            {{ t('dashboard.recent_activity') }}
                        </template>
                        <template #content>
                            <div v-if="dashboardData.activityLog.length === 0" class="text-center py-8">
                                <i class="fas fa-history text-3xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 dark:text-gray-400">No recent activity</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="activity in dashboardData.activityLog" 
                                    :key="activity.id"
                                    class="flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                >
                                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-full">
                                        <i :class="activity.icon" class="text-blue-600 dark:text-blue-400 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ activity.description }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ formatDate(activity.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Quick Actions -->
                <Card class="shadow-md">
                    <template #title>
                        {{ t('dashboard.quick_actions') }}
                    </template>
                    <template #content>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <Button 
                                @click="createInvoice"
                                icon="fas fa-plus"
                                label="New Invoice"
                                class="p-4"
                                severity="success"
                            />
                            <Button 
                                @click="viewModule('Invoicing')"
                                icon="fas fa-file-invoice"
                                label="Invoicing"
                                class="p-4"
                            />
                            <Button 
                                @click="viewModule('Payments')"
                                icon="fas fa-credit-card"
                                label="Payments"
                                class="p-4"
                            />
                            <Button 
                                @click="viewModule('Reports')"
                                icon="fas fa-chart-bar"
                                label="Reports"
                                class="p-4"
                            />
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </div>
</template>