<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Head } from '@inertiajs/vue3'
import UniversalLayout from '../../../../../../resources/js/layouts/UniversalLayout.vue'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../../../../../resources/js/components/ui/table'
import Button from '../../../../../../resources/js/components/ui/button/Button.vue'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '../../../../../../resources/js/components/ui/dialog'
import Badge from '../../../../../../resources/js/components/ui/badge/Badge.vue'
import { Toaster } from 'vue-sonner'
import { route } from 'ziggy-js'

const props = defineProps({
    customers: Object,
    filters: Object,
    statistics: Object,
    can: Object
})

// Using vue-sonner toast directly
// Define page actions for customers
const customerActions = [
    {
        key: 'add-customer',
        label: 'Add Customer',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'customers.create'
    }
]

// State
const deleteCustomerDialog = ref(false)
const customerToDelete = ref(null)
const selectedCustomers = ref([])
const loading = ref(false)

// Computed properties
const filteredCustomers = computed(() => props.customers || { data: [] })

// Methods
const confirmDelete = (customer) => {
    customerToDelete.value = customer
    deleteCustomerDialog.value = true
}

const deleteCustomer = () => {
    if (!customerToDelete.value) return

    toast.success('Customer deleted successfully')
    
    deleteCustomerDialog.value = false
    customerToDelete.value = null
}

const viewCustomer = (customer) => {
    console.log('Viewing customer:', customer)
    
    toast.info('Customer details view coming soon')
}

const getSeverity = (status) => {
    switch (status) {
        case 'active': return 'default'
        case 'inactive': return 'secondary'
        case 'blocked': return 'destructive'
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
  <Head title="Customers" />
  
  <UniversalLayout
    title="Customers"
    subtitle="Manage customer relationships"
    :breadcrumbs="[
      { label: 'Dashboard', href: '/dashboard' },
      { label: 'Customers', active: true }
    ]"
    :header-actions="[
      { label: 'Export', variant: 'outline' },
      { label: 'Add Customer', variant: 'default' }
    ]"
  >
    <Toaster />

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="text-sm text-blue-600">Total Customers</div>
        <div class="text-2xl font-bold text-blue-800">{{ statistics?.total_customers || 0 }}</div>
      </div>

      <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
        <div class="text-sm text-green-600">Active</div>
        <div class="text-2xl font-bold text-green-800">{{ statistics?.active_customers || 0 }}</div>
      </div>

      <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="text-sm text-yellow-600">Inactive</div>
        <div class="text-2xl font-bold text-yellow-800">{{ statistics?.inactive_customers || 0 }}</div>
      </div>

      <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="text-sm text-red-600">Blocked</div>
        <div class="text-2xl font-bold text-red-800">{{ statistics?.blocked_customers || 0 }}</div>
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
            <TableHead>Name</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Credit Limit</TableHead>
            <TableHead>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="customer in (filteredCustomers?.data || [])" :key="customer.id || customer.uuid">
            <TableCell>
              <input type="checkbox" class="rounded" />
            </TableCell>
            <TableCell>
              <div class="cursor-pointer hover:bg-gray-50 p-2 rounded -m-2 transition-colors duration-150">
                <div class="flex items-center">
                  <div>
                    <div class="font-medium">{{ customer.name || 'Unnamed Customer' }}</div>
                    <div class="text-xs text-gray-500">{{ customer.customer_number || 'N/A' }}</div>
                    <div v-if="customer.email" class="text-xs text-blue-600 truncate flex items-center">
                      {{ customer.email }}
                    </div>
                  </div>
                </div>
              </div>
            </TableCell>
            <TableCell>
              <Badge :variant="getSeverity(customer.status || 'inactive')">{{ customer.status || 'inactive' }}</Badge>
            </TableCell>
            <TableCell>
              <div class="flex items-center">
                <div>
                  <span v-if="customer.credit_limit">
                    {{ formatCurrency(customer.credit_limit, customer.default_currency) }}
                  </span>
                  <span v-else class="text-gray-400 italic">No limit</span>
                  <div class="text-xs text-gray-500">{{ customer.default_currency || 'USD' }}</div>
                </div>
              </div>
            </TableCell>
            <TableCell>
              <div class="flex gap-1">
                <Button variant="ghost" size="sm" @click="viewCustomer(customer)">
                  View
                </Button>
                <Button variant="ghost" size="sm">
                  Edit
                </Button>
                <Button variant="destructive" size="sm" @click="confirmDelete(customer)">
                  Delete
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableRow v-if="!filteredCustomers?.data?.length">
            <TableCell colspan="5" class="text-center py-8 text-gray-500">
              No customers found
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="deleteCustomerDialog" @update:open="deleteCustomerDialog = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Confirm Delete</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete <strong v-if="customerToDelete">{{ customerToDelete.name }}</strong>?
            This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="deleteCustomerDialog = false">Cancel</Button>
          <Button variant="destructive" @click="deleteCustomer">Delete</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </UniversalLayout>
</template>