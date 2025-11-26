<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from "@/components/ui/toast/use-toast"
import { useI18n } from 'vue-i18n'
// import Button from 'primevue/button'
// import Card from 'primevue/card'
// import DataTable from 'primevue/datatable'
// import Column from 'primevue/column'
// import Dialog from 'primevue/dialog'
// import FileUpload from 'primevue/fileupload'
// import ProgressBar from 'primevue/progressbar'
// import Tag from 'primevue/tag'
// import Message from 'primevue/message'
// import Textarea from 'primevue/textarea'
import axios from 'axios'

const props = defineProps({
    customer: Object,
    can: Object
})

const toast = useToast()
const { t } = useI18n()

const showImportDialog = ref(false)
const importing = ref(false)
const selectedFile = ref(null)
const importData = ref(null)
const previewData = ref([])
const validationErrors = ref([])
const importResults = ref(null)

const importFormats = [
    { label: 'CSV File', value: 'csv' },
    { label: 'JSON File', value: 'json' },
    { label: 'Manual Entry', value: 'manual' }
]

const selectedFormat = ref('csv')
const manualEntry = ref('')

const sampleCSV = `customer_number,name,email,phone,default_currency,payment_terms,tax_id,website,notes
CUST-001,Acme Corporation,billing@acme.com,+1-555-0123,USD,net_30,12-3456789,https://acme.com,Large enterprise client
CUST-002,Small Business LLC,contact@smallbiz.com,+1-555-0456,USD,net_15,,,
CUST-003,International Co,finance@intl.co.uk,+44-20-1234-5678,GBP,net_45,GB-123456789,https://intl.co.uk,Overseas client`

const sampleJSON = `{
  "customers": [
    {
      "customer_number": "CUST-001",
      "name": "Acme Corporation",
      "email": "billing@acme.com",
      "phone": "+1-555-0123",
      "default_currency": "USD",
      "payment_terms": "net_30",
      "tax_id": "12-3456789",
      "website": "https://acme.com",
      "notes": "Large enterprise client"
    }
  ]
}`

const onFileSelect = (event) => {
    const file = event.files[0]
    if (file) {
        selectedFile.value = file
        parseFile(file)
    }
}

const parseFile = async (file) => {
    try {
        const text = await file.text()
        
        if (selectedFormat.value === 'csv') {
            parseCSV(text)
        } else if (selectedFormat.value === 'json') {
            parseJSON(text)
        }
    } catch (error) {
        console.error('Failed to parse file:', error)
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to parse file. Please check the format.',
            life: 3000
        })
    }
}

const parseCSV = (text) => {
    const lines = text.split('\n').filter(line => line.trim())
    const headers = lines[0].split(',').map(h => h.trim())
    
    const customers = []
    for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',').map(v => v.trim())
        const customer = {}
        
        headers.forEach((header, index) => {
            customer[header] = values[index] || ''
        })
        
        customers.push(customer)
    }
    
    importData.value = { customers }
    generatePreview()
}

const parseJSON = (text) => {
    try {
        const data = JSON.parse(text)
        importData.value = data
        generatePreview()
    } catch (error) {
        throw new Error('Invalid JSON format')
    }
}

const parseManualEntry = () => {
    try {
        const data = JSON.parse(manualEntry.value)
        importData.value = data
        generatePreview()
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Invalid JSON format in manual entry',
            life: 3000
        })
    }
}

const generatePreview = () => {
    if (!importData.value?.customers) return
    
    previewData.value = importData.value.customers.slice(0, 10) // Show preview of first 10
    validateData()
}

