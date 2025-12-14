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
import type { BreadcrumbItem } from '@/types'
import {
  ArrowUpCircle,
  ArrowDownCircle,
  ArrowRightLeft,
  History,
  ArrowLeft,
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

interface Item {
  id: string
  sku: string
  name: string
}

interface User {
  id: string
  name: string
}

interface MovementRow {
  id: string
  item: Item
  warehouse: Warehouse
  movement_date: string
  movement_type: string
  quantity: number
  unit_cost: number | null
  reason: string | null
  created_by: User | null
  created_at: string
}

interface PaginatedMovements {
  data: MovementRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  movements: PaginatedMovements
  warehouses: Warehouse[]
  filters: {
    item_id: string
    warehouse_id: string
    movement_type: string
    date_from: string
    date_to: string
  }
}>()

const warehouseId = ref(props.filters.warehouse_id || 'all')
const movementType = ref(props.filters.movement_type || 'all')
const dateFrom = ref(props.filters.date_from)
const dateTo = ref(props.filters.date_to)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Stock Levels', href: `/${props.company.slug}/stock` },
  { title: 'Movements', href: `/${props.company.slug}/stock/movements` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/stock/movements`,
    {
      warehouse_id: warehouseId.value === 'all' ? '' : warehouseId.value,
      movement_type: movementType.value === 'all' ? '' : movementType.value,
      date_from: dateFrom.value,
      date_to: dateTo.value,
    },
    { preserveState: true }
  )
}

const formatQuantity = (qty: number) => {
  const prefix = qty > 0 ? '+' : ''
  return prefix + new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(qty)
}

const getTypeIcon = (type: string) => {
  if (type.includes('in') || type === 'purchase' || type === 'opening') return ArrowUpCircle
  if (type.includes('out') || type === 'sale') return ArrowDownCircle
  if (type.includes('transfer')) return ArrowRightLeft
  return History
}

const getTypeBadgeVariant = (type: string) => {
  if (type.includes('in') || type === 'purchase' || type === 'opening') return 'success'
  if (type.includes('out') || type === 'sale') return 'destructive'
  return 'secondary'
}

const formatType = (type: string) => {
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'item', label: 'Item' },
  { key: 'warehouse', label: 'Warehouse' },
  { key: 'type', label: 'Type' },
  { key: 'quantity', label: 'Quantity' },
  { key: 'by', label: 'By' },
]

const tableData = computed(() => {
  return props.movements.data.map((movement) => ({
    id: movement.id,
    date: new Date(movement.movement_date).toLocaleDateString(),
    item: `${movement.item.sku} - ${movement.item.name}`,
    item_id: movement.item.id,
    warehouse: movement.warehouse.name,
    type: movement.movement_type,
    quantity: formatQuantity(movement.quantity),
    by: movement.created_by?.name ?? '-',
    _raw: movement,
  }))
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/items/${row.item_id}`)
}

const movementTypes = [
  { value: 'purchase', label: 'Purchase' },
  { value: 'sale', label: 'Sale' },
  { value: 'adjustment_in', label: 'Adjustment In' },
  { value: 'adjustment_out', label: 'Adjustment Out' },
  { value: 'transfer_in', label: 'Transfer In' },
  { value: 'transfer_out', label: 'Transfer Out' },
  { value: 'return_in', label: 'Return In' },
  { value: 'return_out', label: 'Return Out' },
  { value: 'opening', label: 'Opening' },
]
</script>

<template>
  <Head title="Stock Movements" />

  <PageShell
    title="Stock Movements"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/stock`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Stock
      </Button>
    </template>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-4">
      <Select v-model="warehouseId" @update:model-value="handleSearch">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All Warehouses" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Warehouses</SelectItem>
          <SelectItem v-for="wh in warehouses" :key="wh.id" :value="wh.id">
            {{ wh.name }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="movementType" @update:model-value="handleSearch">
        <SelectTrigger class="w-[180px]">
          <SelectValue placeholder="All Types" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Types</SelectItem>
          <SelectItem v-for="mt in movementTypes" :key="mt.value" :value="mt.value">
            {{ mt.label }}
          </SelectItem>
        </SelectContent>
      </Select>

      <div class="flex items-center gap-2">
        <Input
          v-model="dateFrom"
          type="date"
          class="w-[150px]"
          @change="handleSearch"
        />
        <span class="text-muted-foreground">to</span>
        <Input
          v-model="dateTo"
          type="date"
          class="w-[150px]"
          @change="handleSearch"
        />
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-if="movements.data.length === 0"
      title="No movements found"
      description="Stock movements will appear here as inventory changes."
      :icon="History"
    />

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: movements.current_page,
        lastPage: movements.last_page,
        perPage: movements.per_page,
        total: movements.total,
      }"
      @row-click="handleRowClick"
    >
      <template #cell-type="{ row }">
        <Badge :variant="getTypeBadgeVariant(row.type)" class="gap-1">
          <component :is="getTypeIcon(row.type)" class="h-3 w-3" />
          {{ formatType(row.type) }}
        </Badge>
      </template>

      <template #cell-quantity="{ row }">
        <span :class="row._raw.quantity > 0 ? 'text-green-600' : 'text-red-600'">
          {{ row.quantity }}
        </span>
      </template>
    </DataTable>
  </PageShell>
</template>
