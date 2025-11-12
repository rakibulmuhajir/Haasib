<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Textarea from 'primevue/textarea'
import AutoComplete from 'primevue/autocomplete'
import Dialog from 'primevue/dialog'
import Toast from 'primevue/toast'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Divider from 'primevue/divider'
import Fieldset from 'primevue/fieldset'
import CompanySwitcher from '@/Components/CompanySwitcher.vue'
import CommandPalette from '@/Components/CommandPalette.vue'

const { t } = useI18n()
const page = usePage()

const emit = defineEmits(['invoice-created'])

// Refs
const toast = ref()
const commandPalette = ref()
const loading = ref(false)
const saving = ref(false)
const customers = ref([])
const products = ref([])
const taxRates = ref([])
const previewDialog = ref(false)
const customerDialog = ref(false)

// Form data
const invoice = ref({
    customer_id: null,
    invoice_number: '',
    invoice_date: new Date(),
    due_date: new Date(new Date().setDate(new Date().getDate() + 30)),
    status: 'draft',
    currency: 'USD',
    notes: '',
    terms: '',
    items: [
        {
            id: Date.now(),
            description: '',
            quantity: 1,
            unit_price: 0,
            tax_rate_id: null,
            discount: 0,
            total: 0
        }
    ]
})

// New customer form
const newCustomer = ref({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    country: '',
    tax_number: ''
})

// Computed properties
const currentCompany = computed(() => page.props.current_company)
const user = computed(() => page.props.auth?.user)

const subtotal = computed(() => {
    return invoice.value.items.reduce((sum, item) => {
        return sum + (item.quantity * item.unit_price)
    }, 0)
})

const totalTax = computed(() => {
    return invoice.value.items.reduce((sum, item) => {
        const itemTotal = item.quantity * item.unit_price
        const taxRate = taxRates.value.find(t => t.id === item.tax_rate_id)?.rate || 0
        return sum + (itemTotal * (taxRate / 100))
    }, 0)
})

const totalDiscount = computed(() => {
    return invoice.value.items.reduce((sum, item) => {
        const itemTotal = item.quantity * item.unit_price
        return sum + (itemTotal * (item.discount / 100))
    }, 0)
})

const grandTotal = computed(() => {
    return subtotal.value + totalTax.value - totalDiscount.value
})

const hasItems = computed(() => invoice.value.items.some(item => item.description && item.unit_price > 0))

