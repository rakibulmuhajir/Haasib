<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Users,
  UserPlus,
  Mail,
  Phone,
  ArrowLeft,
  Settings,
  Eye,
  Pencil,
  Trash2,
} from 'lucide-vue-next'

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
  base_currency: string
  is_active: boolean
  created_at: string
  open_balance: number
  available_credit: number
  payments_received: number
  outstanding_balance: number
  overdue_balance: number
  last_invoice_date?: string | null
  last_payment_date?: string | null
}

interface PaginatedCustomers {
  data: CustomerRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  links: Array<{ url: string | null; label: string; active: boolean }>
}

interface Filters {
  search: string
  include_inactive: boolean
  with_overdue: boolean
  with_outstanding: boolean
  sort_by: string
  sort_dir: string
}

const props = defineProps<{
  company: CompanyRef
  customers: PaginatedCustomers
  filters: Filters
}>()

const searchQuery = ref(props.filters.search)
const withOverdue = ref(props.filters.with_overdue)
const withOutstanding = ref(props.filters.with_outstanding)
const sortBy = ref(props.filters.sort_by || 'name')
const sortDir = ref(props.filters.sort_dir || 'asc')

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Customers' },
])

const handleSearch = (value: string) => {
  router.get(
    `/${props.company.slug}/customers`,
    {
      search: value,
      include_inactive: props.filters.include_inactive,
      with_overdue: withOverdue.value,
      with_outstanding: withOutstanding.value,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    },
    { preserveState: true, replace: true }
  )
}

const handleCreate = () => {
  router.visit(`/${props.company.slug}/customers/create`)
}

const handleView = (customer: CustomerRow) => {
  router.visit(`/${props.company.slug}/customers/${customer.id}`)
}

const handleEdit = (customer: CustomerRow) => {
  router.visit(`/${props.company.slug}/customers/${customer.id}/edit`)
}

const handleDelete = (customer: CustomerRow) => {
  if (confirm(`Are you sure you want to delete ${customer.name}?`)) {
    router.delete(`/${props.company.slug}/customers/${customer.id}`)
  }
}

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.company.base_currency || 'USD',
  }).format(amount)
}

const applyFilters = () => {
  router.get(
    `/${props.company.slug}/customers`,
    {
      search: searchQuery.value,
      include_inactive: props.filters.include_inactive,
      with_overdue: withOverdue.value,
      with_outstanding: withOutstanding.value,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    },
    { preserveState: true, replace: true }
  )
}

const handleFilterChange = (value: string) => {
  withOverdue.value = value === 'overdue';
  withOutstanding.value = value === 'outstanding';
  applyFilters();
}

const handleSortChange = (value: string) => {
  const [by, dir] = value.split('-')
  sortBy.value = by || 'name'
  sortDir.value = dir === 'desc' ? 'desc' : 'asc'
  applyFilters();
}

const tableColumns = [
  {
    key: 'customer_number',
    label: '#',
    sortable: true,
  },
  {
    key: 'name',
    label: 'Customer',
    sortable: true,
  },
  {
    key: 'contact',
    label: 'Contact',
  },
  {
    key: 'currency',
    label: 'Currency',
  },
  {
    key: 'outstanding_balance',
    label: 'Outstanding',
    sortable: true,
  },
  {
    key: 'overdue_balance',
    label: 'Overdue',
    sortable: true,
  },
  {
    key: 'last_invoice_date',
    label: 'Last Invoice',
    sortable: true,
  },
  {
    key: 'last_payment_date',
    label: 'Last Payment',
    sortable: true,
  },
  {
    key: 'available_credit',
    label: 'Credit Available',
  },
  {
    key: 'is_active',
    label: 'Status',
    sortable: true,
  },
  {
    key: 'actions',
    label: 'Actions',
    class: 'text-right',
  },
]
</script>

