<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import Chart from 'primevue/chart'
import axios from 'axios'

const props = defineProps({
    customer: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

const agingData = ref(null)
const loading = ref(false)
const lastRefreshed = ref(null)
const selectedPeriod = ref('current')

const agingPeriods = [
    { label: 'Current', value: 'current' },
    { label: '1-30 Days', value: '1_30' },
    { label: '31-60 Days', value: '31_60' },
    { label: '61-90 Days', value: '61_90' },
    { label: '90+ Days', value: '90_plus' }
]

const agingHistory = ref([])
const loadingHistory = ref(false)
const showHistory = ref(false)

const refreshAging = async () => {
    if (!props.can.view) return
    
    loading.value = true
    try {
        const response = await axios.post(`/api/customers/${props.customer.id}/aging/refresh`)
        
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Aging data refreshed successfully',
                life: 3000
            })
            
            // Refresh the aging data
            await loadAgingData()
        }
    } catch (error) {
        console.error('Failed to refresh aging:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to refresh aging data',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

const loadAgingData = async () => {
    if (!props.can.view) return
    
    loading.value = true
    try {
        const response = await axios.get(`/api/customers/${props.customer.id}/aging`, {
            params: {
                as_of_date: 'current',
                include_trend: true,
                include_health_score: true
            }
        })
        
        if (response.data.success) {
            agingData.value = response.data.data
            lastRefreshed.value = response.data.data.snapshot?.generated_at || new Date().toISOString()
        }
    } catch (error) {
        console.error('Failed to load aging data:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load aging data',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

const loadAgingHistory = async () => {
    if (!props.can.view) return
    
    loadingHistory.value = true
    try {
        const response = await axios.get(`/api/customers/${props.customer.id}/aging`, {
            params: {
                history_days: 90
            }
        })
        
        if (response.data.success && response.data.data.history) {
            agingHistory.value = response.data.data.history
        }
    } catch (error) {
        console.error('Failed to load aging history:', error)
    } finally {
        loadingHistory.value = false
    }
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount || 0)
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString()
}

const getRiskLevelColor = (riskLevel) => {
    switch (riskLevel) {
        case 'low': return 'text-green-600'
        case 'moderate': return 'text-blue-600'
        case 'elevated': return 'text-yellow-600'
        case 'high': return 'text-orange-600'
        case 'critical': return 'text-red-600'
        default: return 'text-gray-600'
    }
}

const getRiskLevelSeverity = (riskLevel) => {
    switch (riskLevel) {
        case 'low': return 'success'
        case 'moderate': return 'info'
        case 'elevated': return 'warning'
        case 'high': return 'warning'
        case 'critical': return 'danger'
        default: return 'secondary'
    }
}

const getHealthScoreColor = (score) => {
    if (score >= 90) return 'text-green-600'
    if (score >= 70) return 'text-yellow-600'
    if (score >= 50) return 'text-orange-600'
    return 'text-red-600'
}

const getBucketPercentage = (bucket, total) => {
    if (total === 0) return 0
    return Math.round((bucket / total) * 100)
}

const chartData = computed(() => {
    if (!agingData.value?.aging_buckets) return null
    
    const buckets = agingData.value.aging_buckets
    return {
        labels: ['Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'],
        datasets: [{
            label: 'Aging Amount',
            data: [
                buckets.current || 0,
                buckets.bucket_1_30 || 0,
                buckets.bucket_31_60 || 0,
                buckets.bucket_61_90 || 0,
                buckets.bucket_90_plus || 0
            ],
            backgroundColor: [
                'rgba(34, 197, 94, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(251, 191, 36, 0.8)',
                'rgba(249, 115, 22, 0.8)',
                'rgba(239, 68, 68, 0.8)'
            ],
            borderColor: [
                'rgb(34, 197, 94)',
                'rgb(59, 130, 246)',
                'rgb(251, 191, 36)',
                'rgb(249, 115, 22)',
                'rgb(239, 68, 68)'
            ],
            borderWidth: 1
        }]
    }
})

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const label = context.chart.data.labels[context.dataIndex]
                    const value = context.raw
                    return `${label}: ${formatCurrency(value, props.customer.default_currency)}`
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (value) => {
                    return formatCurrency(value, props.customer.default_currency)
                }
            }
        }
    }
}))