// Methods
const loadCustomers = async () => {
    try {
        const response = await fetch('/api/v1/customers')
        const data = await response.json()
        
        if (response.ok) {
            customers.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to load customers:', error)
    }
}

const loadProducts = async () => {
    try {
        const response = await fetch('/api/v1/products')
        const data = await response.json()
        
        if (response.ok) {
            products.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to load products:', error)
    }
}

const loadTaxRates = async () => {
    try {
        const response = await fetch('/api/v1/tax-rates')
        const data = await response.json()
        
        if (response.ok) {
            taxRates.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to load tax rates:', error)
    }
}

const generateInvoiceNumber = async () => {
    try {
        const response = await fetch('/api/v1/invoices/generate-number', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })

        if (response.ok) {
            const data = await response.json()
            invoice.value.invoice_number = data.invoice_number
        }
    } catch (error) {
        console.error('Failed to generate invoice number:', error)
        // Fallback: generate simple number
        const timestamp = Date.now()
        invoice.value.invoice_number = `INV-${timestamp}`
    }
}

const addNewItem = () => {
    invoice.value.items.push({
        id: Date.now(),
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_rate_id: null,
        discount: 0,
        total: 0
    })
}

const removeItem = (index) => {
    if (invoice.value.items.length > 1) {
        invoice.value.items.splice(index, 1)
        updateItemTotals()
    }
}

const updateItemTotal = (item) => {
    item.total = item.quantity * item.unit_price
}

const updateItemTotals = () => {
    invoice.value.items.forEach(item => {
        updateItemTotal(item)
    })
}

const selectCustomer = (customer) => {
    invoice.value.customer_id = customer.id
}

const searchCustomers = async (event) => {
    const query = event.query.toLowerCase()
    
    if (!query) {
        return customers.value.slice(0, 10)
    }

    return customers.value.filter(customer => 
        customer.name.toLowerCase().includes(query) ||
        customer.email.toLowerCase().includes(query)
    ).slice(0, 10)
}

const selectProduct = (item, product) => {
    item.description = product.name
    item.unit_price = product.price
    updateItemTotal(item)
}

const searchProducts = async (event) => {
    const query = event.query.toLowerCase()
    
    if (!query) {
        return products.value.slice(0, 10)
    }

    return products.value.filter(product => 
        product.name.toLowerCase().includes(query) ||
        product.description.toLowerCase().includes(query)
    ).slice(0, 10)
}

const saveAsDraft = async () => {
    invoice.value.status = 'draft'
    await saveInvoice()
}

const saveAndSend = async () => {
    invoice.value.status = 'sent'
    await saveInvoice(true)
}

const saveInvoice = async (send = false) => {
    if (!hasItems.value) {
        toast.value.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please add at least one item to the invoice',
            life: 3000
        })
        return
    }

    saving.value = true

    try {
        const response = await fetch('/api/v1/invoices', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                ...invoice.value,
                subtotal: subtotal.value,
                total_tax: totalTax.value,
                total_discount: totalDiscount.value,
                grand_total: grandTotal.value
            })
        })

        const data = await response.json()

        if (response.ok) {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: send ? 'Invoice created and sent successfully' : 'Invoice saved successfully',
                life: 3000
            })

            emit('invoice-created', data.data)

            // Redirect to invoice list or view page
            setTimeout(() => {
                router.visit('/invoicing')
            }, 1500)
        } else {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: data.message || 'Failed to save invoice',
                life: 3000
            })
        }
    } catch (error) {
        console.error('Failed to save invoice:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Network Error',
            detail: 'Failed to save invoice',
            life: 3000
        })
    } finally {
        saving.value = false
    }
}

const createCustomer = async () => {
    if (!newCustomer.value.name) {
        toast.value.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Customer name is required',
            life: 3000
        })
        return
    }

    try {
        const response = await fetch('/api/v1/customers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(newCustomer.value)
        })

        const data = await response.json()

        if (response.ok) {
            customers.value.push(data.data)
            invoice.value.customer_id = data.data.id
            customerDialog.value = false
            
            // Reset new customer form
            newCustomer.value = {
                name: '',
                email: '',
                phone: '',
                address: '',
                city: '',
                country: '',
                tax_number: ''
            }

            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Customer created successfully',
                life: 3000
            })
        } else {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: data.message || 'Failed to create customer',
                life: 3000
            })
        }
    } catch (error) {
        console.error('Failed to create customer:', error)
        toast.value.add({
            severity: 'error',
            summary: 'Network Error',
            detail: 'Failed to create customer',
            life: 3000
        })
    }
}

const showPreview = () => {
    if (!hasItems.value) {
        toast.value.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please add at least one item to preview',
            life: 3000
        })
        return
    }
    previewDialog.value = true
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount)
}

const getTaxRateLabel = (taxRateId) => {
    const taxRate = taxRates.value.find(t => t.id === taxRateId)
    return taxRate ? `${taxRate.name} (${taxRate.rate}%)` : 'No Tax'
}

