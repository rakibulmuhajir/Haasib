<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Users, Search, Plus, Eye, Pencil, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface CustomerRow {
  id: string
  customer_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string | null
  is_active: boolean
  open_balance: number
  overdue_balance: number
  invoice_count: number
  available_credit: number
  credit_note_count: number
  payments_received: number
  last_invoice_date: string | null
  last_payment_date: string | null
}

interface PaginatedCustomers {
  data: CustomerRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  customers: PaginatedCustomers
  filters: {
    search?: string
    include_inactive?: boolean
    with_overdue?: boolean
    with_outstanding?: boolean
    sort_by?: string
    sort_dir?: string
  }
}>()

const search = ref(props.filters.search ?? '')
const includeInactive = ref(!!props.filters.include_inactive)
const withOverdue = ref(!!props.filters.with_overdue)
const withOutstanding = ref(!!props.filters.with_outstanding)
const sortBy = ref(props.filters.sort_by ?? 'name')
const sortDir = ref(props.filters.sort_dir ?? 'asc')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
]

const columns = [
  { key: 'customer_number', label: 'Customer #' },
  { key: 'name', label: 'Name' },
  { key: 'open_balance', label: 'Open Balance' },
  { key: 'overdue_balance', label: 'Overdue' },
  { key: 'invoice_count', label: 'Invoices' },
  { key: 'available_credit', label: 'Credit' },
  { key: 'last_invoice_date', label: 'Last Invoice' },
  { key: 'last_payment_date', label: 'Last Payment' },
  { key: 'actions', label: 'Actions' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const tableData = computed(() =>
  props.customers.data.map((c) => ({
    id: c.id,
    customer_number: c.customer_number,
    name: c.name,
    open_balance: formatMoney(c.open_balance ?? 0, c.base_currency || props.company.base_currency),
    overdue_balance: formatMoney(c.overdue_balance ?? 0, c.base_currency || props.company.base_currency),
    invoice_count: c.invoice_count ?? 0,
    available_credit: formatMoney(c.available_credit ?? 0, c.base_currency || props.company.base_currency),
    last_invoice_date: c.last_invoice_date ?? '—',
    last_payment_date: c.last_payment_date ?? '—',
  }))
)

const viewCustomer = (id: string) => {
  router.get(`/${props.company.slug}/customers/${id}`)
}

const editCustomer = (id: string) => {
  router.get(`/${props.company.slug}/customers/${id}/edit`)
}

const deleteCustomer = (id: string) => {
  if (confirm('Are you sure you want to delete this customer?')) {
    router.delete(`/${props.company.slug}/customers/${id}`)
  }
}

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/customers`,
    {
      search: search.value,
      include_inactive: includeInactive.value ? 1 : '',
      with_overdue: withOverdue.value ? 1 : '',
      with_outstanding: withOutstanding.value ? 1 : '',
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    },
    { preserveState: true }
  )
}

const sortOptions = [
  { value: 'name', label: 'Name' },
  { value: 'outstanding', label: 'Outstanding Balance' },
  { value: 'overdue', label: 'Overdue Balance' },
  { value: 'last_invoice', label: 'Last Invoice' },
  { value: 'last_payment', label: 'Last Payment' },
]
</script>

<template>
  <Head title="Customers" />
  <PageShell
    title="Customers"
    :breadcrumbs="breadcrumbs"
    :icon="Users"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/customers/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Customer
      </Button>
    </template>

    <div class="mb-4 grid gap-3 md:grid-cols-5">
      <div class="relative md:col-span-2">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search name, email, phone..."
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="includeInactive" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="Active only" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="false">Active only</SelectItem>
          <SelectItem :value="true">Include inactive</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="withOverdue" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All customers" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="false">All customers</SelectItem>
          <SelectItem :value="true">Only overdue</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="withOutstanding" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="Balance filter" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="false">Any balance</SelectItem>
          <SelectItem :value="true">With outstanding</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="sortBy" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="Sort by" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="sortDir" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="Sort direction" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="asc">Ascending</SelectItem>
          <SelectItem value="desc">Descending</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="!customers.data.length">
      <EmptyState
        title="No customers yet"
        description="Create your first customer to start invoicing."
        cta-text="New Customer"
        @click="router.get(`/${company.slug}/customers/create`)"
      />
    </div>

    <div v-else>
      <DataTable
        :columns="columns"
        :data="tableData"
        :pagination="customers"
      >
        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="viewCustomer(row.id)">
              <Eye class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="editCustomer(row.id)">
              <Pencil class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click.stop="deleteCustomer(row.id)">
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
