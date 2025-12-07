<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { FileText, Pencil, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface LineItem {
  id: string
  description: string
  quantity: number
  unit_price: number
  tax_rate: number
  discount_rate: number
  line_total: number
  tax_amount: number
  total: number
}

interface VendorRef {
  id: string
  name: string
}

interface BillRef {
  id: string
  bill_number: string
  vendor: VendorRef | null
  bill_date: string
  due_date: string
  status: string
  currency: string
  subtotal: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  paid_amount: number
  balance: number
  notes: string | null
  internal_notes: string | null
  line_items: LineItem[]
}

const props = defineProps<{
  company: CompanyRef
  bill: BillRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bills', href: `/${props.company.slug}/bills` },
  { title: props.bill.bill_number, href: `/${props.company.slug}/bills/${props.bill.id}` },
]

const columns = [
  { key: 'description', label: 'Description' },
  { key: 'quantity', label: 'Qty' },
  { key: 'unit_price', label: 'Unit Price' },
  { key: 'tax_rate', label: 'Tax %' },
  { key: 'discount_rate', label: 'Discount %' },
  { key: 'total', label: 'Total' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const statusVariant = (s: string) => {
  if (s === 'draft') return 'secondary'
  if (s === 'received') return 'default'
  if (s === 'partial') return 'warning'
  if (s === 'paid') return 'success'
  if (s === 'overdue') return 'destructive'
  return 'secondary'
}

const handleDelete = () => {
  if (!confirm('Delete this bill?')) return
  router.delete(`/${props.company.slug}/bills/${props.bill.id}`)
}
</script>

<template>
  <Head :title="`Bill ${bill.bill_number}`" />
  <PageShell
    :title="`Bill ${bill.bill_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <div class="flex gap-2">
        <Button variant="outline" @click="router.get(`/${company.slug}/bills/${bill.id}/edit`)">
          <Pencil class="mr-2 h-4 w-4" />
          Edit
        </Button>
        <Button variant="destructive" @click="handleDelete">
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
        </Button>
      </div>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Vendor</div>
        <div class="font-semibold">{{ bill.vendor?.name ?? '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Bill Date</div>
        <div class="font-semibold">{{ bill.bill_date }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Due Date</div>
        <div class="font-semibold">{{ bill.due_date }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Status</div>
        <Badge :variant="statusVariant(bill.status)">{{ bill.status }}</Badge>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Total</div>
        <div class="font-semibold">{{ formatMoney(bill.total_amount, bill.currency) }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Balance</div>
        <div class="font-semibold">{{ formatMoney(bill.balance, bill.currency) }}</div>
      </div>
    </div>

    <div class="mt-6">
      <div class="text-lg font-semibold mb-2">Line Items</div>
      <DataTable
        :columns="columns"
        :data="bill.line_items.map((li) => ({ ...li, total: formatMoney(li.total, bill.currency) }))"
      />
    </div>

    <div class="mt-6 grid gap-2 md:w-1/2">
      <div class="flex justify-between text-sm">
        <span>Subtotal</span>
        <span>{{ formatMoney(bill.subtotal, bill.currency) }}</span>
      </div>
      <div class="flex justify-between text-sm">
        <span>Tax</span>
        <span>{{ formatMoney(bill.tax_amount, bill.currency) }}</span>
      </div>
      <div class="flex justify-between text-sm">
        <span>Discount</span>
        <span>{{ formatMoney(bill.discount_amount, bill.currency) }}</span>
      </div>
      <div class="flex justify-between text-base font-semibold">
        <span>Total</span>
        <span>{{ formatMoney(bill.total_amount, bill.currency) }}</span>
      </div>
    </div>
  </PageShell>
</template>
