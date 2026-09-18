<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import EmptyState from '@/components/EmptyState.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, History, Package, Warehouse as WarehouseIcon } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface WarehouseRef {
  id: string
  name: string
  code: string
}

interface Item {
  id: string
  sku: string
  name: string
  unit_of_measure: string | null
  currency: string | null
  avg_cost: number | string | null
  reorder_point: number | string | null
  track_inventory: boolean
  is_active: boolean
}

interface StockLevelRow {
  id: string
  warehouse_id: string
  quantity: number | string
  reserved_quantity: number | string
  available_quantity: number | string | null
  reorder_point: number | string | null
  bin_location: string | null
  warehouse?: WarehouseRef | null
}

interface MovementRow {
  id: string
  movement_date: string
  movement_type: string
  quantity: number | string
  unit_cost: number | string | null
  reason: string | null
  warehouse?: WarehouseRef | null
  created_by?: { id: string; name: string } | null
}

const props = defineProps<{
  company: CompanyRef
  item: Item
  stockLevels: StockLevelRow[]
  movements: MovementRow[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Stock Levels', href: `/${props.company.slug}/stock` },
  { title: props.item.name, href: `/${props.company.slug}/stock/items/${props.item.id}` },
]

/**
 * Quantities are not money: they get a plain number format, not MoneyText.
 * Unit cost is money and goes through MoneyText like every other figure.
 */
const quantityFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 3,
})

const formatQuantity = (value: number | string | null | undefined) =>
  quantityFormat.format(Math.abs(Number(value ?? 0)))

const currency = computed(() => props.item.currency || 'PKR')

const totalOnHand = computed(() =>
  props.stockLevels.reduce((sum, level) => sum + Number(level.quantity ?? 0), 0),
)

const totalAvailable = computed(() =>
  props.stockLevels.reduce(
    (sum, level) => sum + Number(level.available_quantity ?? level.quantity ?? 0),
    0,
  ),
)

const totalReserved = computed(() =>
  props.stockLevels.reduce((sum, level) => sum + Number(level.reserved_quantity ?? 0), 0),
)

const levelColumns = [
  { key: 'warehouse', label: 'Warehouse', kind: 'text' as const },
  { key: 'bin_location', label: 'Bin', kind: 'text' as const },
  { key: 'quantity', label: 'On hand', kind: 'amount' as const },
  { key: 'reserved_quantity', label: 'Reserved', kind: 'amount' as const },
  { key: 'available_quantity', label: 'Available', kind: 'amount' as const },
  { key: 'reorder_point', label: 'Reorder at', kind: 'amount' as const },
]

const reorderPoint = (level: StockLevelRow): string => {
  const point = level.reorder_point ?? props.item.reorder_point
  return point === null || point === undefined || point === '' ? '—' : formatQuantity(point)
}

const levelRows = computed(() =>
  props.stockLevels.map((level) => ({
    id: level.id,
    warehouse: level.warehouse ? `${level.warehouse.code} — ${level.warehouse.name}` : '—',
    bin_location: level.bin_location || '—',
    quantity: formatQuantity(level.quantity),
    reserved_quantity: formatQuantity(level.reserved_quantity),
    available_quantity: formatQuantity(level.available_quantity ?? level.quantity),
    // The item-wide reorder point is the fallback when a warehouse sets none,
    // which is exactly how the stock index decides what counts as low.
    reorder_point: reorderPoint(level),
  })),
)

/**
 * In and out are separate columns — the heading carries the direction, so the
 * sign never has to. The stored sign is the authority; the movement type is
 * consulted only when the quantity is zero and cannot say.
 */
const isOutward = (movement: MovementRow) => {
  const quantity = Number(movement.quantity ?? 0)
  if (quantity !== 0) return quantity < 0
  return /_out$|^sale$/.test(movement.movement_type)
}

const movementColumns = [
  { key: 'movement_date', label: 'Date', kind: 'date' as const },
  { key: 'warehouse', label: 'Warehouse', kind: 'text' as const },
  { key: 'type', label: 'Type', kind: 'text' as const },
  { key: 'quantity_in', label: 'In', kind: 'in' as const },
  { key: 'quantity_out', label: 'Out', kind: 'out' as const },
  { key: 'unit_cost', label: 'Unit cost', kind: 'amount' as const },
  { key: 'by', label: 'By', kind: 'text' as const },
]

const movementRows = computed(() =>
  props.movements.map((movement) => {
    const outward = isOutward(movement)
    const quantity = formatQuantity(movement.quantity)

    return {
      id: movement.id,
      movement_date: movement.movement_date,
      warehouse: movement.warehouse?.name ?? '—',
      type: movement.movement_type,
      quantity_in: outward ? '' : quantity,
      quantity_out: outward ? quantity : '',
      unit_cost: movement.unit_cost,
      by: movement.created_by?.name ?? '—',
    }
  }),
)

const formatType = (type: string) => type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())
</script>

<template>
  <Head :title="`Stock — ${item.name}`" />

  <PageShell
    :title="item.name"
    :description="`${item.sku}${item.unit_of_measure ? ` · ${item.unit_of_measure}` : ''}`"
    :icon="Package"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/stock`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Stock
      </Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/items/${item.id}`)">
        <Package class="mr-2 h-4 w-4" />
        Item record
      </Button>
    </template>

    <div class="space-y-6">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Where it stands</CardTitle>
          <CardDescription>Across every warehouse holding this item.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 sm:grid-cols-4">
          <div>
            <p class="text-xs text-text-secondary">On hand</p>
            <p class="mt-1 font-mono text-lg">{{ formatQuantity(totalOnHand) }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary">Reserved</p>
            <p class="mt-1 font-mono text-lg">{{ formatQuantity(totalReserved) }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary">Available</p>
            <p class="mt-1 font-mono text-lg">{{ formatQuantity(totalAvailable) }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary">Average cost</p>
            <p class="mt-1 text-lg"><MoneyText :amount="item.avg_cost" :currency="currency" /></p>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Stock by warehouse</CardTitle>
          <CardDescription>{{ stockLevels.length }} warehouse records.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <EmptyState
            v-if="!stockLevels.length"
            title="No stock recorded"
            description="This item has no stock level in any warehouse yet. It will appear here after the first receipt or adjustment."
            :icon="WarehouseIcon"
          />
          <LedgerRegister v-else :columns="levelColumns" :data="levelRows" key-field="id" />
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Recent movements</CardTitle>
          <CardDescription>The last {{ movements.length }} movements for this item.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <EmptyState
            v-if="!movements.length"
            title="No movements yet"
            description="Stock movements will appear here as this item is received, sold, transferred or adjusted."
            :icon="History"
          />
          <LedgerRegister v-else :columns="movementColumns" :data="movementRows" key-field="id">
            <template #cell-type="{ row }">
              <Badge variant="outline">{{ formatType(row.type) }}</Badge>
            </template>
            <template #cell-unit_cost="{ row }">
              <MoneyText v-if="row.unit_cost !== null" :amount="row.unit_cost" :currency="currency" />
              <span v-else>—</span>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
