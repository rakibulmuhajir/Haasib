<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import Hint from '@/components/Hint.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { History, ArrowLeft } from 'lucide-vue-next'

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
  total_cost: number | null
  source: { label: string; href: string | null } | null
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

const quantityFormat = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 3,
})
const valueFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })

const signed = (n: number, fmt: Intl.NumberFormat) => {
  if (n === 0) return fmt.format(0)
  return `${n > 0 ? '+' : '-'}${fmt.format(Math.abs(n))}`
}

const typeLabels: Record<string, string> = {
  opening: 'Opening',
  purchase: 'Bought',
  sale: 'Sold',
  adjustment_in: 'Adjusted in',
  adjustment_out: 'Adjusted out',
  transfer_in: 'Transfer in',
  transfer_out: 'Transfer out',
  return_in: 'Returned in',
  return_out: 'Returned out',
  revaluation: 'Revalued',
}

const formatType = (type: string) =>
  typeLabels[type] ?? type.replace(/_/g, ' ').replace(/^\w/, (l) => l.toUpperCase())

const columns = [
  { key: 'movement_date', label: 'Date', kind: 'date' as const },
  { key: 'item', label: 'Item', kind: 'text' as const },
  { key: 'warehouse', label: 'Warehouse', kind: 'text' as const },
  { key: 'type', label: 'Type', kind: 'text' as const },
  { key: 'quantity', label: 'Quantity', kind: 'amount' as const },
  { key: 'source', label: 'Source', kind: 'text' as const },
  { key: 'by', label: 'By', kind: 'text' as const },
]

const tableData = computed(() =>
  props.movements.data.map((movement) => {
    const isReval = movement.movement_type === 'revaluation'
    return {
      id: movement.id,
      movement_date: movement.movement_date,
      item: `${movement.item.sku} - ${movement.item.name}`,
      item_id: movement.item.id,
      warehouse: movement.warehouse.name,
      type: formatType(movement.movement_type),
      quantity: isReval
        ? `${signed(Number(movement.total_cost ?? 0), valueFormat)} value`
        : signed(Number(movement.quantity), quantityFormat),
      negative: isReval ? Number(movement.total_cost ?? 0) < 0 : Number(movement.quantity) < 0,
      source: movement.source,
      by: movement.created_by?.name ?? '-',
    }
  }),
)

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/items/${row.item_id}`)
}

const movementTypes = [
  { value: 'opening', label: 'Opening' },
  { value: 'purchase', label: 'Bought' },
  { value: 'sale', label: 'Sold' },
  { value: 'adjustment_in', label: 'Adjusted in' },
  { value: 'adjustment_out', label: 'Adjusted out' },
  { value: 'transfer_in', label: 'Transfer in' },
  { value: 'transfer_out', label: 'Transfer out' },
  { value: 'return_in', label: 'Returned in' },
  { value: 'return_out', label: 'Returned out' },
  { value: 'revaluation', label: 'Revalued' },
]
</script>

<template>
  <Head title="Stock movements" />

  <PageShell title="Stock movements" :icon="History" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/stock`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Stock
      </Button>
    </template>

    <div class="space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <div class="grid gap-1.5">
          <Label>Warehouse</Label>
          <Select v-model="warehouseId" @update:model-value="handleSearch">
            <SelectTrigger class="w-44">
              <SelectValue placeholder="All warehouses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All warehouses</SelectItem>
              <SelectItem v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="grid gap-1.5">
          <Label>Type</Label>
          <Select v-model="movementType" @update:model-value="handleSearch">
            <SelectTrigger class="w-44">
              <SelectValue placeholder="All types" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All types</SelectItem>
              <SelectItem v-for="mt in movementTypes" :key="mt.value" :value="mt.value">{{ mt.label }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="grid gap-1.5">
          <Label for="date_from">From</Label>
          <Input id="date_from" v-model="dateFrom" type="date" class="w-40" @change="handleSearch" />
        </div>
        <div class="grid gap-1.5">
          <Label for="date_to">To</Label>
          <Input id="date_to" v-model="dateTo" type="date" class="w-40" @change="handleSearch" />
        </div>
        <Hint>
          Signs
          <template #content>+ stock in, - stock out. A revaluation changes value, not quantity.</template>
        </Hint>
      </div>

      <EmptyState
        v-if="movements.data.length === 0"
        title="No movements found"
        description="Stock movements appear here as inventory changes."
        :icon="History"
      />

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
        <template #cell-quantity="{ row }">
          <span :class="row.negative ? 'text-status-attention' : ''">{{ row.quantity }}</span>
        </template>
        <template #cell-source="{ row }">
          <template v-if="row.source">
            <Link
              v-if="row.source.href"
              :href="row.source.href"
              class="underline-offset-2 hover:underline"
              @click.stop
            >{{ row.source.label }}</Link>
            <span v-else class="text-muted-foreground">{{ row.source.label }}</span>
          </template>
          <span v-else class="text-muted-foreground">-</span>
        </template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>
