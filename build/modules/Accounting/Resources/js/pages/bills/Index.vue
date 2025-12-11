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
  vendor_id: string
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
  { key: 'balance', label: 'Balance' },
  { key: 'status', label: 'Status' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const statusVariant = (s: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
  if (s === 'draft') return 'secondary'
  if (s === 'received') return 'default'
  if (s === 'partial') return 'outline'
  if (s === 'paid') return 'default'
  if (s === 'overdue') return 'destructive'
  return 'secondary'
}

const tableData = computed(() =>
  props.bills.data.map((b) => ({
    id: b.id,
    bill_number: b.bill_number,
    vendor: b.vendor?.name ?? '—',
    vendor_id: b.vendor_id,
    bill_date: formatDate(b.bill_date),
    due_date: formatDate(b.due_date),
    total_amount: formatMoney(b.total_amount, b.currency),
    balance: formatMoney(b.balance, b.currency),
    status: b.status,
    _billObject: b,
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

const navigateToBill = (billId: string) => {
  router.get(`/${props.company.slug}/bills/${billId}`)
}

const navigateToVendor = (vendorId: string) => {
  router.get(`/${props.company.slug}/vendors/${vendorId}`)
}

const filterByStatus = (statusValue: string) => {
  status.value = statusValue
  handleSearch()
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
        <Input v-model="fromDate" type="date" placeholder="From" @change="handleSearch" />
        <Input v-model="toDate" type="date" placeholder="To" @change="handleSearch" />
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
        <!-- Bill Number - Clickable Link -->
        <template #cell-bill_number="{ value, row }">
          <button
            @click="navigateToBill(row.id)"
            class="font-medium text-primary hover:underline focus:outline-none focus:underline"
          >
            {{ value }}
          </button>
        </template>

        <!-- Vendor - Clickable Link -->
        <template #cell-vendor="{ value, row }">
          <button
            v-if="row.vendor_id"
            @click="navigateToVendor(row.vendor_id)"
            class="text-foreground hover:text-primary hover:underline focus:outline-none focus:underline transition-colors"
          >
            {{ value }}
          </button>
          <span v-else class="text-muted-foreground">{{ value }}</span>
        </template>

        <!-- Status - Clickable Badge -->
        <template #cell-status="{ value }">
          <button
            @click="filterByStatus(value)"
            class="inline-flex transition-opacity hover:opacity-70 focus:outline-none"
          >
            <Badge :variant="statusVariant(value)">
              {{ value }}
            </Badge>
          </button>
        </template>

        <!-- Mobile Card Template -->
        <template #mobile-card="{ row }">
          <div
            @click="navigateToBill(row.id)"
            class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
          >
            <div class="space-y-3">
              <!-- Header with bill number and status -->
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-semibold text-primary">{{ row.bill_number }}</h3>
                  <button
                    v-if="row.vendor_id"
                    @click.stop="navigateToVendor(row.vendor_id)"
                    class="text-sm text-zinc-500 hover:text-primary hover:underline"
                  >
                    {{ row.vendor }}
                  </button>
                  <span v-else class="text-sm text-muted-foreground">{{ row.vendor }}</span>
                </div>
                <button
                  @click.stop="filterByStatus(row.status)"
                  class="transition-opacity hover:opacity-70"
                >
                  <Badge :variant="statusVariant(row.status)">
                    {{ row.status }}
                  </Badge>
                </button>
              </div>

              <!-- Dates -->
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <span class="text-zinc-500">Date:</span>
                  <span class="font-medium ml-1">{{ row.bill_date }}</span>
                </div>
                <div>
                  <span class="text-zinc-500">Due:</span>
                  <span class="font-medium ml-1">{{ row.due_date }}</span>
                </div>
              </div>

              <!-- Amounts -->
              <div class="flex items-center justify-between pt-2 border-t border-zinc-100">
                <div>
                  <span class="text-sm text-zinc-500">Total:</span>
                  <span class="font-medium ml-1">{{ row.total_amount }}</span>
                </div>
                <div>
                  <span class="text-sm text-zinc-500">Balance:</span>
                  <span class="font-medium ml-1">{{ row.balance }}</span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
