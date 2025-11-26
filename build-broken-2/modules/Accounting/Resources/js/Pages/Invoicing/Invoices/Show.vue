<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { usePageActions } from '@/composables/usePageActions'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
// import UniversalPageHeader from '@/components/ui/sonner'
// import Button from 'primevue/button'
// import Card from 'primevue/card'
// import DataTable from 'primevue/datatable'
// import Column from 'primevue/column'
// import Tag from 'primevue/tag'
// import Dialog from 'primevue/dialog'
// import Toast from 'primevue/toast'
import { route } from 'ziggy-js'

const props = defineProps({
    invoice: Object
})

const { t } = useI18n()
const page = usePage()
const { setActions } = usePageActions()

// Define page actions
const invoiceActions = [
    {
        key: 'edit-invoice',
        label: 'Edit Invoice',
        icon: 'pi pi-pencil',
        severity: 'secondary',
        routeName: 'invoices.edit',
        params: { invoice: props.invoice.id }
    },
    {
        key: 'send-invoice',
        label: 'Send Invoice',
        icon: 'pi pi-envelope',
        severity: 'primary',
        routeName: 'invoices.send',
        params: { invoice: props.invoice.id }
    },
    {
        key: 'duplicate-invoice',
        label: 'Duplicate',
        icon: 'pi pi-clone',
        severity: 'secondary',
        routeName: 'invoices.duplicate',
        params: { invoice: props.invoice.id }
    }
]

// Set actions when component mounts
setActions(invoiceActions)

// State
const showDeleteDialog = ref(false)
const showSendDialog = ref(false)

// Computed properties
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: props.invoice.currency || 'USD'
    }).format(amount || 0)
}

const getStatusBadge = (status) => {
    const statuses = {
        draft: { label: 'Draft', severity: 'secondary' },
        sent: { label: 'Sent', severity: 'info' },
        paid: { label: 'Paid', severity: 'success' },
        overdue: { label: 'Overdue', severity: 'warning' },
        cancelled: { label: 'Cancelled', severity: 'danger' }
    }
    return statuses[status] || { label: status, severity: 'secondary' }
}

// Methods
const editInvoice = () => {
    router.visit(route('invoices.edit', props.invoice.id))
}

const sendInvoice = () => {
    router.post(route('invoices.send', props.invoice.id), {}, {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Invoice sent successfully',
                life: 3000
            })
        }
    })
}

const duplicateInvoice = () => {
    router.post(route('invoices.duplicate', props.invoice.id), {}, {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Invoice duplicated successfully',
                life: 3000
            })
        }
    })
}

const confirmDelete = () => {
    if (props.invoice.status !== 'draft') {
        toast.add({
            severity: 'warn',
            summary: 'Cannot Delete',
            detail: 'Only draft invoices can be deleted',
            life: 3000
        })
        return
    }
    showDeleteDialog.value = true
}

const deleteInvoice = () => {
    router.delete(route('invoices.destroy', props.invoice.id), {
        onSuccess: () => {
            router.visit(route('invoices.index'))
        }
    })
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}
</script>

