<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Calendar from 'primevue/calendar'
import Toast from 'primevue/toast'
import Card from 'primevue/card'

const props = defineProps({
    vendors: {
        type: Array,
        required: true
    },
    selectedVendor: {
        type: Object,
        default: null
    }
})

const toast = ref()

// Form data using Inertia's useForm
const form = useForm({
    vendor_id: props.selectedVendor?.id || '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    currency: 'USD',
    exchange_rate: 1,
    notes: '',
    internal_notes: '',
    lines: [
        {
            description: '',
            quantity: 1,
            unit_price: 0,
            discount_percentage: 0,
            tax_rate: 0
        }
    ],
    action: 'draft'
})

// Options for dropdowns
const currencyOptions = [
    { label: 'USD - US Dollar', value: 'USD' },
    { label: 'EUR - Euro', value: 'EUR' },
    { label: 'GBP - British Pound', value: 'GBP' },
    { label: 'CAD - Canadian Dollar', value: 'CAD' },
    { label: 'AUD - Australian Dollar', value: 'AUD' }
]

const actionOptions = [
    { label: 'Save as Draft', value: 'draft' },
    { label: 'Submit for Approval', value: 'submit_for_approval' }
]

// Computed properties
const vendorOptions = computed(() => {
    return props.vendors.map(vendor => ({
        label: vendor.display_name || vendor.legal_name,
        value: vendor.id
    }))
})

const selectedVendorName = computed(() => {
    const vendor = props.vendors.find(v => v.id === form.vendor_id)
    return vendor?.display_name || vendor?.legal_name || ''
})

// Line item management
const addLine = () => {
    form.lines.push({
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_percentage: 0,
        tax_rate: 0
    })
}

const removeLine = (index) => {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1)
        calculateTotals()
    } else {
        toast.value.add({
            severity: 'warn',
            summary: 'Cannot Remove',
            detail: 'At least one line item is required',
            life: 3000
        })
    }
}

const calculateLineTotal = (line) => {
    const quantity = parseFloat(line.quantity) || 0
    const unitPrice = parseFloat(line.unit_price) || 0
    const discountPercentage = parseFloat(line.discount_percentage) || 0
    
    return (quantity * unitPrice) * (1 - discountPercentage / 100)
}

const calculateLineTax = (line) => {
    const lineTotal = calculateLineTotal(line)
    const taxRate = parseFloat(line.tax_rate) || 0
    
    return lineTotal * (taxRate / 100)
}

const calculateLineTotalWithTax = (line) => {
    return calculateLineTotal(line) + calculateLineTax(line)
}

// Calculate totals
const calculateTotals = () => {
    let subtotal = 0
    let taxAmount = 0
    
    form.lines.forEach(line => {
        const lineTotal = calculateLineTotal(line)
        const lineTax = calculateLineTax(line)
        
        subtotal += lineTotal
        taxAmount += lineTax
    })
    
    return { subtotal, taxAmount, total: subtotal + taxAmount }
}

const totals = computed(() => calculateTotals())

// Form submission
const submit = () => {
    form.post('/purchase-orders', {
        onSuccess: () => {
            toast.value.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Purchase Order created successfully',
                life: 3000
            })
        },
        onError: (errors) => {
            toast.value.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form for errors',
                life: 5000
            })
        }
    })
}

const cancel = () => {
    router.visit('/purchase-orders')
}

// Format currency utility
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount)
}

// Auto-calculate totals when lines change
const updateLineCalculations = () => {
    // Vue's reactivity will automatically update the computed totals
}
</script>

