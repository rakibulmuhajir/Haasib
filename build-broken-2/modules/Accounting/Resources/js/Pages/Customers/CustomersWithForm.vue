<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import CustomerForm from '@/components/forms/CustomerForm.vue'

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
    email?: string | null
    status: string
  }>
  isMultiCurrencyEnabled?: boolean
  currencies?: Array<{
    code: string
    name: string
    symbol: string
    display_name: string
    is_base?: boolean
  }>
  baseCurrency?: {
    code: string
    name: string
    symbol: string
  }
}>()

const handleFormSuccess = () => {
  // Optionally refresh page or show success message
  console.log('Customer created successfully!')
}
</script>

<template>
  <Head title="Customers" />
  <UniversalLayout
    title="Customers"
    subtitle="Manage customer accounts"
    :breadcrumbs="breadcrumbs"
  >
    <div class="p-6 space-y-4">
      <div class="flex justify-between items-center">
        <h2 class="text-lg font-semibold">Customers</h2>
        
        <!-- Reusable Customer Form Component -->
        <CustomerForm 
          :initial-data="{ name: '', email: '' }"
          :is-multi-currency-enabled="props.isMultiCurrencyEnabled"
          :currencies="props.currencies"
          :base-currency="props.baseCurrency"
          submit-url="/accounting/customers"
          @success="handleFormSuccess"
        />
      </div>
      
      <div class="border rounded-md">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Customer #</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="customer in (props.customers || [])" :key="customer.id">
              <TableCell>{{ customer.customer_number }}</TableCell>
              <TableCell>{{ customer.name }}</TableCell>
              <TableCell>{{ customer.email || '—' }}</TableCell>
              <TableCell>{{ customer.status }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </UniversalLayout>
</template>