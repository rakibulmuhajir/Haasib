<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Accounting', href: '/dashboard/accounting' },
  { label: 'Invoices', active: true },
]

const props = defineProps<{
  invoices?: Array<{
    id: string
    invoice_number: string
    customer_name?: string
    total_amount: string | number
    currency_code?: string
    currency_symbol?: string
    status: string
    due_date?: string | null
  }>
}>()
</script>

<template>
  <Head title="Invoices" />
  <UniversalLayout
    title="Invoices"
    subtitle="Manage customer invoices"
    :breadcrumbs="breadcrumbs"
  >
    <div class="p-6 space-y-4">
      <div class="flex justify-between items-center">
        <h2 class="text-lg font-semibold">Invoices</h2>
        <Button variant="default" @click="$inertia.visit('/accounting/invoices/create')">New Invoice</Button>
      </div>
      <div class="border rounded-md">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Invoice #</TableHead>
              <TableHead>Customer</TableHead>
              <TableHead>Amount</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Due</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="invoice in (props.invoices || [])" :key="invoice.id">
              <TableCell>{{ invoice.invoice_number }}</TableCell>
              <TableCell>{{ invoice.customer_name || '—' }}</TableCell>
              <TableCell>
                <span class="font-mono">
                  {{ invoice.currency_symbol || '$' }}{{ invoice.total_amount }}
                </span>
                <span v-if="invoice.currency_code" class="text-xs text-muted-foreground ml-1">
                  {{ invoice.currency_code }}
                </span>
              </TableCell>
              <TableCell>
                <span :class="{
                  'px-2 py-1 text-xs rounded': true,
                  'bg-yellow-100 text-yellow-800': invoice.status === 'draft',
                  'bg-blue-100 text-blue-800': invoice.status === 'sent',
                  'bg-green-100 text-green-800': invoice.status === 'paid',
                  'bg-red-100 text-red-800': invoice.status === 'overdue',
                  'bg-gray-100 text-gray-800': !['draft', 'sent', 'paid', 'overdue'].includes(invoice.status)
                }">
                  {{ invoice.status }}
                </span>
              </TableCell>
              <TableCell>{{ invoice.due_date || '—' }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </UniversalLayout>
</template>
