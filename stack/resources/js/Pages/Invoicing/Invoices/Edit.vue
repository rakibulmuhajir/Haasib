<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { route } from 'ziggy-js'

const props = defineProps({
    invoice: Object,
    customers: Array
})

const { t } = useI18n()

// Form state
const form = useForm({
    customer_id: props.invoice.customer_id,
    invoice_number: props.invoice.invoice_number,
    issue_date: props.invoice.issue_date,
    due_date: props.invoice.due_date,
    currency: props.invoice.currency || 'USD',
    notes: props.invoice.notes || '',
    terms: props.invoice.terms || '',
    line_items: props.invoice.line_items || []
})

// Add new line item
const addLineItem = () => {
    form.line_items.push({
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_rate: 0
    })
}

// Remove line item
const removeLineItem = (index) => {
    form.line_items.splice(index, 1)
}

// Calculate line item total
const calculateLineTotal = (item) => {
    const subtotal = item.quantity * item.unit_price
    const tax = subtotal * (item.tax_rate / 100)
    return subtotal + tax
}

// Calculate totals
const subtotal = computed(() => {
    return form.line_items.reduce((total, item) => {
        return total + (item.quantity * item.unit_price)
    }, 0)
})

const taxTotal = computed(() => {
    return form.line_items.reduce((total, item) => {
        const subtotal = item.quantity * item.unit_price
        return total + (subtotal * (item.tax_rate / 100))
    }, 0)
})

const total = computed(() => {
    return subtotal.value + taxTotal.value
})

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: form.currency || 'USD'
    }).format(amount || 0)
}

// Currency options
const currencyOptions = [
    { label: 'USD', value: 'USD' },
    { label: 'EUR', value: 'EUR' },
    { label: 'GBP', value: 'GBP' }
]

// Submit form
const submit = () => {
    form.transform((data) => ({
        ...data,
        subtotal: subtotal.value,
        tax_total: taxTotal.value,
        total: total.value
    })).put(route('invoices.update', props.invoice.id))
}

// Cancel and go back
const cancel = () => {
    router.visit(route('invoices.show', props.invoice.id))
}
</script>

