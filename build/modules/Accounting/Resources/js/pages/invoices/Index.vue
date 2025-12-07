<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  FileText,
  Plus,
  ArrowLeft,
  Eye,
  Pencil,
  Trash2,
  Send,
  MoreHorizontal,
  Search,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface InvoiceLineItem {
  id: string
  description: string
  quantity: number
  unit_price: number
  total: number
}

interface Customer {
  id: string
  name: string
}

interface InvoiceRow {
  id: string
  invoice_number: string
  customer_id: string
  customer: Customer
  status: string
  currency: string
  total_amount: number
  balance: number
  invoice_date: string
  due_date: string
  created_at: string
}

interface PaginatedInvoices {
  data: InvoiceRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  invoices: PaginatedInvoices
  filters: {
    search: string
    status: string
    customer_id: string
  }
}>()

const search = ref(props.filters.search)
const status = ref(props.filters.status || 'all')
const customerId = ref(props.filters.customer_id)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Invoices', href: `/${props.company.slug}/invoices` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/invoices`,
    {
      search: search.value,
      status: status.value === 'all' ? '' : status.value,
      customer_id: customerId.value,
    },
    { preserveState: true }
  )
}

const getStatusBadgeVariant = (status: string) => {
  switch (status) {
    case 'draft':
      return 'secondary'
    case 'sent':
    case 'viewed':
      return 'default'
    case 'paid':
      return 'success'
    case 'overdue':
      return 'destructive'
    case 'cancelled':
    case 'void':
      return 'outline'
    default:
      return 'secondary'
  }
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
}

const columns = [
  { key: 'invoice_number', label: 'Invoice #' },
  { key: 'customer', label: 'Customer' },
  { key: 'status', label: 'Status' },
  { key: 'total_amount', label: 'Total' },
  { key: 'balance', label: 'Balance' },
  { key: 'due_date', label: 'Due Date' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.invoices.data.map((invoice) => ({
    id: invoice.id,
    invoice_number: invoice.invoice_number,
    customer: invoice.customer.name,
    status: invoice.status,
    total_amount: formatCurrency(invoice.total_amount, invoice.currency),
    balance: formatCurrency(invoice.balance, invoice.currency),
    due_date: new Date(invoice.due_date).toLocaleDateString(),
    _actions: invoice.id, // Only store the ID for fallback
    _invoiceObject: invoice, // Store full object for template
  }))
})
</script>

<template>
  <Head title="Invoices" />

  <PageShell
    :title="`Invoices`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/invoices/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Create Invoice
      </Button>
    </template>

    <!-- Filters -->
    <div class="flex flex-col gap-4 md:flex-row mb-6">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search invoices..."
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="status" @update:modelValue="handleSearch">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All Status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Status</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="sent">Sent</SelectItem>
          <SelectItem value="viewed">Viewed</SelectItem>
          <SelectItem value="paid">Paid</SelectItem>
          <SelectItem value="overdue">Overdue</SelectItem>
          <SelectItem value="cancelled">Cancelled</SelectItem>
          <SelectItem value="void">Void</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Data Table -->
    <DataTable
      :columns="columns"
      :data="tableData"
      :pagination="invoices"
    >
      <template #status="{ value }">
        <Badge :variant="getStatusBadgeVariant(value)">
          {{ value }}
        </Badge>
      </template>

      <template #cell-_actions="{ row }">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" class="h-8 w-8 p-0">
              <MoreHorizontal class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${row._invoiceObject.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${row._invoiceObject.id}/edit`)">
              <Pencil class="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.post(`/${company.slug}/invoices/${row._invoiceObject.id}/send`)">
              <Send class="mr-2 h-4 w-4" />
              Send
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>

      <!-- Mobile Card Template -->
      <template #mobile-card="{ row }">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
          <div class="space-y-3">
            <!-- Header with invoice number and status -->
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-zinc-900">{{ row.invoice_number }}</h3>
                <p class="text-sm text-zinc-500">{{ row.customer }}</p>
              </div>
              <Badge :variant="getStatusBadgeVariant(row.status)">
                {{ row.status }}
              </Badge>
            </div>

            <!-- Amount and due date -->
            <div class="flex items-center justify-between">
              <span class="text-sm text-zinc-500">Amount</span>
              <span class="font-medium">{{ row.total_amount }}</span>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-sm text-zinc-500">Due Date</span>
              <span class="font-medium">{{ row.due_date }}</span>
            </div>

            <!-- Actions -->
            <div class="pt-2 border-t border-zinc-100">
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" class="w-full justify-between">
                    Actions
                    <MoreHorizontal class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${row._invoiceObject.id}`)">
                    <Eye class="mr-2 h-4 w-4" />
                    View
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${row._invoiceObject.id}/edit`)">
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="router.post(`/${company.slug}/invoices/${row._invoiceObject.id}/send`)">
                    <Send class="mr-2 h-4 w-4" />
                    Send
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </div>
      </template>
    </DataTable>

    <!-- Empty State -->
    <EmptyState
      v-if="invoices.data.length === 0"
      icon="FileText"
      title="No invoices yet"
      description="Create your first invoice to get started."
      :action-label="'Create Invoice'"
      @action="router.get(`/${company.slug}/invoices/create`)"
    />
  </PageShell>
</template>
