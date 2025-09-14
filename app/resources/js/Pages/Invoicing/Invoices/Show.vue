<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'

const props = defineProps({
  invoice: Object,
})

const page = usePage()
const toast = page.props.toast || {}

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoices', icon: 'file-text' },
  { label: 'Invoices', url: '/invoices', icon: 'list' },
  { label: `Invoice ${props.invoice.invoice_number}`, url: `/invoices/${props.invoice.id}`, icon: 'eye' },
])

// Status badge styling
const getStatusSeverity = (status) => {
  const severityMap = {
    draft: 'secondary',
    sent: 'info',
    posted: 'warning',
    paid: 'success',
    cancelled: 'danger',
    void: 'contrast'
  }
  return severityMap[status] || 'secondary'
}

// Format currency
const formatCurrency = (amount, currency) => {
  if (!amount || !currency) return '-'
  
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency.code || 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

// Format date
const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// Available actions based on invoice status
const availableActions = computed(() => {
  const actions = []
  const status = props.invoice.status

  if (status === 'draft') {
    actions.push(
      { label: 'Edit', icon: 'pi pi-pencil', route: 'invoices.edit', severity: 'primary' },
      { label: 'Mark as Sent', icon: 'pi pi-send', route: 'invoices.send', severity: 'info' },
      { label: 'Cancel', icon: 'pi pi-times', route: 'invoices.cancel', severity: 'danger' }
    )
  } else if (status === 'sent') {
    actions.push(
      { label: 'Post to Ledger', icon: 'pi pi-check', route: 'invoices.post', severity: 'success' },
      { label: 'Cancel', icon: 'pi pi-times', route: 'invoices.cancel', severity: 'danger' }
    )
  } else if (status === 'posted') {
    if (props.invoice.balance_due > 0) {
      actions.push({
        label: 'Record Payment',
        icon: 'pi pi-plus',
        route: 'payments.create',
        params: { invoice_id: props.invoice.id },
        severity: 'success'
      })
    }
    actions.push({ label: 'Void', icon: 'pi pi-ban', route: 'invoices.void', severity: 'danger' })
  }

  // Common actions
  actions.push(
    { label: 'Download PDF', icon: 'pi pi-file-pdf', route: 'invoices.generate-pdf', severity: 'info', external: true },
    { label: 'Send Email', icon: 'pi pi-envelope', route: 'invoices.send-email', severity: 'warning' },
    { label: 'Duplicate', icon: 'pi pi-copy', route: 'invoices.duplicate', severity: 'secondary' }
  )

  return actions
})

// Execute action
const executeAction = (action) => {
  if (action.external) {
    window.open(route(action.route, props.invoice.id), '_blank')
    return
  }

  if (action.route === 'payments.create') {
    window.location.href = route(action.route) + '?invoice_id=' + props.invoice.id
    return
  }

  router.post(route(action.route, props.invoice.id), {}, {
    onSuccess: () => {
      toast.success = `${action.label} action completed successfully!`
    },
    onError: () => {
      toast.error = `Failed to ${action.label.toLowerCase()}. Please try again.`
    }
  })
}

// Payment allocation summary
const paymentSummary = computed(() => {
  const payments = props.invoice.payments || []
  const totalPaid = payments.reduce((sum, payment) => {
    const allocation = payment.allocations?.find(a => a.invoice_id === props.invoice.id)
    return sum + (allocation?.amount || 0)
  }, 0)

  return {
    totalPayments: payments.length,
    totalPaid: totalPaid,
    balanceDue: props.invoice.total_amount - totalPaid
  }
})
</script>

<template>
  <Head :title="`Invoice ${invoice.invoice_number}`" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing System" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
        <div class="flex items-center gap-2">
          <Link :href="route('invoices.index')">
            <Button label="Back to Invoices" icon="pi pi-arrow-left" severity="secondary" outlined />
          </Link>
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Invoice Header -->
      <div class="flex items-center justify-between">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              Invoice {{ invoice.invoice_number }}
            </h1>
            <Tag :value="invoice.status" :severity="getStatusSeverity(invoice.status)" />
          </div>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Created on {{ formatDate(invoice.created_at) }}
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2">
          <Button
            v-for="action in availableActions"
            :key="action.label"
            :label="action.label"
            :icon="action.icon"
            :severity="action.severity"
            :outlined="action.severity !== 'primary'"
            size="small"
            @click="executeAction(action)"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Invoice Details -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Customer & Invoice Details -->
          <Card>
            <template #content>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Bill To -->
                <div>
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Bill To</h3>
                  <div class="space-y-1">
                    <p class="font-medium text-gray-900 dark:text-white">
                      {{ invoice.customer?.name }}
                    </p>
                    <p v-if="invoice.customer?.email" class="text-sm text-gray-600 dark:text-gray-400">
                      {{ invoice.customer.email }}
                    </p>
                    <p v-if="invoice.customer?.phone" class="text-sm text-gray-600 dark:text-gray-400">
                      {{ invoice.customer.phone }}
                    </p>
                    <div v-if="invoice.customer?.address" class="text-sm text-gray-600 dark:text-gray-400">
                      <p>{{ invoice.customer.address }}</p>
                      <p v-if="invoice.customer?.city">{{ invoice.customer.city }}, {{ invoice.customer?.state }} {{ invoice.customer?.postal_code }}</p>
                      <p v-if="invoice.customer?.country">{{ invoice.customer.country }}</p>
                    </div>
                  </div>
                </div>

                <!-- Invoice Details -->
                <div>
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Invoice Details</h3>
                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-600 dark:text-gray-400">Invoice Date:</span>
                      <span class="font-medium">{{ formatDate(invoice.invoice_date) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-600 dark:text-gray-400">Due Date:</span>
                      <span 
                        class="font-medium"
                        :class="new Date(invoice.due_date) < new Date() && invoice.status !== 'paid' ? 'text-red-600 dark:text-red-400' : ''"
                      >
                        {{ formatDate(invoice.due_date) }}
                      </span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-600 dark:text-gray-400">Currency:</span>
                      <span class="font-medium">{{ invoice.currency?.code }} ({{ invoice.currency?.symbol }})</span>
                    </div>
                    <div v-if="invoice.notes" class="mt-3">
                      <p class="text-sm text-gray-600 dark:text-gray-400">Notes:</p>
                      <p class="text-sm">{{ invoice.notes }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </Card>

          <!-- Invoice Items -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="list" class="w-5 h-5" />
                Invoice Items
              </span>
            </template>
            <template #content>
              <DataTable :value="invoice.items" responsiveLayout="scroll" class="w-full">
                <Column field="description" header="Description">
                  <template #body="slotProps">
                    <div class="font-medium">{{ slotProps.data.description }}</div>
                  </template>
                </Column>
                <Column field="quantity" header="Qty" style="width: 80px">
                  <template #body="slotProps">
                    {{ slotProps.data.quantity }}
                  </template>
                </Column>
                <Column field="unit_price" header="Unit Price" style="width: 120px">
                  <template #body="slotProps">
                    {{ formatCurrency(slotProps.data.unit_price, invoice.currency) }}
                  </template>
                </Column>
                <Column field="tax_rate" header="Tax %" style="width: 80px">
                  <template #body="slotProps">
                    {{ slotProps.data.tax_rate }}%
                  </template>
                </Column>
                <Column field="total_amount" header="Total" style="width: 120px">
                  <template #body="slotProps">
                    {{ formatCurrency(slotProps.data.total_amount, invoice.currency) }}
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>

          <!-- Payments -->
          <Card v-if="invoice.payments?.length > 0">
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="credit-card" class="w-5 h-5" />
                Payments
              </span>
            </template>
            <template #content>
              <DataTable :value="invoice.payments" responsiveLayout="scroll" class="w-full">
                <Column field="payment_date" header="Date" style="width: 120px">
                  <template #body="slotProps">
                    {{ formatDate(slotProps.data.payment_date) }}
                  </template>
                </Column>
                <Column field="reference_number" header="Reference" />
                <Column field="payment_method" header="Method" />
                <Column field="amount" header="Amount" style="width: 120px">
                  <template #body="slotProps">
                    {{ formatCurrency(slotProps.data.amount, slotProps.data.currency) }}
                  </template>
                </Column>
                <Column field="allocated_amount" header="Allocated" style="width: 120px">
                  <template #body="slotProps">
                    <div>
                      {{ formatCurrency(
                        slotProps.data.allocations?.find(a => a.invoice_id === invoice.id)?.amount || 0, 
                        slotProps.data.currency
                      ) }}
                    </div>
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Invoice Summary -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="calculator" class="w-5 h-5" />
                Invoice Summary
              </span>
            </template>
            <template #content>
              <div class="space-y-3">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                  <span class="font-medium">{{ formatCurrency(invoice.subtotal, invoice.currency) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Tax:</span>
                  <span class="font-medium">{{ formatCurrency(invoice.tax_amount, invoice.currency) }}</span>
                </div>
                <div class="border-t pt-3">
                  <div class="flex justify-between">
                    <span class="font-medium text-gray-900 dark:text-white">Total:</span>
                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                      {{ formatCurrency(invoice.total_amount, invoice.currency) }}
                    </span>
                  </div>
                </div>
                <div class="border-t pt-3 space-y-2">
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Paid:</span>
                    <span class="font-medium text-green-600 dark:text-green-400">
                      {{ formatCurrency(paymentSummary.totalPaid, invoice.currency) }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-medium text-gray-900 dark:text-white">Balance Due:</span>
                    <span 
                      class="font-bold"
                      :class="paymentSummary.balanceDue > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                    >
                      {{ formatCurrency(paymentSummary.balanceDue, invoice.currency) }}
                    </span>
                  </div>
                </div>
              </div>
            </template>
          </Card>

          <!-- Status Information -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="info" class="w-5 h-5" />
                Status Information
              </span>
            </template>
            <template #content>
              <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Current Status:</span>
                  <Tag :value="invoice.status" :severity="getStatusSeverity(invoice.status)" />
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Created:</span>
                  <span>{{ formatDate(invoice.created_at) }}</span>
                </div>
                <div v-if="invoice.updated_at !== invoice.created_at" class="flex items-center justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Last Updated:</span>
                  <span>{{ formatDate(invoice.updated_at) }}</span>
                </div>
                <div v-if="new Date(invoice.due_date) < new Date() && invoice.status !== 'paid'" class="p-3 rounded bg-red-50 dark:bg-red-900/20">
                  <p class="text-sm text-red-600 dark:text-red-400 font-medium">
                    <SvgIcon name="alert-triangle" class="w-4 h-4 inline mr-1" />
                    This invoice is overdue
                  </p>
                </div>
              </div>
            </template>
          </Card>

          <!-- Quick Actions -->
          <Card>
            <template #title>
              <span class="flex items-center gap-2">
                <SvgIcon name="lightning" class="w-5 h-5" />
                Quick Actions
              </span>
            </template>
            <template #content>
              <div class="space-y-2">
                <Link :href="route('invoices.edit', invoice.id)" v-if="invoice.status === 'draft'">
                  <Button label="Edit Invoice" icon="pi pi-pencil" severity="primary" class="w-full" />
                </Link>
                <Link :href="route('payments.create') + '?invoice_id=' + invoice.id" v-if="invoice.balance_due > 0">
                  <Button label="Record Payment" icon="pi pi-plus" severity="success" class="w-full" />
                </Link>
                <Button 
                  label="Download PDF" 
                  icon="pi pi-file-pdf" 
                  severity="info" 
                  outlined 
                  class="w-full" 
                  @click="executeAction({ label: 'Download PDF', icon: 'pi pi-file-pdf', route: 'invoices.generate-pdf', severity: 'info', external: true })"
                />
                <Link :href="route('customers.show', invoice.customer_id)">
                  <Button label="View Customer" icon="pi pi-user" severity="secondary" outlined class="w-full" />
                </Link>
              </div>
            </template>
          </Card>
        </div>
      </div>
    </div>

    <!-- Toast for notifications -->
    <Toast position="top-right" />
  </LayoutShell>
</template>