<template>
  <Head :title="`Customers - ${company.name}`" />
  <PageShell
    title="Customers"
    :icon="Users"
    :breadcrumbs="breadcrumbs"
    :back-button="{
      label: 'Back to Company',
      onClick: () => router.visit(`/${company.slug}`),
      icon: ArrowLeft,
    }"
    searchable
    v-model:search="searchQuery"
    search-placeholder="Search by name, email, phone, or customer number..."
    @update:search="handleSearch"
  >
    <template #description>
      Manage customers for <span class="font-medium text-slate-300">{{ company.name }}</span>
    </template>

    <template #actions>
      <Button size="sm" @click="handleCreate">
        <UserPlus class="mr-2 h-4 w-4" />
        Add Customer
      </Button>
    </template>

    <!-- Filters & Sort -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-4">
      <div class="flex gap-3">
        <Select :model-value="withOverdue ? 'overdue' : withOutstanding ? 'outstanding' : 'all'" @update:modelValue="handleFilterChange">
          <SelectTrigger class="w-[200px]">
            <SelectValue placeholder="Filter customers" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All customers</SelectItem>
            <SelectItem value="overdue">With overdue balance</SelectItem>
            <SelectItem value="outstanding">With outstanding balance</SelectItem>
          </SelectContent>
        </Select>

        <Select :model-value="`${sortBy}-${sortDir}`" @update:modelValue="handleSortChange">
          <SelectTrigger class="w-[200px]">
            <SelectValue placeholder="Sort by" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="name-asc">Name (A-Z)</SelectItem>
            <SelectItem value="outstanding-desc">Outstanding (high → low)</SelectItem>
            <SelectItem value="overdue-desc">Overdue (high → low)</SelectItem>
            <SelectItem value="last_invoice-desc">Last invoice (newest)</SelectItem>
            <SelectItem value="last_payment-desc">Last payment (newest)</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-if="customers.data.length === 0"
      :icon="Users"
      title="No customers found"
      :description="searchQuery ? 'Try adjusting your search terms' : 'Get started by adding your first customer'"
    >
      <template #actions>
        <Button v-if="!searchQuery" @click="handleCreate" size="sm">
          <UserPlus class="mr-2 h-4 w-4" />
          Add Customer
        </Button>
      </template>
    </EmptyState>

    <!-- Customers Table -->
    <DataTable
      v-else
      :data="customers.data"
      :columns="tableColumns"
      title="Customers"
      :description="`${customers.total} ${customers.total === 1 ? 'customer' : 'customers'}`"
      key-field="id"
    >
      <template #cell-customer_number="{ row }">
        <span class="font-mono text-xs text-slate-400">{{ row.customer_number }}</span>
      </template>

      <template #cell-name="{ row }">
        <div class="flex flex-col">
          <span class="font-medium text-slate-100">{{ row.name }}</span>
        </div>
      </template>

      <template #cell-contact="{ row }">
        <div class="flex flex-col gap-1">
          <div v-if="row.email" class="flex items-center gap-1 text-slate-400">
            <Mail class="h-3 w-3" />
            <span class="text-xs">{{ row.email }}</span>
          </div>
          <div v-if="row.phone" class="flex items-center gap-1 text-slate-400">
            <Phone class="h-3 w-3" />
            <span class="text-xs">{{ row.phone }}</span>
          </div>
          <span v-if="!row.email && !row.phone" class="text-slate-500">—</span>
        </div>
      </template>

      <template #cell-currency="{ row }">
        <Badge variant="outline">{{ row.base_currency }}</Badge>
      </template>

      <template #cell-outstanding_balance="{ row }">
        <span class="font-medium text-slate-100">{{ formatMoney(row.outstanding_balance || row.open_balance) }}</span>
      </template>

      <template #cell-overdue_balance="{ row }">
        <span class="text-slate-100">{{ formatMoney(row.overdue_balance) }}</span>
      </template>

      <template #cell-last_invoice_date="{ row }">
        <span class="text-xs text-slate-300">{{ row.last_invoice_date ? new Date(row.last_invoice_date).toLocaleDateString() : '—' }}</span>
      </template>

      <template #cell-last_payment_date="{ row }">
        <span class="text-xs text-slate-300">{{ row.last_payment_date ? new Date(row.last_payment_date).toLocaleDateString() : '—' }}</span>
      </template>

      <template #cell-available_credit="{ row }">
        <span class="text-slate-100">{{ formatMoney(row.available_credit) }}</span>
      </template>

      <template #cell-is_active="{ row }">
        <Badge :variant="row.is_active ? 'default' : 'secondary'">
          {{ row.is_active ? 'Active' : 'Inactive' }}
        </Badge>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button size="sm" variant="ghost">
                <Settings class="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem @click="handleView(row)">
                <Eye class="mr-2 h-4 w-4" />
                View
              </DropdownMenuItem>
              <DropdownMenuItem @click="handleEdit(row)">
                <Pencil class="mr-2 h-4 w-4" />
                Edit
              </DropdownMenuItem>
              <DropdownMenuItem @click="handleDelete(row)" class="text-red-400">
                <Trash2 class="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </template>

      <template #mobile-card="{ row }">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="font-medium text-slate-100">{{ row.name }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ row.customer_number }}</div>
            <div v-if="row.email" class="flex items-center gap-1 text-xs text-slate-400 mt-1">
              <Mail class="h-3 w-3" />
              <span>{{ row.email }}</span>
            </div>
            <div v-if="row.phone" class="flex items-center gap-1 text-xs text-slate-400 mt-1">
              <Phone class="h-3 w-3" />
              <span>{{ row.phone }}</span>
            </div>
            <div class="flex items-center gap-4 mt-3 text-xs text-slate-400">
              <div class="flex flex-col">
                <span class="text-slate-500">Outstanding</span>
                <span class="font-medium text-slate-100">{{ formatMoney(row.outstanding_balance || row.open_balance) }}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-slate-500">Overdue</span>
                <span class="text-slate-100">{{ formatMoney(row.overdue_balance) }}</span>
              </div>
            </div>
            <div class="flex items-center gap-3 mt-2">
              <Badge variant="outline" size="sm">{{ row.base_currency }}</Badge>
              <Badge :variant="row.is_active ? 'default' : 'secondary'" size="sm">
                {{ row.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </div>
          </div>
          <Button size="sm" variant="ghost" @click="handleView(row)">
            <Eye class="h-4 w-4" />
          </Button>
        </div>
      </template>
    </DataTable>
  </PageShell>
</template>