const totalOutstanding = computed(() => {
    if (!agingData.value?.aging_buckets) return 0
    const buckets = agingData.value.aging_buckets
    return buckets.current + buckets.bucket_1_30 + buckets.bucket_31_60 + buckets.bucket_61_90 + buckets.bucket_90_plus
})

const bucketDistribution = computed(() => {
    if (!agingData.value?.aging_buckets) return {}
    const buckets = agingData.value.aging_buckets
    const total = totalOutstanding.value
    
    if (total === 0) {
        return {
            current: 0,
            bucket_1_30: 0,
            bucket_31_60: 0,
            bucket_61_90: 0,
            bucket_90_plus: 0
        }
    }
    
    return {
        current: getBucketPercentage(buckets.current, total),
        bucket_1_30: getBucketPercentage(buckets.bucket_1_30, total),
        bucket_31_60: getBucketPercentage(buckets.bucket_31_60, total),
        bucket_61_90: getBucketPercentage(buckets.bucket_61_90, total),
        bucket_90_plus: getBucketPercentage(buckets.bucket_90_plus, total)
    }
})

onMounted(() => {
    loadAgingData()
})
</script>

<template>
    <div>
        <Toast />
        
        <!-- Aging Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ t('customers.aging.title') }}</h3>
                <p class="text-sm text-gray-600 mt-1" v-if="lastRefreshed">
                    {{ t('customers.aging.last_updated') }}: {{ formatDateTime(lastRefreshed) }}
                </p>
            </div>
            
            <div class="flex gap-2">
                <Button
                    v-if="props.can.view"
                    label="{{ t('customers.aging.refresh') }}"
                    icon="pi pi-refresh"
                    :loading="loading"
                    @click="refreshAging"
                />
                
                <Button
                    v-if="props.can.view"
                    label="View History"
                    icon="pi pi-chart-bar"
                    severity="secondary"
                    @click="showHistory = !showHistory"
                />
            </div>
        </div>

        <!-- Aging Summary Cards -->
        <div v-if="agingData" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(agingData.aging_buckets?.current || 0, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">{{ t('customers.aging.current') }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ bucketDistribution.current }}%
                        </div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(agingData.aging_buckets?.bucket_1_30 || 0, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">{{ t('customers.aging.1_30') }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ bucketDistribution.bucket_1_30 }}%
                        </div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(agingData.aging_buckets?.bucket_31_60 || 0, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">{{ t('customers.aging.31_60') }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ bucketDistribution.bucket_31_60 }}%
                        </div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(agingData.aging_buckets?.bucket_61_90 || 0, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">{{ t('customers.aging.61_90') }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ bucketDistribution.bucket_61_90 }}%
                        </div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(agingData.aging_buckets?.bucket_90_plus || 0, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">{{ t('customers.aging.90_plus') }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ bucketDistribution.bucket_90_plus }}%
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Aging Chart -->
        <Card class="mb-6" v-if="agingData && chartData">
            <template #title>
                Aging Analysis
            </template>
            <template #content>
                <div class="h-64">
                    <Chart type="bar" :data="chartData" :options="chartOptions" />
                </div>
            </template>
        </Card>

        <!-- Risk Assessment -->
        <Card class="mb-6" v-if="agingData?.latest_snapshot">
            <template #title>
                Risk Assessment
            </template>
            <template #content>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold" :class="getHealthScoreColor(agingData.latest_snapshot.health_score)">
                            {{ agingData.latest_snapshot.health_score }}/100
                        </div>
                        <div class="text-sm text-gray-600">Health Score</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-lg font-bold" :class="getRiskLevelColor(agingData.latest_snapshot.risk_level)">
                            {{ agingData.latest_snapshot.risk_level?.toUpperCase() || 'UNKNOWN' }}
                        </div>
                        <div class="text-sm text-gray-600">Risk Level</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ formatCurrency(totalOutstanding, customer.default_currency) }}
                        </div>
                        <div class="text-sm text-gray-600">Total Outstanding</div>
                    </div>
                </div>
                
                <!-- Risk Warnings -->
                <div v-if="agingData.latest_snapshot.risk_level && agingData.latest_snapshot.risk_level !== 'low'" class="mt-4">
                    <Message 
                        :severity="getRiskLevelSeverity(agingData.latest_snapshot.risk_level)"
                        :closable="false"
                    >
                        <span class="text-sm">
                            Customer has {{ agingData.latest_snapshot.risk_level }} aging risk. 
                            Consider proactive collection efforts.
                        </span>
                    </Message>
                </div>
            </template>
        </Card>

        <!-- Aging History -->
        <Card v-if="showHistory">
            <template #title>
                <div class="flex justify-between items-center">
                    <span>Aging History</span>
                    <Button 
                        label="Close History" 
                        icon="pi pi-times" 
                        size="small"
                        @click="showHistory = false" 
                    />
                </div>
            </template>
            <template #content>
                <DataTable 
                    :value="agingHistory" 
                    :loading="loadingHistory"
                    responsiveLayout="scroll"
                    :paginator="false"
                >
                    <Column field="snapshot_date" header="Date">
                        <template #body="{ data }">
                            {{ formatDate(data.snapshot_date) }}
                        </template>
                    </Column>
                    <Column field="bucket_current" header="Current">
                        <template #body="{ data }">
                            {{ formatCurrency(data.bucket_current, customer.default_currency) }}
                        </template>
                    </Column>
                    <Column field="bucket_1_30" header="1-30 Days">
                        <template #body="{ data }">
                            {{ formatCurrency(data.bucket_1_30, customer.default_currency) }}
                        </template>
                    </Column>
                    <Column field="bucket_31_60" header="31-60 Days">
                        <template #body="{ data }">
                            {{ formatCurrency(data.bucket_31_60, customer.default_currency) }}
                        </template>
                    </Column>
                    <Column field="bucket_61_90" header="61-90 Days">
                        <template #body="{ data }">
                            {{ formatCurrency(data.bucket_61_90, customer.default_currency) }}
                        </template>
                    </Column>
                    <Column field="bucket_90_plus" header="90+ Days">
                        <template #body="{ data }">
                            {{ formatCurrency(data.bucket_90_plus, customer.default_currency) }}
                        </template>
                    </Column>
                    <Column field="total_outstanding" header="Total">
                        <template #body="{ data }">
                            {{ formatCurrency(data.total_outstanding, customer.default_currency) }}
                        </template>
                    </Column>
                    <Column field="generated_via" header="Generated Via">
                        <template #body="{ data }">
                            <Tag :value="data.generated_via" severity="info" size="small" />
                        </template>
                    </Column>
                </DataTable>
                
                <div v-if="agingHistory.length === 0 && !loadingHistory" class="text-center py-8 text-gray-500">
                    No aging history available.
                </div>
            </template>
        </Card>

        <!-- No Data State -->
        <div v-if="!agingData && !loading" class="text-center py-12 text-gray-500">
            <div class="text-lg font-medium mb-2">No aging data available</div>
            <p class="text-sm">Click "Refresh Aging" to generate aging analysis for this customer.</p>
            <Button 
                v-if="props.can.view"
                label="Generate Aging Analysis"
                icon="pi pi-refresh"
                @click="refreshAging"
                class="mt-4"
            />
        </div>
        
        <!-- Loading State -->
        <div v-if="loading && !agingData" class="text-center py-12">
            <ProgressBar mode="indeterminate" class="w-48 mx-auto" />
            <p class="text-gray-600 mt-4">Loading aging data...</p>
        </div>
    </div>
</template>