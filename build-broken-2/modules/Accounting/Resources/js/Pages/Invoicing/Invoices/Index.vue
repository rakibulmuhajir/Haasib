<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Head } from '@inertiajs/vue3'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Toaster } from '@/components/ui/sonner'
import { route } from 'ziggy-js'

const props = defineProps({
    invoices: Array,
    filters: Object,
    statistics: Object,
    can: Object
})

// Define page actions for invoices
const invoiceActions = [
    {
        key: 'add-invoice',
        label: 'Add Invoice',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'invoices.create'
    }
]

// State
const deleteInvoiceDialog = ref(false)
const invoiceToDelete = ref(null)
const selectedInvoices = ref([])
const loading = ref(false)

// Computed properties
const filteredInvoices = computed(() => props.invoices || { data: [] })

// Methods
const confirmDelete = (invoice) => {
    invoiceToDelete.value = invoice
    deleteInvoiceDialog.value = true
}

const deleteInvoice = () => {
    if (!invoiceToDelete.value) return

    toast.success('Invoice deleted successfully')
    
    deleteInvoiceDialog.value = false
    invoiceToDelete.value = null
}

const viewInvoice = (invoice) => {
    console.log('Viewing invoice:', invoice)
    
    toast.info('Invoice details view coming soon')
}

const getSeverity = (status) => {
    switch (status) {
        case 'paid': return 'default'
        case 'pending': return 'secondary'
        case 'overdue': return 'destructive'
        case 'draft': return 'outline'
        default: return 'outline'
    }
}

const formatCurrency = (amount, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount || 0)
}
</script>

<template>
  <Head title="Invoices" />
  
  <UniversalLayout
    title="Invoices"
    subtitle="Manage your invoices"
    :breadcrumbs="[
      { label: 'Dashboard', href: '/dashboard' },
      { label: 'Invoices', active: true }
    ]"
    :header-actions="[
      { label: 'Export', variant: 'outline' },
      { label: 'Create Invoice', variant: 'default' }
    ]"
  >
    <Toaster />

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="text-sm text-blue-600">Total Invoices</div>
        <div class="text-2xl font-bold text-blue-800">{{ statistics?.total_invoices || 0 }}</div>
      </div>

      <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
        <div class="text-sm text-green-600">Paid</div>
        <div class="text-2xl font-bold text-green-800">{{ statistics?.paid_invoices || 0 }}</div>
      </div>

      <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="text-sm text-yellow-600">Pending</div>
        <div class="text-2xl font-bold text-yellow-800">{{ statistics?.pending_invoices || 0 }}</div>
      </div>

      <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="text-sm text-red-600">Overdue</div>
        <div class="text-2xl font-bold text-red-800">{{ statistics?.overdue_invoices || 0 }}</div>
      </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead class="w-12">
              <input type="checkbox" class="rounded" />
            </TableHead>
            <TableHead>Invoice #</TableHead>
            <TableHead>Customer</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Amount</TableHead>
            <TableHead>Due Date</TableHead>
            <TableHead>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="invoice in (filteredInvoices?.data || [])" :key="invoice.id || invoice.uuid">
            <TableCell>
              <input type="checkbox" class="rounded" />
            </TableCell>
            <TableCell>
              <div class="cursor-pointer hover:bg-gray-50 p-2 rounded -m-2 transition-colors duration-150">
                <div class="font-medium"># {{ invoice.invoice_number || 'INV-001' }}</div>
                <div class="text-xs text-gray-500">{{ new Date(invoice.issue_date || Date.now()).toLocaleDateString() }}</div>
              </div>
            </TableCell>
            <TableCell>
              <div class="font-medium">{{ invoice.customer_name || 'Unknown Customer' }}</div>
              <div class="text-xs text-gray-500">{{ invoice.customer_email || '' }}</div>
            </TableCell>
            <TableCell>
              <Badge :variant="getSeverity(invoice.status || 'draft')">{{ invoice.status || 'draft' }}</Badge>
            </TableCell>
            <TableCell>
              <div class="font-medium">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</div>
              <div class="text-xs text-gray-500">{{ invoice.currency || 'USD' }}</div>
            </TableCell>
            <TableCell>
              <div>{{ invoice.due_date ? new Date(invoice.due_date).toLocaleDateString() : 'No due date' }}</div>
            </TableCell>
            <TableCell>
              <div class="flex gap-1">
                <Button variant="ghost" size="sm" @click="viewInvoice(invoice)">
                  View
                </Button>
                <Button variant="ghost" size="sm">
                  Edit
                </Button>
                <Button variant="destructive" size="sm" @click="confirmDelete(invoice)">
                  Delete
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableRow v-if="!filteredInvoices?.data?.length">
            <TableCell colspan="7" class="text-center py-8 text-gray-500">
              No invoices found
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="deleteInvoiceDialog" @update:open="deleteInvoiceDialog = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Confirm Delete</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete <strong v-if="invoiceToDelete">{{ invoiceToDelete.invoice_number }}</strong>?
            This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="deleteInvoiceDialog = false">Cancel</Button>
          <Button variant="destructive" @click="deleteInvoice">Delete</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </UniversalLayout>
</template>