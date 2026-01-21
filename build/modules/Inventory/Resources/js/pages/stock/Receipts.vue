<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import { Package, PackageCheck } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface VendorRef {
  id: string
  name: string
}

interface PendingBill {
  id: string
  bill_number: string
  bill_date: string
  total_amount: number
  currency: string
  status: string
  pending_lines: number | string
  vendor: VendorRef | null
}

interface ReceiptRow {
  id: string
  receipt_date: string
  variance_transaction_id: string | null
  bill: {
    id: string
    bill_number: string
    vendor: VendorRef | null
  } | null
  lines_count: number
  total_received: number | string | null
  total_variance: number | string | null
  created_by: { id: string; name: string } | null
}

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  pendingBills: Paginated<PendingBill>
  receipts: Paginated<ReceiptRow>
  filters: {
    tab?: string
  }
}>()

const { t } = useLexicon()

const activeTab = ref(props.filters.tab || 'pending')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Inventory', href: `/${props.company.slug}/items` },
  { title: t('stockReceipts'), href: `/${props.company.slug}/stock/receipts` },
]

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const formatQuantity = (qty: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(qty)
}

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
    currencyDisplay: 'narrowSymbol',
  }).format(val)

const pendingColumns = [
  { key: 'bill_number', label: t('billNumber') },
  { key: 'vendor', label: t('vendor') },
  { key: 'bill_date', label: t('date') },
  { key: 'pending_lines', label: 'Pending Lines' },
  { key: 'total_amount', label: t('total') },
  { key: 'action', label: t('receiveStock') },
]

const receiptColumns = [
  { key: 'receipt_date', label: 'Receipt Date' },
  { key: 'bill_number', label: t('billNumber') },
  { key: 'vendor', label: t('vendor') },
  { key: 'lines_count', label: 'Lines' },
  { key: 'total_received', label: 'Received Qty' },
  { key: 'total_variance', label: 'Variance' },
  { key: 'action', label: 'Actions' },
]

const pendingRows = computed(() =>
  props.pendingBills.data.map((bill) => ({
    id: bill.id,
    bill_number: bill.bill_number,
    vendor: bill.vendor?.name ?? '—',
    bill_date: formatDate(bill.bill_date),
    pending_lines: bill.pending_lines ?? 0,
    total_amount: formatMoney(bill.total_amount, bill.currency),
    _raw: bill,
  }))
)

const receiptRows = computed(() =>
  props.receipts.data.map((receipt) => ({
    id: receipt.id,
    receipt_date: formatDate(receipt.receipt_date),
    bill_number: receipt.bill?.bill_number ?? '—',
    vendor: receipt.bill?.vendor?.name ?? '—',
    lines_count: receipt.lines_count ?? 0,
    total_received: formatQuantity(Number(receipt.total_received ?? 0)),
    total_variance: receipt.total_variance ?? 0,
    _raw: receipt,
  }))
)

const handlePendingPage = (page: number) => {
  router.get(
    `/${props.company.slug}/stock/receipts`,
    {
      tab: activeTab.value,
      pending_page: page,
      receipt_page: props.receipts.current_page,
    },
    { preserveScroll: true, preserveState: true }
  )
}

const handleReceiptPage = (page: number) => {
  router.get(
    `/${props.company.slug}/stock/receipts`,
    {
      tab: activeTab.value,
      pending_page: props.pendingBills.current_page,
      receipt_page: page,
    },
    { preserveScroll: true, preserveState: true }
  )
}

const openBill = (billId: string) => {
  router.get(`/${props.company.slug}/bills/${billId}`)
}

const varianceClass = (variance: number) => {
  if (variance > 0) return 'text-emerald-600'
  if (variance < 0) return 'text-amber-600'
  return 'text-muted-foreground'
}
</script>

<template>
  <Head :title="t('stockReceipts')" />

  <PageShell
    :title="t('stockReceipts')"
    description="Track incoming deliveries and confirm received quantities."
    :breadcrumbs="breadcrumbs"
  >
    <Tabs v-model="activeTab" class="space-y-6">
      <TabsList class="grid w-full grid-cols-2">
        <TabsTrigger value="pending" class="flex items-center gap-2">
          Pending
          <Badge variant="secondary">{{ pendingBills.total }}</Badge>
        </TabsTrigger>
        <TabsTrigger value="received" class="flex items-center gap-2">
          Received
          <Badge variant="secondary">{{ receipts.total }}</Badge>
        </TabsTrigger>
      </TabsList>

      <TabsContent value="pending" class="space-y-4">
        <EmptyState
          v-if="pendingBills.data.length === 0"
          title="No pending receipts"
          description="Paid bills that still need receiving will appear here."
          :icon="Package"
        />

        <DataTable
          v-else
          :columns="pendingColumns"
          :data="pendingRows"
          :pagination="{
            currentPage: pendingBills.current_page,
            lastPage: pendingBills.last_page,
            perPage: pendingBills.per_page,
            total: pendingBills.total,
          }"
          @page-change="handlePendingPage"
        >
          <template #cell-action="{ row }">
            <Button size="sm" variant="outline" @click="openBill(row._raw.id)">
              <Package class="mr-2 h-4 w-4" />
              {{ t('receiveStock') }}
            </Button>
          </template>
        </DataTable>
      </TabsContent>

      <TabsContent value="received" class="space-y-4">
        <EmptyState
          v-if="receipts.data.length === 0"
          title="No receipts yet"
          description="Confirmed stock receipts will appear here."
          :icon="PackageCheck"
        />

        <DataTable
          v-else
          :columns="receiptColumns"
          :data="receiptRows"
          :pagination="{
            currentPage: receipts.current_page,
            lastPage: receipts.last_page,
            perPage: receipts.per_page,
            total: receipts.total,
          }"
          @page-change="handleReceiptPage"
        >
          <template #cell-total_variance="{ row }">
            <span :class="varianceClass(Number(row.total_variance || 0))">
              {{ formatQuantity(Number(row.total_variance || 0)) }}
            </span>
          </template>

          <template #cell-action="{ row }">
            <div class="flex items-center gap-2">
              <Button
                v-if="row._raw.bill?.id"
                size="sm"
                variant="outline"
                @click="openBill(row._raw.bill.id)"
              >
                View Bill
              </Button>
              <Button
                v-if="row._raw.variance_transaction_id"
                size="sm"
                variant="ghost"
                @click="router.get(`/${company.slug}/journals/${row._raw.variance_transaction_id}`)"
              >
                View Journal
              </Button>
            </div>
          </template>
        </DataTable>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