<template>
    <LayoutShell>
        <Head :title="`Edit Invoice ${invoice.invoice_number}`" />

        <UniversalPageHeader
            :title="`Edit Invoice ${invoice.invoice_number}`"
            subtitle="Update invoice details and line items"
        />

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Invoice Details -->
            <Card>
                <template #title>
                    Invoice Details
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-surface-700 mb-2">
                                Customer
                            </label>
                            <Dropdown
                                id="customer_id"
                                v-model="form.customer_id"
                                :options="customers"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Select customer"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.customer_id }"
                            />
                            <small v-if="form.errors.customer_id" class="text-red-600">
                                {{ form.errors.customer_id }}
                            </small>
                        </div>

                        <div>
                            <label for="invoice_number" class="block text-sm font-medium text-surface-700 mb-2">
                                Invoice Number
                            </label>
                            <InputText
                                id="invoice_number"
                                v-model="form.invoice_number"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.invoice_number }"
                            />
                            <small v-if="form.errors.invoice_number" class="text-red-600">
                                {{ form.errors.invoice_number }}
                            </small>
                        </div>

                        <div>
                            <label for="issue_date" class="block text-sm font-medium text-surface-700 mb-2">
                                Issue Date
                            </label>
                            <Calendar
                                id="issue_date"
                                v-model="form.issue_date"
                                dateFormat="yy-mm-dd"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.issue_date }"
                            />
                            <small v-if="form.errors.issue_date" class="text-red-600">
                                {{ form.errors.issue_date }}
                            </small>
                        </div>

                        <div>
                            <label for="due_date" class="block text-sm font-medium text-surface-700 mb-2">
                                Due Date
                            </label>
                            <Calendar
                                id="due_date"
                                v-model="form.due_date"
                                dateFormat="yy-mm-dd"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.due_date }"
                            />
                            <small v-if="form.errors.due_date" class="text-red-600">
                                {{ form.errors.due_date }}
                            </small>
                        </div>

                        <div>
                            <label for="currency" class="block text-sm font-medium text-surface-700 mb-2">
                                Currency
                            </label>
                            <Dropdown
                                id="currency"
                                v-model="form.currency"
                                :options="currencyOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.currency }"
                            />
                            <small v-if="form.errors.currency" class="text-red-600">
                                {{ form.errors.currency }}
                            </small>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Line Items -->
            <Card>
                <template #title>
                    Line Items
                </template>
                <template #content>
                    <DataTable :value="form.line_items" :paginator="false" responsiveLayout="scroll">
                        <Column header="Description">
                            <template #body="{ data, index }">
                                <InputText
                                    v-model="data.description"
                                    placeholder="Item description"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors[`line_items.${index}.description`] }"
                                />
                                <small v-if="form.errors[`line_items.${index}.description`]" class="text-red-600">
                                    {{ form.errors[`line_items.${index}.description`] }}
                                </small>
                            </template>
                        </Column>
                        
                        <Column header="Quantity" style="min-width: 120px">
                            <template #body="{ data, index }">
                                <InputText
                                    v-model.number="data.quantity"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors[`line_items.${index}.quantity`] }"
                                />
                                <small v-if="form.errors[`line_items.${index}.quantity`]" class="text-red-600">
                                    {{ form.errors[`line_items.${index}.quantity`] }}
                                </small>
                            </template>
                        </Column>
                        
                        <Column header="Unit Price" style="min-width: 140px">
                            <template #body="{ data, index }">
                                <InputText
                                    v-model.number="data.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors[`line_items.${index}.unit_price`] }"
                                />
                                <small v-if="form.errors[`line_items.${index}.unit_price`]" class="text-red-600">
                                    {{ form.errors[`line_items.${index}.unit_price`] }}
                                </small>
                            </template>
                        </Column>
                        
                        <Column header="Tax %" style="min-width: 100px">
                            <template #body="{ data, index }">
                                <InputText
                                    v-model.number="data.tax_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    class="w-full"
                                />
                            </template>
                        </Column>
                        
                        <Column header="Total" style="min-width: 120px">
                            <template #body="{ data }">
                                <span class="font-semibold">
                                    {{ formatCurrency(calculateLineTotal(data)) }}
                                </span>
                            </template>
                        </Column>
                        
                        <Column header="Actions" style="min-width: 80px">
                            <template #body="{ index }">
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    size="small"
                                    @click="removeLineItem(index)"
                                    v-tooltip="'Remove item'"
                                />
                            </template>
                        </Column>
                    </DataTable>

                    <Button
                        label="Add Line Item"
                        icon="pi pi-plus"
                        severity="secondary"
                        text
                        @click="addLineItem"
                        class="mt-4"
                    />
                </template>
            </Card>

            <!-- Notes and Terms -->
            <Card>
                <template #title>
                    Additional Information
                </template>
                <template #content>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <label for="notes" class="block text-sm font-medium text-surface-700 mb-2">
                                Notes
                            </label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="w-full"
                                placeholder="Add any notes or comments..."
                            />
                        </div>

                        <div>
                            <label for="terms" class="block text-sm font-medium text-surface-700 mb-2">
                                Terms & Conditions
                            </label>
                            <Textarea
                                id="terms"
                                v-model="form.terms"
                                rows="3"
                                class="w-full"
                                placeholder="Add terms and conditions..."
                            />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Totals Summary -->
            <Card>
                <template #title>
                    Invoice Summary
                </template>
                <template #content>
                    <div class="space-y-2">
                        <div class="flex justify-between text-lg">
                            <span>Subtotal:</span>
                            <span>{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-lg">
                            <span>Tax:</span>
                            <span>{{ formatCurrency(taxTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-xl font-bold">
                            <span>Total:</span>
                            <span>{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <Button
                    label="Save Changes"
                    icon="pi pi-save"
                    severity="primary"
                    type="submit"
                    :loading="form.processing"
                />
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    severity="secondary"
                    text
                    @click="cancel"
                />
            </div>
        </form>
    </LayoutShell>
</template>