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
import { useLexicon } from '@/composables/useLexicon'
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

const { t } = useLexicon()
const allVendorsValue = '__all_vendors'
const allStatusValue = '__all_status'
const search = ref(props.filters.search ?? '')
const vendorId = ref(props.filters.vendor_id ?? allVendorsValue)
const status = ref(props.filters.status ?? allStatusValue)
const fromDate = ref(props.filters.from_date ?? '')
const toDate = ref(props.filters.to_date ?? '')

const breadcrumbs: BreadcrumbItem[] = [
  { title: t('dashboard'), href: `/${props.company.slug}` },
  { title: t('bills'), href: `/${props.company.slug}/bills` },
]

const columns = [
  { key: 'bill_number', label: t('billNumber') },
  { key: 'vendor', label: t('vendor') },
  { key: 'bill_date', label: t('date') },
  { key: 'due_date', label: t('due') },
  { key: 'total_amount', label: t('total') },
  { key: 'balance', label: t('balance') },
  { key: 'status', label: t('status') },
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
  <Head :title="t('bills')" />
  <PageShell
    :title="t('bills')"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/bills/create`)">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('newBill') }}
      </Button>
    </template>

  <div class="mb-4 grid gap-3 md:grid-cols-5">
    <div class="relative md:col-span-2">
      <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        v-model="search"
        :placeholder="t('searchBillPlaceholder')"
        class="pl-10"
        @keyup.enter="handleSearch"
      />
    </div>
    <Select v-model="vendorId" @update:modelValue="handleSearch">
      <SelectTrigger>
        <SelectValue :placeholder="t('allVendors')" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem :value="allVendorsValue">{{ t('allVendors') }}</SelectItem>
        <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</SelectItem>
      </SelectContent>
    </Select>
    <Select v-model="status" @update:modelValue="handleSearch">
      <SelectTrigger>
        <SelectValue :placeholder="t('allStatus')" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem :value="allStatusValue">{{ t('allStatus') }}</SelectItem>
        <SelectItem value="draft">{{ t('draft') }}</SelectItem>
        <SelectItem value="received">{{ t('received') }}</SelectItem>
        <SelectItem value="partial">{{ t('partial') }}</SelectItem>
        <SelectItem value="paid">{{ t('paid') }}</SelectItem>
        <SelectItem value="overdue">{{ t('overdue') }}</SelectItem>
        <SelectItem value="void">{{ t('void') }}</SelectItem>
        <SelectItem value="cancelled">{{ t('cancelled') }}</SelectItem>
      </SelectContent>
    </Select>
      <div class="grid grid-cols-2 gap-2">
        <Input v-model="fromDate" type="date" placeholder="From" @change="handleSearch" />
        <Input v-model="toDate" type="date" placeholder="To" @change="handleSearch" />
      </div>
    </div>

    <div v-if="!bills.data.length">
      <EmptyState
        :title="t('noBills')"
        :description="t('noBillsDesc')"
        :cta-text="t('newBill')"
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