<template>
    <UniversalLayout>
        <Head :title="`Invoice ${invoice.invoice_number}`" />

        <Toast />

        <UniversalPageHeader
            :title="`Invoice ${invoice.invoice_number}`"
            :subtitle="`Customer: ${invoice.customer?.name || 'Unknown'}`"
            :actions="invoiceActions"
        />

        <!-- Invoice Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Customer Information -->
            <Card class="lg:col-span-2">
                <template #title>
                    Invoice Details
                </template>
                <template #content>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="font-semibold text-surface-900 mb-2">Bill To:</h3>
                            <div class="space-y-1">
                                <p class="font-medium">{{ invoice.customer?.name }}</p>
                                <p class="text-surface-600 text-sm">{{ invoice.customer?.email }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="space-y-2">
                                <div>
                                    <span class="text-surface-600">Invoice Number:</span>
                                    <span class="font-medium ml-2">{{ invoice.invoice_number }}</span>
                                </div>
                                <div>
                                    <span class="text-surface-600">Issue Date:</span>
                                    <span class="font-medium ml-2">{{ formatDate(invoice.issue_date) }}</span>
                                </div>
                                <div>
                                    <span class="text-surface-600">Due Date:</span>
                                    <span class="font-medium ml-2">{{ formatDate(invoice.due_date) }}</span>
                                </div>
                                <div>
                                    <span class="text-surface-600">Status:</span>
                                    <Tag 
                                        :value="getStatusBadge(invoice.status).label"
                                        :severity="getStatusBadge(invoice.status).severity"
                                        class="ml-2"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Invoice Summary -->
            <Card>
                <template #title>
                    Invoice Summary
                </template>
                <template #content>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span>{{ formatCurrency(invoice.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax:</span>
                            <span>{{ formatCurrency(invoice.tax_total) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg">
                            <span>Total:</span>
                            <span>{{ formatCurrency(invoice.total) }}</span>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Line Items -->
        <Card>
            <template #title>
                Line Items
            </template>
            <template #content>
                <DataTable 
                    :value="invoice.line_items || []" 
                    :paginator="false"
                    responsiveLayout="scroll"
                >
                    <Column field="description" header="Description">
                        <template #body="{ data }">
                            <div>
                                <p class="font-medium">{{ data.description }}</p>
                            </div>
                        </template>
                    </Column>
                    <Column field="quantity" header="Quantity" style="min-width: 100px">
                        <template #body="{ data }">
                            {{ data.quantity }}
                        </template>
                    </Column>
                    <Column field="unit_price" header="Unit Price" style="min-width: 120px">
                        <template #body="{ data }">
                            {{ formatCurrency(data.unit_price) }}
                        </template>
                    </Column>
                    <Column field="tax_rate" header="Tax Rate" style="min-width: 100px">
                        <template #body="{ data }">
                            {{ data.tax_rate || 0 }}%
                        </template>
                    </Column>
                    <Column field="line_total" header="Total" style="min-width: 120px">
                        <template #body="{ data }">
                            <span class="font-semibold">
                                {{ formatCurrency(data.line_total) }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <!-- Notes and Terms -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <Card v-if="invoice.notes">
                <template #title>
                    Notes
                </template>
                <template #content>
                    <p class="text-surface-700">{{ invoice.notes }}</p>
                </template>
            </Card>

            <Card v-if="invoice.terms">
                <template #title>
                    Terms & Conditions
                </template>
                <template #content>
                    <p class="text-surface-700">{{ invoice.terms }}</p>
                </template>
            </Card>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3 mt-6">
            <Button 
                label="Edit Invoice" 
                icon="pi pi-pencil"
                severity="secondary"
                @click="editInvoice"
                v-if="invoice.status === 'draft'"
            />
            <Button 
                label="Send Invoice" 
                icon="pi pi-envelope"
                severity="primary"
                @click="sendInvoice"
                v-if="invoice.status === 'draft'"
            />
            <Button 
                label="Duplicate" 
                icon="pi pi-clone"
                severity="secondary"
                @click="duplicateInvoice"
            />
            <Button 
                label="Delete" 
                icon="pi pi-trash"
                severity="danger"
                @click="confirmDelete"
                v-if="invoice.status === 'draft'"
            />
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog 
            v-model:visible="showDeleteDialog" 
            :style="{ width: '450px' }" 
            header="Confirm Delete" 
            :modal="true"
        >
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle text-3xl text-orange-500" />
                <div>
                    Are you sure you want to delete invoice 
                    <span class="font-semibold">{{ invoice.invoice_number }}</span>?
                    <br>
                    This action cannot be undone.
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="showDeleteDialog = false" />
                <Button
                    label="Delete"
                    icon="pi pi-trash"
                    severity="danger"
                    @click="deleteInvoice"
                />
            </template>
        </Dialog>
    </UniversalLayout>
</template>