<template>
    <LayoutShell>
        <Toast ref="toast" />

        <!-- Universal Page Header -->
        <UniversalPageHeader
            title="Create Purchase Order"
            description="Create a new purchase order for goods and services"
            subDescription="Add line items and submit for approval"
        />

        <!-- Form Container -->
        <div class="max-w-6xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <!-- Form Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Purchase Order Details</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Vendor, dates, and financial information</p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <!-- Vendor Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Vendor Selection -->
                        <div>
                            <label for="vendor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Vendor <span class="text-red-500">*</span>
                            </label>
                            <Dropdown
                                id="vendor_id"
                                v-model="form.vendor_id"
                                :options="vendorOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.vendor_id }"
                                placeholder="Select a vendor"
                            />
                            <small v-if="form.errors.vendor_id" class="text-red-500">{{ form.errors.vendor_id }}</small>
                            <p v-if="selectedVendorName" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Selected: {{ selectedVendorName }}
                            </p>
                        </div>

                        <!-- Order Date -->
                        <div>
                            <label for="order_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Order Date <span class="text-red-500">*</span>
                            </label>
                            <Calendar
                                id="order_date"
                                v-model="form.order_date"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.order_date }"
                                dateFormat="yy-mm-dd"
                            />
                            <small v-if="form.errors.order_date" class="text-red-500">{{ form.errors.order_date }}</small>
                        </div>

                        <!-- Expected Delivery Date -->
                        <div>
                            <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Expected Delivery Date
                            </label>
                            <Calendar
                                id="expected_delivery_date"
                                v-model="form.expected_delivery_date"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.expected_delivery_date }"
                                dateFormat="yy-mm-dd"
                            />
                            <small v-if="form.errors.expected_delivery_date" class="text-red-500">{{ form.errors.expected_delivery_date }}</small>
                        </div>

                        <!-- Currency -->
                        <div>
                            <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Currency <span class="text-red-500">*</span>
                            </label>
                            <Dropdown
                                id="currency"
                                v-model="form.currency"
                                :options="currencyOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.currency }"
                            />
                            <small v-if="form.errors.currency" class="text-red-500">{{ form.errors.currency }}</small>
                        </div>

                        <!-- Exchange Rate -->
                        <div>
                            <label for="exchange_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Exchange Rate
                            </label>
                            <InputText
                                id="exchange_rate"
                                v-model="form.exchange_rate"
                                type="number"
                                step="0.000001"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.exchange_rate }"
                                placeholder="1.000000"
                            />
                            <small v-if="form.errors.exchange_rate" class="text-red-500">{{ form.errors.exchange_rate }}</small>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                For converting to base currency
                            </p>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Notes
                            </label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.notes }"
                                rows="3"
                                placeholder="Notes for vendor or internal use..."
                            />
                            <small v-if="form.errors.notes" class="text-red-500">{{ form.errors.notes }}</small>
                        </div>

                        <!-- Internal Notes -->
                        <div>
                            <label for="internal_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Internal Notes
                            </label>
                            <Textarea
                                id="internal_notes"
                                v-model="form.internal_notes"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.internal_notes }"
                                rows="3"
                                placeholder="Internal notes not visible to vendor..."
                            />
                            <small v-if="form.errors.internal_notes" class="text-red-500">{{ form.errors.internal_notes }}</small>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Line Items</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Add products and services to this purchase order</p>
                            </div>
                            <Button
                                type="button"
                                @click="addLine"
                                icon="pi pi-plus"
                                label="Add Line"
                                severity="secondary"
                                size="small"
                            />
                        </div>

                        <div v-if="form.errors.lines" class="mb-4">
                            <small class="text-red-500">{{ form.errors.lines }}</small>
                        </div>

                        <!-- Line Items Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Quantity
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Unit Price
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Discount %
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Tax %
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Total
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="(line, index) in form.lines"
                                        :key="index"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        <!-- Description -->
                                        <td class="px-6 py-4">
                                            <InputText
                                                v-model="line.description"
                                                class="w-full"
                                                :class="{ 'p-invalid': form.errors[`lines.${index}.description`] }"
                                                placeholder="Item description"
                                            />
                                            <small v-if="form.errors[`lines.${index}.description`]" class="text-red-500">
                                                {{ form.errors[`lines.${index}.description`] }}
                                            </small>
                                        </td>

                                        <!-- Quantity -->
                                        <td class="px-6 py-4">
                                            <InputText
                                                v-model="line.quantity"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                class="w-full"
                                                :class="{ 'p-invalid': form.errors[`lines.${index}.quantity`] }"
                                                @input="updateLineCalculations"
                                            />
                                            <small v-if="form.errors[`lines.${index}.quantity`]" class="text-red-500">
                                                {{ form.errors[`lines.${index}.quantity`] }}
                                            </small>
                                        </td>

                                        <!-- Unit Price -->
                                        <td class="px-6 py-4">
                                            <InputText
                                                v-model="line.unit_price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-full"
                                                :class="{ 'p-invalid': form.errors[`lines.${index}.unit_price`] }"
                                                @input="updateLineCalculations"
                                            />
                                            <small v-if="form.errors[`lines.${index}.unit_price`]" class="text-red-500">
                                                {{ form.errors[`lines.${index}.unit_price`] }}
                                            </small>
                                        </td>

                                        <!-- Discount -->
                                        <td class="px-6 py-4">
                                            <InputText
                                                v-model="line.discount_percentage"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                class="w-full"
                                                :class="{ 'p-invalid': form.errors[`lines.${index}.discount_percentage`] }"
                                                @input="updateLineCalculations"
                                            />
                                            <small v-if="form.errors[`lines.${index}.discount_percentage`]" class="text-red-500">
                                                {{ form.errors[`lines.${index}.discount_percentage`] }}
                                            </small>
                                        </td>

                                        <!-- Tax -->
                                        <td class="px-6 py-4">
                                            <InputText
                                                v-model="line.tax_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                class="w-full"
                                                :class="{ 'p-invalid': form.errors[`lines.${index}.tax_rate`] }"
                                                @input="updateLineCalculations"
                                            />
                                            <small v-if="form.errors[`lines.${index}.tax_rate`]" class="text-red-500">
                                                {{ form.errors[`lines.${index}.tax_rate`] }}
                                            </small>
                                        </td>

                                        <!-- Total -->
                                        <td class="px-6 py-4 text-right">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ formatCurrency(calculateLineTotalWithTax(line)) }}
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 text-center">
                                            <Button
                                                v-if="form.lines.length > 1"
                                                type="button"
                                                @click="removeLine(index)"
                                                icon="pi pi-trash"
                                                severity="danger"
                                                size="small"
                                                text
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Totals Summary -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <Card>
                            <template #content>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Subtotal</label>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(totals.subtotal) }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Amount</label>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(totals.taxAmount) }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</label>
                                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {{ formatCurrency(totals.total) }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>

                    <!-- Action Selection -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="flex items-center space-x-4">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Action:</label>
                            <Dropdown
                                v-model="form.action"
                                :options="actionOptions"
                                option-label="label"
                                option-value="value"
                                class="w-48"
                            />
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                <span v-if="form.action === 'draft'" class="text-gray-600">Save as draft and edit later</span>
                                <span v-else class="text-orange-600">Submit for approval workflow</span>
                            </span>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="flex items-center justify-end space-x-4">
                            <Button
                                type="button"
                                @click="cancel"
                                label="Cancel"
                                severity="secondary"
                                :disabled="form.processing"
                            />
                            <Button
                                type="submit"
                                :label="form.action === 'draft' ? 'Save as Draft' : 'Submit for Approval'"
                                :loading="form.processing"
                                :severity="form.action === 'draft' ? 'secondary' : 'primary'"
                                icon="pi pi-save"
                            />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </LayoutShell>
</template>