<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Form, Field } from '@/components/ui/form'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useForm } from '@inertiajs/vue3'

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
}>()

const form = useForm({
  name: '',
  email: '',
})

const submit = () => {
  form.post('/customers', {
    onSuccess: () => form.reset(),
  })
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
        <Form @submit.prevent="submit" class="flex items-center gap-2">
          <Field name="name">
            <input
              v-model="form.name"
              class="h-9 px-3 rounded border border-input bg-background text-sm"
              type="text"
              placeholder="Customer name"
              required
            />
          </Field>
          <Field name="email">
            <input
              v-model="form.email"
              class="h-9 px-3 rounded border border-input bg-background text-sm"
              type="email"
              placeholder="Email (optional)"
            />
          </Field>
          <Button type="submit" :disabled="form.processing || !form.name.trim()">
            {{ form.processing ? 'Saving...' : 'Add Customer' }}
          </Button>
        </Form>
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
