<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'
import {
  Package,
  Search,
  ArrowRightLeft,
  PlusCircle,
  AlertTriangle,
  ClipboardCheck,
  Boxes,
  History,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Warehouse {
  id: string
  name: string
  code: string
}

interface StockLevelRow {
  id: string
  item_id: string
  sku: string
  item_name: string
  unit_of_measure: string
  item_reorder_point: number
  warehouse_id: string
  warehouse_name: string
  warehouse_code: string
  quantity: number
  reserved_quantity: number
  available_quantity: number
  reorder_point: number | null
  pending_receipts: number
  pending_receipts_qty: number
}

interface VendorRef {
  id: string
  name: string
  vendor_type?: string | null
}

interface PendingDelivery {
  id: string
  bill_number: string
  bill_date: string
  total_amount: number
  currency: string
  pending_lines: number | string
  pending_quantity: number | string | null
  vendor: VendorRef | null
}

interface RecentMovement {
  id: string
  movement_date: string
  movement_type: string
  quantity: number | string
  unit_cost: number | string | null
  total_cost: number | string | null
  gl_transaction_id: string | null
  item: {
    id: string
    sku: string
    name: string
    unit_of_measure: string
  } | null
  warehouse: {
    id: string
    name: string
    code: string
  } | null
}

interface PaginatedStockLevels {
  data: StockLevelRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  stockLevels: PaginatedStockLevels
  warehouses: Warehouse[]
  summary: {
    tracked_items: number
    stock_records: number
    low_stock: number
    pending_deliveries: number
  }
  pendingDeliveries: PendingDelivery[]
  recentMovements: RecentMovement[]
  filters: {
    search: string
    warehouse_id: string
    low_stock_only: boolean
  }
}>()

const search = ref(props.filters.search)
const warehouseId = ref(props.filters.warehouse_id || 'all')
const lowStockOnly = ref(props.filters.low_stock_only)
const { t } = useLexicon()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Stock Management', href: `/${props.company.slug}/stock` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/stock`,
    {
      search: search.value,
      warehouse_id: warehouseId.value === 'all' ? '' : warehouseId.value,
      low_stock_only: lowStockOnly.value ? '1' : '',
    },
    { preserveState: true }
  )
}

const formatQuantity = (qty: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(qty)
}

const formatDate = (dateString: string) => {
  return formatSharedDateTime(dateString, { mode: 'date' })
}

const formatMoney = (val: number | string | null, currency = 'USD') => {
  const amount = Number(val ?? 0)
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    currencyDisplay: 'narrowSymbol',
  }).format(amount)
}

