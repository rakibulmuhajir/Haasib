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
  MoreHorizontal,
  Search,
  Receipt,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Customer {
  id: string
  name: string
}

interface Invoice {
  id: string
  invoice_number: string
}

interface CreditNoteRow {
  id: string
  credit_note_number: string
  customer_id: string
  customer: Customer
  invoice_id?: string
  invoice?: Invoice
  amount: number
  base_currency: string
  reason: string
  status: string
  credit_date: string
  sent_at?: string
  posted_at?: string
  created_at: string
}

interface PaginatedCreditNotes {
  data: CreditNoteRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  credit_notes: PaginatedCreditNotes
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
  { title: 'Credit Notes', href: `/${props.company.slug}/credit-notes` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/credit-notes`,
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
    case 'issued':
      return 'default'
    case 'partial':
      return 'warning'
    case 'applied':
      return 'success'
    case 'void':
      return 'destructive'
    default:
      return 'secondary'
  }
}

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currency || 'USD',
  }).format(amount)
}

const columns = [
  { key: 'credit_note_number', label: 'Credit Note #' },
  { key: 'customer', label: 'Customer' },
  { key: 'invoice', label: 'Invoice' },
  { key: 'reason', label: 'Reason' },
  { key: 'amount', label: 'Amount' },
  { key: 'status', label: 'Status' },
  { key: 'credit_date', label: 'Date' },
  { key: 'actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.credit_notes.data.map((creditNote) => ({
    id: creditNote.id,
    credit_note_number: creditNote.credit_note_number,
    customer: creditNote.customer.name,
    invoice: creditNote.invoice?.invoice_number || '-',
    reason: creditNote.reason,
    amount: formatCurrency(creditNote.amount, creditNote.base_currency),
    status: creditNote.status,
    credit_date: new Date(creditNote.credit_date).toLocaleDateString(),
    actions: creditNote.id, // Only store the ID for fallback
    _creditNoteObject: creditNote, // Store full object for template
  }))
})
</script>

<template>
  <Head title="Credit Notes" />

  <PageShell
    :title="`Credit Notes`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/credit-notes/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Create Credit Note
      </Button>
    </template>

    <!-- Filters -->
    <div class="flex flex-col gap-4 md:flex-row mb-6">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search credit notes..."
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
          <SelectItem value="issued">Issued</SelectItem>
          <SelectItem value="partial">Partially Applied</SelectItem>
          <SelectItem value="applied">Applied</SelectItem>
          <SelectItem value="void">Void</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Data Table -->
    <DataTable
      :columns="columns"
      :data="tableData"
      :pagination="credit_notes"
    >
      <template #status="{ value }">
        <Badge :variant="getStatusBadgeVariant(value)">
          {{ value }}
        </Badge>
      </template>

      <template #actions="{ row }">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" class="h-8 w-8 p-0">
              <MoreHorizontal class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/credit-notes/${row._creditNoteObject.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/credit-notes/${row._creditNoteObject.id}/edit`)">
              <Pencil class="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>

      <!-- Mobile Card Template -->
      <template #mobile-card="{ row }">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
          <div class="space-y-3">
            <!-- Header with credit note number and status -->
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-zinc-900">{{ row.credit_note_number }}</h3>
                <p class="text-sm text-zinc-500">{{ row.customer }}</p>
              </div>
              <Badge :variant="getStatusBadgeVariant(row.status)">
                {{ row.status }}
              </Badge>
            </div>

            <!-- Amount and date -->
            <div class="flex items-center justify-between">
              <span class="text-sm text-zinc-500">Amount</span>
              <span class="font-medium">{{ row.amount }}</span>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-sm text-zinc-500">Date</span>
              <span class="font-medium">{{ row.credit_date }}</span>
            </div>

            <!-- Reason -->
            <div>
              <span class="text-sm text-zinc-500">Reason</span>
              <p class="text-sm text-zinc-900 mt-1">{{ row.reason }}</p>
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
                  <DropdownMenuItem @click="router.get(`/${company.slug}/credit-notes/${row._creditNoteObject.id}`)">
                    <Eye class="mr-2 h-4 w-4" />
                    View
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="router.get(`/${company.slug}/credit-notes/${row._creditNoteObject.id}/edit`)">
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit
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
      v-if="credit_notes.data.length === 0"
      icon="Receipt"
      title="No credit notes yet"
      description="Create your first credit note to get started."
      :action-label="'Create Credit Note'"
      @action="router.get(`/${company.slug}/credit-notes/create`)"
    />
  </PageShell>
</template>