<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from "@/components/ui/toast/use-toast"
import { useI18n } from 'vue-i18n'
// import Button from 'primevue/button'
// import Card from 'primevue/card'
// import Dropdown from 'primevue/dropdown'
// import Calendar from 'primevue/calendar'
// import Checkbox from 'primevue/checkbox'
// import Dialog from 'primevue/dialog'
// import ProgressBar from 'primevue/progressbar'
// import Tag from 'primevue/tag'
// import Message from 'primevue/message'
import axios from 'axios'

const props = defineProps({
    customer: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

const showExportDialog = ref(false)
const exporting = ref(false)
const exportResults = ref(null)

// Export filters
const exportFilters = ref({
    format: 'csv',
    fields: [],
    date_range: false,
    date_from: null,
    date_to: null,
    status_filter: 'all',
    include_contacts: false,
    include_addresses: false,
    include_credit_info: false,
    include_aging_data: false
})

const exportFormats = [
    { label: 'CSV', value: 'csv' },
    { label: 'JSON', value: 'json' },
    { label: 'Excel (XLSX)', value: 'xlsx' }
]

const statusOptions = [
    { label: 'All Customers', value: 'all' },
    { label: 'Active Only', value: 'active' },
    { label: 'Inactive Only', value: 'inactive' },
    { label: 'Blocked Only', value: 'blocked' }
]

const availableFields = [
    { label: 'Customer Number', value: 'customer_number' },
    { label: 'Name', value: 'name' },
    { label: 'Legal Name', value: 'legal_name' },
    { label: 'Email', value: 'email' },
    { label: 'Phone', value: 'phone' },
    { label: 'Status', value: 'status' },
    { label: 'Default Currency', value: 'default_currency' },
    { label: 'Payment Terms', value: 'payment_terms' },
    { label: 'Credit Limit', value: 'credit_limit' },
    { label: 'Tax ID', value: 'tax_id' },
    { label: 'Website', value: 'website' },
    { label: 'Notes', value: 'notes' },
    { label: 'Created At', value: 'created_at' },
    { label: 'Updated At', value: 'updated_at' }
]

const recentExports = ref([])
const loadingExports = ref(false)

// Initialize default fields selection
const initializeFields = () => {
    exportFilters.value.fields = [
        'customer_number',
        'name',
        'email',
        'phone',
        'status',
        'default_currency',
        'payment_terms',
        'created_at'
    ]
}

const exportCustomers = async () => {
    if (!props.can.export) return
    
    exporting.value = true
    try {
        const payload = {
            format: exportFilters.value.format,
            filters: {
                fields: exportFilters.value.fields,
                status: exportFilters.value.status_filter !== 'all' ? exportFilters.value.status_filter : null,
                date_from: exportFilters.value.date_range ? exportFilters.value.date_from?.toISOString().split('T')[0] : null,
                date_to: exportFilters.value.date_range ? exportFilters.value.date_to?.toISOString().split('T')[0] : null,
                include_contacts: exportFilters.value.include_contacts,
                include_addresses: exportFilters.value.include_addresses,
                include_credit_info: exportFilters.value.include_credit_info,
                include_aging_data: exportFilters.value.include_aging_data
            }
        }
        
        const response = await axios.post('/api/customers/export', payload)
        
        if (response.data.success) {
            exportResults.value = response.data.data
            
            // Download the file
            if (response.data.data.download_url) {
                downloadExport(response.data.data)
            }
            
            toast.add({
                severity: 'success',
                summary: 'Export Successful',
                detail: `Exported ${response.data.data.total_records} customers`,
                life: 3000
            })
            
            showExportDialog.value = false
            await loadRecentExports()
        }
    } catch (error) {
        console.error('Export failed:', error)
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: error.response?.data?.message || 'Failed to export customers',
            life: 5000
        })
    } finally {
        exporting.value = false
    }
}

const downloadExport = async (exportData) => {
    try {
        const response = await axios.get(exportData.download_url, {
            responseType: 'blob'
        })
        
        // Create download link
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', exportData.filename)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
    } catch (error) {
        console.error('Download failed:', error)
        toast.add({
            severity: 'error',
            summary: 'Download Failed',
            detail: 'Failed to download export file',
            life: 3000
        })
    }
}

