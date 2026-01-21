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
import { Checkbox } from '@/components/ui/checkbox'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import {
  Package,
  Search,
  ArrowRightLeft,
  PlusCircle,
  AlertTriangle,
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
  { title: 'Stock Levels', href: `/${props.company.slug}/stock` },
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
</script>

<template>
  <Head title="Stock Levels" />

  <PageShell
    title="Stock Levels"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
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
