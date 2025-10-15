<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Calendar from 'primevue/calendar'
import Dropdown from 'primevue/dropdown'
import Dialog from 'primevue/dialog'
import FileUpload from 'primevue/fileupload'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import axios from 'axios'

const props = defineProps({
    customer: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

const statements = ref([])
const loading = ref(false)
const showGenerateDialog = ref(false)
const generating = ref(false)
const downloading = ref(false)

// Form data for generating statements
const generateForm = ref({
    period_start: null,
    period_end: null,
    format: 'pdf',
    include_paid_invoices: true,
    include_credit_notes: true,
    email_to_customer: false
})

const formats = [
    { label: 'PDF', value: 'pdf' },
    { label: 'CSV', value: 'csv' }
]

const loadStatements = async () => {
    if (!props.can.view) return
    
    loading.value = true
    try {
        const response = await axios.get(`/api/customers/${props.customer.id}/statements`)
        
        if (response.data.success) {
            statements.value = response.data.data
        }
    } catch (error) {
        console.error('Failed to load statements:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load statements',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

const generateStatement = async () => {
    if (!props.can.manage) return
    
    generating.value = true
    try {
        const payload = {
            period_start: generateForm.value.period_start?.toISOString().split('T')[0],
            period_end: generateForm.value.period_end?.toISOString().split('T')[0],
            format: generateForm.value.format,
            options: {
                include_paid_invoices: generateForm.value.include_paid_invoices,
                include_credit_notes: generateForm.value.include_credit_notes,
                email_to_customer: generateForm.value.email_to_customer
            }
        }
        
        const response = await axios.post(`/api/customers/${props.customer.id}/statements/generate`, payload)
        
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Statement generated successfully',
                life: 3000
            })
            
            showGenerateDialog.value = false
            resetGenerateForm()
            await loadStatements()
            
            // Auto-download if not emailed
            if (!generateForm.value.email_to_customer && response.data.data.document_path) {
                downloadStatement(response.data.data)
            }
        }
    } catch (error) {
        console.error('Failed to generate statement:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to generate statement',
            life: 3000
        })
    } finally {
        generating.value = false
    }
}

const downloadStatement = async (statement) => {
    downloading.value = true
    try {
        const response = await axios.get(`/api/customers/${props.customer.id}/statements/${statement.id}/download`, {
            responseType: 'blob'
        })
        
        // Create download link
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `statement-${statement.period_start}-${statement.period_end}.${statement.format}`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Statement downloaded successfully',
            life: 3000
        })
    } catch (error) {
        console.error('Failed to download statement:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to download statement',
            life: 3000
        })
    } finally {
        downloading.value = false
    }
}

const emailStatement = async (statement) => {
    try {
        const response = await axios.post(`/api/customers/${props.customer.id}/statements/${statement.id}/email`)
        
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Statement emailed successfully',
                life: 3000
            })
        }
    } catch (error) {
        console.error('Failed to email statement:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to email statement',
            life: 3000
        })
    }
}

const deleteStatement = async (statement) => {
    if (!confirm('Are you sure you want to delete this statement?')) return
    
    try {
        const response = await axios.delete(`/api/customers/${props.customer.id}/statements/${statement.id}`)
        
        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Statement deleted successfully',
                life: 3000
            })
            
            await loadStatements()
        }
    } catch (error) {
        console.error('Failed to delete statement:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete statement',
            life: 3000
        })
    }
}

