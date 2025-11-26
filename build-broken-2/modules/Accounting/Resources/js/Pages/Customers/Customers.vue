<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import CurrencySelector from '@/components/currency/CurrencySelector.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Accounting', href: '/dashboard/accounting' },
  { label: 'Customers', active: true },
]

interface Currency {
  code: string
  name: string
  symbol: string
  display_name: string
  is_base?: boolean
}

interface Customer {
  id: string
  customer_number: string
  name: string
  email?: string | null
  status: string
  preferred_currency_code?: string
}

const props = defineProps<{
  customers?: Customer[]
  currencies: Currency[]
  baseCurrency?: {
    code: string
    name: string
    symbol: string
  }
}>()

const showCreateModal = ref(false)

const form = useForm({
  name: '',
  email: '',
  preferred_currency_code: props.baseCurrency?.code || '',
})

const submit = () => {
  form.post('/accounting/customers', {
    onSuccess: () => {
      form.reset()
      showCreateModal.value = false
    },
  })
}

const openCreateModal = () => {
  form.reset()
  form.preferred_currency_code = props.baseCurrency?.code || ''
  showCreateModal.value = true
}
</script>

<template>
  <Head title="Customers" />
  <AppSidebarLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
      <div class="space-y-2">
        <h1 class="text-3xl font-bold tracking-tight">Customers</h1>
        <p class="text-muted-foreground">
          Manage customer accounts, contact details, and preferred currencies.
        </p>
      </div>

      <Card>
        <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle>Customer Directory</CardTitle>
            <CardDescription>View and create customers for invoicing and billing.</CardDescription>
          </div>
          <Dialog v-model:open="showCreateModal">
            <DialogTrigger asChild>
              <Button @click="openCreateModal">Add Customer</Button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Create New Customer</DialogTitle>
              </DialogHeader>
              <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                  <Label for="name">Customer Name *</Label>
                  <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    placeholder="Enter customer name"
                    required
                    :class="{ 'border-red-500': form.errors.name }"
                  />
                  <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
                </div>
                
                <div class="space-y-2">
                  <Label for="email">Email Address</Label>
                  <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="Enter email address (optional)"
                    :class="{ 'border-red-500': form.errors.email }"
                  />
                  <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
                </div>
                
                <div class="space-y-2">
                  <Label for="currency">Preferred Currency</Label>
                  <CurrencySelector
                    id="currency"
                    v-model="form.preferred_currency_code"
                    :currencies="currencies"
                    placeholder="Select currency"
                    :class="{ 'border-red-500': form.errors.preferred_currency_code }"
                  />
                  <p v-if="form.errors.preferred_currency_code" class="text-sm text-red-500">{{ form.errors.preferred_currency_code }}</p>
                  <p class="text-sm text-muted-foreground">This will be the default currency for invoices</p>
                </div>
                
                <div class="flex justify-end gap-2 pt-4">
                  <Button 
                    type="button" 
                    variant="outline" 
                    @click="showCreateModal = false"
                    :disabled="form.processing"
                  >
                    Cancel
                  </Button>
                  <Button 
                    type="submit" 
                    :disabled="form.processing || !form.name.trim()"
                  >
                    {{ form.processing ? 'Creating...' : 'Create Customer' }}
                  </Button>
                </div>
              </form>
            </DialogContent>
          </Dialog>
        </CardHeader>
        <CardContent class="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer #</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Preferred Currency</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="customer in (props.customers || [])" :key="customer.id">
                <TableCell>{{ customer.customer_number }}</TableCell>
                <TableCell>{{ customer.name }}</TableCell>
                <TableCell>{{ customer.email || '—' }}</TableCell>
                <TableCell>
                  <div v-if="customer.preferred_currency_code" class="flex items-center gap-2">
                    <span class="font-mono text-sm">{{
                      currencies.find(c => c.code === customer.preferred_currency_code)?.symbol || customer.preferred_currency_code
                    }}</span>
                    <Badge variant="outline">{{ customer.preferred_currency_code }}</Badge>
                    <Badge v-if="customer.preferred_currency_code === baseCurrency?.code" variant="default" class="text-xs">
                      Base
                    </Badge>
                  </div>
                  <span v-else class="text-muted-foreground">—</span>
                </TableCell>
                <TableCell>
                  <Badge :variant="customer.status === 'active' ? 'default' : 'secondary'">
                    {{ customer.status }}
                  </Badge>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  </AppSidebarLayout>
</template>
