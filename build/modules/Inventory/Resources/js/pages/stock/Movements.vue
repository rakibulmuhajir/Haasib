<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
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

/**
 * A quantity, with nothing said about which way it went — the column it lands
 * in says that. Signs and plus-prefixes were how this page used to carry
 * direction in a single column, and they are exactly what a register with
 * separate sides makes unnecessary.
 */
const quantityFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 3,
})

const formatQuantity = (qty: number) => quantityFormat.format(Math.abs(qty))

const getTypeIcon = (type: string) => {
  if (type.includes('in') || type === 'purchase' || type === 'opening') return ArrowUpCircle
  if (type.includes('out') || type === 'sale') return ArrowDownCircle
  if (type.includes('transfer')) return ArrowRightLeft
  return History
}

/**
 * Direction is not severity. Stock leaving the shelf because it was sold is
 * the business working, not an incident — painting all 96 of those rows red
 * meant the page had no way left to say when something was actually wrong.
 * The arrow and the sign carry direction; the chip stays neutral.
 */
const getTypeBadgeVariant = (_type: string) => 'outline' as const

const formatType = (type: string) => {
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

/**
 * In and out are separate columns, because that is what a stock register is.
 * Reading one signed column and working out what a leading minus meant was
 * work the reader was doing on the page's behalf; the heading does it now, and
 * the eye can run down one side to see everything that left the shelf.
 */
const columns = [
  { key: 'movement_date', label: 'Date', kind: 'date' as const },
  { key: 'item', label: 'Item', kind: 'text' as const },
  { key: 'warehouse', label: 'Warehouse', kind: 'text' as const },
  { key: 'type', label: 'Type', kind: 'text' as const },
  { key: 'quantity_in', label: 'In', kind: 'in' as const },
  { key: 'quantity_out', label: 'Out', kind: 'out' as const },
  { key: 'by', label: 'By', kind: 'text' as const },
]

/**
 * Which side a movement belongs on. The sign is the authority — a return keyed
 * as a negative adjustment is stock arriving, whatever its type is called — and
 * the type name is consulted only when the quantity is zero and cannot say.
 */
const isOutward = (movement: MovementRow) => {
  const quantity = Number(movement.quantity)
  if (quantity !== 0) return quantity < 0
  return /_out$|^sale$/.test(movement.movement_type)
}

const tableData = computed(() => {
  return props.movements.data.map((movement) => {
    const outward = isOutward(movement)
    const quantity = formatQuantity(movement.quantity)

    return {
      id: movement.id,
      movement_date: movement.movement_date,
      item: `${movement.item.sku} - ${movement.item.name}`,
      item_id: movement.item.id,
      warehouse: movement.warehouse.name,
      type: movement.movement_type,
      quantity_in: outward ? '' : quantity,
      quantity_out: outward ? quantity : '',
      by: movement.created_by?.name ?? '-',
      _raw: movement,
    }
  })
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
    <LedgerRegister
      v-else
      :columns="columns"
      :data="tableData"
      clickable
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
    </LedgerRegister>
  </PageShell>
</template>