const resetGenerateForm = () => {
    generateForm.value = {
        period_start: null,
        period_end: null,
        format: 'pdf',
        include_paid_invoices: true,
        include_credit_notes: true,
        email_to_customer: false
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

const getFormatSeverity = (format) => {
    return format === 'pdf' ? 'info' : 'success'
}

const recentStatements = computed(() => {
    return statements.value.slice(0, 10) // Show only recent statements
})

onMounted(() => {
    loadStatements()
})
</script>

<template>
    <div>
        <Toast />
        
        <!-- Statements Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ t('customers.statements.title') }}</h3>
                <p class="text-sm text-gray-600 mt-1">
                    View and generate customer statements
                </p>
            </div>
            
            <div class="flex gap-2">
                <Button
                    v-if="props.can.manage"
                    :label="t('customers.statements.generate')"
                    icon="pi pi-file-pdf"
                    @click="showGenerateDialog = true"
                />
            </div>
        </div>

        <!-- Recent Statements -->
        <Card>
            <template #title>
                Recent Statements
            </template>
            <template #content>
                <DataTable 
                    :value="recentStatements" 
                    :loading="loading"
                    responsiveLayout="scroll"
                    :paginator="statements.length > 10"
                    :rows="10"
                >
                    <Column field="period_start" header="Period Start">
                        <template #body="{ data }">
                            {{ formatDate(data.period_start) }}
                        </template>
                    </Column>
                    
                    <Column field="period_end" header="Period End">
                        <template #body="{ data }">
                            {{ formatDate(data.period_end) }}
                        </template>
                    </Column>
                    
                    <Column field="opening_balance" header="Opening Balance">
                        <template #body="{ data }">
                            {{ formatCurrency(data.opening_balance, customer.default_currency) }}
                        </template>
                    </Column>
                    
                    <Column field="total_invoiced" header="Invoiced">
                        <template #body="{ data }">
                            {{ formatCurrency(data.total_invoiced, customer.default_currency) }}
                        </template>
                    </Column>
                    
                    <Column field="total_paid" header="Paid">
                        <template #body="{ data }">
                            {{ formatCurrency(data.total_paid, customer.default_currency) }}
                        </template>
                    </Column>
                    
                    <Column field="closing_balance" header="Closing Balance">
                        <template #body="{ data }">
                            {{ formatCurrency(data.closing_balance, customer.default_currency) }}
                        </template>
                    </Column>
                    
                    <Column field="format" header="Format">
                        <template #body="{ data }">
                            <Tag :value="data.format.toUpperCase()" :severity="getFormatSeverity(data.format)" />
                        </template>
                    </Column>
                    
                    <Column field="generated_at" header="Generated">
                        <template #body="{ data }">
                            {{ formatDateTime(data.generated_at) }}
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
                                    :loading="downloading"
                                    @click="downloadStatement(data)"
                                    v-tooltip="'Download'"
                                />
                                
                                <Button
                                    v-if="props.can.manage && customer.email"
                                    icon="pi pi-envelope"
                                    size="small"
                                    text
                                    rounded
                                    @click="emailStatement(data)"
                                    v-tooltip="'Email to Customer'"
                                />
                                
                                <Button
                                    v-if="props.can.manage"
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    rounded
                                    severity="danger"
                                    @click="deleteStatement(data)"
                                    v-tooltip="'Delete'"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
                
                <div v-if="statements.length === 0 && !loading" class="text-center py-8 text-gray-500">
                    <div class="text-lg font-medium mb-2">No statements available</div>
                    <p class="text-sm">Generate your first customer statement to get started.</p>
                    <Button 
                        v-if="props.can.manage"
                        :label="t('customers.statements.generate')"
                        icon="pi pi-file-pdf"
                        @click="showGenerateDialog = true"
                        class="mt-4"
                    />
                </div>
            </template>
        </Card>

        <!-- Generate Statement Dialog -->
        <Dialog 
            v-model:visible="showGenerateDialog" 
            :header="t('customers.statements.generate')"
            :style="{ width: '450px' }"
            :modal="true"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('customers.statements.period_start') }}
                    </label>
                    <Calendar
                        v-model="generateForm.period_start"
                        :showIcon="true"
                        dateFormat="yy-mm-dd"
                        placeholder="Select start date"
                        class="w-full"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ t('customers.statements.period_end') }}
                    </label>
                    <Calendar
                        v-model="generateForm.period_end"
                        :showIcon="true"
                        dateFormat="yy-mm-dd"
                        placeholder="Select end date"
                        class="w-full"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Format
                    </label>
                    <Dropdown
                        v-model="generateForm.format"
                        :options="formats"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select format"
                        class="w-full"
                    />
                </div>
                
                <div class="space-y-2">
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="include_paid_invoices"
                            v-model="generateForm.include_paid_invoices"
                            class="mr-2"
                        />
                        <label for="include_paid_invoices" class="text-sm">
                            Include paid invoices
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="include_credit_notes"
                            v-model="generateForm.include_credit_notes"
                            class="mr-2"
                        />
                        <label for="include_credit_notes" class="text-sm">
                            Include credit notes
                        </label>
                    </div>
                    
                    <div v-if="customer.email" class="flex items-center">
                        <input
                            type="checkbox"
                            id="email_to_customer"
                            v-model="generateForm.email_to_customer"
                            class="mr-2"
                        />
                        <label for="email_to_customer" class="text-sm">
                            Email to {{ customer.email }}
                        </label>
                    </div>
                </div>
            </div>
            
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    text
                    @click="showGenerateDialog = false"
                />
                <Button
                    label="Generate"
                    icon="pi pi-check"
                    :loading="generating"
                    @click="generateStatement"
                />
            </template>
        </Dialog>
        
        <!-- Loading State -->
        <div v-if="loading && statements.length === 0" class="text-center py-12">
            <ProgressBar mode="indeterminate" class="w-48 mx-auto" />
            <p class="text-gray-600 mt-4">Loading statements...</p>
        </div>
    </div>
</template>