const validateData = () => {
    validationErrors.value = []
    
    if (!importData.value?.customers) {
        validationErrors.value.push('No customer data found')
        return
    }
    
    const customers = importData.value.customers
    const customerNumbers = new Set()
    const emails = new Set()
    
    customers.forEach((customer, index) => {
        const rowNumber = index + 1
        
        // Required fields
        if (!customer.customer_number) {
            validationErrors.value.push(`Row ${rowNumber}: Customer number is required`)
        } else if (customerNumbers.has(customer.customer_number)) {
            validationErrors.value.push(`Row ${rowNumber}: Duplicate customer number "${customer.customer_number}"`)
        } else {
            customerNumbers.add(customer.customer_number)
        }
        
        if (!customer.name) {
            validationErrors.value.push(`Row ${rowNumber}: Customer name is required`)
        }
        
        // Email validation
        if (customer.email) {
            if (emails.has(customer.email)) {
                validationErrors.value.push(`Row ${rowNumber}: Duplicate email "${customer.email}"`)
            } else {
                emails.add(customer.email)
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
            if (!emailRegex.test(customer.email)) {
                validationErrors.value.push(`Row ${rowNumber}: Invalid email format "${customer.email}"`)
            }
        }
        
        // Currency validation
        if (customer.default_currency && customer.default_currency.length !== 3) {
            validationErrors.value.push(`Row ${rowNumber}: Invalid currency code "${customer.default_currency}"`)
        }
    })
}

const importCustomers = async () => {
    if (!props.can.create) return
    
    importing.value = true
    try {
        const formData = new FormData()
        formData.append('format', selectedFormat.value)
        
        if (selectedFormat.value === 'manual') {
            formData.append('data', JSON.stringify(importData.value))
        } else if (selectedFile.value) {
            formData.append('file', selectedFile.value)
        } else {
            formData.append('data', JSON.stringify(importData.value))
        }
        
        const response = await axios.post('/api/customers/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        
        if (response.data.success) {
            importResults.value = response.data.data
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `Imported ${response.data.data.imported} customers successfully`,
                life: 3000
            })
            
            // Reset form
            showImportDialog.value = false
            resetImportForm()
            
            // Refresh customer list
            window.location.reload()
        }
    } catch (error) {
        console.error('Import failed:', error)
        toast.add({
            severity: 'error',
            summary: 'Import Failed',
            detail: error.response?.data?.message || 'Failed to import customers',
            life: 5000
        })
        
        if (error.response?.data?.errors) {
            validationErrors.value = Object.values(error.response.data.errors).flat()
        }
    } finally {
        importing.value = false
    }
}

const resetImportForm = () => {
    selectedFile.value = null
    importData.value = null
    previewData.value = []
    validationErrors.value = []
    importResults.value = null
    selectedFormat.value = 'csv'
    manualEntry.value = ''
}

const downloadTemplate = (format) => {
    let content = ''
    let filename = ''
    let mimeType = ''
    
    if (format === 'csv') {
        content = sampleCSV
        filename = 'customer_import_template.csv'
        mimeType = 'text/csv'
    } else if (format === 'json') {
        content = sampleJSON
        filename = 'customer_import_template.json'
        mimeType = 'application/json'
    }
    
    const blob = new Blob([content], { type: mimeType })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    
    toast.add({
        severity: 'success',
        summary: 'Template Downloaded',
        detail: `${format.toUpperCase()} template downloaded successfully`,
        life: 3000
    })
}

const hasValidationErrors = computed(() => validationErrors.value.length > 0)
const canImport = computed(() => importData.value && !hasValidationErrors.value)
</script>

