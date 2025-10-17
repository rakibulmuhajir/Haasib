<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import FileUpload from 'primevue/fileupload'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Message from 'primevue/message'
import Badge from 'primevue/badge'

const props = defineProps({
    bankAccounts: Array,
    recentStatements: Array,
    permissions: Object,
})

const toast = useToast()

const form = useForm({
    bank_account_id: null,
    statement_file: null,
    statement_period_start: null,
    statement_period_end: null,
    opening_balance: null,
    closing_balance: null,
    currency: 'USD',
})

const isUploading = ref(false)
const uploadProgress = ref(0)
const pollingIntervals = ref(new Map())

const currencies = [
    { code: 'USD', name: 'US Dollar' },
    { code: 'EUR', name: 'Euro' },
    { code: 'GBP', name: 'British Pound' },
    { code: 'CAD', name: 'Canadian Dollar' },
    { code: 'AUD', name: 'Australian Dollar' },
]

const getStatusSeverity = (status) => {
    switch (status) {
        case 'processed': return 'success'
        case 'processing': return 'info'
        case 'failed': return 'danger'
        case 'pending': return 'warning'
        default: return 'secondary'
    }
}

const getFormatBadge = (format) => {
    const colors = {
        csv: 'info',
        ofx: 'success',
        qfx: 'warning',
    }
    return colors[format] || 'secondary'
}

const onFileSelect = (event) => {
    const file = event.files[0]
    if (file) {
        form.statement_file = file
        
        // Try to extract dates from filename if possible
        const dateMatch = file.name.match(/(\d{4})[_-]?(\d{2})[_-]?(\d{2})/)
        if (dateMatch) {
            const [, year, month, day] = dateMatch
            const extractedDate = `${year}-${month}-${day}`
            form.statement_period_start = extractedDate
            form.statement_period_end = extractedDate
        }
    }
}

const uploadStatement = async () => {
    try {
        isUploading.value = true
        uploadProgress.value = 0

        const formData = new FormData()
        formData.append('bank_account_id', form.bank_account_id)
        formData.append('statement_file', form.statement_file)
        formData.append('statement_period_start', form.statement_period_start)
        formData.append('statement_period_end', form.statement_period_end)
        formData.append('opening_balance', form.opening_balance)
        formData.append('closing_balance', form.closing_balance)
        formData.append('currency', form.currency)

        const response = await axios.post(route('bank-reconciliation.statements.import'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round(
                    (progressEvent.loaded * 100) / progressEvent.total
                )
            },
        })

        toast.add({
            severity: 'success',
            summary: 'Upload Successful',
            detail: 'Your bank statement has been uploaded and is being processed.',
            life: 5000,
        })

        // Start polling for processing status
        startStatusPolling(response.data.statement.id)

        // Reset form
        form.reset()
        uploadProgress.value = 0

        // Refresh recent statements
        await fetchRecentStatements()

    } catch (error) {
        isUploading.value = false
        uploadProgress.value = 0

        let errorMessage = 'Upload failed. Please try again.'
        
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message
        } else if (error.response?.data?.errors) {
            errorMessage = Object.values(error.response.data.errors).flat().join(', ')
        }

        toast.add({
            severity: 'error',
            summary: 'Upload Failed',
            detail: errorMessage,
            life: 5000,
        })
    }
}

const startStatusPolling = (statementId) => {
    const interval = setInterval(async () => {
        try {
            const response = await axios.get(route('bank-reconciliation.statements.status', statementId))
            const statement = response.data

            if (statement.is_processed || statement.status === 'failed') {
                clearInterval(interval)
                pollingIntervals.value.delete(statementId)
                
                if (statement.is_processed) {
                    toast.add({
                        severity: 'success',
                        summary: 'Processing Complete',
                        detail: `Statement processed successfully with ${statement.lines_count} lines.`,
                        life: 5000,
                    })
                } else {
                    toast.add({
                        severity: 'error',
                        summary: 'Processing Failed',
                        detail: 'Statement processing failed. Please check the file and try again.',
                        life: 5000,
                    })
                }
                
                await fetchRecentStatements()
            }
        } catch (error) {
            clearInterval(interval)
            pollingIntervals.value.delete(statementId)
        }
    }, 3000) // Poll every 3 seconds

    pollingIntervals.value.set(statementId, interval)
}

