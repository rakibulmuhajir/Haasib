<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { FileText, Plus, Search } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface VendorRef {
  id: string
  name: string
}

interface BillRow {
  id: string
  bill_number: string
  vendor: VendorRef | null
  bill_date: string
  due_date: string
  total_amount: number
  paid_amount: number
  balance: number
  status: string
  currency: string
}

interface PaginatedBills {
  data: BillRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  bills: PaginatedBills
  filters: {
    vendor_id?: string
    status?: string
    search?: string
    from_date?: string
    to_date?: string
  }
  vendors: VendorRef[]
}>()

const allVendorsValue = '__all_vendors'
const allStatusValue = '__all_status'
const search = ref(props.filters.search ?? '')
const vendorId = ref(props.filters.vendor_id ?? allVendorsValue)
const status = ref(props.filters.status ?? allStatusValue)
const fromDate = ref(props.filters.from_date ?? '')
const toDate = ref(props.filters.to_date ?? '')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bills', href: `/${props.company.slug}/bills` },
]

const columns = [
  { key: 'bill_number', label: 'Bill #' },
  { key: 'vendor', label: 'Vendor' },
  { key: 'bill_date', label: 'Date' },
  { key: 'due_date', label: 'Due' },
  { key: 'total_amount', label: 'Total' },
  { key: 'paid_amount', label: 'Paid' },
  { key: 'balance', label: 'Balance' },
  { key: 'status', label: 'Status' },
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

const tableData = computed(() =>
  props.bills.data.map((b) => ({
    id: b.id,
    bill_number: b.bill_number,
    vendor: b.vendor?.name ?? '—',
    bill_date: b.bill_date,
    due_date: b.due_date,
    total_amount: formatMoney(b.total_amount, b.currency),
    paid_amount: formatMoney(b.paid_amount, b.currency),
    balance: formatMoney(b.balance, b.currency),
    status: b.status,
  }))
)

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/bills`,
    {
      search: search.value,
      vendor_id: vendorId.value === allVendorsValue ? '' : vendorId.value,
      status: status.value === allStatusValue ? '' : status.value,
      from_date: fromDate.value,
      to_date: toDate.value,
    },
    { preserveState: true }
  )
}
</script>

<template>
  <Head title="Bills" />
  <PageShell
    title="Bills"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/bills/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Bill
      </Button>
    </template>

    <div class="mb-4 grid gap-3 md:grid-cols-5">
      <div class="relative md:col-span-2">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search bill # or vendor invoice #"
          class="pl-10"
          @keyup.enter="handleSearch"
        />
      </div>
      <Select v-model="vendorId" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All vendors" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="allVendorsValue">All vendors</SelectItem>
          <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="status" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="allStatusValue">All status</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="received">Received</SelectItem>
          <SelectItem value="partial">Partial</SelectItem>
          <SelectItem value="paid">Paid</SelectItem>
          <SelectItem value="overdue">Overdue</SelectItem>
          <SelectItem value="void">Void</SelectItem>
          <SelectItem value="cancelled">Cancelled</SelectItem>
        </SelectContent>
      </Select>
      <div class="grid grid-cols-2 gap-2">
        <Input v-model="fromDate" type="date" @change="handleSearch" />
        <Input v-model="toDate" type="date" @change="handleSearch" />
      </div>
    </div>

    <div v-if="!bills.data.length">
      <EmptyState
        title="No bills yet"
        description="Create your first bill to track vendor payables."
        cta-text="Create Bill"
        @click="router.get(`/${company.slug}/bills/create`)"
      />
    </div>

    <div v-else>
      <DataTable
        :columns="columns"
        :data="tableData"
        :pagination="bills"
      >
        <template #status="{ value }">
          <Badge :variant="statusVariant(value)">{{ value }}</Badge>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
