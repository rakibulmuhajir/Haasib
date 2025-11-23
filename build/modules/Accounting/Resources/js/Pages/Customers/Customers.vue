<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Accounting', href: '/dashboard/accounting' },
  { label: 'Customers', active: true },
]

const props = defineProps<{
  customers?: Array<{
    id: string
    customer_number: string
    name: string
    email?: string
    status: string
    created_at: string
  }>
}>()

const headerActions = [
  { label: 'Import', variant: 'outline' as const },
  { label: 'Add Customer', variant: 'default' as const },
]
</script>

<template>
  <Head title="Customers" />
  
  <UniversalLayout
    title="Customers"
    subtitle="Manage your customer relationships"
    :breadcrumbs="breadcrumbs"
    :header-actions="headerActions"
  >
    <div class="space-y-6">
      <!-- Customer Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Customers</h3>
          <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ customers?.length || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</h3>
          <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ customers?.filter(c => c.status === 'active').length || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</h3>
          <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ customers?.filter(c => c.status === 'pending').length || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Inactive</h3>
          <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ customers?.filter(c => c.status === 'inactive').length || 0 }}</p>
        </div>
      </div>

      <!-- Customers Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Customer #</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Created</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="customer in customers" :key="customer.id">
              <TableCell class="font-medium">{{ customer.customer_number }}</TableCell>
              <TableCell class="font-semibold">{{ customer.name }}</TableCell>
              <TableCell>{{ customer.email || '-' }}</TableCell>
              <TableCell>
                <span 
                  :class="{
                    'inline-flex px-2 py-1 text-xs font-semibold rounded-full': true,
                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': customer.status === 'active',
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': customer.status === 'pending',
                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': customer.status === 'inactive'
                  }"
                >
                  {{ customer.status }}
                </span>
              </TableCell>
              <TableCell>{{ new Date(customer.created_at).toLocaleDateString() }}</TableCell>
              <TableCell>
                <div class="flex space-x-2">
                  <Button variant="ghost" size="sm">Edit</Button>
                  <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-700">Delete</Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="!customers?.length">
              <TableCell colspan="6" class="text-center py-8 text-gray-500 dark:text-gray-400">
                No customers found
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </UniversalLayout>
</template>