const fetchRecentStatements = async () => {
    try {
        const response = await axios.get(route('bank-reconciliation.import'))
        // Update recent statements through Inertia reload
        window.location.reload()
    } catch (error) {
        console.error('Failed to fetch recent statements:', error)
    }
}

const deleteStatement = async (statementId) => {
    if (!confirm('Are you sure you want to delete this bank statement?')) {
        return
    }

    try {
        await axios.delete(route('bank-reconciliation.statements.destroy', statementId))
        
        toast.add({
            severity: 'success',
            summary: 'Statement Deleted',
            detail: 'Bank statement has been deleted successfully.',
            life: 3000,
        })

        await fetchRecentStatements()
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Deletion Failed',
            detail: 'Failed to delete the bank statement.',
            life: 3000,
        })
    }
}

const downloadStatement = async (statementId, statementName) => {
    try {
        const response = await axios.get(route('bank-reconciliation.statements.download', statementId), {
            responseType: 'blob',
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', statementName)
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Download Failed',
            detail: 'Failed to download the bank statement.',
            life: 3000,
        })
    }
}

const isFormValid = computed(() => {
    return form.bank_account_id &&
           form.statement_file &&
           form.statement_period_start &&
           form.statement_period_end &&
           form.opening_balance !== null &&
           form.closing_balance !== null &&
           form.currency
})

// Cleanup polling intervals on unmount
onUnmounted(() => {
    pollingIntervals.value.forEach(interval => clearInterval(interval))
})
</script>