// Lifecycle
onMounted(async () => {
    loading.value = true
    
    await Promise.all([
        loadCustomers(),
        loadProducts(),
        loadTaxRates(),
        generateInvoiceNumber()
    ])
    
    loading.value = false
})
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center space-x-4">
                        <Button 
                            @click="$inertia.visit('/invoicing')"
                            icon="fas fa-arrow-left"
                            text
                        />
                        <i class="fas fa-file-invoice text-2xl text-blue-600 dark:text-blue-400"></i>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Create Invoice
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <CompanySwitcher />
                        <CommandPalette ref="commandPalette" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center py-12">
                <ProgressSpinner />
            </div>

            <!-- Invoice Form -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Customer Information -->
                    <Card class="shadow-md">
                        <template #title>
                            Customer Information
                        </template>
                        <template #content>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Customer *
                                    </label>
                                    <div class="flex space-x-2">
                                        <AutoComplete 
                                            v-model="invoice.customer_id"
                                            :suggestions="searchCustomers"
                                            optionLabel="name"
                                            optionValue="id"
                                            placeholder="Search or select customer..."
                                            class="flex-1"
                                            :dropdown="true"
                                            forceSelection
                                        />
                                        <Button 
                                            @click="customerDialog = true"
                                            icon="fas fa-plus"
                                            label="New Customer"
                                            text
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- Invoice Details -->
                    <Card class="shadow-md">
                        <template #title>
                            Invoice Details
                        </template>
                        <template #content>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Number
                                    </label>
                                    <InputText 
                                        v-model="invoice.invoice_number"
                                        class="w-full"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Currency
                                    </label>
                                    <Dropdown 
                                        v-model="invoice.currency"
                                        :options="[
                                            { label: 'USD', value: 'USD' },
                                            { label: 'EUR', value: 'EUR' },
                                            { label: 'GBP', value: 'GBP' }
                                        ]"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-full"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Date
                                    </label>
                                    <Calendar 
                                        v-model="invoice.invoice_date"
                                        dateFormat="yy-mm-dd"
                                        class="w-full"
                                        showButtonBar
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Due Date
                                    </label>
                                    <Calendar 
                                        v-model="invoice.due_date"
                                        dateFormat="yy-mm-dd"
                                        class="w-full"
                                        showButtonBar
                                    />
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Notes
                                </label>
                                <Textarea 
                                    v-model="invoice.notes"
                                    placeholder="Additional notes for the customer..."
                                    class="w-full"
                                    rows="3"
                                />
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Terms & Conditions
                                </label>
                                <Textarea 
                                    v-model="invoice.terms"
                                    placeholder="Payment terms and conditions..."
                                    class="w-full"
                                    rows="3"
                                />
                            </div>
                        </template>
                    </Card>

                    <!-- Line Items -->
                    <Card class="shadow-md">
                        <template #title>
                            <div class="flex justify-between items-center">
                                <span>Line Items</span>
                                <Button 
                                    @click="addNewItem"
                                    icon="fas fa-plus"
                                    label="Add Item"
                                    text
                                    size="small"
                                />
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-4">
                                <div 
                                    v-for="(item, index) in invoice.items" 
                                    :key="item.id"
                                    class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                                >
                                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Description *
                                            </label>
                                            <div class="space-y-2">
                                                <AutoComplete 
                                                    v-model="item.description"
                                                    :suggestions="searchProducts"
                                                    optionLabel="name"
                                                    placeholder="Search products or enter description..."
                                                    class="w-full"
                                                    :dropdown="true"
                                                    @option-select="selectProduct(item, $event.value)"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Quantity
                                            </label>
                                            <InputNumber 
                                                v-model="item.quantity"
                                                :min="1"
                                                class="w-full"
                                                @input="updateItemTotal(item)"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Unit Price *
                                            </label>
                                            <InputNumber 
                                                v-model="item.unit_price"
                                                :min="0"
                                                mode="currency"
                                                currency="USD"
                                                class="w-full"
                                                @input="updateItemTotal(item)"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Tax Rate
                                            </label>
                                            <Dropdown 
                                                v-model="item.tax_rate_id"
                                                :options="taxRates"
                                                optionLabel="label"
                                                optionValue="id"
                                                placeholder="No Tax"
                                                class="w-full"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Discount %
                                            </label>
                                            <InputNumber 
                                                v-model="item.discount"
                                                :min="0"
                                                :max="100"
                                                class="w-full"
                                            />
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-between items-center">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Line Total: {{ formatCurrency(item.total) }}
                                        </div>
                                        <Button 
                                            v-if="invoice.items.length > 1"
                                            @click="removeItem(index)"
                                            icon="fas fa-trash"
                                            text
                                            size="small"
                                            severity="danger"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Summary -->
                    <Card class="shadow-md">
                        <template #title>
                            Invoice Summary
                        </template>
                        <template #content>
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(subtotal) }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Tax:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(totalTax) }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Discount:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        -{{ formatCurrency(totalDiscount) }}
                                    </span>
                                </div>
                                <Divider />
                                <div class="flex justify-between">
                                    <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                        Total:
                                    </span>
                                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                        {{ formatCurrency(grandTotal) }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- Actions -->
                    <Card class="shadow-md">
                        <template #content>
                            <div class="space-y-3">
                                <Button 
                                    @click="showPreview"
                                    icon="fas fa-eye"
                                    label="Preview Invoice"
                                    class="w-full"
                                    outlined
                                />
                                <Button 
                                    @click="saveAsDraft"
                                    icon="fas fa-save"
                                    label="Save as Draft"
                                    class="w-full"
                                    :loading="saving"
                                />
                                <Button 
                                    @click="saveAndSend"
                                    icon="fas fa-paper-plane"
                                    label="Save & Send"
                                    class="w-full"
                                    severity="success"
                                    :loading="saving"
                                />
                            </div>
                        </template>
                    </Card>
                </div>
            </div>
        </div>

        <!-- New Customer Dialog -->
        <Dialog 
            v-model:visible="customerDialog" 
            modal 
            header="Create New Customer"
            :style="{ width: '500px' }"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Customer Name *
                    </label>
                    <InputText 
                        v-model="newCustomer.name"
                        class="w-full"
                        placeholder="Enter customer name"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Email
                    </label>
                    <InputText 
                        v-model="newCustomer.email"
                        type="email"
                        class="w-full"
                        placeholder="customer@example.com"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Phone
                    </label>
                    <InputText 
                        v-model="newCustomer.phone"
                        class="w-full"
                        placeholder="+1 (555) 123-4567"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Address
                    </label>
                    <Textarea 
                        v-model="newCustomer.address"
                        class="w-full"
                        placeholder="123 Main St, City, State 12345"
                        rows="2"
                    />
                </div>
            </div>

            <template #footer>
                <Button 
                    @click="customerDialog = false"
                    :label="$t('common.cancel')"
                    text
                />
                <Button 
                    @click="createCustomer"
                    label="Create Customer"
                    :loading="saving"
                />
            </template>
        </Dialog>

        <!-- Preview Dialog -->
        <Dialog 
            v-model:visible="previewDialog" 
            modal 
            header="Invoice Preview"
            :style="{ width: '800px' }"
            maximizable
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
                <!-- Invoice preview content would go here -->
                <div class="text-center text-gray-600 dark:text-gray-400">
                    <i class="fas fa-file-invoice text-4xl mb-4"></i>
                    <p>Invoice preview will be rendered here</p>
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded text-left">
                        <h3 class="font-semibold mb-2">{{ invoice.invoice_number }}</h3>
                        <p class="text-sm">Total: {{ formatCurrency(grandTotal) }}</p>
                        <p class="text-sm">Items: {{ invoice.items.length }}</p>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button 
                    @click="previewDialog = false"
                    :label="$t('common.close')"
                />
            </template>
        </Dialog>

        <!-- Toast -->
        <Toast ref="toast" />
    </div>
</template>

<style scoped>
:deep(.p-autocomplete-input) {
    width: 100%;
}

:deep(.p-inputnumber-input) {
    width: 100%;
}

.line-item {
    transition: all 0.2s ease-in-out;
}

.line-item:hover {
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}
</style>