const formatMovementType = (type: string) => {
  return type.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

const columns = [
  { key: 'sku', label: 'SKU' },
  { key: 'item_name', label: 'Item' },
  { key: 'warehouse', label: 'Warehouse' },
  { key: 'quantity', label: 'On Hand' },
  { key: 'available', label: 'Available' },
  { key: 'status', label: 'Status' },
  { key: 'receive_stock', label: t('receiveStock') },
]

const tableData = computed(() => {
  return props.stockLevels.data.map((level) => {
    const reorderPoint = level.reorder_point ?? level.item_reorder_point ?? 0
    const isLow = level.quantity < reorderPoint && reorderPoint > 0
    return {
      id: level.id,
      item_id: level.item_id,
      sku: level.sku,
      item_name: level.item_name,
      warehouse: `${level.warehouse_name} (${level.warehouse_code})`,
      quantity: `${formatQuantity(level.quantity)} ${level.unit_of_measure}`,
      available: `${formatQuantity(level.available_quantity)} ${level.unit_of_measure}`,
      status: isLow ? 'low' : 'ok',
      pending_receipts: level.pending_receipts ?? 0,
      pending_receipts_qty: level.pending_receipts_qty ?? 0,
      receive_stock: level.pending_receipts > 0 ? t('receiveStock') : '—',
      _raw: level,
    }
  })
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/items/${row.item_id}`)
}

const openReceiptsForItem = (itemId: string) => {
  router.get(`/${props.company.slug}/bills`, {
    item_id: itemId,
    needs_receiving: '1',
  })
}

const openBill = (billId: string) => {
  router.get(`/${props.company.slug}/bills/${billId}`)
}
</script>

<template>
  <Head title="Stock Management" />

  <PageShell
    title="Stock Management"
    description="Watch stock levels, receive paid purchases, and record correction adjustments."
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/stock/receipts`)">
        <ClipboardCheck class="mr-2 h-4 w-4" />
        Pending Receipts
      </Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/stock/movements`)">
        View Movements
      </Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/stock/transfer`)">
        <ArrowRightLeft class="mr-2 h-4 w-4" />
        Transfer
      </Button>
      <Button @click="router.get(`/${company.slug}/stock/adjustment`)">
        <PlusCircle class="mr-2 h-4 w-4" />
        Adjustment
      </Button>
    </template>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <Boxes class="h-4 w-4" />
            Tracked Items
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-semibold">{{ summary.tracked_items }}</div>
          <p class="text-xs text-muted-foreground">{{ summary.stock_records }} stock locations</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <AlertTriangle class="h-4 w-4" />
            Low Stock
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-semibold">{{ summary.low_stock }}</div>
          <p class="text-xs text-muted-foreground">Below reorder point</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <ClipboardCheck class="h-4 w-4" />
            Paid Bills to Receive
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-semibold">{{ summary.pending_deliveries }}</div>
          <p class="text-xs text-muted-foreground">Waiting for goods receipt</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <History class="h-4 w-4" />
            Latest Activity
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-semibold">{{ recentMovements.length }}</div>
          <p class="text-xs text-muted-foreground">Recent stock movements</p>
        </CardContent>
      </Card>
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold">Paid Bills Waiting for Delivery</h2>
          <Button variant="ghost" size="sm" @click="router.get(`/${company.slug}/stock/receipts`)">
            View all
          </Button>
        </div>
        <div v-if="pendingDeliveries.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
          No paid inventory bills are waiting for receipt.
        </div>
        <div v-else class="divide-y rounded-md border">
          <Button
            v-for="bill in pendingDeliveries"
            :key="bill.id"
            type="button"
            variant="ghost"
            class="h-auto w-full justify-between gap-3 rounded-none p-3 text-left"
            @click="openBill(bill.id)"
          >
            <span class="min-w-0">
              <span class="block truncate text-sm font-medium">{{ bill.bill_number }} · {{ bill.vendor?.name ?? 'No vendor' }}</span>
              <span class="block text-xs text-muted-foreground">
                {{ formatDate(bill.bill_date) }} · {{ bill.pending_lines }} pending line{{ Number(bill.pending_lines) === 1 ? '' : 's' }}
              </span>
            </span>
            <span class="shrink-0 text-right text-sm">
              <span class="block font-medium">{{ formatMoney(bill.total_amount, bill.currency) }}</span>
              <span class="block text-xs text-muted-foreground">{{ formatQuantity(Number(bill.pending_quantity ?? 0)) }} pending</span>
            </span>
          </Button>
        </div>
      </section>

      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold">Recent Stock Movements</h2>
          <Button variant="ghost" size="sm" @click="router.get(`/${company.slug}/stock/movements`)">
            View all
          </Button>
        </div>
        <div v-if="recentMovements.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
          Stock movements will appear after purchases, adjustments, or transfers.
        </div>
        <div v-else class="divide-y rounded-md border">
          <div
            v-for="movement in recentMovements"
            :key="movement.id"
            class="flex items-center justify-between gap-3 p-3"
          >
            <span class="min-w-0">
              <span class="block truncate text-sm font-medium">{{ movement.item?.name ?? 'Unknown item' }}</span>
              <span class="block text-xs text-muted-foreground">
                {{ formatMovementType(movement.movement_type) }} · {{ movement.warehouse?.name ?? 'No warehouse' }} · {{ formatDate(movement.movement_date) }}
              </span>
            </span>
            <span class="shrink-0 text-right text-sm">
              <span class="block font-medium">
                {{ Number(movement.quantity) > 0 ? '+' : '' }}{{ formatQuantity(Number(movement.quantity)) }}
                {{ movement.item?.unit_of_measure ?? '' }}
              </span>
              <span class="block text-xs text-muted-foreground">
                {{ movement.gl_transaction_id ? 'Posted' : 'No GL' }}
              </span>
            </span>
          </div>
        </div>
      </section>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-4">
      <div class="relative flex-1 min-w-[200px] max-w-sm">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search items..."
          class="pl-9"
          @keyup.enter="handleSearch"
        />
      </div>

      <Select v-model="warehouseId" @update:model-value="handleSearch">
        <SelectTrigger class="w-[200px]">
          <SelectValue placeholder="All Warehouses" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Warehouses</SelectItem>
          <SelectItem v-for="wh in warehouses" :key="wh.id" :value="wh.id">
            {{ wh.name }}
          </SelectItem>
        </SelectContent>
      </Select>

      <div class="flex items-center space-x-2">
        <Checkbox
          id="low_stock"
          :checked="lowStockOnly"
          @update:checked="(val) => { lowStockOnly = val; handleSearch() }"
        />
        <label for="low_stock" class="text-sm cursor-pointer">Low stock only</label>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-if="stockLevels.data.length === 0"
      title="No stock records"
      description="Stock levels will appear here once you start tracking inventory."
      :icon="Package"
    />

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: stockLevels.current_page,
        lastPage: stockLevels.last_page,
        perPage: stockLevels.per_page,
        total: stockLevels.total,
      }"
      @row-click="handleRowClick"
    >
      <template #cell-status="{ row }">
        <Badge v-if="row.status === 'low'" variant="destructive" class="gap-1">
          <AlertTriangle class="h-3 w-3" />
          Low Stock
        </Badge>
        <Badge v-else variant="success">In Stock</Badge>
      </template>

      <template #cell-quantity="{ row }">
        <div class="space-y-1">
          <div class="font-medium">{{ row.quantity }}</div>
          <p v-if="row.pending_receipts_qty > 0" class="text-xs text-muted-foreground">
            {{ t('expectedInbound') }}:
            +{{ formatQuantity(row.pending_receipts_qty) }} {{ row._raw.unit_of_measure }}
          </p>
        </div>
      </template>

      <template #cell-receive_stock="{ row }">
        <div class="flex justify-end">
          <Button
            v-if="row.pending_receipts > 0"
            size="sm"
            variant="outline"
            @click.stop="openReceiptsForItem(row.item_id)"
          >
            {{ t('receiveStock') }}
          </Button>
          <span v-else class="text-xs text-muted-foreground">—</span>
        </div>
      </template>
    </DataTable>
  </PageShell>
</template>