<template>
    <Head title="Bank Statement Import" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        Bank Statement Import
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Upload CSV, OFX, or QFX bank statements for reconciliation
                    </p>
                </div>
                
                <Link 
                    :href="route('ledger.index')"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                    Back to Ledger
                </Link>
            </div>

            <!-- Upload Form -->
            <Card>
                <template #title>
                    Upload New Statement
                </template>
                
                <template #content>
                    <form @submit.prevent="uploadStatement" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Bank Account -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Bank Account *
                                </label>
                                <Dropdown
                                    v-model="form.bank_account_id"
                                    :options="bankAccounts"
                                    option-label="name"
                                    option-value="id"
                                    placeholder="Select bank account"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors.bank_account_id }"
                                />
                                <p v-if="form.errors.bank_account_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.bank_account_id }}
                                </p>
                            </div>

                            <!-- Currency -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Currency *
                                </label>
                                <Dropdown
                                    v-model="form.currency"
                                    :options="currencies"
                                    option-label="name"
                                    option-value="code"
                                    placeholder="Select currency"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors.currency }"
                                />
                                <p v-if="form.errors.currency" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.currency }}
                                </p>
                            </div>

                            <!-- Period Start -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Period Start *
                                </label>
                                <Calendar
                                    v-model="form.statement_period_start"
                                    date-format="yy-mm-dd"
                                    placeholder="YYYY-MM-DD"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors.statement_period_start }"
                                />
                                <p v-if="form.errors.statement_period_start" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.statement_period_start }}
                                </p>
                            </div>

                            <!-- Period End -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Period End *
                                </label>
                                <Calendar
                                    v-model="form.statement_period_end"
                                    date-format="yy-mm-dd"
                                    placeholder="YYYY-MM-DD"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors.statement_period_end }"
                                />
                                <p v-if="form.errors.statement_period_end" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.statement_period_end }}
                                </p>
                            </div>

                            <!-- Opening Balance -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Opening Balance *
                                </label>
                                <InputNumber
                                    v-model="form.opening_balance"
                                    mode="currency"
                                    currency="USD"
                                    locale="en-US"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors.opening_balance }"
                                />
                                <p v-if="form.errors.opening_balance" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.opening_balance }}
                                </p>
                            </div>

                            <!-- Closing Balance -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Closing Balance *
                                </label>
                                <InputNumber
                                    v-model="form.closing_balance"
                                    mode="currency"
                                    currency="USD"
                                    locale="en-US"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors.closing_balance }"
                                />
                                <p v-if="form.errors.closing_balance" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.closing_balance }}
                                </p>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Statement File * (CSV, OFX, QFX - Max 10MB)
                            </label>
                            <FileUpload
                                mode="basic"
                                :auto="false"
                                :choose-label="'Choose File'"
                                @select="onFileSelect"
                                :accept="'.csv,.ofx,.qfx'"
                                :max-file-size="10485760"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.statement_file }"
                            />
                            <p v-if="form.statement_file" class="mt-2 text-sm text-gray-600">
                                Selected: {{ form.statement_file.name }}
                            </p>
                            <p v-if="form.errors.statement_file" class="mt-1 text-sm text-red-600">
                                {{ form.errors.statement_file }}
                            </p>
                        </div>

                        <!-- Upload Progress -->
                        <ProgressBar v-if="isUploading" :value="uploadProgress" class="w-full" />

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                label="Upload Statement"
                                :loading="isUploading"
                                :disabled="!isFormValid || isUploading"
                                icon="pi pi-upload"
                            />
                        </div>
                    </form>
                </template>
            </Card>

            <!-- Recent Statements -->
            <Card>
                <template #title>
                    Recent Statements
                </template>
                
                <template #content>
                    <DataTable 
                        :value="recentStatements" 
                        :paginator="true" 
                        :rows="10"
                        :loading="false"
                        striped-rows
                        responsive-layout="scroll"
                    >
                        <Column field="statement_name" header="File Name">
                            <template #body="{ data }">
                                <span class="font-medium">{{ data.statement_name }}</span>
                            </template>
                        </Column>
                        
                        <Column field="bank_account" header="Bank Account" />
                        
                        <Column field="period" header="Period">
                            <template #body="{ data }">
                                {{ data.period_start }} to {{ data.period_end }}
                            </template>
                        </Column>
                        
                        <Column field="status" header="Status">
                            <template #body="{ data }">
                                <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
                            </template>
                        </Column>
                        
                        <Column field="format" header="Format">
                            <template #body="{ data }">
                                <Badge :value="data.format.toUpperCase()" :severity="getFormatBadge(data.format)" />
                            </template>
                        </Column>
                        
                        <Column field="lines_count" header="Lines">
                            <template #body="{ data }">
                                {{ data.lines_count || 'Processing...' }}
                            </template>
                        </Column>
                        
                        <Column field="imported_at" header="Imported">
                            <template #body="{ data }">
                                {{ new Date(data.imported_at).toLocaleDateString() }}
                            </template>
                        </Column>
                        
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex space-x-2">
                                    <Button
                                        icon="pi pi-download"
                                        size="small"
                                        text
                                        rounded
                                        @click="downloadStatement(data.id, data.statement_name)"
                                        v-tooltip="'Download'"
                                    />
                                    
                                    <Button
                                        v-if="data.can_be_reconciled"
                                        icon="pi pi-sync"
                                        size="small"
                                        text
                                        rounded
                                        as="a"
                                        :href="route('bank-reconciliation.workspace.create', { statement_id: data.id })"
                                        v-tooltip="'Start Reconciliation'"
                                    />
                                    
                                    <Button
                                        v-if="permissions.can_delete && data.status !== 'reconciled'"
                                        icon="pi pi-trash"
                                        size="small"
                                        text
                                        rounded
                                        severity="danger"
                                        @click="deleteStatement(data.id)"
                                        v-tooltip="'Delete'"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                    
                    <div v-if="recentStatements.length === 0" class="text-center py-8 text-gray-500">
                        No statements imported yet. Upload your first statement above.
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>