<template>
    <div>
        <Toast />
        
        <!-- Import Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Import Customers</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Import customer data from CSV, JSON files or manual entry
                </p>
            </div>
            
            <div class="flex gap-2">
                <Button
                    v-if="props.can.create"
                    label="Import Customers"
                    icon="pi pi-upload"
                    @click="showImportDialog = true"
                />
            </div>
        </div>

        <!-- Import Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl mb-2">📊</div>
                        <div class="font-medium text-gray-900">CSV Import</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Import from spreadsheet files
                        </div>
                        <Button
                            label="Download CSV Template"
                            size="small"
                            text
                            @click="downloadTemplate('csv')"
                            class="mt-2"
                        />
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl mb-2">🔧</div>
                        <div class="font-medium text-gray-900">JSON Import</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Import from structured data files
                        </div>
                        <Button
                            label="Download JSON Template"
                            size="small"
                            text
                            @click="downloadTemplate('json')"
                            class="mt-2"
                        />
                    </div>
                </template>
            </Card>
            
            <Card>
                <template #content>
                    <div class="text-center">
                        <div class="text-2xl mb-2">✏️</div>
                        <div class="font-medium text-gray-900">Manual Entry</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Enter data directly in JSON format
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            For small imports or testing
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Import Instructions -->
        <Card>
            <template #title>
                Import Instructions
            </template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Required Fields</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li><code>customer_number</code> - Unique customer identifier</li>
                            <li><code>name</code> - Customer display name</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Optional Fields</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li><code>email</code> - Primary contact email</li>
                            <li><code>phone</code> - Phone number (E.164 format)</li>
                            <li><code>default_currency</code> - 3-letter currency code (default: USD)</li>
                            <li><code>payment_terms</code> - Payment terms (e.g., net_30)</li>
                            <li><code>tax_id</code> - Tax identification number</li>
                            <li><code>website</code> - Company website URL</li>
                            <li><code>notes</code> - Internal notes</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Validation Rules</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                            <li>Customer numbers must be unique</li>
                            <li>Email addresses must be unique and valid format</li>
                            <li>Currency codes must be 3 letters (ISO 4217)</li>
                            <li>Duplicate entries will be skipped with warnings</li>
                        </ul>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Import Dialog -->
        <Dialog 
            v-model:visible="showImportDialog" 
            header="Import Customers"
            :style="{ width: '800px' }"
            :modal="true"
            :maximizable="true"
        >
            <div class="space-y-4">
                <!-- Format Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Import Format
                    </label>
                    <div class="flex gap-2">
                        <Button
                            v-for="format in importFormats"
                            :key="format.value"
                            :label="format.label"
                            :severity="selectedFormat === format.value ? 'primary' : 'secondary'"
                            @click="selectedFormat = format.value"
                        />
                    </div>
                </div>
                
                <!-- File Upload -->
                <div v-if="selectedFormat !== 'manual'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Select {{ selectedFormat.toUpperCase() }} File
                    </label>
                    <FileUpload
                        mode="basic"
                        :accept="selectedFormat === 'csv' ? '.csv' : '.json'"
                        :auto="true"
                        :customUpload="true"
                        @select="onFileSelect"
                        @upload="onFileSelect"
                        chooseLabel="Browse Files"
                    />
                </div>
                
                <!-- Manual Entry -->
                <div v-if="selectedFormat === 'manual'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Enter Customer Data (JSON Format)
                    </label>
                    <Textarea
                        v-model="manualEntry"
                        rows="10"
                        placeholder='{"customers": [{...}]}'
                        class="w-full font-mono text-sm"
                    />
                    <Button
                        label="Parse Data"
                        icon="pi pi-check"
                        @click="parseManualEntry"
                        class="mt-2"
                    />
                </div>
                
                <!-- Validation Errors -->
                <Message v-if="hasValidationErrors" severity="error" :closable="false">
                    <div class="space-y-1">
                        <div class="font-medium">Validation Errors:</div>
                        <ul class="list-disc list-inside text-sm space-y-1">
                            <li v-for="error in validationErrors" :key="error">{{ error }}</li>
                        </ul>
                    </div>
                </Message>
                
                <!-- Preview -->
                <div v-if="previewData.length > 0">
                    <h4 class="font-medium text-gray-900 mb-2">
                        Preview (first {{ previewData.length }} records)
                    </h4>
                    <DataTable :value="previewData" responsiveLayout="scroll" class="text-sm">
                        <Column field="customer_number" header="Customer #" />
                        <Column field="name" header="Name" />
                        <Column field="email" header="Email" />
                        <Column field="phone" header="Phone" />
                        <Column field="default_currency" header="Currency" />
                    </DataTable>
                </div>
            </div>
            
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    text
                    @click="showImportDialog = false"
                />
                <Button
                    label="Import"
                    icon="pi pi-upload"
                    :loading="importing"
                    :disabled="!canImport"
                    @click="importCustomers"
                />
            </template>
        </Dialog>
    </div>
</template>