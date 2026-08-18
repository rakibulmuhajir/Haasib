<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import MoneyText from '@/components/MoneyText.vue'
import StatusBadge from '@/components/StatusBadge.vue'
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
import { useLexicon } from '@/composables/useLexicon'
import { formatDateTime } from '@/lib/datetime'
import { Plus, Eye, Pencil, Send, MoreHorizontal, Search } from 'lucide-vue-next'

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
const { t } = useLexicon()

const breadcrumbs: BreadcrumbItem[] = [
  { title: t('dashboard'), href: `/${props.company.slug}` },
  { title: t('invoices'), href: `/${props.company.slug}/invoices` },
]

/** Inertia keeps the page mounted across a filter round-trip, so the table can
 *  show it is fetching rather than swapping silently to a different result. */
const fetching = ref(false)

const query = (page?: number) => ({
  search: search.value,
  status: status.value === 'all' ? '' : status.value,
  customer_id: customerId.value,
  page,
})

const go = (page?: number) => {
  fetching.value = true
  router.get(`/${props.company.slug}/invoices`, query(page), {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => (fetching.value = false),
  })
}

const handleSearch = () => go()
const handlePage = (page: number) => go(page)

/* An invoice is overdue when the money is late, not when a column says so.
   The server sets `overdue` on some rows and leaves others `sent` past their
   due date, so the register decides for itself and the amount carries it. */
const SETTLED = ['paid', 'cancelled', 'void', 'reversed']

const isOverdue = (invoice: InvoiceRow) =>
  !SETTLED.includes(invoice.status) &&
  invoice.balance > 0 &&
  new Date(invoice.due_date) < new Date()

const columns = [
  { key: 'invoice_number', label: t('invoiceNumber') },
  { key: 'customer', label: t('customer') },
  { key: 'status', label: t('status') },
  { key: 'total_amount', label: t('total'), numeric: true },
  { key: 'balance', label: t('balance'), numeric: true },
  { key: 'due_date', label: t('dueDate'), numeric: true },
  { key: '_actions', label: '', sortable: false },
]

/* Rows keep their real values. The old version pre-formatted everything into
   strings here, which is why the status column rendered raw text and the
   amounts arrived as unalignable prose — once a figure is a string the table
   can no longer tell it is a figure. */
const rows = computed(() =>
  props.invoices.data.map((invoice) => ({
    ...invoice,
    customer_name: invoice.customer?.name ?? '—',
    overdue: isOverdue(invoice),
  })),
)

const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })
</script>

<template>
  <Head :title="t('invoices')" />

  <PageShell :title="t('invoices')" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button @click="router.get(`/${company.slug}/invoices/create`)">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('newInvoice') }}
      </Button>
    </template>

    <div class="flex flex-col gap-4 md:flex-row">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
        <Input
          v-model="search"
          :placeholder="t('searchInvoicePlaceholder')"
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="status" @update:modelValue="handleSearch">
        <SelectTrigger class="w-[180px]">
          <SelectValue :placeholder="t('allStatus')" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">{{ t('allStatus') }}</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="sent">Sent</SelectItem>
          <SelectItem value="viewed">Viewed</SelectItem>
          <SelectItem value="paid">Paid</SelectItem>
          <SelectItem value="overdue">Overdue</SelectItem>
          <SelectItem value="cancelled">Cancelled</SelectItem>
          <SelectItem value="void">Voided</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- A long register of like things: compact is a statement about the work,
         not about who is doing it. -->
    <DataTable
      :columns="columns"
      :data="rows"
      :pagination="invoices"
      :loading="fetching"
      density="compact"
      @page-change="handlePage"
    >
      <template #cell-invoice_number="{ row }">
        <a
          :href="`/${company.slug}/invoices/${row.id}`"
          class="reference"
          @click.prevent="router.get(`/${company.slug}/invoices/${row.id}`)"
        >
          {{ row.invoice_number }}
        </a>
      </template>

      <template #cell-customer="{ row }">
        <span class="text-text-primary" dir="auto">{{ row.customer_name }}</span>
      </template>

      <template #cell-status="{ row }">
        <StatusBadge :status="row.overdue && row.status === 'sent' ? 'overdue' : row.status" />
      </template>

      <template #cell-total_amount="{ row }">
        <MoneyText :amount="row.total_amount" :currency="row.currency" locale="en-PK" />
      </template>

      <!-- The only red in the register, and only when the money is actually late. -->
      <template #cell-balance="{ row }">
        <MoneyText
          :amount="row.balance"
          :currency="row.currency"
          locale="en-PK"
          :tone="row.overdue ? 'overdue' : row.balance === 0 ? 'muted' : 'default'"
          dash-zero
        />
      </template>

      <template #cell-due_date="{ row }">
        <span class="date">{{ formatDate(row.due_date) }}</span>
      </template>

      <template #cell-_actions="{ row }">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" class="h-8 w-8 p-0">
              <MoreHorizontal class="h-4 w-4" />
              <span class="sr-only">Actions for {{ row.invoice_number }}</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${row.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/invoices/${row.id}/edit`)">
              <Pencil class="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.post(`/${company.slug}/invoices/${row.id}/send`)">
              <Send class="mr-2 h-4 w-4" />
              Mark as sent
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>

      <template #mobile-card="{ row }">
        <div class="mobile-card" @click="router.get(`/${company.slug}/invoices/${row.id}`)">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="reference">{{ row.invoice_number }}</div>
              <p class="truncate text-sm text-text-secondary" dir="auto">{{ row.customer_name }}</p>
            </div>
            <StatusBadge :status="row.overdue && row.status === 'sent' ? 'overdue' : row.status" />
          </div>

          <div class="mt-3 flex items-baseline justify-between gap-4">
            <span class="text-sm text-text-tertiary">Total</span>
            <MoneyText :amount="row.total_amount" :currency="row.currency" locale="en-PK" />
          </div>
          <div class="mt-1 flex items-baseline justify-between gap-4">
            <span class="text-sm text-text-tertiary">Still owed</span>
            <MoneyText
              :amount="row.balance"
              :currency="row.currency"
              locale="en-PK"
              :tone="row.overdue ? 'overdue' : row.balance === 0 ? 'muted' : 'default'"
              dash-zero
            />
          </div>
          <div class="mt-1 flex items-baseline justify-between gap-4">
            <span class="text-sm text-text-tertiary">Due</span>
            <span class="date">{{ formatDate(row.due_date) }}</span>
          </div>
        </div>
      </template>

      <!-- Empty belongs inside the table, not stacked beneath it. The old page
           rendered its own EmptyState below a table that was already saying
           "No data available", so the screen apologised twice. -->
      <template #empty>
        <EmptyState
          icon="FileText"
          :title="t('noInvoices')"
          :description="t('noInvoicesDesc')"
          :action-label="t('newInvoice')"
          size="sm"
          @action="router.get(`/${company.slug}/invoices/create`)"
        />
      </template>
    </DataTable>
  </PageShell>
</template>

<style scoped>
/* References and IDs are the mono role: they are read character by character
   and compared against a paper copy, not read as words. */
.reference {
  font-family: var(--mono-family);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-primary);
}

a.reference:hover {
  text-decoration: underline;
  text-underline-offset: 3px;
}

/* Dates line up like figures because in a register they are figures. */
.date {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  color: var(--text-secondary);
}

.mobile-card {
  border: 1px solid var(--rule-default);
  border-radius: var(--radius);
  background: var(--surface-raised);
  padding: var(--space-4, 1rem);
  cursor: pointer;
}
</style>