const loadRecentExports = async () => {
    loadingExports.value = true
    try {
        const response = await axios.get('/api/customers/exports/history')
        
        if (response.data.success) {
            recentExports.value = response.data.data
        }
    } catch (error) {
        console.error('Failed to load export history:', error)
    } finally {
        loadingExports.value = false
    }
}

const resetExportForm = () => {
    exportFilters.value = {
        format: 'csv',
        fields: [],
        date_range: false,
        date_from: null,
        date_to: null,
        status_filter: 'all',
        include_contacts: false,
        include_addresses: false,
        include_credit_info: false,
        include_aging_data: false
    }
    initializeFields()
}

const getFormatSeverity = (format) => {
    switch (format) {
        case 'csv': return 'success'
        case 'json': return 'info'
        case 'xlsx': return 'warning'
        default: return 'secondary'
    }
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString()
}

const selectedFieldsLabel = computed(() => {
    const count = exportFilters.value.fields.length
    return count === 0 ? 'No fields selected' : `${count} field${count !== 1 ? 's' : ''} selected`
})

onMounted(() => {
    initializeFields()
    loadRecentExports()
})
</script>

<template>
    <div>
        <Toast />
        
        <!-- Export Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Export Customers</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Export customer data in various formats with custom filters
                </p>
            </div>
            
            <div class="flex gap-2">
                <Button
                    v-if="props.can.export"
                    label="Export Customers"
                    icon="pi pi-download"
                    @click="showExportDialog = true"
                />
            </div>
        </div>

        <!-- Export Options Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl mb-2">📊</div>
                        <div class="font-medium text-gray-900">CSV Export</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Comma-separated values for spreadsheets
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            Compatible with Excel, Google Sheets
                        </div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl mb-2">🔧</div>
                        <div class="font-medium text-gray-900">JSON Export</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Structured data for applications
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            Includes nested relationships
                        </div>
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl mb-2">📈</div>
                        <div class="font-medium text-gray-900">Excel Export</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Native Excel format with formatting
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            Advanced filtering and calculations
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Recent Exports -->
        <Card class="mb-6">
            <template #title>
                Recent Exports
            </template>
            <template #content>
                <DataTable 
                    :value="recentExports" 
                    :loading="loadingExports"
                    responsiveLayout="scroll"
                    :paginator="recentExports.length > 10"
                    :rows="10"
                >
                    <Column field="filename" header="File Name">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <Tag :value="data.format.toUpperCase()" :severity="getFormatSeverity(data.format)" />
                                <span>{{ data.filename }}</span>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="total_records" header="Records">
                        <template #body="{ data }">
                            {{ data.total_records?.toLocaleString() || 0 }}
                        </template>
                    </Column>
                    
                    <Column field="file_size" header="Size">
                        <template #body="{ data }">
                            {{ formatFileSize(data.file_size) }}
                        </template>
                    </Column>
                    
                    <Column field="exported_by" header="Exported By">
                        <template #body="{ data }">
                            {{ data.exported_by || 'System' }}
                        </template>
                    </Column>
                    
                    <Column field="created_at" header="Date">
                        <template #body="{ data }">
                            {{ formatDateTime(data.created_at) }}
                        </template>
                    </Column>
                    
                    <Column field="expires_at" header="Expires">
                        <template #body="{ data }">
                            {{ formatDate(data.expires_at) }}
                        </template>
                    </Column>
                    
                    <Column header="Actions">
                        <template #body="{ data }">
                            <div class="flex gap-1">
                                <Button
                                    icon="pi pi-download"
                                    size="small"
                                    text
                                    rounded
                                    @click="downloadExport(data)"
                                    v-tooltip="'Download'"
                                    :disabled="new Date(data.expires_at) < new Date()"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
                
                <div v-if="recentExports.length === 0 && !loadingExports" class="text-center py-8 text-gray-500">
                    <div class="text-lg font-medium mb-2">No recent exports</div>
                    <p class="text-sm">Create your first customer export to get started.</p>
                    <Button 
                        v-if="props.can.export"
                        label="Export Customers"
                        icon="pi pi-download"
                        @click="showExportDialog = true"
                        class="mt-4"
                    />
                </div>
            </template>
        </Card>

        <!-- Export Dialog -->
        <Dialog 
            v-model:visible="showExportDialog" 
            header="Export Customers"
            :style="{ width: '700px' }"
            :modal="true"
            :maximizable="true"
        >
            <div class="space-y-6">
                <!-- Format Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Export Format
                    </label>
                    <Dropdown
                        v-model="exportFilters.format"
                        :options="exportFormats"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select format"
                        class="w-full"
                    />
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Customer Status
                    </label>
                    <Dropdown
                        v-model="exportFilters.status_filter"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select status"
                        class="w-full"
                    />
                </div>
                
                <!-- Date Range -->
                <div>
                    <div class="flex items-center mb-2">
                        <Checkbox
                            id="date_range"
                            v-model="exportFilters.date_range"
                            binary
                        />
                        <label for="date_range" class="ml-2 text-sm font-medium text-gray-700">
                            Filter by Date Range
                        </label>
                    </div>
                    
                    <div v-if="exportFilters.date_range" class="grid grid-cols-2 gap-4 mt-2">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">From Date</label>
                            <Calendar
                                v-model="exportFilters.date_from"
                                :showIcon="true"
                                dateFormat="yy-mm-dd"
                                placeholder="Start date"
                                class="w-full"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">To Date</label>
                            <Calendar
                                v-model="exportFilters.date_to"
                                :showIcon="true"
                                dateFormat="yy-mm-dd"
                                placeholder="End date"
                                class="w-full"
                            />
                        </div>
                    </div>
                </div>
                
                <!-- Field Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fields to Export: {{ selectedFieldsLabel }}
                    </label>
                    <div class="grid grid-cols-2 gap-2 max-h-32 overflow-y-auto border rounded p-2">
                        <div v-for="field in availableFields" :key="field.value" class="flex items-center">
                            <Checkbox
                                :id="field.value"
                                v-model="exportFilters.fields"
                                :value="field.value"
                            />
                            <label :for="field.value" class="ml-2 text-sm">{{ field.label }}</label>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Options -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Additional Data
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <Checkbox
                                id="include_contacts"
                                v-model="exportFilters.include_contacts"
                                binary
                            />
                            <label for="include_contacts" class="ml-2 text-sm">
                                Include Contact Information
                            </label>
                        </div>
                        <div class="flex items-center">
                            <Checkbox
                                id="include_addresses"
                                v-model="exportFilters.include_addresses"
                                binary
                            />
                            <label for="include_addresses" class="ml-2 text-sm">
                                Include Addresses
                            </label>
                        </div>
                        <div class="flex items-center">
                            <Checkbox
                                id="include_credit_info"
                                v-model="exportFilters.include_credit_info"
                                binary
                            />
                            <label for="include_credit_info" class="ml-2 text-sm">
                                Include Credit Limit Information
                            </label>
                        </div>
                        <div class="flex items-center">
                            <Checkbox
                                id="include_aging_data"
                                v-model="exportFilters.include_aging_data"
                                binary
                            />
                            <label for="include_aging_data" class="ml-2 text-sm">
                                Include Current Aging Data
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Export Preview -->
                <Message severity="info" :closable="false">
                    <div class="text-sm">
                        <div class="font-medium mb-1">Export Summary:</div>
                        <ul class="space-y-1 text-xs">
                            <li>• Format: {{ exportFilters.format.toUpperCase() }}</li>
                            <li>• Status Filter: {{ statusOptions.find(s => s.value === exportFilters.status_filter)?.label }}</li>
                            <li>• Fields: {{ exportFilters.value.fields.length }} selected</li>
                            <li>• Date Range: {{ exportFilters.date_range ? 'Applied' : 'Not applied' }}</li>
                            <li>• Additional Data: {{ [
                                exportFilters.include_contacts && 'Contacts',
                                exportFilters.include_addresses && 'Addresses', 
                                exportFilters.include_credit_info && 'Credit Info',
                                exportFilters.include_aging_data && 'Aging Data'
                            ].filter(Boolean).join(', ') || 'None' }}</li>
                        </ul>
                    </div>
                </Message>
            </div>
            
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    text
                    @click="showExportDialog = false"
                />
                <Button
                    label="Export"
                    icon="pi pi-download"
                    :loading="exporting"
                    @click="exportCustomers"
                />
            </template>
        </Dialog>
        
        <!-- Loading State -->
        <div v-if="loadingExports && recentExports.length === 0" class="text-center py-12">
            <ProgressBar mode="indeterminate" class="w-48 mx-auto" />
            <p class="text-gray-600 mt-4">Loading export history...</p>
        </div>
    </div>
</template>